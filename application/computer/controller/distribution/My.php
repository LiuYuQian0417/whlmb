<?php
declare(strict_types = 1);

namespace app\computer\controller\distribution;

use app\computer\model\Distribution;
use app\computer\model\DistributionBook;
use app\computer\model\DistributionLevel;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;
use think\facade\View;

/**
 * 分销_我的
 * Class My
 * @package app\computer\controller\distribution
 */
class My extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => ''],
    ];

    /**
     * 分销粉丝
     * @param Request $request
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function fans(Request $request, Distribution $distribution)
    {
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'];
        $param['type'] = $param['type'] ?? 0;
        //获取分销商id 判断当前会员是否是分销商并相应处理
        $distribution->get_distribution_id();
        // 查询当前会员的顶级上线
        $distribution_info = $distribution
            ->where([['member_id', '=', $param['member_id']]])
            ->field('top_id,branch_strand,distribution_id')
            ->find();

        // 订单数量order_num[含已结算和未结算] 总收益total_brokerage[含已结算和未结算] 推荐日期date
        $order = [];
        if (array_key_exists('order', $param) && $param['order']) {
            $orderText = [1 => 'order_num', 2 => 'd.total_brokerage', 3 => 'd.create_time'];
            $sort = (array_key_exists('sort', $param) && $param['sort'] == 1) ? 'asc' : 'desc';
            $order[$orderText[$param['order']]] = $sort;
        }
        $order['distribution_id'] = 'desc';
        // 默认查询当前会员所占层级以下的层级(即为当前会员的粉丝)
        $where = 'distribution_id <> ' . $distribution_info['distribution_id'] . ' and `audit_status` = 1 and `top_id` = '
            . $distribution_info['top_id'] . ' and locate(\'' . $distribution_info['branch_strand'] . ',\',`branch_strand`,1) = 1';
        $distribution_hierarchy = self::$functionStatus['distribution_hierarchy'];
        $allRec = true;
        switch ($distribution_hierarchy) {
            case 2:
                // 2级分销,直属第1级,推荐只有第2级,无3级推荐
                $fans_level = ($param['type'] == 1) ? 1 : 2;
                break;
            case 3:
                // 3级分销,直属为下一层级,推荐为隔代2层级
                $fans_level = 3;
                if ($param['type'] == 1) {
                    // 直属
                    $fans_level = 1;
                } elseif ($param['type'] == 2) {
                    $allRec = false;
                    // 推荐上2级和上3级
                    $where .= ' and ((length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) = '
                        . (substr_count($distribution_info['branch_strand'], ',') + 2) . " or 
                        (length(`branch_strand`) - length(replace(`branch_strand`,',',''))) = " .
                        (substr_count($distribution_info['branch_strand'], ',') + 3) . ")";
                }
                break;
            default:
                // 1级分销,全部和直属相同,无推荐
                $fans_level = ($param['type'] == 2) ? 0 : 1;
                break;
        }
        if ($allRec) {
            $where .= ' and (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) '
                . (($param['type']) ? '= ' : '<= ')
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
             as order_num,m.nickname,m.avatar'
            )
            ->order($order)
            ->paginate($distribution->pageLimits);
        return $this->fetch('', ['code' => 0, 'message' => config('message.')[0][0], 'data' => $data]);

    }

    /**
     * 粉丝收益详情
     * @param Request $request
     * @param DistributionBook $distributionBook
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function fans_earnings_details(Request $request, DistributionBook $distributionBook, Distribution $distribution)
    {

        $param = $request::instance()->param();

        $param['distribution_id'] = $param['distribution_id'] ?? $distribution->get_distribution_id();

        $where = [
            ['db.distributor_a|db.distributor_b|db.distributor_c', '=', $param['distribution_id']],
        ];
        $whereStr = '`delete_time` is null';
        if (isset($param['limit'])) {
            $distributionBook->pageLimits = $param['limit'];
        }
        if (array_key_exists('date', $param) && $param['date']) {
            if ($param['date'] == 'today') {
                // 查询今日
                $begin = date('Y-m-d');
                $end = date('Y-m-d', strtotime($begin . '+1 days'));
            } else {
                $begin = $param['date'] . '-01';
                $end = date('Y-m-d', strtotime($begin . '+1 month'));
            }
            array_push($where, ['db.create_time', 'between time', [$begin, $end]]);
            $whereStr .= ' and `create_time` between \'' . $begin . ' 00:00:00\' and \'' . $end . ' 00:00:00\'';
        }
        if (array_key_exists('type', $param) && $param['type']) {
            array_push($where, ['db.status', '=', ($param['type'] - 1)]);
            $whereStr .= ' and status = ' . ($param['type'] - 1);
        } else {
            // 排除已关闭订单
            array_push($where, ['db.status', '<', 2]);
            $whereStr .= ' and status < 2';
        }

        $order = ['db.create_time' => 'desc', 'db.distribution_book_id' => 'desc'];
        $data = $distributionBook
            ->alias('db')
            ->where($where)
            ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
            ->join('member m', 'm.member_id = og.member_id')
            ->with(['memberADistribution', 'memberBDistribution', 'memberCDistribution'])
            ->field(
                'db.distribution_book_id,(case when ' . $param['distribution_id'] . ' = db.distributor_a 
            then db.distributor_a_brokerage when ' . $param['distribution_id'] . ' = db.distributor_b 
            then db.distributor_b_brokerage when ' . $param['distribution_id'] . ' = db.distributor_c 
            then db.distributor_c_brokerage else 0 end) as brokerage,db.status,og.file,og.goods_name,
            convert(og.single_price*og.quantity*og.discount,decimal(10,2)) as price,m.avatar,m.nickname,
            date_format(og.create_time,\'%Y-%m-%d %H:%i\') as underOrder_time,
            date_format(og.create_time,\'%Y-%m\') as underOrder_date'
            )
            ->order($order)
            ->paginate(10, false, ['query' => $param]);
        if (!empty($data->toArray()['data'])) {
            $dateList = array_unique(array_column($data->toArray(), 'underOrder_date'));
            if (!empty($dateList)) {
                // 查询月份对应的小计金额
                $dateSumQuery = Db::query(
                    'select sum(case when ' . $param['distribution_id'] . ' = distributor_a
                            then distributor_a_brokerage when ' . $param['distribution_id'] . ' = distributor_b
                            then distributor_b_brokerage when ' . $param['distribution_id'] . ' = distributor_c
                            then distributor_c_brokerage else 0 end) as brokerage,date_format(create_time,\'%Y-%m\')
                            as date from ishop_distribution_book where ' . $whereStr . ' group by date'
                );
                $data['data'] = array_values(
                    array_reduce(
                        $data['data'],
                        function ($value, $key) {
                            $name = $key['underOrder_date'];
                            unset($key['underOrder_date']);
                            $value[$name]['date'] = $name;
                            if ($key['file']) {
                                $key['file'] = fileOss($key['file']);
                            }
                            if ($key['avatar']) {
                                $key['avatar'] = fileOss($key['avatar']);
                            }
                            $value[$name]['list'][] = $key;
                            return $value;
                        }
                    )
                );
                foreach ($data['data'] as &$_d) {
                    foreach ($dateSumQuery as $_dsq) {
                        if ($_d['date'] == $_dsq['date']) {
                            $_d['subtotal_price'] = $_dsq['brokerage'];
                        }
                    }
                }
            }
            $total_price = $distributionBook
                ->alias('db')
                ->where($where)
                ->field(
                    'sum(case when ' . $param['distribution_id'] . ' = db.distributor_a
            then db.distributor_a_brokerage when ' . $param['distribution_id'] . ' = db.distributor_b
            then db.distributor_b_brokerage when ' . $param['distribution_id'] . ' = db.distributor_c
            then db.distributor_c_brokerage else 0 end) as sum'
                )
                ->find();
            $total_price = $total_price['sum'];
        }
        return $this->fetch('', ['code' => 0, 'data' => $data, 'total_price' => $total_price ?? 0.00]);

    }

    /**
     * 收益主页
     * @param RSACrypt $crypt
     * @param DistributionBook $distributionBook
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function earnings_view(RSACrypt $crypt,
                                  DistributionBook $distributionBook,
                                  Distribution $distribution)
    {
        //获取分销商id
        $distribution_id = $distribution->get_distribution_id();
        $disInfo = $distribution
            ->alias('d')
            ->where([['d.distribution_id', '=', $distribution_id]])
            ->join('member m', 'm.member_id = d.member_id')
            ->field('d.total_brokerage,d.total_close_brokerage,d.close_brokerage as close_pro,m.usable_money')
            ->find();
        if (is_null($disInfo)) {
            return $crypt->response(['code' => 1, 'message' => '此分销商不存在'], true);
        }
        $dbInfo = $distributionBook
            ->alias('db')
            ->field(
                'sum(case when ' . $distribution_id . ' = db.distributor_a  and db.status = 0
                then db.distributor_a_brokerage when ' . $distribution_id . ' = db.distributor_b and db.status = 0
                then db.distributor_b_brokerage when ' . $distribution_id . ' = db.distributor_c and db.status = 0
                then db.distributor_c_brokerage else 0 end) as wait_pro,
                sum(case when ' . $distribution_id . ' = db.distributor_a and db.status = 1
                and date_format(db.create_time,\'%Y-%m-%d\') = \'' . date('Y-m-d') . '\'
                then db.distributor_a_brokerage when ' . $distribution_id . ' = db.distributor_b and db.status = 1
                and date_format(db.create_time,\'%Y-%m-%d\') = \'' . date('Y-m-d') . '\'
                then db.distributor_b_brokerage when ' . $distribution_id . ' = db.distributor_c and db.status = 1
                and date_format(db.create_time,\'%Y-%m-%d\') = \'' . date('Y-m-d') . '\'
                then db.distributor_c_brokerage else 0 end) as today_close_pro,
                sum(case when ' . $distribution_id . ' = db.distributor_a  and db.status = 0
                and date_format(db.create_time,\'%Y-%m-%d\') = \'' . date('Y-m-d') . '\'
                then db.distributor_a_brokerage when ' . $distribution_id . ' = db.distributor_b and db.status = 0
                and date_format(db.create_time,\'%Y-%m-%d\') = \'' . date('Y-m-d') . '\'
                then db.distributor_b_brokerage when ' . $distribution_id . ' = db.distributor_c and db.status = 0
                and date_format(db.create_time,\'%Y-%m-%d\') = \'' . date('Y-m-d') . '\'
                then db.distributor_c_brokerage else 0 end) as today_wait_pro'
            )
            ->find()
            ->toArray();
        $data = array_merge($disInfo->toArray(), $dbInfo);

        $where = [
            ['db.distributor_a|db.distributor_b|db.distributor_c', '=', $distribution_id],
        ];

        array_push($where, ['db.status', '=', 1]);

        $result = $distributionBook
            ->alias('db')
            ->where($where)
            ->whereTime('db.update_time', 'today')
            ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
            ->join('member m', 'm.member_id = og.member_id')
            ->with(['memberADistribution', 'memberBDistribution', 'memberCDistribution'])
            ->field(
                'db.distribution_book_id,(case when ' . $distribution_id . ' = db.distributor_a
            then db.distributor_a_brokerage when ' . $distribution_id . ' = db.distributor_b
            then db.distributor_b_brokerage when ' . $distribution_id . ' = db.distributor_c
            then db.distributor_c_brokerage else 0 end) as brokerage,db.status,og.file,og.goods_name,og.goods_id,
            date_format(og.create_time,\'%Y-%m-%d %H:%i\') as underOrder_time,m.avatar,m.nickname,
            date_format(og.create_time,\'%Y-%m\') as underOrder_date,
            convert(og.single_price*og.quantity*og.discount,decimal(10,2)) as price'
            )->limit(2)
            ->select();
        return $this->fetch('', ['data' => $data ?? '', 'result' => $result]);
    }


    public function ajax_earnings(RSACrypt $crypt, DistributionBook $distributionBook, Distribution $distribution)
    {
        try {
            $param = $crypt->request();
            $param = array_filter($param);
            $param['member_id'] = request(0)->mid;
            $distribution_id = $distribution->get_distribution_id();
            $where = [
                ['db.distributor_a|db.distributor_b|db.distributor_c', '=', $distribution_id],
            ];
            if (array_key_exists('type', $param) && $param['type']) {
                array_push($where, ['db.status', '=', ($param['type'] - 1)]);
            }

            $result = $distributionBook
                ->alias('db')
                ->where($where)
                ->whereTime('db.update_time', 'today')
                ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
                ->join('member m', 'm.member_id = og.member_id')
                ->with(['memberADistribution', 'memberBDistribution', 'memberCDistribution'])
                ->field(
                    'db.distribution_book_id,(case when ' . $distribution_id . ' = db.distributor_a
            then db.distributor_a_brokerage when ' . $distribution_id . ' = db.distributor_b
            then db.distributor_b_brokerage when ' . $distribution_id . ' = db.distributor_c
            then db.distributor_c_brokerage else 0 end) as brokerage,db.status,og.file,og.goods_name,
            date_format(og.create_time,\'%Y-%m-%d %H:%i\') as underOrder_time,m.avatar,m.nickname,
            date_format(og.create_time,\'%Y-%m\') as underOrder_date,og.goods_id,
            convert(og.single_price*og.quantity*og.discount,decimal(10,2)) as price'
                )->limit(2)->select();


            return $crypt->response(['code' => 0, 'data' => $result, 'message' => config('message.')[0][0]], true);
        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }
    }


    /**
     * 分销商收益详情
     * @param Request $request
     * @param Distribution $distribution
     * @param DistributionBook $distributionBook
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function earnings_details(Request $request, Distribution $distribution, DistributionBook $distributionBook)
    {
        $param = $request::instance()->param();

        $param['distribution_id'] = $distribution->get_distribution_id();

        $where = [
            ['db.distributor_a|db.distributor_b|db.distributor_c', '=', $param['distribution_id']],
        ];
        $whereStr = '`delete_time` is null';
        if (isset($param['limit'])) {
            $distributionBook->pageLimits = $param['limit'];
        }
        if (array_key_exists('date', $param) && $param['date']) {
            if ($param['date'] == 'today') {
        // 查询今日
        $begin = date('Y-m-d');
        $end = date('Y-m-d', strtotime($begin . '+1 days'));
        } else {
            $begin = $param['date'] . '-01';
            $end = date('Y-m-d', strtotime($begin . '+1 month'));
        }
        array_push($where, ['db.create_time', 'between time', [$begin, $end]]);
        $whereStr .= ' and `create_time` between \'' . $begin . ' 00:00:00\' and \'' . $end . ' 00:00:00\'';
        }
        if (array_key_exists('type', $param) && $param['type']) {
            array_push($where, ['db.status', '=', ($param['type'] - 1)]);
            $whereStr .= ' and status = ' . ($param['type'] - 1);
        } else {
            // 排除已关闭订单
            array_push($where, ['db.status', '<', 2]);
            $whereStr .= ' and status < 2';
        }
        $order = ['db.create_time' => 'desc', 'db.distribution_book_id' => 'desc'];
        $data = $distributionBook
            ->alias('db')
            ->where($where)
            ->join('order_goods og', 'og.order_goods_id = db.order_goods_id')
            ->join('member m', 'm.member_id = og.member_id')
            ->with(['memberADistribution', 'memberBDistribution', 'memberCDistribution'])
            ->field(
                'db.distribution_book_id,(case when ' . $param['distribution_id'] . ' = db.distributor_a 
            then db.distributor_a_brokerage when ' . $param['distribution_id'] . ' = db.distributor_b 
            then db.distributor_b_brokerage when ' . $param['distribution_id'] . ' = db.distributor_c 
            then db.distributor_c_brokerage else 0 end) as brokerage,db.status,og.file,og.goods_name,
            convert(og.single_price*og.quantity*og.discount,decimal(10,2)) as price,m.avatar,m.nickname,
            date_format(og.create_time,\'%Y-%m-%d %H:%i\') as underOrder_time,
            date_format(og.create_time,\'%Y-%m\') as underOrder_date'
            )
            ->order($order)
            ->paginate(10, false, ['query' => $param]);
        if (!empty($data->toArray()['data'])) {
            $dateList = array_unique(array_column($data->toArray(), 'underOrder_date'));
            if (!empty($dateList)) {
                // 查询月份对应的小计金额
                $dateSumQuery = Db::query(
                    'select sum(case when ' . $param['distribution_id'] . ' = distributor_a
                            then distributor_a_brokerage when ' . $param['distribution_id'] . ' = distributor_b
                            then distributor_b_brokerage when ' . $param['distribution_id'] . ' = distributor_c
                            then distributor_c_brokerage else 0 end) as brokerage,date_format(create_time,\'%Y-%m\')
                            as date from ishop_distribution_book where ' . $whereStr . ' group by date'
                );
                $data['data'] = array_values(
                    array_reduce(
                        $data['data'],
                        function ($value, $key) {
                            $name = $key['underOrder_date'];
                            unset($key['underOrder_date']);
                            $value[$name]['date'] = $name;
                            if ($key['file']) {
                                $key['file'] = fileOss($key['file']);
                            }
                            if ($key['avatar']) {
                                $key['avatar'] = fileOss($key['avatar']);
                            }
                            $value[$name]['list'][] = $key;
                            return $value;
                        }
                    )
                );
                foreach ($data['data'] as &$_d) {
                    foreach ($dateSumQuery as $_dsq) {
                        if ($_d['date'] == $_dsq['date']) {
                            $_d['subtotal_price'] = $_dsq['brokerage'];
                        }
                    }
                }
            }
            $total_price = $distributionBook
                ->alias('db')
                ->where($where)
                ->field(
                    'sum(case when ' . $param['distribution_id'] . ' = db.distributor_a
            then db.distributor_a_brokerage when ' . $param['distribution_id'] . ' = db.distributor_b
            then db.distributor_b_brokerage when ' . $param['distribution_id'] . ' = db.distributor_c
            then db.distributor_c_brokerage else 0 end) as sum'
                )
                ->find();
            $total_price = $total_price['sum'];
        }
        return $this->fetch('', ['code' => 0, 'data' => $data, 'total_price' => $total_price ?? 0.00]);
    }

    /**
     * 获取最近七天收益数据
     * @param DistributionBook $distributionBook
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function seven_data(DistributionBook $distributionBook)
    {
        //获取分销商id
        $distribution_id = (new Distribution)->get_distribution_id();
        $data['seven_data'] = $distributionBook
            ->alias('db')
            ->where(
                [
                    [
                        'db.create_time',
                        'between time',
                        [
                            date('Y-m-d', strtotime(date('Y-m-d') . '-6 days')),
                            date('Y-m-d', strtotime(date('Y-m-d') . '+1 days')),
                        ],
                    ],
                ]
            )
            ->field(
                'sum(case when ' . $distribution_id . ' = db.distributor_a and status < 2
                then db.distributor_a_brokerage when ' . $distribution_id . ' = db.distributor_b and status < 2
                then db.distributor_b_brokerage when ' . $distribution_id . ' = db.distributor_c and status < 2
                then db.distributor_c_brokerage else 0 end) as brokerage,date_format(db.create_time,\'%Y-%m-%d\') as day_time'
            )
            ->group('day_time')
            ->order(['day_time' => 'desc'])
            ->select()
            ->toArray();
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $week[] = date('Y-m-d', strtotime(date('Y-m-d') . '-' . $i . ' days'));
            //            $week[] = substr($val, 5);
        }
        $diff = array_diff($week, $day_time = array_column($data['seven_data'], 'day_time'));
        foreach ($diff as $item) {
            $data['seven_data'][] = ['brokerage' => 0, 'day_time' => $item,];
        }
        // 对7天统计排序
        usort(
            $data['seven_data'],
            function ($x, $y) {
                return $x['day_time'] <=> $y['day_time'];
            }
        );
        foreach ($data['seven_data'] as &$v) {
            $seven_data[] = [
                $v['day_time'],
                (int)$v['brokerage'],
            ];
        }
        return json(['code' => 0, 'data' => array_values($seven_data)]);
    }

    /**
     * 收益说明[html]
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function explain(RSACrypt $crypt)
    {
        Env::load(Env::get('APP_PATH') . 'common/ini/.distribution');
        $set = Env::get();
        $data = [
            'income_explain' => $set['INCOME_EXPLAIN'] ? str_replace("<@>", "<br/>", $set['INCOME_EXPLAIN']) : '',
            'about_income' => $set['ABOUT_INCOME'] ? str_replace("<@>", "<br/>", $set['ABOUT_INCOME']) : '',
            'noun_explain' => $set['NOUN_EXPLAIN'] ? str_replace("<@>", "<br/>", $set['NOUN_EXPLAIN']) : '',
            'income_strategy' => $set['INCOME_STRATEGY'] ? str_replace("<@>", "<br/>", $set['INCOME_STRATEGY']) : '',
        ];

        //        halt($data);

        return $this->fetch('', ['data' => $data]);
    }
}