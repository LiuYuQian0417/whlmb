<?php
declare(strict_types = 1);

namespace app\interfaces\behavior;

use app\common\model\Distribution as DistributionModel;
use app\common\model\DistributionBook;
use app\common\model\DistributionChangeRecord;
use app\common\model\DistributionLevel;
use app\common\model\Message;
use app\common\service\Beanstalk;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;

class Distribution
{
    /**
     * 分销商数据更新
     * @param $args
     * @return array @ 用于检测升级的分销商id集合
     * @throws \Exception
     */
    public function distributionUpdate($args)
    {
        $distributionIdAgg = [];
        $distributionModel = new DistributionModel();
        if ($args['member_id'] && $args['distribution']['order_sum_offset']) {
            if ($args['distribution']['distribution_id']) {
                // 当前会员为分销商
                $distributionModel->allowField(true)->isUpdate(true)->save([
                    'distribution_id' => $args['distribution']['distribution_id'],
                    // 更新周期订单金额,用于降级检测
                    'cycle_consumer' => Db::raw('cycle_consumer + ' . $args['distribution']['order_sum_offset']),
                    // 周期订单总金额,用于升级检测(升级归0)
                    'cycle_up_order_sum' => Db::raw('cycle_up_order_sum + ' . $args['distribution']['order_sum_offset']),
                    // 周期订单总数量,用于升级检测(升级归0)
                    'cycle_up_order_num' => Db::raw('cycle_up_order_num + ' . $args['distribution']['order_num_offset']),
                ]);
                array_push($distributionIdAgg, $args['distribution']['distribution_id']);
            }
        }
        if (!empty($args['distribution']['update'])) {
            $updateArrTotal = [];
            foreach ($args['distribution']['update'] as $_update) {
                if ($_update['distributor_a']) {
                    if (!array_key_exists($_update['distributor_a'], $updateArrTotal)) {
                        $updateArrTotal[$_update['distributor_a']] = [
                            'distribution_id' => $_update['distributor_a'],
                            'brokerage' => 0,
                        ];
                    }
                    $updateArrTotal[$_update['distributor_a']]['brokerage'] += $_update['distributor_a_brokerage'];
                }
                if ($_update['distributor_b']) {
                    if (!array_key_exists($_update['distributor_b'], $updateArrTotal)) {
                        $updateArrTotal[$_update['distributor_b']] = [
                            'distribution_id' => $_update['distributor_b'],
                            'brokerage' => 0,
                        ];
                    }
                    $updateArrTotal[$_update['distributor_b']]['brokerage'] += $_update['distributor_b_brokerage'];
                }
                if ($_update['distributor_c']) {
                    if (!array_key_exists($_update['distributor_c'], $updateArrTotal)) {
                        $updateArrTotal[$_update['distributor_c']] = [
                            'distribution_id' => $_update['distributor_c'],
                            'brokerage' => 0,
                        ];
                    }
                    $updateArrTotal[$_update['distributor_c']]['brokerage'] += $_update['distributor_c_brokerage'];
                }
            }
            if (!empty($updateArrTotal)) {
                $finalUpdateArr = [];
                foreach ($updateArrTotal as $_updateArrTotal) {
                    // 更新`已结算佣金总额`,`周期佣金(用于降级检测)`
                    $finalUpdateArr[] = [
                        'distribution_id' => $_updateArrTotal['distribution_id'],
                        // 已结算金额(通道关闭到手佣金 - 提现金额)
                        'close_brokerage' => Db::raw('close_brokerage + ' . $_updateArrTotal['brokerage']),
                        // 已结算金额(通道关闭到手佣金)(只增长记录值)
                        'total_close_brokerage' => Db::raw('total_close_brokerage + ' . $_updateArrTotal['brokerage']),
                        // 周期佣金金额,用于降级检测(降级归0)
                        'cycle_brokerage' => Db::raw('cycle_brokerage + ' . $_updateArrTotal['brokerage']),
                        // 周期佣金总金额(已关闭),用于升级检测(升级归0)
                        'cycle_up_brokerage' => Db::raw('cycle_up_brokerage + ' . $_updateArrTotal['brokerage']),
                    ];
                }
                if (!empty($finalUpdateArr)) {
                    $distributionIdAgg = array_merge(array_column($finalUpdateArr, 'distribution_id'), $distributionIdAgg);
                    $distributionModel->allowField(true)->isUpdate(true)->saveAll($finalUpdateArr);
                }
            }
            // 更新分销订单状态为已结算并且记录结算时间
            $dbi = array_column($args['distribution']['update'], 'distribution_book_id');
            if (!empty($dbi)) {
                $distributionBookModel = new DistributionBook();
                $distributionBookModel
                    ->allowField(true)
                    ->isUpdate(true)
                    ->saveAll(array_map(function ($x) {
                        return [
                            'distribution_book_id' => $x,
                            'status' => 1,
                            'settlement_time' => date('Y-m-d H:i:s'),
                        ];
                    }, $dbi));
            }
        }
        if (!empty($distributionIdAgg)) {
            // 分销商升级检测并更新[]
            self::levelUpgrade(['distribution_id' => implode(',', array_unique($distributionIdAgg))]);
        }
        return empty($distributionIdAgg) ? [] : array_unique($distributionIdAgg);
    }
    
    /**
     * 分销商升级检测并更新
     * @param $args
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function levelUpgrade($args)
    {
        if (array_key_exists('distribution_id', $args) && $args['distribution_id']) {
            // 查询当前分销商等级的升级策略
            $distributionModel = new DistributionModel();
            $info = $distributionModel
                ->alias('d')
                ->where([
                    ['distribution_id', 'in', $args['distribution_id']],
                ])
                ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
                ->join('member m', 'm.member_id = d.member_id')
                ->field('d.distribution_id,dl.distribution_level_id,dl.level_weight,dl.level_title,dl.upgrade_total_brokerage,
                dl.upgrade_total_order_num,dl.upgrade_total_order_sum,dl.upgrade_direct_next_num,dl.upgrade_next_num,m.member_id,
                dl.upgrade_relation,d.cycle_up_brokerage,d.cycle_up_order_sum,d.cycle_up_order_num,d.cycle_up_referrer_num,
                d.cycle_up_relation_num,m.web_open_id,m.nickname,m.micro_open_id,m.subscribe_time,m.phone')
                ->order(['distribution_id' => 'asc'])
                ->select();
            // 查询等级结构[从低到高]
            $distributionLevelModel = new DistributionLevel();
            $level = $distributionLevelModel
                ->field('distribution_level_id,level_title,level_weight,downgrade_brokerage_cycle,
                downgrade_order_cycle')
                ->order(['level_weight' => 'asc'])
                ->select();
            if (!$level->isEmpty() && !$info->isEmpty()) {
                $updateArr = $checkDown = $upgradeChangeRecord = [];
                foreach ($info as $_info) {
                    // 罗列各分销商上层等级
                    $next_level = $downgrade_brokerage_cycle = $downgrade_order_cycle = $next_level_title = '';
                    foreach ($level as $_level) {
                        if ($_level['level_weight'] > $_info['level_weight'] && !$next_level) {
                            // 找到上层等级
                            $next_level = $_level['distribution_level_id'];
                            $next_level_title = $_level['level_title'];
                            $downgrade_brokerage_cycle = $_level['downgrade_brokerage_cycle'];
                            $downgrade_order_cycle = $_level['downgrade_order_cycle'];
                            break;
                        }
                    }
                    $res = [
                        ['type', 0],
                        ['brokerage', $_info['cycle_up_brokerage'], $_info['upgrade_total_brokerage']],
                        ['order_num', $_info['cycle_up_order_num'], $_info['upgrade_total_order_num']],
                        ['order_sum', $_info['cycle_up_order_sum'], $_info['upgrade_total_order_sum']],
                        ['referrer_num', $_info['cycle_up_referrer_num'], $_info['upgrade_direct_next_num']],
                        ['relation_num', $_info['cycle_up_relation_num'], $_info['upgrade_next_num']],
                    ];
                    if ($next_level) {
                        if ($_info['upgrade_relation'] == 1) {
                            // 与关系
                            if (
                                ($_info['cycle_up_brokerage'] >= $_info['upgrade_total_brokerage'] || !(float)$_info['upgrade_total_brokerage']) &&
                                ($_info['cycle_up_order_num'] >= $_info['upgrade_total_order_num'] || !$_info['upgrade_total_order_num']) &&
                                ($_info['cycle_up_order_sum'] >= $_info['upgrade_total_order_sum'] || !(float)$_info['upgrade_total_order_sum']) &&
                                ($_info['cycle_up_referrer_num'] >= $_info['upgrade_direct_next_num'] || !$_info['upgrade_direct_next_num']) &&
                                ($_info['cycle_up_relation_num'] >= $_info['upgrade_next_num'] || !$_info['upgrade_next_num'])
                            ) {
                                $res[0] = ['type', 1];
                            }
                        } else {
                            // 或关系
                            if (
                                ($_info['cycle_up_brokerage'] >= $_info['upgrade_total_brokerage'] && (float)$_info['upgrade_total_brokerage']) ||
                                ($_info['cycle_up_order_num'] >= $_info['upgrade_total_order_num'] && $_info['upgrade_total_order_num']) ||
                                ($_info['cycle_up_order_sum'] >= $_info['upgrade_total_order_sum'] && (float)$_info['upgrade_total_order_sum']) ||
                                ($_info['cycle_up_referrer_num'] >= $_info['upgrade_direct_next_num'] && $_info['upgrade_direct_next_num']) ||
                                ($_info['cycle_up_relation_num'] >= $_info['upgrade_next_num'] && $_info['upgrade_next_num'])
                            ) {
                                $res[0] = ['type', 2];
                            }
                        }
                    }
                    // 达标晋级
                    if ($res[0] != ['type', 0]) {
                        $updateArr[] = [
                            'distribution_id' => $_info['distribution_id'],
                            'distribution_level_id' => $next_level,
                            'cycle_up_order_sum' => 0,              // 初始化升级周期检测数据
                            'cycle_up_order_num' => 0,
                            'cycle_up_brokerage' => 0,
                            'cycle_up_referrer_num' => 0,
                            'cycle_up_relation_num' => 0,
                        ];
                        $checkDown[] = [
                            'distribution_id' => $_info['distribution_id'],
                            'downgrade_brokerage_cycle' => $downgrade_brokerage_cycle,
                            'downgrade_order_cycle' => $downgrade_order_cycle,
                        ];
                        $upgradeChangeRecord[] = [
                            'distribution_id' => $_info['distribution_id'],
                            'change_type' => 1,         // 升降类型 1升级 2降级
                            'now_level_id' => $next_level,
                            'now_level_title' => $next_level_title,
                            'ago_level_id' => $_info['distribution_level_id'],
                            'ago_level_title' => $_info['level_title'],
                            'upgrade_down_reason' => implode(';', array_map(function ($v) {
                                return implode(',', $v);
                            }, $res)),
                        ];
                        // 推送消息[分销商晋级][无小程序跳转]
                        $pushServer = app('app\\interfaces\\behavior\\Push');
                        $pushServer->send([
                            'tplKey' => 'distribution_state',
                            'openId' => $_info['web_open_id'],
                            'microId' => '',
                            'subscribe_time' => $_info['subscribe_time'],
                            'phone' => $_info['phone'],
                            'data' => [1, $_info['nickname'], $next_level_title],
                            'inside_data' => [
                                'member_id' => $_info['member_id'],
                                'type' => 0,
                                'attach_id' => $_info['distribution_id'],
                                'jump_state' => '3',
                                'file' => 'image/dui.png',
                            ],
                            'sms_data' => [],
                        ]);
                    }
                }
                if (!empty($updateArr)) {
                    // 归0
                    $distributionModel
                        ->allowField(true)
                        ->isUpdate(true)
                        ->saveAll($updateArr);
                }
                // 插入分销商升级记录
                if (!empty($upgradeChangeRecord)) {
                    $distributionChangeRecordModel = new DistributionChangeRecord();
                    $distributionChangeRecordModel
                        ->allowField(true)
                        ->isUpdate(false)
                        ->saveAll($upgradeChangeRecord);
                }
                // 消息队列增加分销商降级检测
                if (!empty($checkDown)) {
                    self::sendDownMsg($checkDown);
                }
            }
        }
    }
    
    /**
     * 发送降级检测
     * @param $checkDown
     */
    public function sendDownMsg($checkDown)
    {
        $disDown = [];
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $set = Env::get();
        // 模块未关闭并且含有下级等级分销商(不含最低级分销商)则准备下次降级检测
        if ($set['DISTRIBUTION_STATUS']) {
            foreach ($checkDown as $_checkDown_item) {
                $i = null;
                if ($i = self::itemOpt($_checkDown_item)) {
                    $disDown += $i;
                }
            }
        }
        // 更新/加入有效值
        if (!empty($disDown)) {
            self::disDownFlag($disDown);
        }
    }
    
    /**
     * 发送升级检测消息
     * @param $item
     * @return array
     */
    protected function itemOpt($item)
    {
        $disDown = [];
        if ($item['downgrade_order_cycle'] > 0 && $item['downgrade_brokerage_cycle'] > 0) {
            if ($item['downgrade_order_cycle'] == $item['downgrade_brokerage_cycle']) {
                $disDown[$item['distribution_id']] = $flag = uniqid();
                // 订单和佣金时间检测一致
                $delay = $item['downgrade_order_cycle'] * 3600 * 24;
                (new Beanstalk())->put(json_encode(['queue' => 'distributionDowngradeCheck', 'flag' => $flag,
                    'id' => $item['distribution_id'], 'order' => 1, 'brokerage' => 1, 'time' => date('Y-m-d H:i:s')]), $delay);
            } else {
                $disDown[$item['distribution_id']] = ($flagA = uniqid()) . "_" . ($flagB = uniqid());
                // 订单和佣金时间检测不一致
                $orderDelay = $item['downgrade_order_cycle'] * 3600 * 24;
                (new Beanstalk())->put(json_encode(['queue' => 'distributionDowngradeCheck', 'flag' => $flagA,
                    'id' => $item['distribution_id'], 'order' => 1,
                    'brokerage' => 0, 'time' => date('Y-m-d H:i:s')]), $orderDelay);
                $brokerageDelay = $item['downgrade_brokerage_cycle'] * 3600 * 24;
                (new Beanstalk())->put(json_encode(['queue' => 'distributionDowngradeCheck', 'flag' => $flagB,
                    'id' => $item['distribution_id'], 'order' => 0,
                    'brokerage' => 1, 'time' => date('Y-m-d H:i:s')]), $brokerageDelay);
            }
        }
        return $disDown;
    }
    
    /**
     * 更新分销商降级有效值
     * 升级后需要重新发送一条降级检测消息,故忽略上一条降级检测消息
     * @param $disDown
     */
    public function disDownFlag($disDown)
    {
        if (!empty($disDown)) {
            $redis = Cache::handler();
            $redis->select(5);
            $prefix = Config::get('cache.default')['prefix'];
            // key 为分销商id val 为随机唯一值
            foreach ($disDown as $_key => $_disDown) {
                $redis->set($prefix . 'downgrade_distribution_' . $_key, $_disDown);
            }
        }
    }
    
    /**
     * 会员升级为分销商
     * @param $memInfo
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function toBeDistributor($memInfo)
    {
        $distributionModel = new DistributionModel();
        $distributionLevelModel = new DistributionLevel();
        // 查找最低分销商等级
        $low_level_id = $distributionLevelModel
            ->order(['level_weight' => 'asc'])
            ->value('distribution_level_id');
        $superior = [];
        // 邀请人分销商id
        if ($memInfo['distribution_superior']) {
            // 有推荐人
            $superior = $distributionModel
                ->alias('d')
                ->where([
                    ['d.distribution_id', '=', $memInfo['distribution_superior']],
                    ['d.audit_status', '=', 1],
                ])
                ->join('member m', 'm.member_id = d.member_id')
                ->field('d.member_id,d.distribution_id,d.top_id,d.branch_strand,(select count(distribution_id) from `ishop_distribution`
                    where delete_time is null and audit_status = 1 and referrer_id = ' . $memInfo['distribution_superior'] . ') as num,
                    m.nickname,m.web_open_id,m.subscribe_time,m.micro_open_id,m.phone')
                ->find();
            if (!is_null($superior)) {
                // 推送消息[不含短信,小程序][获取下级粉丝]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey' => 'distribution_state',
                    'openId' => $superior['web_open_id'],
                    'subscribe_time' => $superior['subscribe_time'],
                    'microId' => '',
                    'phone' => $superior['phone'],
                    'data' => [0, $superior['nickname'], $memInfo['nickname']],
                    'inside_data' => [
                        'member_id' => $superior['member_id'],
                        'type' => 0,
                        'attach_id' => $superior['distribution_id'],
                        'jump_state' => '7',
                        'file' => 'image/dui.png',
                    ],
                ], 2);
            }
        }
        $inc = [
            'member_id' => $memInfo['member_id'],
            'real_name' => $memInfo['nickname'],
            'id_card' => '',
            'phone' => $memInfo['phone'],
            'sex' => $memInfo['sex'],
            'top_id' => !$superior ? 0 : $superior['top_id'],
            'referrer_id' => $memInfo['distribution_superior'] ?: 0,
            'branch_strand' => !$superior ? '1' : $superior['branch_strand'] . ',' . ($superior['num'] + 1),
            'distribution_level_id' => $low_level_id,
            'status' => 1,
            'audit_status' => 1,
            'become_type' => $memInfo['bType'],
            'audit_time' => date('Y-m-d H:i:s'),
            'apply_time' => date('Y-m-d H:i:s'),
        ];
        // 升级为分销商
        $distributionModel
            ->allowField(true)
            ->isUpdate(false)
            ->save($inc);
        // 推送消息[只含站内信][会员升级为分销商]
        $pushServer = app('app\\interfaces\\behavior\\Push');
        $pushServer->send([
            'tplKey' => 'distribution_state',
            'openId' => $memInfo['web_open_id'],
            'subscribe_time' => $memInfo['subscribe_time'],
            'microId' => $memInfo['micro_open_id'],
            'phone' => $memInfo['phone'],
            'data' => [4, (int)$memInfo['text']],
            'inside_data' => [
                'member_id' => $memInfo['member_id'],
                'type' => 0,
                'jump_state' => '3',
                'attach_id' => $distributionModel->distribution_id,
                'file' => 'image/dui.png',
            ],
            'sms_data' => [],
        ], 3);
        $inc['distribution_id'] = $distributionModel->distribution_id;
        // $inc['branch_strand'] = substr();
        if (!$superior) {
            // 自己为自己的顶级分销商
            $distributionModel->top_id = $distributionModel->distribution_id;
            $distributionModel->save();
            $inc['top_id'] = $distributionModel->distribution_id;
        } else {
            // 更新升级分销商计数器
            $strand = '(`distribution_id` != ' . $inc['distribution_id'] . ' and `top_id` = '
                . $inc['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' . $inc['branch_strand'] . '\',1) = 1 and'
                . ' (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) >= ' .
                (substr_count($inc['branch_strand'], ',') - 3) . ')';
            $inc['count'] = substr_count($inc['branch_strand'], ',');
            $relation = '(`top_id` = ' . $inc['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' .
                $inc['branch_strand'] . '\',1) = 1 and' . ' (length(`branch_strand`) - ' .
                'length(replace(`branch_strand`,\',\',\'\'))) < ' . substr_count($inc['branch_strand'], ',') . ')';
            $args = [
                'strand' => $strand,
                'relation' => $relation,
                'info' => $inc,
            ];
            // 更新上级分销商计数器
            self::updateUpper($args);
        }
        
        return $inc;
    }
    
    /**
     * 更新已有的分销商绑定
     * @param $data
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function bindExisted($data)
    {
        $distributionModel = new DistributionModel();
        // 查询当前用户的分销商信息
        $owner = $distributionModel
            ->where([
                ['distribution_id', '=', $data['distribution_id']],
                ['audit_status', '=', 1],
            ])
            ->field('distribution_id,top_id,referrer_id,branch_strand')
            ->find();
        if (!is_null($owner) && !$owner->referrer_id) {
            // 有推荐人
            $superior = $distributionModel
                ->alias('d')
                ->where([
                    ['d.distribution_id', '=', $data['distribution_superior']],
                    ['d.audit_status', '=', 1],
                ])
                ->join('member m', 'm.member_id = d.member_id')
                ->field('d.member_id,d.distribution_id,d.top_id,d.branch_strand,(select count(distribution_id) from `ishop_distribution`
                    where delete_time is null and audit_status = 1 and referrer_id = ' . $data['distribution_superior'] . ') as num,
                    m.nickname,m.web_open_id,m.subscribe_time,m.micro_open_id,m.phone')
                ->find();
            if (!is_null($superior)) {
                $owner->top_id = $superior['top_id'];
                $owner->referrer_id = $data['distribution_superior'];
                $owner->branch_strand = $superior['branch_strand'] . ',' . ($superior['num'] + 1);
                $owner->isUpdate(true)->save();
                // 推送消息[不含短信,小程序][获取下级粉丝]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey' => 'distribution_state',
                    'openId' => $superior['web_open_id'],
                    'subscribe_time' => $superior['subscribe_time'],
                    'microId' => '',
                    'phone' => $superior['phone'],
                    'data' => [0, $superior['nickname'], $data['nickname']],
                    'inside_data' => [
                        'member_id' => $superior['member_id'],
                        'type' => 0,
                        'attach_id' => $superior['distribution_id'],
                        'jump_state' => '7',
                        'file' => 'image/dui.png',
                    ],
                ], 2);
                // 更新升级分销商计数器
                $strand = '(`distribution_id` != ' . $owner->distribution_id . ' and `top_id` = '
                    . $owner->top_id . ' and locate(concat(`branch_strand`,\',\'),\'' . $owner->branch_strand . '\',1) = 1 and'
                    . ' (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) >= ' .
                    (substr_count($owner->branch_strand, ',') - 3) . ')';
                $owner->count = substr_count($owner->branch_strand, ',');
                $relation = '(`top_id` = ' . $owner->top_id . ' and locate(concat(`branch_strand`,\',\'),\'' .
                    $owner->branch_strand . '\',1) = 1 and' . ' (length(`branch_strand`) - ' .
                    'length(replace(`branch_strand`,\',\',\'\'))) < ' . substr_count($owner->branch_strand, ',') . ')';
                $args = [
                    'strand' => $strand,
                    'relation' => $relation,
                    'info' => $owner->toArray(),
                ];
                // 更新上级分销商计数器
                self::updateUpper($args);
            }
        }
    }
    
    /**
     * 升级上级分销商计数器
     * @param $args
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @throws \Exception
     */
    public function updateUpper($args)
    {
        $didArr = array_column(is_array($args['info']) ? $args['info'] : $args['info']->toArray(), 'distribution_id');
        $distributionModel = new DistributionModel();
        $whereRel = (is_array($args['relation']) ? '(' . implode(' or ', $args['relation']) . ')' . ' and distribution_id not in (' . implode(',', $didArr) . ')' : '(' . $args['relation'] . ')');
        $whereStr = (is_array($args['strand']) ? '(' . implode(' or ', $args['strand']) . ')' . ' and distribution_id not in (' . implode(',', $didArr) . ')' : '(' . $args['strand'] . ')');
        // 更新有关联的上级分销商的下级计数器
        Db::name('distribution')
            ->where($whereRel)
            ->update(['next_sum' => Db::raw('next_sum + 1')]);
        
        // 直接1和推荐2,3
        $strandInfo = $distributionModel
            ->where([
                ['audit_status', '=', 1],
            ])
            ->where($whereStr)
            ->field('distribution_id,top_id,branch_strand')
            ->select();
        $checkUpgrade = [];
        if (!$strandInfo->isEmpty()) {
            $updateDistributionArr = [];
            if (is_array($args['strand']) && !empty($args['strand'])) {
                foreach ($strandInfo as $_strandInfo) {
                    // 上三层分销商
                    $count = substr_count($_strandInfo['branch_strand'], ',');
                    $upc = [];
                    foreach ($args['info'] as $_info) {
                        // 同一分销链
                        if ($_strandInfo['top_id'] == $_info['top_id']) {
                            $upc['distribution_id'] = $_strandInfo['distribution_id'];
                            if ($count + 1 == $_info['count']) {
                                // 直属分销商上级
                                $upc['referrer_num'] = Db::raw('referrer_num + 1');
                                // 周期检测直属
                                $upc['cycle_up_referrer_num'] = Db::raw('cycle_up_referrer_num + 1');
                            }
                            if ($count + 2 == $_info['count'] || $count + 3 == $_info['count']) {
                                // 推荐2,3分销商上级(此时不验证平台分销层级设置)
                                $upc['relation_num'] = Db::raw('relation_num + 1');
                                // 周期检测推荐
                                $upc['cycle_up_relation_num'] = Db::raw('cycle_up_relation_num + 1');
                            }
                            // 含有上级分销商更新
                            if (count($upc) >= 2) {
                                array_push($updateDistributionArr, $upc);
                                $checkUpgrade[] = $upc['distribution_id'];
                            }
                        }
                    }
                }
            } elseif (is_string($args['strand']) && $args['strand'] !== '') {
                foreach ($strandInfo as $_strandInfo) {
                    // 上两层分销商
                    $count = substr_count($_strandInfo['branch_strand'], ',');
                    $upc = [];
                    // 同一分销链
                    if ($_strandInfo['top_id'] == $args['info']['top_id']) {
                        $upc['distribution_id'] = $_strandInfo['distribution_id'];
                        if ($count + 1 == $args['info']['count']) {
                            // 直属分销商上级
                            $upc['referrer_num'] = Db::raw('referrer_num + 1');
                            // 周期检测直属
                            $upc['cycle_up_referrer_num'] = Db::raw('cycle_up_referrer_num + 1');
                        }
                        if ($count + 2 == $args['info']['count'] || $count + 3 == $args['info']['count']) {
                            // 推荐2,3分销商上级(此时不验证平台分销层级设置)
                            $upc['relation_num'] = Db::raw('relation_num + 1');
                            // 周期检测推荐
                            $upc['cycle_up_relation_num'] = Db::raw('cycle_up_relation_num + 1');
                        }
                        // 含有上级分销商更新
                        if (count($upc) >= 2) {
                            array_push($updateDistributionArr, $upc);
                            $checkUpgrade[] = $upc['distribution_id'];
                        }
                    }
                }
            }
            if (!empty($updateDistributionArr)) {
                // 更新分销商直属和隔代层级计数器
                $distributionModel->allowField(true)->isUpdate(true)->saveAll($updateDistributionArr);
            };
        }
        // 分销商升级检测并更新[直属和隔代分销商变化]
        if (!empty($checkUpgrade)) {
            $dis['distribution_id'] = array_unique($checkUpgrade);
            self::levelUpgrade($dis);
        }
    }
    
    /**
     * 注册直接成为分销商
     * @param $memInfo
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function regToBe($memInfo)
    {
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $set = Env::get();
        $ret = [];
        // 平台开启分销模块并且开启注册即升为分销商
        if ($set['DISTRIBUTION_STATUS'] && $set['DISTRIBUTION_REGISTER']) {
            $ret = self::toBeDistributor($memInfo);
        }
        return $ret;
    }
}