<?php
// 资产
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\CheckOrder;
use app\common\model\CheckOrder as CheckOrderModel;
use app\common\model\OrderAttach;
use app\common\model\OrderAttach as OrderAttachModel;
use app\common\model\OrderGoods as OrderGoodsModel;
use app\common\model\Store as StoreModel;
use app\common\model\StoreAuth as StoreAuthModel;
use app\common\model\StoreCapital as StoreCapitalModel;
use think\Controller;
use think\Db;
use think\facade\Request;
use think\facade\Session;
use app\common\model\Area as AreaModel;
use app\common\model\OrderGoodsRefund;

class StoreCapital extends Controller
{
    /**
     * 资产概况
     *
     * @param OrderGoodsModel   $orderGoods
     * @param StoreModel        $store
     * @param StoreCapitalModel $storeCapital
     *
     * @return mixed
     */
    public function index(OrderGoodsModel $orderGoods, StoreModel $store, StoreCapitalModel $storeCapital, CheckOrder $checkOrder, OrderAttachModel $orderAttach, OrderGoodsRefund $orderGoodsRefund)
    {
        try
        {
            $balance = $store->where(['store_id' => Session::get('client_store_id')])->value('balance'); // 可用余额

            // 待结算金额
            $_where = [
                ['store_id', '=', Session::get('client_store_id')],
                ['status', 'notin', '0,6'],
                ['pay_channel', '<>', 0],
                ['check_order_id', '=', 0],
            ];
            $price = $checkOrder->reconciliation_data(
                $orderAttach::withTrashed()->where($_where)->with('orderGoodsWD')->select(), ''
            )[Session::get('client_store_id')]['should_be_price'] ?? 0;

//            $price = (float)OrderAttach::where([
//                ['is_checking','=',0],
//                ['store_id','=',Session::get('client_store_id')]
//            ])->field(
//                'SUM(subtotal_price + subtotal_share_platform_coupon_price + subtotal_share_platform_packet_price) as wait_settlement'
//            )->find()['wait_settlement'];


            // 今日销售额
            $today = $orderGoods->where(
                [
                    ['status', 'in', '1.1,1.2,2.1,2.2,3.1,4.1,5.1,5.2,5.5,5.7'],
                ]
            )->where(['store_id' => Session::get('client_store_id')])->whereTime('create_time', 'today')->field(
                'IFNULL(sum(single_price*quantity),0) as today'
            )->find();
            // 今日退款额
            $todayBack = $orderGoodsRefund->alias('a')->join('order_goods order_goods', 'order_goods.order_goods_id = a.order_goods_id')->where(
                [
                    ['a.status', 'in', '1'],
                ]
            )->where(['order_goods.store_id' => Session::get('client_store_id')])->whereTime('a.create_time', 'today')->sum(
                'refund_amount'
            );
            // 昨日销售额
            $yesterday = $orderGoods->where(
                [
                    ['status', 'in', '1.1,1.2,2.1,2.2,3.1,4.1,5.1,5.2,5.5,5.7'],
                ]
            )->where(['store_id' => Session::get('client_store_id')])->whereTime('create_time', 'yesterday')->field(
                'IFNULL(sum(single_price*quantity),0) as yesterday'
            )->find();

            // 昨日退款额
            $yesterdayBack = $orderGoodsRefund->alias('a')->join('order_goods order_goods', 'order_goods.order_goods_id = a.order_goods_id')->where(
                [
                    ['a.status', 'in', '1'],
                ]
            )->where(['order_goods.store_id' => Session::get('client_store_id')])->whereTime('a.create_time', 'yesterday')->sum(
                'refund_amount'
            );

            $sales = [];
            $back = [];
            $spending = [];
            for ($i = 7; $i > 0; $i--)
            {
                // 近7日销售额
                $sales[] = $orderGoods->where(
                    [
                        ['status', 'in', '1.1,1.2,2.1,2.2,3.1,4.1,5.1,5.2,5.5,5.7'],
                    ]
                )->where(['store_id' => Session::get('client_store_id')])
                    ->whereTime(
                        'create_time', 'between', [
                                         date("Y-m-d", strtotime("-" . $i . " day")) . '00:00:00',
                                         date("Y-m-d", strtotime("-" . $i . " day")) . '23:59:59',
                                     ]
                    )
                    ->value('sum(single_price * quantity)')??0;
                // 近7日退款额
                $back[] = $orderGoods->where(
                    [
                        ['status', 'in', '4.2,4.3,5.3,5.4,5.6'],
                    ]
                )->where(['store_id' => Session::get('client_store_id')])
                    ->whereTime(
                        'create_time', 'between', [
                                         date("Y-m-d", strtotime("-" . $i . " day")) . '00:00:00',
                                         date("Y-m-d", strtotime("-" . $i . " day")) . '23:59:59',
                                     ]
                    )
                    ->sum('single_price');
                // 近七天提现金额
                $spending[] = $storeCapital->where([['status', 'in', '1.1,1.2'],])
                    ->where(['store_id' => Session::get('client_store_id')])
                    ->whereTime(
                        'create_time', 'between', [
                                         date("Y-m-d", strtotime("-" . $i . " day")) . '00:00:00',
                                         date("Y-m-d", strtotime("-" . $i . " day")) . '23:59:59',
                                     ]
                    )
                    ->sum('price');
            }

            $date = [];
            for ($i = 7; $i > 0; $i--)
            {
                $date[] = date("Y-m-d", strtotime('-' . $i . ' days', time()));
            }

            // 组合近七天销售额（收入）  提现额（支出）
            $d = [];
            foreach ($sales as $key => $val)
            {
                $d[$key]['day'] = $date[$key];
                $d[$key]['income'] = $sales[$key];
                $d[$key]['spending'] = $spending[$key];
            }

        } catch (\Exception $e)
        {
            return $e->getMessage();
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch(
            '', [
                  'balance'                      => $balance,
                  'price' => $price,
                  'today'                        => $today,
                  'yesterday'                    => $yesterday,
                  'todayBack'                    => $todayBack,
                  'yesterdayBack'                => $yesterdayBack,
                  'sales'                        => $sales,
                  'back'                         => $back,
                  'data'                         => $d,
                  'date'                         => $date,
              ]
        );
    }

    /**
     * 对账明细
     *
     * @param Request           $request
     * @param StoreCapitalModel $storeCapital
     * @param CheckOrderModel   $checkOrder
     *
     * @return array|mixed
     */
    public function details(Request $request, StoreCapitalModel $storeCapital, CheckOrder $checkOrder)
    {
        try
        {
            // 获取数据
            $param = $request::get();

            $param['start_date'] = !empty($param['start_date']) ? $param['start_date'] : '1968-01-01 00:00:00'; // 开始时间
            $param['end_date'] = !empty($param['end_date']) ? $param['end_date']: date('Y-m-d H:i:s', time()); // 结束时间

            // 筛选条件
            $condition = [];
            $min = [];
            $max = [];
            // 关键词
            if (!empty($param['keyword']))
            {
                $condition[] = [
                    'order_attach.order_attach_number|order_attach.trade_no',
                    'like',
                    '%' . $param['keyword'] . '%',
                ];
            }
            // 支付方式 0全部 1微信 2支付宝 3余额 5货到付款
            if (isset($param['pay_channel']) && $param['pay_channel'] != 0)
            {
                $condition[] = ['store_capital.pay_channel', 'eq', in_array($param['status']??-1,[-1,2,3])?$param['pay_channel']:0];
            }
            // 类型 0充值 1.提现 2交易订单 3退款订单
            if (isset($param['status']) && $param['status'] != -1)
            {
                $condition[] = ['store_capital.status', 'eq', $param['status']];
            }
            // 状态 0全部 1进行中 2交易成功 3退款成功
            if (isset($param['order_referer']) && $param['order_referer'] != -1 && $param['order_referer'] != 3)
            {
                $condition[] = ['order_attach.is_checking', 'eq', $param['order_referer']];
            }
            if (isset($param['order_referer']) && $param['order_referer'] == 3)
            {
                $condition[] = ['store_capital.status', 'eq', $param['order_referer']];
            }
            // 查询金额
            if (isset($param['min']) && $param['min'] != '')
            {
                $min [] = ['order_attach.subtotal_price', '>=', $param['min']];
            }
            if (isset($param['max']) && $param['max'] != '')
            {
                $max [] = ['order_attach.subtotal_price', '<=', $param['max']];
            }
            $data = $storeCapital
                ->alias('store_capital')
                ->join(
                    'order_attach order_attach', 'order_attach.order_attach_id = store_capital.order_attach_id', 'left'
                )
                ->join('consumption consumption', 'consumption.consumption_id = store_capital.parameter_id', 'left')
                ->join('member member', 'consumption.member_id = member.member_id', 'left')
                ->relation(
                    [
                        'orderGoodsRefund' => function ($g)
                        {
                            $g->alias('a')
                                ->join(
                                    'order_goods order_goods', 'order_goods.order_goods_id = a.order_goods_id', 'left'
                                )
                                ->join(
                                    'order_attach order_attach',
                                    'order_goods.order_attach_id = order_attach.order_attach_id', 'left'
                                )
                                ->field(
                                    'a.*,order_attach.order_attach_number,order_goods.single_price,order_attach.create_time,order_attach.pay_channel'
                                );
                        },
                    ]
                )
                ->where('store_capital.store_id', Session::get('client_store_id'))
                ->whereNotIn('store_capital.status', '1.1,1.3,1.4,1.5')
                ->where($condition)
                ->where($min)
                ->where($max)
                ->whereTime('store_capital.create_time', 'between', [$param['start_date'], $param['end_date']])
                ->field(
                    'store_capital.*,order_attach.order_attach_number,order_attach.is_checking,order_attach.subtotal_price,order_attach.trade_no,
                order_attach.create_time as order_attach_number_create_time,order_attach.pay_channel,consumption.member_id,member.phone'
                )
                ->order(['store_capital.create_time' => 'desc']);

            if (isset($param['dc']))
            {
                $data1 = $data->select();
                $simple = ['账户变动时间', '类型', '名称/备注', '收入/支出', '状态'];

                $status = '';
                $pay_channel = '';
                $content = '';
                $symbol = '';
                $state = '';
                $result = [];
                foreach ($data1 as $key => $value)
                {

                    switch ($value['pay_channel'])
                    {
                        case '1' :
                            $pay_channel = '微信';
                            break;
                        case '2' :
                            $pay_channel = '支付宝';
                            break;
                        case '3' :
                            $pay_channel = '余额';
                            break;
                        case '4' :
                            $pay_channel = '银行卡';
                            break;
                        case '5' :
                            $pay_channel = '线下';
                            break;
                        case '6' :
                            $pay_channel = '货到付款';
                            break;
                    }

                    switch ($value['orderGoodsRefund']['type'])
                    {
                        case '1' :
                            $type = '店铺未发货申请的订单退款';
                            break;
                        default :
                            $type = '店铺完成发货后申请的订单退款';
                            break;
                    }

                    switch ($value['is_checking'])
                    {
                        case '1' :
                            $is_checking = '交易成功';
                            break;
                        default :
                            $is_checking = '进行中';
                            break;
                    }

                    switch ($value['status'])
                    {
                        case '0' :
                            $status = '充值';
                            $content = '店铺充值';
                            $symbol = '+' . $value['price'];
                            $state = '充值成功';
                            break;
                        case '1.2' :
                            $status = '提现';
                            $content = '店铺提现' .
                                '提现编号：' . $value['withdraw_number'] .
                                '提现时间：' . $value['create_time'] .
                                '提现金额：' . $value['price'];
                            $symbol = '-' . $value['price'];
                            $state = '提现成功';
                            break;
                        case '2' :
                            $status = '交易订单';
                            $content = '店铺售卖商品收益' .
                                '订单编号：' . $value['order_attach_number'] .
                                '下单时间：' . $value['create_time'] .
                                '订单金额：' . $value['subtotal_price'] .
                                '支付方式：' . $pay_channel;
                            $symbol = '+' . $value['subtotal_price'];
                            $state = $is_checking;
                            break;
                        case '3' :
                            $status = '退款订单';
                            $content = $type .
                                '订单编号：' . $value['orderGoodsRefund']['order_attach_number'] .
                                '下单时间：' . $value['orderGoodsRefund']['create_time'] .
                                '订单金额：' . $value['orderGoodsRefund']['single_price'] .
                                '支付方式：' . $pay_channel;
                            $symbol = '-' . $value['orderGoodsRefund']['refund_amount'];
                            $state = '退款成功';
                            break;
                    }

                    $result[] = [
                        ' ' . $value['create_time'],
                        $status,
                        $content,
                        $symbol,
                        $state,
                    ];
                }

                $checkOrder->putCsv('账户明细导出' . '.csv', $result, $simple);

            } else
            {
                $data = $data->paginate(10, FALSE, ['query' => $param]);
            }

            $price = $storeCapital->alias('store_capital')
                ->join('order_attach order_attach', 'order_attach.order_attach_id = store_capital.order_attach_id')
                ->where($condition)
                ->where($min)
                ->where($max)
                ->where(
                    [
                        ['store_capital.store_id', 'eq', Session::get('client_store_id')],
                    ]
                )
                ->whereTime('store_capital.create_time', 'between', [$param['start_date'], $param['end_date']])
                ->field(
                    'store_capital.*,IFNULL(sum(if(store_capital.status in (0,2),price,NULL)),0) as income,IFNULL(sum(if(store_capital.status in (1.2,3),price,NULL)),0) as expend'
                )
                ->find();

        } catch (\Exception $e)
        {
            return $e->getMessage();
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch(
            '', [
                  'data'   => $data,
                  'render' => $data->render(),
                  'price'  => $price,
              ]
        );
    }

    /**
     * 对账单列表
     *
     * @param OrderAttach $orderAttach
     * @param CheckOrder  $checkOrder
     * @param Request     $request
     *
     * @return array|mixed
     */
    public function propertyList(OrderAttach $orderAttach, CheckOrder $checkOrder, Request $request)
    {
        try
        {
            $client_store_id = Session::get('client_store_id');
            $day_time = date('Y-m-d');
            //已经出账的账单数据
            $order_attach_where = [
                ['sale_after_status', '=', 0],
                ['store_id', '=', $client_store_id],
                ['check_order_id', '=', 0],
                ['status', '=', '4'],
                ['update_time', '>=', $day_time],
                ['update_time', '<', date('Y-m-d', strtotime('+1 day' . $day_time))],
            ];
            $data = $checkOrder
                ->order('time', 'desc')
                ->where(['store_id' => Session::get('client_store_id')]);
            $order_attach_data = $orderAttach::withTrashed()->with(
                [
                    'orderGoodsWD' => function ($query)
                    {
                        $query->field(
                            'store_id,order_attach_id,order_goods_id,status,quantity,single_price,discount,original_price,sub_share_shop_coupon_price,sub_share_platform_coupon_price,subtotal_share_platform_packet_price,sub_freight_price,sum_alter_goods_price,sum_alter_freight_price'
                        );
                    },
                ]
            )->where($order_attach_where)->field(
                'order_attach_id,store_id,pay_channel,status,order_attach_id,store_id'
            )->select();
            //当天的未出账账单数据
            $today_data = $checkOrder->reconciliation_data($order_attach_data, $day_time);
            if ($request::has('dc'))
            {
                $data = $data->select();
                empty($today_data) ?: $data[] = $today_data[$client_store_id];
                $_data = [];
                foreach ($data as $v)
                {
                    $_data[] = [
                        $v['order_number'] ?? 0,
                        $v['sum_pay_price'] ?? 0,
                        $v['sum_activity_price'] ?? 0,
                        $v['sum_freight_price'] ?? 0,
                        $v['sum_refund_price'] ?? 0,
                        $v['sum_brokerage_price'] ?? 0,
                        $v['costs'] ?? 0,
                        $v['should_be_price'] ?? 0,
                        isset($v['check_status']) && $v['check_status'] == 2 ? '已出账' : '未出账',
                        isset($v['time']) ? $v['time'] : date('Y-m-d'),
                    ];
                }
                $checkOrder->create_exoirt('client_propertyList', $_data, '店铺对账单');
                return;
            }
            $data = $data->paginate(10, FALSE);

            //进账金额
            $income_where[] = ['store_id','=',Session::get('client_store_id')];
            $income_where[] = ['check_status','=',2];
            $income = $checkOrder->where($income_where)->sum('should_be_price');

            //出账金额
            $charge_off_where[] = ['store_id','=',Session::get('client_store_id')];
            $charge_off_where[] = ['is_checking','=',1];
            $charge_off = $orderAttach->where($charge_off_where)->field('SUM(subtotal_coupon_price+subtotal_freight_price) as charge_off')->find();

        } catch (\Exception $e)
        {
            halt($e->getMessage());
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch(
            '', [
                  'data'       => $data,
                  'render'     => $data->render(),
                  'income'     => $income,
                  'charge_off' => $charge_off,
                  'today_data' => isset($today_data[$client_store_id]) ? $today_data[$client_store_id] : [],
              ]
        );
    }

    /**
     * 对账单详情
     *
     * @param CheckOrder $checkOrder
     * @param Request    $request
     *
     * @return array|mixed
     * @throws \think\Exception\DbException `
     */
    public function property_examine(CheckOrder $checkOrder, Request $request)
    {

        //设置返回上一级链接地址
        $client_store_id = Session::get('client_store_id');
        try
        {
            $day_time = date('Y-m-d');
            $the_day_before = date('Y-m-d', strtotime('+1 day' . $day_time));
            $input_data = $request::get();
            //筛选条件
            if (empty($input_data['check_order_id']))
            {
                $order_attach_where = [
                    ['store_id', '=', $client_store_id],
                    ['check_order_id', '=', 0],
                    ['status', '=', '4'],
                    ['update_time', '>=', $day_time],
                    ['update_time', '<', $the_day_before],
                    ['pay_channel', '<>', 0],
                ];;
            } else
            {
                $day_time = $checkOrder::get($input_data['check_order_id'])['time'];
                $order_attach_where = [
                    ['store_id', '=', $client_store_id],
                    ['check_order_id', '=', $input_data['check_order_id']],
                ];
            }
            if (!empty($input_data['order_attach_number']))
            {
                $order_attach_where[] = ['order_attach_number', '=', $input_data['order_attach_number']];
            }
            if (!empty($input_data['pay_type']))
            {
                $order_attach_where[] = ['pay_type', '=', $input_data['pay_type']];
            }
            if (isset($input_data['dc']))
            {
                $check_order_info = $checkOrder->get_check_info($order_attach_where, TRUE)['order_attach_data'];
                $_data = [];
                foreach ($check_order_info as $v)
                {
                    $_data[] = [
                        $v['order_attach_number'],
                        $v['create_time'],
                        $v['deal_time'],
                        $v['check_time'],
                        $v['pay_price'],
                        $v['activity_price'],
                        $v['subtotal_freight_price'],
                        $v['refund_price'],
                        $v['should_be_price'],
                        $v['is_checking'] == 1 ? '已出账' : '未出账',
                    ];
                }
                $checkOrder->create_exoirt(
                    'client_property_examine', $_data,
                    $day_time . '-' . date('Y-m-d', strtotime('+1 day' . $day_time)) . '对账单详情'
                );
                return;
            }
            $check_order_info = $checkOrder->get_check_info($order_attach_where);
        } catch (\Exception $e)
        {
            $this->error($e->getMessage());
        }
        //店铺名
        $client_storeName = StoreModel::get($client_store_id)->store_name;
        return $this->fetch(
            '', [
                  'data'             => $check_order_info['order_attach_data'],
                  'should_be_price'  => $check_order_info['should_be_price'],
                  'order_number'     => $check_order_info['order_number'],
                  'client_storeName' => $client_storeName,
                  'time'             => date('Y-m-d', strtotime('+1 day ' . input('time'))),
              ]
        );
    }

    /**
     * 提现申请
     *
     * @param Request           $request
     * @param StoreCapitalModel $storeCapital
     * @param StoreAuthModel    $storeAuth
     * @param StoreModel        $store
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function withdraw(Request $request, StoreCapitalModel $storeCapital, StoreAuthModel $storeAuth, StoreModel $store, AreaModel $area)
    {
        $item = $storeAuth->where(['store_id' => Session::get('client_store_id')])->find();
        $store = $store->where(['store_id' => Session::get('client_store_id')])->field('bank_status')->find();

        if ($request::isPost())
        {

            Db::startTrans();

            try
            {
                // 获取数据
                $param = $request::post();

                if ($param['bank_type'] == 1) {
                    $param['type'] = 1;
                    $param['province'] = $param['province_1'];
                    $param['city'] = $param['city_1'];
                    $param['area'] = $param['area_1'];
                    $param['account_bank_name'] = $param['account_bank_name_1'];
                    $param['account_name'] = $param['account_name_1'];
                    $param['bank_number'] = $param['bank_number_1'];
                } else {
                    $param['type'] = 2;
                    $param['province'] = $area->where('area_id', $param['province'])->value('area_name');
                    $param['city'] = $area->where('area_id', $param['city'])->value('area_name');
                    $param['area'] = $area->where('area_id', $param['area'])->value('area_name');
                }
                $param['store_id'] = Session::get('client_store_id');
                $param['status'] = 1.1;


                // 验证信息
//                dump($param);
                $check = $storeCapital->valid($param, 'create');
                if ($check['code'])
                {
                    return $check;
                }

                $store->where(['store_id' => Session::get('client_store_id')])->setDec('balance', $param['price']);
//                dump($param);
                // 写入
                $operation = $storeCapital->allowField(TRUE)->save($param);

                // 提交事务
                Db::commit();
                if ($operation)
                {
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/store_capital/index'];
                }

            } catch (\Exception $e)
            {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }

        $_areaCondition = "{$item['bank_province']},{$item['bank_city']},{$item['bank_area']}";
        $_areaList = $area->where([
            ['area_id', 'in', $_areaCondition]
        ])->orderRaw("field(area_name,'{$item['bank_province']}','{$item['bank_city']}','{$item['bank_area']}')")->column('area_name');

        $item['bank_province'] = $_areaList[0];
        $item['bank_city'] = $_areaList[1];
        $item['bank_area'] = $_areaList[2];

        return $this->fetch(
            '', [
                  'item' => $item,
              ]
        );
    }

    //每天定时处理对账   12:30   处理前一天订单
    public function check_order(CheckOrder $checkOrder)
    {
        $info = $checkOrder->reconciliation();
        return $info . '\n';
    }

    public function withdraw_details(StoreCapitalModel $storeCapital, Request $request, CheckOrderModel $checkOrder)
    {
        try {
            // 获取数据
            $param = $request::get();
            $where = [];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($where, ['store_capital.create_time', 'between', [$begin, $end]]);
            }
//            $param['start_date'] = !empty($param['start_date']) ? $param['start_date'] : '1968-01-01 00:00:00'; // 开始时间
//            $param['end_date'] = !empty($param['end_date']) ? $param['end_date'] : date('Y-m-d H:i:s', time()); // 结束时间

            // 筛选条件
            $condition = [];
            $min = [];
            $max = [];
            // 关键词
            if (!empty($param['keyword'])) $condition[] = ['store.store_name', 'like', '%' . $param['keyword'] . '%'];
            // 支付方式 0全部  1自动打款 2银行卡线下打款 3无
            if (isset($param['type']) && $param['type'] != 0) $condition[] = ['store_capital.type', 'eq', $param['type']];

            // -1全部 1.1提现申请中 1.4 审核通过提现中 1.2提现成功 1.3提现失败
            if (isset($param['status']) && $param['status'] != -1) {
                $condition[] = ['store_capital.status', 'eq', $param['status']];
            } else {
                $condition[] = ['store_capital.status', 'in', '1.1,1.2,1.3,1.4,1.5'];
            }

            // 查询金额
            if (isset($param['min']) && $param['min'] != '') $min [] = ['price', '>=', $param['min']];
            if (isset($param['max']) && $param['max'] != '') $max [] = ['price', '<=', $param['max']];

            $data = $storeCapital
                ->alias('store_capital')
                ->join('store store', 'store.store_id = store_capital.store_id', 'left')
                ->where('store_capital.store_id', '=', Session::get('client_store_id'))
                ->where($condition)
                ->where($min)
                ->where($max)
                ->where($where)
                ->field('store_capital.*,store.store_name')
                ->order(['store_capital.create_time' => 'desc']);

            if (isset($param['dc'])) {
                $data1 = $data->select();
                $simple = ['申请时间', '商家名称', '提现金额', '提现账户', '提现状态'];

                $status = '';
                $result = [];
                foreach ($data1 as $key => $value) {

                    switch ($value['type']) {
                        case '1' :
                            $type = '微信账户';
                            break;
                        case '2' :
                            $type = ' '.$value['bank_number'];
                            break;
                        default:
                            $type = '';
                            break;
                    }

                    switch ($value['status']) {
                        case '1.1' :
                            $status = '待审核';
                            break;
                        case '1.4' :
                            $status = '审核通过提现中';
                            break;
                        case '1.2' :
                            $status = '提现成功';
                            break;
                        case '1.3' :
                            $status = '提现成功';
                            break;
                    }

                    $result[] = [
                        ' ' . $value['create_time'],
                        $value['store_name'],
                        ['type_format'=>'number','value'=>$value['price']],
                        $type,
                        $status
                    ];
                }
                $checkOrder->putCsv1('提现记录导出', $result, $simple);
            } else {
                $data = $data->paginate(10, false, ['query' => $param]);
            }

            $price = $storeCapital->alias('store_capital')
                ->join('order_attach order_attach', 'order_attach.order_attach_id = store_capital.order_attach_id', 'left')
                ->join('store store', 'store.store_id = store_capital.store_id', 'left')
                ->where($condition)
                ->where('store_capital.store_id', '=', Session::get('client_store_id'))
                ->where($min)
                ->where($max)
                ->where($where)
                ->field('IFNULL(sum(case when store_capital.status = 1.1 then price else 0 end),0) as N_checking,
                IFNULL(sum(case when store_capital.status = 1.4 then price else 0 end),0) as Y_checking,
                IFNULL(sum(case when store_capital.status = 1.2 then price else 0 end),0) as succeed,
                IFNULL(sum(case when store_capital.status = 1.3 then price else 0 end),0) as N_succeed,store.store_name')
                ->find();

        } catch (\Exception $e) {
            halt($e->getMessage().'-'.$e->getLine().'-'.$e->getFile());
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
            'price' => $price,
        ]);
    }

    public function withdraw_examine(Request $request, StoreCapitalModel $storeCapital)
    {
        // status1.2成功  1.3失败
        $data = $storeCapital->where('capital_id', $request::get('capital_id'))->find();
        return $this->fetch('', [
            'item' => $data,
            'status' => $request::get('status'),
        ]);
    }
}