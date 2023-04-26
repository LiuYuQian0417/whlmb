<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\Distribution;
use think\Controller;
use think\facade\Env;
use think\facade\Request;
use app\common\model\DistributionBook as DistributionBoolModel;

class DistributionBook extends Controller
{
    /**
     * 商家结算
     * @param Request $request
     * @param DistributionBoolModel $distributionBook
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function store(Request $request, DistributionBoolModel $distributionBook)
    {
        $param = $request::get();
        $where = [];
        array_push($where, ['db.status', '<', 2]);
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['db.settlement_time', 'between time', [$begin, $end]]);
        }
        if (array_key_exists('shop', $param) && $param['shop'] !== '') {
            array_push($where, ['s.shop', '=', $param['shop']]);
        }
        if (array_key_exists('keyword', $param) && $param['keyword']) {
            array_push($where, ['s.store_name', 'like', "%" . trim($param['keyword']) . "%"]);
        }
        $database = $distributionBook
            ->alias('db')
            ->join('store s', 's.store_id = db.store_id')
            ->join('check_order co', 'co.store_id = db.store_id', 'left')
            ->where($where)
            // 总佣金(不含已取消的),已结算,待结算
            ->field('ifnull((sum(case when db.status < 2 then (distributor_a_brokerage + distributor_b_brokerage + 
            distributor_c_brokerage) else 0 end)),0) as total_bro,
            ifnull(sum(case when db.status = 0 then (distributor_a_brokerage + distributor_b_brokerage + 
            distributor_c_brokerage) else 0 end),0) as wait_pay');
        // 分销商提现审核中[不含暂停结账]
        $count = $database
            ->field('ifnull((select sum(sum_brokerage_price) from `ishop_check_order` where store_id = db.store_id and 
            sum_brokerage_price > 0 and check_status = 2),0) as close_pro,ifnull((select sum(price) 
            from `ishop_distribution_withdraw` where status = 0 and delete_time is null),0) as withdraw')
            ->find();
        $data = $database
            ->where($where)
            ->group('db.store_id')
            ->field('s.store_name,db.store_id,count(distinct(order_attach_id)) as order_num,
            ifnull((select sum(sum_brokerage_price) from `ishop_check_order`
             where store_id = db.store_id and check_status = 2),0) as close_pro')
            ->order(['total_bro' => 'desc', 'wait_pay' => 'desc', 'order_num' => 'desc'])
            ->paginate($distributionBook->pageLimits, false, ['query' => $param]);
        return $this->fetch('', [
            'data' => $data,
            'count' => $count,
            'single_store' => config('user.one_more'),
        ]);
    }

    /**
     * 商家结算对账单
     * @param Request $request
     * @param Distribution $distribution
     * @param DistributionBoolModel $distributionBook
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function statement(Request $request, Distribution $distribution, DistributionBoolModel $distributionBook)
    {
        $param = $request::get();
        $where = $whereFind = [['og.status','not in','4.2,4.3']];
        array_push($where, ['db.store_id', '=', $param['store_id']]);
        array_push($whereFind, ['db.store_id', '=', $param['store_id']]);
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['og.create_time', 'between', [$begin, $end]]);
            array_push($whereFind, ['og.create_time', 'between', [$begin, $end]]);
        }
        // 根据订单编号模糊查询
        if (array_key_exists('keyword', $param) && $param['keyword']) {
            array_push($where, ['oa.order_number', 'like', "%" . trim($param['keyword']) . "%"]);
            array_push($whereFind, ['oa.order_number', 'like', "%" . trim($param['keyword']) . "%"]);
        }
        if (array_key_exists('type', $param) && $param['type'] < 3) {
            array_push($where, ['db.status', '=', ($param['type'] == 2) ? 0 : 1]);
        }
        $find = $distributionBook
            ->alias('db')
            ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
            ->join('order_attach oa', 'oa.order_attach_id = db.order_attach_id')
            ->where($whereFind)
            ->field('sum(case when db.status = 1 then (distributor_a_brokerage + 
            distributor_b_brokerage + distributor_c_brokerage) else 0 end) as close_pro,
            sum(case when db.status = 0 then (distributor_a_brokerage + 
            distributor_b_brokerage + distributor_c_brokerage) else 0 end) as wait_pro,
            count(distinct(db.order_goods_id)) as order_num')
            ->find();
        $data = $distributionBook
            ->alias('db')
            ->where($where)
            ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
            ->join('order_attach oa', 'oa.order_attach_id = db.order_attach_id')
            ->join('member m', 'm.member_id = og.member_id')
            ->with(['memberADistribution', 'memberBDistribution', 'memberCDistribution'])
            ->field('db.distribution_book_id,db.rule_type_a,db.rule_type_b,db.rule_type_c,db.rule_snapshot_content_a,
            db.distributor_a,db.distributor_a_brokerage,db.distributor_b,db.distributor_b_brokerage,db.rule_snapshot_content_b,
            db.distributor_c,db.distributor_c_brokerage,db.rule_snapshot_content_c,db.status,og.file,og.goods_name,oa.order_number,
            og.create_time,og.single_price,og.quantity,m.avatar,m.nickname')
            ->paginate($distribution->pageLimits, false, ['query' => $param]);
        return $this->fetch('', [
            'find' => $find,
            'data' => $data,
        ]);
    }

    /**
     * 分销商对账
     * @param Request $request
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distribution(Request $request, Distribution $distribution)
    {
        $param = $request::get();
        $where = [
            ['d.audit_status', '=', 1],
        ];
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distribution_hierarchy = Env::get('DISTRIBUTION_HIERARCHY');
        if (array_key_exists('date', $param) && $param['date'])
        {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['d.create_time', 'between', [$begin, $end]]);
        }
        if (array_key_exists('keyword', $param) && $param['keyword'])
        {
            array_push($where, ['m.nickname|m.phone', 'like', "%" . trim($param['keyword']) . "%"]);
        }
        $data = $distribution
            ->alias('d')
            ->join('member m', 'm.member_id = d.member_id')
            ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
            ->where($where)
            ->field(
                [
                    'd.distribution_id',
                    'm.member_id',
                    'm.nickname',
                    'm.avatar',
                    'm.phone',
                    'd.distribution_level_id',
                    'd.referrer_num',
                    'd.relation_num',
                    'dl.level_title',
                    'd.total_close_brokerage',
                ]
            )
            ->order(['d.create_time' => 'desc', 'distribution_id' => 'desc'])
            ->paginate($distribution->pageLimits, FALSE, ['query' => $param])->each(
                function ($item)
                {
                    $item->close_brokerage = 0;
                    $item->total_brokerage = 0;
                    DistributionBoolModel::where("(distributor_a = {$item['distribution_id']} OR distributor_b = {$item['distribution_id']} OR distributor_c = {$item['distribution_id']})")
                        ->field(
                            [
                                'distributor_a',
                                'distributor_a_brokerage',
                                'distributor_b',
                                'distributor_b_brokerage',
                                'distributor_c',
                                'distributor_c_brokerage',
                                'status',
                            ]
                        )->select()->each(
                            function ($item1) use (&$item)
                            {
                                switch ((string)$item->distribution_id)
                                {
                                    case (string)$item1['distributor_a']:
                                        $item->total_brokerage += $item1->distributor_a_brokerage;
                                        if ($item1['status'] == 1)
                                        {
                                            $item->close_brokerage += $item1->distributor_a_brokerage;
                                        }
                                        break;
                                    case (string)$item1['distributor_b']:
                                        $item->total_brokerage += $item1->distributor_b_brokerage;
                                        if ($item1['status'] == 1)
                                        {
                                            $item->close_brokerage += $item1->distributor_b_brokerage;
                                        }
                                        break;
                                    case (string)$item1['distributor_c']:
                                        $item->total_brokerage += $item1->distributor_c_brokerage;
                                        if ($item1['status'] == 1)
                                        {
                                            $item->close_brokerage += $item1->distributor_c_brokerage;
                                        }
                                        break;
                                }
                            }
                        );
                }
            );


        $only['close'] = DistributionBoolModel::where('status = 1')->field('SUM(distributor_a_brokerage+distributor_b_brokerage+distributor_c_brokerage) as close')->find()['close'];
        $only['total'] = DistributionBoolModel::where('status <> 2')->field('SUM(distributor_a_brokerage+distributor_b_brokerage+distributor_c_brokerage) as total')->find()['total'];
//
//        $only = $distribution
//            ->field('sum(total_brokerage) as total,sum(close_brokerage) as close')
//            ->find();
        return $this->fetch(
            '', [
                  'data'                   => $data,
                  'only'                   => $only,
                  'distribution_hierarchy' => $distribution_hierarchy,
                  'single_store'           => config('user.one_more'),
              ]
        );
    }

    /**
     * 分销商对账详情
     * @param Request $request
     * @param Distribution $distribution
     * @param DistributionBoolModel $distributionBook
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distributionDetails(Request $request,
                                        Distribution $distribution,
                                        DistributionBoolModel $distributionBook)
    {
        $param = $request::get();
        $fans = $order = $dbWhere = $orderWhere = $countWhere = [];
        array_push($dbWhere, ['distribution_id', '=', $param['distribution_id']]);
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distribution_hierarchy = Env::get('DISTRIBUTION_HIERARCHY');
        // type 1已结算 2待结算 3直属粉丝 4推荐粉丝
        if (array_key_exists('type', $param) && $param['type'] <= 4 && $param['type'] > 0) {
            if ($param['type'] > 0 && $param['type'] < 3) {
                array_push($orderWhere, ['distributor_a|distributor_b|distributor_c', '=', $param['distribution_id']]);
                // 下单日期
                if (array_key_exists('date', $param) && $param['date']) {
                    list($begin, $end) = explode(' - ', $param['date']);
                    $end = $end . ' 23:59:59';
                    array_push($orderWhere, ['og.create_time', 'between', [$begin, $end]]);
                    array_push($countWhere, ['og.create_time', 'between', [$begin, $end]]);
                }
                // 订单编号
                if (array_key_exists('keyword', $param) && $param['keyword']) {
                    array_push($orderWhere, ['oa.order_number', 'like', "%" . trim($param['keyword']) . "%"]);
                    array_push($countWhere, ['oa.order_number', 'like', "%" . trim($param['keyword']) . "%"]);
                }
                array_push($orderWhere, ['db.status', '=', ($param['type'] == 1 ? 1 : 0)]);
                $order = $distributionBook
                    ->alias('db')
                    ->where($orderWhere)
                    ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
                    ->join('order_attach oa', 'oa.order_attach_id = db.order_attach_id')
                    ->join('member m', 'm.member_id = og.member_id')
                    ->with(['memberADistribution', 'memberBDistribution', 'memberCDistribution'])
                    ->field('db.distribution_book_id,db.rule_type_a,db.rule_type_b,db.rule_type_c,db.rule_snapshot_content_a,
                    db.rule_snapshot_content_b,db.rule_snapshot_content_c,db.distributor_a,db.distributor_a_brokerage,
                    db.distributor_b,db.distributor_b_brokerage,db.distributor_c,db.distributor_c_brokerage,db.status,
                    og.file,og.goods_name,oa.order_number,og.create_time,og.single_price,og.quantity,m.avatar,m.nickname,
                    db.level_a_snapshot,db.level_b_snapshot,db.level_c_snapshot')
                    ->paginate($distribution->pageLimits, false, ['query' => $param]);
            } else {
                // 查找此分销商链的分销商
                $distribution_info = $distribution
                    ->where($dbWhere)
                    ->field('top_id,branch_strand,distribution_id')
                    ->find();
                // 默认查询当前会员所占层级以下的层级(即为当前会员的粉丝)
                $fansWhere = '`audit_status` = 1 and `top_id` = ' . $distribution_info['top_id']
                    . ' and locate(\'' . $distribution_info['branch_strand'] . ',\',`branch_strand`,1) = 1';
                $allRec = true;
                switch ($distribution_hierarchy) {
                    case 2:
                        // 2级分销,直属第1级,推荐只有第2级,无3级推荐
                        $fans_level = ($param['type'] == 3) ? 1 : 2;
                        break;
                    case 3:
                        // 3级分销,直属为下一层级,推荐为隔代2层级
                        $fans_level = 3;
                        if ($param['type'] == 3) {
                            // 直属
                            $fans_level = 1;
                        } elseif ($param['type'] == 4) {
                            $allRec = false;
                            // 推荐上2级和上3级
                            $fansWhere .= ' and ((length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) = '
                                . (substr_count($distribution_info['branch_strand'], ',') + 2) . " or 
                        (length(`branch_strand`) - length(replace(`branch_strand`,',',''))) = " .
                                (substr_count($distribution_info['branch_strand'], ',') + 3) . ")";
                        }
                        break;
                    default:
                        // 1级分销,全部和直属相同,无推荐
                        $fans_level = ($param['type'] == 3) ? 0 : 1;
                        break;
                }
                if ($allRec) {
                    $fansWhere .= ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) '
                        . (($param['type']) ? '= ' : '<= ')
                        . (substr_count($distribution_info['branch_strand'], ',') + $fans_level);
                }
                // 昵称
                if (array_key_exists('keyword', $param) && $param['keyword']) {
                    $fansWhere .= ' and m.nickname like "%' . trim($param['keyword']) . '%"';
                }
                $fans = $distribution
                    ->alias('d')
                    ->where($fansWhere)
                    ->join('member m', 'm.member_id = d.member_id')
                    ->field('d.distribution_id,m.nickname,m.avatar,d.create_time')
                    ->paginate($distribution->pageLimits, false, ['query' => $param]);
            }
        }
        // dump($fans);
        $distributionNum = $distribution
            ->where($dbWhere)
            ->field('referrer_num,relation_num')
            ->find()
            ->toArray();
        $count = $distributionBook
            ->alias('db')
            ->where($countWhere)
            ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
            ->join('order_attach oa', 'oa.order_attach_id = db.order_attach_id')
            ->field('sum(case when db.status = 1 and distributor_a = ' . $param['distribution_id'] .
                ' then distributor_a_brokerage when db.status = 1 and distributor_b = ' . $param['distribution_id'] .
                ' then distributor_b_brokerage when  db.status = 1 and distributor_c = ' . $param['distribution_id'] .
                ' then distributor_c_brokerage else 0 end) as close_pro,
            sum(case when db.status = 0 and distributor_a = ' . $param['distribution_id'] .
                ' then distributor_a_brokerage when db.status = 0 and distributor_b = ' . $param['distribution_id'] .
                ' then distributor_b_brokerage when  db.status = 0 and distributor_c = ' . $param['distribution_id'] .
                ' then distributor_c_brokerage else 0 end) as wait_pro')
            ->find()
            ->toArray();
        $count = array_merge($count, $distributionNum);
        return $this->fetch('', [
            'order' => $order,
            'fans' => $fans,
            'count' => $count,
            'distribution_hierarchy' => $distribution_hierarchy,
        ]);
    }

    /**
     * 订单对账
     * @param Request $request
     * @param Distribution $distribution
     * @param DistributionBoolModel $distributionBook
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order(Request $request, Distribution $distribution, DistributionBoolModel $distributionBook)
    {
        $param = $request::get();
        $where = $whereFind = [['og.status','not in','4.2,4.3']];
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['og.create_time', 'between', [$begin, $end]]);
            array_push($whereFind, ['og.create_time', 'between', [$begin, $end]]);
        }
        // 根据订单编号模糊查询
        if (array_key_exists('keyword', $param) && $param['keyword']) {
            array_push($where, ['oa.order_number', 'like', "%" . trim($param['keyword']) . "%"]);
            array_push($whereFind, ['oa.order_number', 'like', "%" . trim($param['keyword']) . "%"]);
        }
        if (array_key_exists('type', $param) && $param['type']) {
            if ($param['type'] < 3) {
                array_push($where, ['db.status', '=', ($param['type'] == 2) ? 0 : 1]);
            }
        }
        $find = $distributionBook
            ->alias('db')
            ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
            ->join('order_attach oa', 'oa.order_attach_id = db.order_attach_id')
            ->where($whereFind)
            ->field('sum(case when db.status = 1 then (distributor_a_brokerage + 
            distributor_b_brokerage + distributor_c_brokerage) else 0 end) as close_pro,
            sum(case when db.status = 0 then (distributor_a_brokerage + 
            distributor_b_brokerage + distributor_c_brokerage) else 0 end) as wait_pro,
            count(db.order_goods_id) as order_num')
            ->find();
        $data = $distributionBook
            ->alias('db')
            ->where($where)
            ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
            ->join('order_attach oa', 'oa.order_attach_id = db.order_attach_id')
            ->join('member m', 'm.member_id = og.member_id')
            ->with(['memberADistribution', 'memberBDistribution', 'memberCDistribution'])
            ->field('db.distribution_book_id,db.rule_type_a,db.rule_type_b,db.rule_type_c,db.rule_snapshot_content_a,
            db.rule_snapshot_content_b,db.rule_snapshot_content_c,db.distributor_a,db.distributor_a_brokerage,
            db.distributor_b,db.distributor_b_brokerage,db.distributor_c,db.distributor_c_brokerage,db.status,
            og.file,og.goods_name,oa.order_attach_number,og.create_time,og.single_price,og.quantity,m.avatar,m.nickname')
            ->paginate($distribution->pageLimits, false, ['query' => $param]);
        return $this->fetch('', [
            'find' => $find,
            'data' => $data,
            'single_store' => config('user.one_more'),
        ]);
    }
}