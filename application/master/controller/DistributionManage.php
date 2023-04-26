<?php
declare(strict_types = 1);

namespace app\master\controller;

use app\common\model\Distribution;
use app\common\model\DistributionChangeRecord;
use app\common\model\DistributionLevel;
use app\common\model\Member;
use app\common\model\MemberGrowthRecord;
use app\common\model\MemberRank;
use app\common\model\Message;
use think\Controller;
use think\Db;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;

class DistributionManage extends Controller
{
    
    /**
     * 分销商列表
     * @param Request $request
     * @param Distribution $distribution
     * @param DistributionLevel $distributionLevel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function manageList(Request $request,
                               Distribution $distribution,
                               DistributionLevel $distributionLevel)
    {
        $param = $request::get();
        $where = [['audit_status', '=', 1]];
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['d.create_time', 'between time', [$begin, $end]]);
        }
        if (array_key_exists('level', $param) && $param['level']) {
            array_push($where, ['d.distribution_level_id', '=', $param['level']]);
        }
        if (array_key_exists('keyword', $param) && $param['keyword']) {
            array_push($where, ['m.nickname|m.phone', 'like', "%" . trim($param['keyword']) . "%"]);
        }
        $data = $distribution
            ->alias('d')
            ->where($where)
            ->join('member m', 'm.member_id = d.member_id')
            ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
            ->field(
                'd.distribution_id,d.member_id,d.distribution_level_id,dl.level_title,m.cumulative_order_sum,
            d.total_brokerage,d.next_sum,d.status,d.audit_time,m.nickname,m.avatar,m.phone'
            )
            ->order(['audit_time' => 'desc', 'distribution_id' => 'desc'])
            ->paginate($distribution->pageLimits, false, ['query' => $param]);
        //  查询分销商等级
        $level = $distributionLevel
            ->field('distribution_level_id,level_title,mark,one_ratio,two_ratio,three_ratio')
            ->append(['mark_alias'])
            ->order(['level_weight' => 'asc', 'distribution_level_id' => 'asc'])
            ->select();
        // 查询分销商等级集合
        return $this->fetch(
            '',
            [
                'data' => $data,
                'level' => $level,
                'single_store' => config('user.one_more'),
            ]
        );
    }
    
    /**
     * 冻结/解冻分销商
     * 不可提现(其他没限制)
     * @param Request $request
     * @param Distribution $distribution
     * @return array
     */
    public function distributionFrozen(Request $request, Distribution $distribution)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                if (array_key_exists('data', $param) && $param['data']) {
                    $distribution
                        ->allowField(true)
                        ->isUpdate(true)
                        ->saveAll($param['data']);
                }
                return ['code' => 0, 'message' => '修改成功'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 会员转分销商...会员列表
     * @param Request $request
     * @param Member $member
     * @param MemberRank $memberRank
     * @param MemberGrowthRecord $memberGrowthRecord
     * @param DistributionLevel $distributionLevel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function memberConvertDistribution(Request $request,
                                              Member $member,
                                              MemberRank $memberRank,
                                              MemberGrowthRecord $memberGrowthRecord,
                                              DistributionLevel $distributionLevel)
    {
        $param = $request::get();
        $where = [
            // 查询非分销商数据
            ['distribution_id', 'exp', Db::raw('is null')],
        ];
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['m.register_time', 'between time', [$begin, $end]]);
        }
        if (array_key_exists('level', $param) && $param['level']) {
            list($min, $max) = explode('_', $param['level']);
            $filterMemberId = Db::table(
                $memberGrowthRecord
                    ->field('member_id,sum(growth_value) as growth_value_sum')
                    ->having(('growth_value_sum > ' . $min . ' and growth_value_sum < ' . $max))
                    ->group('member_id')
                    ->buildSql() . ' sub'
            )->column('sub.member_id');
            array_push($where, ['m.member_id', 'in', implode(',', $filterMemberId)]);
        }
        if (array_key_exists('keyword', $param) && $param['keyword']) {
            array_push($where, ['m.nickname|m.phone', 'like', "%" . trim($param['keyword']) . "%"]);
        }
        $list = $member
            ->alias('m')
            ->where($where)
            ->field(
                'm.member_id,m.avatar,m.nickname,m.phone,m.pay_points,
            m.usable_money,m.frozen_money,m.register_time,d.distribution_id'
            )
            ->join(
                'distribution d',
                'd.member_id = m.member_id and d.audit_status <> 2 and d.delete_time is null',
                'left'
            )
            ->order(['register_time' => 'desc', 'member_id' => 'desc'])
            ->paginate($member->pageLimits, false, ['query' => $param]);
        $rank = $memberRank
            ->field('member_rank_id,rank_name,min_points,max_points')
            ->select();
        // 查询分销商等级集合
        $levelArr = $distributionLevel
            ->field('distribution_level_id,level_title,mark,one_ratio,two_ratio,three_ratio')
            ->append(['mark_alias'])
            ->order(['level_weight' => 'asc'])
            ->select();
        return $this->fetch(
            'distribution_manage/member_convert_distribution',
            [
                'data' => $list,
                'rank' => $rank,
                'levelArr' => $levelArr,
                'single_store' => config('user.one_more'),
            ]
        );
    }
    
    /**
     * 会员转分销商...转化
     * @param Request $request
     * @param Distribution $distribution
     * @param Member $member
     * @param DistributionLevel $distributionLevel
     * @return array
     */
    public function covertToDistribution(Request $request,
                                         Distribution $distribution,
                                         Member $member,
                                         DistributionLevel $distributionLevel)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                Db::startTrans();
                if (array_key_exists('id', $param) && $param['id'] && $param['level_id']) {
                    $info = $member
                        ->where([['member_id', 'in', $param['id']]])
                        ->field('member_id,nickname,phone,sex,web_open_id,subscribe_time,micro_open_id,phone')
                        ->select();
                    $data = $msg = $checkDown = $changeSelf = $distributionMsg = [];
                    if (!$info->isEmpty()) {
                        foreach ($info as $item) {
                            array_push($data, [
                                'member_id' => $item['member_id'],
                                'distribution_level_id' => $param['level_id'],
                                'phone' => $item['phone'],
                                'real_name' => $item['nickname'],
                                'sex' => $item['sex'],
                                'audit_status' => 1,        // 默认已通过
                                'audit_time' => date('Y-m-d H:i:s'),
                            ]);
                            $msg[$item['member_id']] = [
                                'tplKey' => 'distribution_state',
                                'openId' => $item['web_open_id'],
                                'subscribe_time' => $item['subscribe_time'],
                                'microId' => $item['micro_open_id'],
                                'phone' => $item['phone'],
                                'data' => [3],
                                'inside_data' => [
                                    'member_id' => $item['member_id'],
                                    'type' => 0,
                                    'jump_state' => '3',
                                    'file' => 'image/dui.png',
                                ],
                                'sms_data' => [],
                            ];
                        }
                    }
                    if ($data) {
                        // 插入新分销商
                        $inRet = $distribution->allowField(true)->isUpdate(false)->saveAll($data);
                        if ($inRet) {
                            // 查询等级降级策略
                            $downInfo = $distributionLevel
                                ->where([['distribution_level_id', '=', $param['level_id']]])
                                ->field('distribution_level_id,level_weight,downgrade_brokerage_cycle,
                               downgrade_order_cycle')
                                ->find();
                            // 非最低等级发送检测
                            foreach ($inRet as $_inRet) {
                                $changeSelf[] = [
                                    'distribution_id' => $_inRet['distribution_id'],
                                    'top_id' => $_inRet['distribution_id'],
                                ];
                                $msg[$_inRet['member_id']]['inside_data']['attach_id'] = $_inRet['distribution_id'];
                                $distributionMsg[] = $msg[$_inRet['member_id']];
                                if ($downInfo['level_weight'] > 0) {
                                    array_push($checkDown, [
                                        'distribution_id' => $_inRet['distribution_id'],
                                        'downgrade_order_cycle' => $downInfo['downgrade_order_cycle'],
                                        'downgrade_brokerage_cycle' => $downInfo['downgrade_brokerage_cycle'],
                                    ]);
                                }
                            }
                        }
                    }
                    if (!empty($distributionMsg)) {
                        // 推送消息[会员转分销商][只含站内信]
                        $pushServer = app('app\\interfaces\\behavior\\Push');
                        foreach ($distributionMsg as $_msg) {
                            $pushServer->send($_msg, 3);
                        }
                    }
                    if (!empty($changeSelf)) {
                        // 更新自己的分销商
                        $distribution->isUpdate(true)->allowField(true)->saveAll($changeSelf);
                    }
                    if (!empty($checkDown)) {
                        Hook::exec(['app\\interfaces\\behavior\\Distribution', 'sendDownMsg'], $checkDown);
                    }
                }
                Db::commit();
                return ['code' => 0, 'message' => '转化成功'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 分销商审核...分销商列表
     * @param Request $request
     * @param Distribution $distribution
     * @param MemberGrowthRecord $memberGrowthRecord
     * @param MemberRank $memberRank
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distributionAuditList(Request $request,
                                          Distribution $distribution,
                                          MemberGrowthRecord $memberGrowthRecord,
                                          MemberRank $memberRank)
    {
        $param = $request::get();
        $where = [['audit_status', '=', 0]];
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['d.apply_time', 'between time', [$begin, $end]]);
        }
        if (array_key_exists('level', $param) && $param['level']) {
            list($min, $max) = explode('_', $param['level']);
            $filterMemberId = Db::table(
                $memberGrowthRecord
                    ->field('member_id,sum(growth_value) as growth_value_sum')
                    ->having(('growth_value_sum > ' . $min . ' and growth_value_sum < ' . $max))
                    ->group('member_id')
                    ->buildSql() . ' sub'
            )->column('sub.member_id');
            array_push($where, ['m.member_id', 'in', implode(',', $filterMemberId)]);
        }
        if (array_key_exists('keyword', $param) && $param['keyword']) {
            array_push($where, ['m.nickname|m.phone', 'like', "%" . trim($param['keyword']) . "%"]);
        }
        $data = $distribution
            ->alias('d')
            ->where($where)
            ->join('member m', 'm.member_id = d.member_id')
            ->field(
                'm.member_id,m.avatar,m.nickname,m.phone,m.pay_points,
            m.usable_money,m.frozen_money,d.apply_time,d.distribution_id,
            d.real_name,d.id_card,d.phone,d.sex,d.wechat_no,d.address'
            )
            ->order(['apply_time' => 'desc', 'distribution_id' => 'desc'])
            ->paginate($distribution->pageLimits, false, ['query' => $param]);
        $rank = $memberRank
            ->field('member_rank_id,rank_name,min_points,max_points')
            ->select();
        return $this->fetch(
            '',
            [
                'data' => $data,
                'rank' => $rank,
                'single_store' => config('user.one_more'),
            ]
        );
    }
    
    /**
     * 分销商审核
     * @param Request $request
     * @param Distribution $distribution
     * @return array
     */
    public function distributionAudit(Request $request,
                                      Distribution $distribution)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                if (is_numeric($param['text'])) {
                    return ['code' => -100, 'message' => '重新填写拒绝原因'];
                }
                if (array_key_exists('id', $param) && $param['id']) {
                    // 查询分销商信息
                    $info = $distribution
                        ->alias('d')
                        ->where([
                            ['distribution_id', 'in', $param['id']],
                        ])
                        ->join('member m', 'm.member_id = d.member_id')
                        ->field('d.distribution_id,d.branch_strand,d.top_id,d.referrer_id,ifnull((select count(ds.distribution_id) from
                        `ishop_distribution` ds where delete_time is null and audit_status = 1 and referrer_id = d.referrer_id),0) as num,
                        m.phone,m.web_open_id,m.subscribe_time,m.micro_open_id')
                        ->select();
                    $data = $msg = $strand = $relation = [];
                    foreach ($info as $key => &$item) {
                        $item['branch_strand'] = ($item['referrer_id'] ?
                            $item['branch_strand'] . ',' . ($item['num'] + 1) : $item['branch_strand']);
                        $udt = [
                            'distribution_id' => $item['distribution_id'],
                            'branch_strand' => $item['branch_strand'],
                            'audit_status' => $param['status'],
                            'refuse_reason' => $param['text'],
                            'audit_time' => date('Y-m-d H:i:s'),
                        ];
                        if ($param['status'] == 2) {
                            // 拒绝即删除此记录
                            $udt['delete_time'] = date('Y-m-d H:i:s');
                        }
                        array_push($data, $udt);
                        // 审核通过添加更改分销链的数据
                        if ($param['status'] == 1) {
                            $strand[] = '(`distribution_id` != ' . $item['distribution_id'] . ' and `top_id` = '
                                . $item['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' . $item['branch_strand'] . '\',1) = 1 and'
                                . ' (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) >= ' .
                                (substr_count($item['branch_strand'], ',') - 3) . ')';
                            $item['count'] = substr_count($item['branch_strand'], ',');
                            $relation[] = '(`top_id` = ' . $item['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' .
                                $item['branch_strand'] . '\',1) = 1 and' . ' (length(`branch_strand`) - ' .
                                'length(replace(`branch_strand`,\',\',\'\'))) < ' . substr_count(
                                    $item['branch_strand'],
                                    ','
                                ) . ')';
                        }
                        array_push($msg, [
                            'tplKey' => 'distribution_state',
                            'openId' => $item['web_open_id'],
                            'subscribe_time' => $item['subscribe_time'],
                            'microId' => $item['micro_open_id'],
                            'phone' => $item['phone'],
                            'data' => [($param['status'] == 1) ? 5 : 6, ($param['status'] == 1 ? '' : $param['text'])],
                            'inside_data' => [
                                'member_id' => explode(',', $param['mid'])[$key],
                                'type' => 0,
                                'attach_id' => $item['distribution_id'],
                                'jump_state' => ($param['status'] == 1) ? '3' : '12',
                                'file' => ($param['status'] == 1) ? 'image/dui.png' : 'image/cuo.png',
                            ],
                            'sms_data' => [],
                        ]);
                    }
                    Db::startTrans();
                    if ($data) {
                        // 更新审核状态
                        $distribution->allowField(true)->isUpdate(true)->saveAll($data);
                    }
                    if ($msg) {
                        // 推送消息[只含站内信][分销商申请审核]
                        $pushServer = app('app\\interfaces\\behavior\\Push');
                        foreach ($msg as $_msg) {
                            $pushServer->send($_msg, 3);
                        }
                    }
                    // 更新上级分销商计数器
                    if (!empty($strand) && !empty($relation)) {
                        $args = [
                            'strand' => $strand,
                            'relation' => $relation,
                            'info' => $info,
                        ];
                        Hook::exec(['app\\interfaces\\behavior\\Distribution', 'updateUpper'], $args);
                    }
                    Db::commit();
                }
                return ['code' => 0, 'message' => '审核成功'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 修改分销商等级
     * @param Request $request
     * @param Distribution $distribution
     * @param DistributionLevel $distributionLevel
     * @return array
     */
    public function editDistributionLevel(Request $request,
                                          Distribution $distribution,
                                          DistributionLevel $distributionLevel)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                // 查询更改后的分销商等级降级策略
                $levelDown = $distributionLevel
                    ->where([['distribution_level_id', '=', $param['level_id']]])
                    ->field(
                        'distribution_level_id,level_weight,downgrade_brokerage_cycle,
                    downgrade_order_cycle,level_title'
                    )
                    ->find();
                if (!is_null($levelDown)) {
                    $info = $distribution
                        ->alias('d')
                        ->where(
                            [
                                ['distribution_id', '=', $param['id']],
                            ]
                        )
                        ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
                        ->field(
                            'd.distribution_id,dl.level_title,d.distribution_level_id,dl.level_weight,
                        d.cycle_up_brokerage,d.cycle_up_order_num,d.cycle_up_order_sum,d.cycle_up_referrer_num,
                        d.cycle_up_relation_num,dl.upgrade_total_brokerage,dl.upgrade_total_order_num,dl.upgrade_total_order_sum,
                        dl.upgrade_direct_next_num,dl.upgrade_next_num'
                        )
                        ->find();
                    if (is_null($info)) {
                        return ['code' => 0, 'message' => '分销商不存在'];
                    }
                    $info->distribution_level_id = $levelDown['distribution_level_id'];
                    $info->save();
                    $res = [
                        ['type', 3],
                        ['brokerage', $info['cycle_up_brokerage'], $info['upgrade_total_brokerage']],
                        ['order_num', $info['cycle_up_order_num'], $info['upgrade_total_order_num']],
                        ['order_sum', $info['cycle_up_order_sum'], $info['upgrade_total_order_sum']],
                        ['referrer_num', $info['cycle_up_referrer_num'], $info['upgrade_direct_next_num']],
                        ['relation_num', $info['cycle_up_relation_num'], $info['upgrade_next_num']],
                    ];
                    $upgradeChangeRecord = [
                        'distribution_id' => $param['id'],
                        'change_type' => $info['level_weight'] > $levelDown['level_weight'] ? 2 : 1,
                        // 升降类型 1升级 2降级
                        'now_level_id' => $levelDown['distribution_level_id'],
                        'now_level_title' => $levelDown['level_title'],
                        'ago_level_id' => $info['distribution_level_id'],
                        'ago_level_title' => $info['level_title'],
                        'upgrade_down_reason' => implode(
                            ';',
                            array_map(
                                function ($v) {
                                    return implode(',', $v);
                                },
                                $res
                            )
                        ),
                    ];
                    // 插入分销商记录
                    (new DistributionChangeRecord())
                        ->allowField(true)
                        ->isUpdate(false)
                        ->save($upgradeChangeRecord);
                    if ($levelDown['level_weight'] > 0) {
                        // 非最低分销商等级则更改分销商的降级策略检测
                        $checkDown[] = [
                            'distribution_id' => $param['id'],
                            'downgrade_order_cycle' => $levelDown['downgrade_order_cycle'],
                            'downgrade_brokerage_cycle' => $levelDown['downgrade_brokerage_cycle'],
                        ];
                        Hook::exec(['app\\interfaces\\behavior\\Distribution', 'sendDownMsg'], $checkDown);
                    }
                    return ['code' => 0, 'message' => '修改成功'];
                } else {
                    return ['code' => 0, 'message' => '修改无效,等级错误'];
                }
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 分销商粉丝列表
     * @param Request $request
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function fans(Request $request, Distribution $distribution)
    {
        $args = $request::get();
        // 查询当前会员的顶级上线
        $distribution_info = $distribution
            ->where([['distribution_id', '=', $args['did']]])
            ->field('top_id,branch_strand,distribution_id,member_id')
            ->find();
        if ($distribution_info) {
            // 订单数量order_num[含已结算和未结算] 总收益total_brokerage[含已结算和未结算] 推荐日期date
            $order = [];
            if (array_key_exists('order', $args) && $args['order']) {
                $orderText = [1 => 'order_num', 2 => 'd.total_brokerage', 3 => 'd.create_time'];
                $sort = (array_key_exists('sort', $args) && $args['sort'] == 1) ? 'asc' : 'desc';
                $order[$orderText[$args['order']]] = $sort;
            }
            $order['distribution_id'] = 'desc';
            // 默认查询当前会员所占层级以下的层级(即为当前会员的粉丝)
            $where = 'distribution_id <> ' . $distribution_info['distribution_id'] . ' and `audit_status` = 1 and `top_id` = '
                . $distribution_info['top_id'] . ' and locate(\'' . $distribution_info['branch_strand'] . ',\',`branch_strand`,1) = 1';
            Env::load(Env::get('app_path') . 'common/ini/.distribution');
            $distribution_hierarchy = Env::get('DISTRIBUTION_HIERARCHY');
            $allRec = true;
            switch ($distribution_hierarchy) {
                case 2:
                    // 2级分销,直属第1级,推荐只有第2级,无3级推荐
                    $fans_level = ($args['type'] == 1) ? 1 : 2;
                    $stc['all'] = $distribution
                        ->where(
                            $where . ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) <= '
                            . (substr_count($distribution_info['branch_strand'], ',') + 2)
                        )
                        ->count();
                    $stc['referrer'] = $distribution
                        ->where(
                            $where . ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) = '
                            . (substr_count($distribution_info['branch_strand'], ',') + 1)
                        )
                        ->count();
                    $stc['relation'] = $distribution
                        ->where(
                            $where . ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) = '
                            . (substr_count($distribution_info['branch_strand'], ',') + 2)
                        )
                        ->count();
                    break;
                case 3:
                    // 3级分销,直属为下一层级,推荐为隔代2层级
                    $stc['all'] = $distribution
                        ->where(
                            $where . ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) <= '
                            . (substr_count($distribution_info['branch_strand'], ',') + 3)
                        )
                        ->count();
                    $stc['referrer'] = $distribution
                        ->where(
                            $where . ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) = '
                            . (substr_count($distribution_info['branch_strand'], ',') + 1)
                        )
                        ->count();
                    $stc['relation'] = $distribution
                        ->where(
                            $where . ' and ((length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\')) = '
                            . (substr_count($distribution_info['branch_strand'], ',') + 2) .
                            ') or (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\')) = ' .
                            (substr_count($distribution_info['branch_strand'], ',') + 3 . '))')
                        
                        )
                        ->count();
                    $fans_level = 3;
                    if ($args['type'] == 1) {
                        // 直属
                        $fans_level = 1;
                    } else {
                        if ($args['type'] == 2) {
                            $allRec = false;
                            // 推荐上2级和上3级
                            $where .= ' and ((length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) = '
                                . (substr_count($distribution_info['branch_strand'], ',') + 2) . " or 
                        (length(`branch_strand`) - length(replace(`branch_strand`,',',''))) = " .
                                (substr_count($distribution_info['branch_strand'], ',') + 3) . ")";
                        }
                    }
                    break;
                default:
                    // 1级分销,全部和直属相同,无推荐
                    $fans_level = ($args['type'] == 2) ? 0 : 1;
                    $stc['all'] = $distribution
                        ->where(
                            $where . ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) <= '
                            . (substr_count($distribution_info['branch_strand'], ',') + 1)
                        )
                        ->count();
                    $stc['referrer'] = $distribution
                        ->where(
                            $where . ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) = '
                            . (substr_count($distribution_info['branch_strand'], ',') + 1)
                        )
                        ->count();
                    $stc['relation'] = 0;
                    break;
            }
            // 按推荐日期范围查询
            if (array_key_exists('date', $args) && $args['date']) {
                list($begin, $end) = explode(' - ', $args['date']);
                if ($begin == $end) {
                    $end = date('Y-m-d', strtotime($end . '+1 days'));
                }
                $where .= " and d.create_time between \"" . $begin . " 00:00:00\" and \"" . $end . " 00:00:00\"";
            }
            if (array_key_exists('keyword', $args) && $args['keyword']) {
                $where .= " and m.nickname like '%" . $args['keyword'] . "%'";
            }
            if ($allRec) {
                $where .= ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) '
                    . ($args['type'] != 3 ? '= ' : '<= ')
                    . (substr_count($distribution_info['branch_strand'], ',') + $fans_level);
            }
            $data = $distribution
                ->alias('d')
                ->where($where)
                ->join('member m', 'm.member_id = d.member_id')
                ->field(
                    'd.distribution_id,d.member_id,date_format(d.create_time,"%Y-%m-%d %H:%i") as recommend_time,
                d.total_brokerage,ifnull((select count(order_goods_id) from `ishop_distribution_book` where 
                distributor_a = d.distribution_id or distributor_b = d.distribution_id or distributor_c = d.distribution_id),0)
                 as order_num,m.nickname,m.avatar,m.phone,d.phone as dist_phone'
                )
                ->order($order)
                ->paginate($distribution->pageLimits, false, ['query' => $args]);
        } else {
            // 该会员非分销商
            $data = $stc = null;
        }
        return $this->fetch(
            'distribution_manage/distribution_fans',
            [
                'data' => $data,
                'stc' => $stc,
            ]
        );
    }
    
    /**
     * 取消分销商资格
     * @param Request $request
     * @return array
     */
    public function cancelDist(Request $request)
    {
        try {
            $args = $request::post();
            Db::startTrans();
            (new \app\common\service\Distribution())->distributionRevoke(
                [
                    'distribution_id' => [$args['id']],
                    'type' => 3,
                ]
            );
            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0]];
        } catch (\Exception $e) {
            dump($e->getMessage());
            Db::rollback();
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }
}