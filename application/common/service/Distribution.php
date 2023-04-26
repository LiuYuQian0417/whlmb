<?php
declare(strict_types = 1);

namespace app\common\service;

use app\common\model\Distribution as DistributionModel;
use app\common\model\DistributionBook;
use app\common\model\Member;
use app\common\model\Message;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Hook;

/**
 * 分销
 * Class Distribution
 * @package app\common\service
 */
class Distribution
{
    protected $pk = 'distribution_id';
    protected $set;
    
    public function __construct()
    {
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $this->set = Env::get();
    }
    
    /**
     * 平台是否开启分销
     * @return bool
     */
    public function is_open()
    {
        return $this->set['DISTRIBUTION_STATUS'] ? true : false;
    }
    
    /**
     * 返回当前平台设置的层级
     * @return mixed
     */
    public function hierarchy()
    {
        return $this->set['DISTRIBUTION_HIERARCHY'];
    }
    
    /**
     * 平台设置是否开启分销商自购获利
     * @return mixed
     */
    public function commission()
    {
        return $this->set['DISTRIBUTION_COMMISSION'];
    }
    
    /**
     * 满X元升级分销商
     * @return int
     */
    public function full()
    {
        return $this->set['DISTRIBUTION_ACCUMULATIVE_PRICE'];
    }
    
    /**
     * 平台是否开启订单金额满X元
     * @return mixed
     */
    public function is_full()
    {
        return $this->set['DISTRIBUTION_ACCUMULATIVE'];
    }
    
    /**
     * 平台是否开启按照商品利润分佣
     * @return mixed
     */
    public function is_profit()
    {
        return $this->set['DISTRIBUTION_GOODS_PROFIT'];
    }
    
    /**
     * 平台默认分销佣金
     * @return array
     */
    public function ratio()
    {
        return [
            'one_ratio' => $this->set['DISTRIBUTION_ONE'],
            'two_ratio' => $this->set['DISTRIBUTION_TWO'],
            'three_ratio' => $this->set['DISTRIBUTION_THREE'],
        ];
    }
    
    
    /**
     * 分销处理
     * @param $data
     * @param $distribution_info
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function opera($data, $distribution_info)
    {
        $ret = ['distributionGoodsArr' => [], 'disId' => 0];
        // 平台分销开启
        if (self::is_open() && !empty($data)) {
            $distributionModel = new DistributionModel();
            // 获取层级
            $hierarchy = self::hierarchy();
            // 平台开启分销商自购获利
            $is_commission = self::commission();
            $levelWhere = '';
            // 输出佣金条件
            function lw($is_commission, $hierarchy, $levelWhere, $distribution_info)
            {
                if ($is_commission) {
                    if ($hierarchy == 1) {
                        // 只有分销商自己拿佣金
                        $levelWhere .= '(d.distribution_id = ' . $distribution_info['distribution_id'] . ')';
                    } else {
                        $levelWhere .= '(d.distribution_id = ' . $distribution_info['distribution_id'] . ' or (d.top_id = ' .
                            $distribution_info['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' .
                            $distribution_info['branch_strand'] . '\',1) = 1 and (length(`branch_strand`) ' .
                            '- length(replace(`branch_strand`,\',\',\'\'))) >= ' . (substr_count($distribution_info['branch_strand'], ',') - $hierarchy + 1) .
                            '))';
                    }
                } else {
                    if ($hierarchy == 1) {
                        if ($distribution_info['referrer_id']) {
                            // 直属推荐人不为0,表示不为顶级分销商
                            $levelWhere .= '(d.distribution_id = ' . $distribution_info['referrer_id'] . ')';
                        }
                    } else {
                        $levelWhere .= '(d.distribution_id != ' . $distribution_info['distribution_id'] . ' and d.top_id = ' .
                            $distribution_info['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' .
                            $distribution_info['branch_strand'] . '\',1) = 1 and (length(`branch_strand`) ' .
                            '- length(replace(`branch_strand`,\',\',\'\'))) >= ' . (substr_count($distribution_info['branch_strand'], ',') - $hierarchy) .
                            ')';
                    }
                }
                return $levelWhere;
            }
            
            $redis = Cache::handler();
            $redis->select(6);
            $prefix = Config::get('cache.default')['prefix'];
            $is_first = 0;
            // 当前购买者为[已审核过的]分销商
            if (!is_null($distribution_info['distribution_record']) &&
                $distribution_info['distribution_record']['audit_status'] == 1) {
                if (!empty($data['goods'])) {
                    $levelWhere = lw($is_commission, $hierarchy, $levelWhere, $distribution_info['distribution_record']);
                }
                $ret['disId'] = $distribution_info['distribution_record']['distribution_id'];
                // 查询是否含有缓存标识(没有则为稳定型)
                $flag = $redis->get($prefix . '_distribution_become_' . $ret['disId']);
                if ($flag) {
                    $flag_arr = json_decode($flag, true);
                    if ($flag_arr['type'] == 1 && !empty($data['distributor_goods'])) {
                        // 又购买了指定商品,续值
                        $flag_arr['val'] .= ',' . implode(',', $data['distributor_goods']);
                        $redis->set($prefix . '_distribution_become_' . $ret['disId'], json_encode($flag_arr));
                    }
                }
            } else {
                // 非分销商
                // 平台开启,购买成为分销商指定商品
                if (!empty($data['distributor_goods']) && Env::get('DISTRIBUTION_BUY', 0)) {
                    $flag_arr = [
                        'type' => 1,
                        'val' => implode(',', $data['distributor_goods']),
                    ];
                    if (is_null($distribution_info['distribution_record'])) {
                        // 升级为分销商
                        $toBe = [
                            'member_id' => $distribution_info['member_id'],
                            'nickname' => $distribution_info['nickname'],
                            'phone' => $distribution_info['phone'],
                            'sex' => $distribution_info['sex'],
                            'distribution_superior' => $distribution_info['distribution_superior'],
                            'text' => 0,    //购买指定商品
                            'web_open_id' => $distribution_info['web_open_id'],
                            'subscribe_time' => $distribution_info['subscribe_time'],
                            'micro_open_id' => $distribution_info['micro_open_id'],
                            'bType' => 3,
                        ];
                        $toBeRet = Hook::exec(['app\\interfaces\\behavior\\Distribution', 'toBeDistributor'], $toBe);
                        // 此次订单依旧分销
                        if (!empty($data['goods'])) {
                            $levelWhere = lw($is_commission, $hierarchy, $levelWhere, $toBeRet);
                        }
                        $ret['disId'] = $toBeRet['distribution_id'];
                        $is_first = 1;
                    } elseif ($distribution_info['distribution_record']['audit_status'] == 0) {
                        // 手动申请审核(审核中),更新为已审核
                        $distributionModel
                            ->allowField(true)
                            ->isUpdate(true)
                            ->save([
                                'distribution_id' => $distribution_info['distribution_record']['distribution_id'],
                                'become_type' => 3,
                                'audit_status' => 1,
                            ]);
                        // 更新升级分销商计数器
                        $updateUpperArgs = [
                            'strand' => '(`distribution_id` != ' . $distribution_info['distribution_record']['distribution_id'] . ' and `top_id` = '
                                . $distribution_info['distribution_record']['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' .
                                $distribution_info['distribution_record']['branch_strand'] . '\',1) = 1 and'
                                . ' (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) >= ' .
                                (substr_count($distribution_info['distribution_record']['branch_strand'], ',') - 2) . ')',
                            'relation' => '(`top_id` = ' . $distribution_info['distribution_record']['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' .
                                $distribution_info['distribution_record']['branch_strand'] . '\',1) = 1 and' . ' (length(`branch_strand`) - ' .
                                'length(replace(`branch_strand`,\',\',\'\'))) < ' . substr_count($distribution_info['distribution_record']['branch_strand'], ',') . ')',
                            'info' => [
                                'top_id' => $distribution_info['top_id'],
                                'count' => substr_count($distribution_info['distribution_record']['branch_strand'], ','),
                            ],
                        ];
                        Hook::exec(['app\\interfaces\\behavior\\Distribution', 'updateUpper'], $updateUpperArgs);
                        // 此次订单依旧分销
                        if (!empty($data['goods'])) {
                            $levelWhere = lw($is_commission, $hierarchy, $levelWhere, $distribution_info['distribution_record']);
                        }
                        $ret['disId'] = $distribution_info['distribution_record']['distribution_id'];
                        $is_first = 2;
                    }
                    $redis->set($prefix . '_distribution_become_' . $ret['disId'], json_encode($flag_arr));
                } elseif ((Env::get('DISTRIBUTION_ACCUMULATIVE', 0) &&
                    $data['cumulative_order_sum'] >= Env::get('DISTRIBUTION_ACCUMULATIVE_PRICE'))) {
                    $flag_arr = [
                        'type' => 2,
                        'val' => '',
                    ];
                    // 模块开启,购物满X元升级为分销商
                    if (is_null($distribution_info['distribution_record'])) {
                        // 当前会员非分销商并且(开启累积消费X元升级为分销商达标)
                        $toBe = [
                            'member_id' => $distribution_info['member_id'],
                            'nickname' => $distribution_info['nickname'],
                            'phone' => $distribution_info['phone'],
                            'sex' => $distribution_info['sex'],
                            'distribution_superior' => $distribution_info['distribution_superior'],
                            'web_open_id' => $distribution_info['web_open_id'],
                            'subscribe_time' => $distribution_info['subscribe_time'],
                            'micro_open_id' => $distribution_info['micro_open_id'],
                            'bType' => 4,   //满X元升级途径
                            'text' => 1,    //订单金额达标
                        ];
                        $toBeRet = Hook::exec(['app\\interfaces\\behavior\\Distribution', 'toBeDistributor'], $toBe);
                        // 此次订单依旧分销
                        if (!empty($data['goods'])) {
                            $levelWhere = lw($is_commission, $hierarchy, $levelWhere, $toBeRet);
                        }
                        $ret['disId'] = $toBeRet['distribution_id'];
                        $is_first = 3;
                    } elseif ($distribution_info['distribution_record']['audit_status'] == 0) {
                        // 手动申请审核(审核中),更新为已审核
                        $distributionModel
                            ->allowField(true)
                            ->isUpdate(true)
                            ->save([
                                'distribution_id' => $distribution_info['distribution_record']['distribution_id'],
                                'become_type' => 4,
                                'audit_status' => 1,
                                'audit_time' => date('Y-m-d H:i:s'),
                            ]);
                        // 更新上级分销商计数器
                        $updateUpperArgs = [
                            'strand' => '(`distribution_id` != ' . $distribution_info['distribution_record']['distribution_id'] . ' and `top_id` = '
                                . $distribution_info['distribution_record']['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' . $distribution_info['distribution_record']['branch_strand'] . '\',1) = 1 and'
                                . ' (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) >= ' .
                                (substr_count($distribution_info['distribution_record']['branch_strand'], ',') - 2) . ')',
                            'relation' => '(`top_id` = ' . $distribution_info['distribution_record']['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' .
                                $distribution_info['distribution_record']['branch_strand'] . '\',1) = 1 and' . ' (length(`branch_strand`) - ' .
                                'length(replace(`branch_strand`,\',\',\'\'))) < ' . substr_count($distribution_info['distribution_record']['branch_strand'], ',') . ')',
                            'info' => [
                                'top_id' => $distribution_info['distribution_record']['top_id'],
                                'count' => substr_count($distribution_info['distribution_record']['branch_strand'], ','),
                            ],
                        ];
                        Hook::exec(['app\\interfaces\\behavior\\Distribution', 'updateUpper'], $updateUpperArgs);
                        // 此次订单依旧分销
                        if (!empty($data['goods'])) {
                            $levelWhere = lw($is_commission, $hierarchy, $levelWhere, $distribution_info['distribution_record']);
                        }
                        $ret['disId'] = $distribution_info['distribution_record']['distribution_id'];
                        $is_first = 4;
                    }
                    $redis->set($prefix . '_distribution_become_' . $ret['disId'], json_encode($flag_arr));
                }
            }
            // 确切分销身份下..查询获利分销商数据
            if ($levelWhere && $ret['disId']) {
                $chain = $distributionModel
                    ->alias('d')
                    ->where([['audit_status', '=', 1]])
                    ->where($levelWhere)
                    ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
                    ->field('d.distribution_id,d.distribution_level_id,dl.one_ratio,dl.two_ratio,dl.three_ratio,dl.level_title')
                    // 加入分销商的时间越短越贴近当前购买者
                    ->order(['distribution_id' => 'desc'])
                    ->select();
                $distribution_book_arr = $distribution_update_arr = $updateTotalBro = [];
                if (!$chain->isEmpty()) {
                    // 平台默认分销规则
                    $goods_distribution_set['platform'] = self::ratio();
                    $distributor = ['a', 'b', 'c'];
                    foreach ($data['goods'] as $_data) {
                        array_push($ret['distributionGoodsArr'], $_data['order_goods_id']);
                        $goods_distribution_set['single'] = null;
                        $book_item = [
                            'order_goods_id' => $_data['order_goods_id'],
                            'order_attach_id' => $_data['order_attach_id'],
                            // 购买者分销商id
                            'distribution_id' => $ret['disId'],
                            'store_id' => $_data['store_id'],
                            'status' => 0,  // 待结算
                        ];
                        // 含有单品分佣规则
                        if ($_data['distribution_set']) {
                            $goods_distribution_set['single'] = unserialize($_data['distribution_set']);
                        }
                        foreach ($chain as $key => $_chain) {
                            $price = self::is_profit() ? $_data['profit'] : $_data['actual'];
                            $book_item['distributor_' . $distributor[$key]] = $_chain['distribution_id'];
                            if (!array_key_exists($_chain['distribution_id'], $updateTotalBro)) {
                                $updateTotalBro[$_chain['distribution_id']] = [
                                    'distribution_id' => $_chain['distribution_id'],
                                    'total_brokerage' => 0,
                                ];
                            }
                            // 分销商等级名称快照
                            $book_item['level_' . $distributor[$key] . '_snapshot'] = $_chain['level_title'];
                            if (!is_null($goods_distribution_set['single'])
                                // 使用单品分佣比例
                                && isset($goods_distribution_set['single'][($key + 1)])
                                && isset($goods_distribution_set['single'][($key + 1)][$_chain['distribution_level_id']])) {
                                $single_scale = $goods_distribution_set['single'][($key + 1)][$_chain['distribution_level_id']];
                                // 检测是否含有当前分销商等级的分佣规则
                                $book_item['rule_type_' . $distributor[$key]] = 1;
                                $book_item['distributor_' . $distributor[$key] . '_brokerage'] = $_data['rebates_type'] ?
                                    ($price > 0 ? $price : 0) * $single_scale / 100 : $single_scale * $_data['quantity'];
                                // 分销商分佣规则内容快照
                                $book_item['rule_snapshot_content_' . $distributor[$key]] = '单品佣金规则,' . ['一级', '二级', '三级'][$key]
                                    . $_chain['level_title'] . ":" . $single_scale . ($_data['rebates_type'] ? '%' : '元');
                            } elseif (($level_scale = $_chain[['one_ratio', 'two_ratio', 'three_ratio'][$key]]) > 0) {
                                // 使用分销商等级比例
                                $book_item['rule_type_' . $distributor[$key]] = 2;
                                $book_item['distributor_' . $distributor[$key] . '_brokerage'] = ($price > 0 ? $price : 0) * $level_scale / 100;
                                // 分销商分佣规则内容快照
                                $book_item['rule_snapshot_content_' . $distributor[$key]] = '分销商等级佣金规则,' . ['一级', '二级', '三级'][$key]
                                    . $_chain['level_title'] . ":" . $level_scale . '%';
                            } else {
                                // 使用平台比例
                                $platform = $goods_distribution_set['platform'][['one_ratio', 'two_ratio', 'three_ratio'][$key]];
                                $book_item['rule_type_' . $distributor[$key]] = 3;
                                $book_item['distributor_' . $distributor[$key] . '_brokerage'] = ($price > 0 ? $price : 0) * $platform / 100;
                                // 分销商分佣规则内容快照
                                $book_item['rule_snapshot_content_' . $distributor[$key]] = '平台佣金规则,' . ['一级', '二级', '三级'][$key]
                                    . $_chain['level_title'] . ":" . $platform . '%';
                            }
                            $updateTotalBro[$_chain['distribution_id']]['total_brokerage'] += $book_item['distributor_' . $distributor[$key] . '_brokerage'];
                        }
                        array_push($distribution_book_arr, $book_item);
                        $book_item = null;
                    }
                }
                // 将分销商获利订单数据插表
                if (!empty($distribution_book_arr)) {
                    $distributionBookModel = new DistributionBook();
                    $distributionBookModel->allowField(true)->isUpdate(false)->saveAll($distribution_book_arr);
                }
                // 更新分销商分销佣金总金额[此时是待结算佣金]
                if (!empty($updateTotalBro)) {
                    $distributionModel->allowField(true)
                        ->isUpdate(true)
                        ->saveAll(array_map(function ($v) {
                            $v['total_brokerage'] = Db::raw('total_brokerage + ' . $v['total_brokerage']);
                            return $v;
                        }, $updateTotalBro));
                }
                if ($is_first > 0) {
                    // 加入第一次升级分销商标识
                    Cache::store('file')->set($ret['disId'], $is_first);
                }
            }
        }
        return $ret;
    }
    
    /**
     * 检测分销商是否需要撤销
     * @param $args
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkDistIsRevoke($args)
    {
        $redis = Cache::handler();
        $redis->select(6);
        $prefix = Config::get('cache.default')['prefix'];
        $flag = $redis->get($prefix . '_distribution_become_' . $args['distribution_id']);
        if ($flag) {
            $flag_arr = json_decode($flag, true);
            if ($flag_arr['type'] == 1) {
                // 方式购买指定成为的分销商
                // 检测是否含有此次售后,去除,为空则撤销分销资格
                $flag_arr['val'] = explode(',', $flag_arr['val']);
                $new_flag_arr = [
                    'type' => $flag_arr['type'],
                    'val' => [],
                ];
                foreach ($flag_arr['val'] as $_v) {
                    if (!in_array($_v, $args['og_id'])) {
                        array_push($new_flag_arr['val'], $_v);
                    }
                }
                if (empty($new_flag_arr['val'])) {
                    // 撤销分销商资格
                    $args['type'] = 1;
                    $args['distribution_id'] = [$args['distribution_id']];
                    self::distributionRevoke($args);
                } else {
                    // 重新计入缓存标识
                    $new_flag_arr['val'] = implode(',', $new_flag_arr['val']);
                    $redis->set($prefix . '_distribution_become_' . $args['distribution_id'], json_encode($new_flag_arr));
                }
            } else {
                // 不管此时是否开启满X升级分销商
                $acc = self::full();
                // 会员现有订单金额小于成为分销商满额
                if ($acc && $args['cumulative_order_sum'] < $acc) {
                    // 撤销分销商资格
                    $args['type'] = 2;
                    if (!is_array($args['distribution_id'])) {
                        $args['distribution_id'] = [$args['distribution_id']];
                    }
                    self::distributionRevoke($args);
                }
            }
        }
    }
    
    /**
     * 解除分销商资格后更改上下级分销商层级关系
     * @param $args
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function distributionRevoke($args)
    {
        if (!empty($args)) {
            $distributionModel = new DistributionModel();
            $msg = $memberArr = [];
            if (!array_key_exists('audit_status', $args) || $args['audit_status'] == 1) {
                $info = $distributionModel
                    ->alias('d')
                    ->where([
                        ['distribution_id', 'in', implode(',', $args['distribution_id'])],
                    ])
                    ->join('member m', 'm.member_id = d.member_id')
                    ->field('d.distribution_id,d.top_id,d.branch_strand,d.member_id,
                    m.web_open_id,m.phone,m.micro_open_id,m.subscribe_time')
                    ->select();
                // 清除记录
                $distributionModel
                    ->isUpdate(true)
                    ->where([['distribution_id', 'in', implode(',', $args['distribution_id'])]])
                    ->update(['delete_time' => date('Y-m-d H:i:s')]);
                // 抹除选中分销商的上下级记录存在
                if (!$info->isEmpty()) {
                    $sql = $count = [];
                    // 查询关联选中分销商的其他分销商
                    foreach ($info as $_info) {
                        $memberArr[] = $_info['member_id'];
                        // 记录被撤销分销商数据
                        $count[] = [
                            'cur_id' => $_info['distribution_id'],
                            'top_id' => $_info['top_id'],
                            'branch_strand' => $_info['branch_strand'],
                            'count' => substr_count($_info['branch_strand'], ','),
                        ];
                        $sql[] = '(top_id = ' . $_info['top_id'] . ' and audit_status = 1 and distribution_id != ' . $_info['distribution_id'] .
                            ' and (locate(concat(`branch_strand`,\',\'),\'' . $_info['branch_strand'] . '\',1) = 1 or ' .
                            'locate(\'' . $_info['branch_strand'] . '\',`branch_strand`,1) = 1))';
                        array_push($msg, [
                            'tplKey' => 'distribution_state',
                            'openId' => $_info['web_open_id'],
                            'subscribe_time' => $_info['subscribe_time'],
                            'microId' => $_info['micro_open_id'],
                            'phone' => $_info['phone'],
                            'data' => [2, $args['type'] - 1],
                            'inside_data' => [
                                'member_id' => $_info['member_id'],
                                'type' => 0,
                                'jump_state' => '-1',
                                'file' => 'image/cuo.png',
                            ],
                            'sms_data' => [],
                        ]);
                        
                    }
                    if (!empty($sql) && !empty($count)) {
                        $other = $distributionModel
                            ->whereRaw(implode(' or ', $sql))
                            ->field('distribution_id,top_id,branch_strand')
                            ->order(['branch_strand' => 'asc'])
                            ->select();
                        if (!$other->isEmpty()) {
                            $change = $top = [];
                            $next_all_num = 0;
                            foreach ($other as $_other) {
                                foreach ($count as $_count) {
                                    $_change = [];
                                    if ($_count['top_id'] == $_other['top_id'] &&
                                        (stripos($_other['branch_strand'], $_count['branch_strand'], 0) === 0 ||
                                            stripos($_count['branch_strand'], $_other['branch_strand'], 0) === 0)) {
                                        $_change['distribution_id'] = $_other['distribution_id'];
                                        // 同一分销链
                                        $curCount = substr_count($_other['branch_strand'], ',');
                                        if ($curCount < $_count['count']) {
                                            // 确认为被撤销分销商的上级
                                            // 撤销分销商的上级
                                            if ($curCount == $_count['count'] - 1) {
                                                // 直属上级
                                                $_change['referrer_num'] = Db::raw('referrer_num - 1');
                                            }
                                            if ($curCount == $_count['count'] - 2 || $curCount == $_count['count'] - 3) {
                                                // 隔代上2级
                                                $_change['relation_num'] = Db::raw('relation_num - 1');
                                            }
                                            // 默认下级关系计数器减1
                                            $_change['next_sum'] = Db::raw('next_sum - 1');
                                        } else {
                                            // 确认为被撤销分销商的下级
                                            // 撤销分销商的下级
                                            if ($curCount == $_count['count'] + 1) {
                                                // 直属下级
                                                $top[$_other['top_id']] = $_other['distribution_id'];
                                                // 更新顶级分销商id为自己的分销商id
                                                $_change['top_id'] = $_other['distribution_id'];
                                                $_change['referrer_id'] = 0;
                                            } else {
                                                // 其他下级
                                                $_change['top_id'] = $top[$_other['top_id']];
                                            }
                                            // 更新分销链值[截取掉已撤销分销商的分销链值]
                                            $_change['branch_strand'] = substr($_other['branch_strand'], (strlen($_count['branch_strand']) + 1));
                                            // 成为独立链
                                            $_change['branch_strand'][0] = 1;
                                            $next_all_num++;
                                        }
                                    }
                                    if (!empty($_change)) {
                                        array_push($change, $_change);
                                    }
                                }
                            }
                            if (!empty($change)) {
                                if ($next_all_num > 0) {
                                    $next_all_num++;
                                    foreach ($change as &$_change) {
                                       $_change['next_sum'] = Db::raw("next_sum - {$next_all_num}");
                                    }
                                }
                                // 更新分销商设置
                                $distributionModel->allowField(true)->isUpdate(true)->saveAll($change);
                            }
                        }
                    }
                }
            } else {
                $save = [];
                $disInfo = $distributionModel
                    ->alias('d')
                    ->where([
                        ['distribution_id', 'in', implode(',', $args['distribution_id'])],
                    ])
                    ->join('member m', 'm.member_id =  d.member_id')
                    ->field('d.distribution_id,m.web_open_id,m.subscribe_time,m.phone,
                    m.micro_open_id')
                    ->select();
                foreach ($disInfo as $key => $_arg) {
                    array_push($save, [
                        'distribution_id' => $_arg['distribution_id'],
                        'audit_status' => 2,
                        'refuse_reason' => '会员删除,分销商资格撤销',
                        'audit_time' => date('Y-m-d H:i:s'),
                    ]);
                    // 发送撤销资格消息
                    array_push($msg, [
                        'tplKey' => 'distribution_state',
                        'openId' => $_arg['web_open_id'],
                        'subscribe_time' => $_arg['subscribe_time'],
                        'microId' => $_arg['micro_open_id'],
                        'phone' => $_arg['phone'],
                        'data' => [2, $args['type'] - 1],
                        'inside_data' => [
                            'member_id' => $args['member_id'][$key],
                            'type' => 0,
                            'jump_state' => '-1',
                            'file' => 'image/cuo.png',
                        ],
                        'sms_data' => [],
                    ]);
                }
                $memberArr = $args['member_id'];
                // 拒绝分销商申请
                if ($save) {
                    $distributionModel
                        ->allowField(true)
                        ->isUpdate(true)
                        ->saveAll($save);
                }
            }
            if (!empty($msg)) {
                // 推送消息[只含站内信][撤销分销商资格]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                foreach ($msg as $_msg) {
                    $pushServer->send($_msg, 3);
                }
            }
            // 清空用户的累计订单金额
            if (!empty($memberArr)) {
                (new Member())
                    ->allowField(true)
                    ->isUpdate(true)
                    ->where([
                        ['member_id', 'in', implode(',', $memberArr)],
                    ])
                    ->update(['cumulative_order_sum' => 0]);
            }
        }
        
    }
}