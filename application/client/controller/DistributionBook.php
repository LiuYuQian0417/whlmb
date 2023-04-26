<?php
declare(strict_types = 1);
namespace app\client\controller;

use app\common\model\Distribution;
use think\Controller;
use think\facade\Env;
use think\facade\Request;
use app\common\model\DistributionBook as DistributionBookModel;
use think\facade\Session;

class DistributionBook extends Controller
{
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
            ['d.audit_status', '=', 1]
        ];
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distribution_hierarchy = Env::get('DISTRIBUTION_HIERARCHY');
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['d.create_time', 'between', [$begin, $end]]);
        }
        if (array_key_exists('keywords', $param) && $param['keywords']) {
            array_push($where, ['m.nickname|m.phone', 'like', "%" . trim($param['keywords']) . "%"]);
        }
        $allow = (new DistributionBookModel())
            ->where([
                ['store_id', '=', Session::get('client_store_id')],
                ['status', '<>', 2],
            ])
            ->field('distributor_a,distributor_b,distributor_c')
            ->select()
            ->toArray();
        $confirmAllow = [];
        if (!empty($allow)) {
            foreach ($allow as $_allow) {
                $confirmAllow[] = $_allow['distributor_a'];
                $confirmAllow[] = $_allow['distributor_b'];
                $confirmAllow[] = $_allow['distributor_c'];
            }
            $confirmAllow = array_filter(array_unique($confirmAllow));
        }
        $data = $distribution
            ->alias('d')
            ->where([['d.distribution_id', 'in', implode(',', $confirmAllow)]])
            ->join('member m', 'm.member_id = d.member_id')
            ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
            ->where($where)
            ->field('d.distribution_id,m.nickname,m.avatar,m.phone,d.distribution_level_id,d.total_brokerage,d.total_close_brokerage,
            d.referrer_num,d.relation_num,dl.level_title')
            ->order(['d.create_time' => 'desc', 'distribution_id' => 'desc'])
            ->paginate($distribution->pageLimits, false, ['query' => $param]);
        $only = $distribution
            ->where([['distribution_id', 'in', implode(',', $confirmAllow)]])
            ->field('sum(total_brokerage) as total,sum(total_close_brokerage) as close')
            ->find();
        return $this->fetch('', [
            'data' => $data,
            'only' => $only,
            'distribution_hierarchy' => $distribution_hierarchy,
        ]);
    }

    /**
     * 分销商对账详情
     * @param Request $request
     * @param Distribution $distribution
     * @param DistributionBookModel $distributionBook
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distributionDetails(Request $request,
                                        Distribution $distribution,
                                        DistributionBookModel $distributionBook)
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
                switch ($distribution_hierarchy) {
                    case 2:
                        // 2级分销,全部和直属相同,无推荐
                        $fans_level = 1;
                        break;
                    case 3:
                        // 3级分销,直属为下一层级,推荐为隔代层级
                        $fans_level = ($param['type'] == 3) ? 1 : 2;
                        break;
                    default:
                        // 1级分销,无全部,无直属,无推荐粉丝
                        $fans_level = 0;
                        break;
                }
                $fansWhere .= ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) = '
                    . (substr_count($distribution_info['branch_strand'], ',') + $fans_level);
                // 昵称
                if (array_key_exists('keyword', $param) && $param['keyword']) {
                    $param['keyword'] = htmlspecialchars($param['keyword']);
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
     * @param DistributionBookModel $distributionBook
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order(Request $request, Distribution $distribution, DistributionBookModel $distributionBook)
    {
        $param = $request::get();
        $where = $whereFind = [['og.status','not in','4.2,4.3']];
        array_push($where, ['db.store_id', '=', Session::get('client_store_id')]);
        array_push($whereFind, ['db.store_id', '=', Session::get('client_store_id')]);
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            array_push($where, ['og.create_time', 'between', [$begin, $end]]);
            array_push($whereFind, ['og.create_time', 'between', [$begin, $end]]);
        }
        // 根据订单编号模糊查询
        if (array_key_exists('keywords', $param) && $param['keywords']) {
            array_push($where, ['oa.order_number', 'like', "%" . trim($param['keywords']) . "%"]);
            array_push($whereFind, ['oa.order_number', 'like', "%" . trim($param['keywords']) . "%"]);
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
        ]);
    }
}