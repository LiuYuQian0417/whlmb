<?php
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\AlterPriceLog;
use app\common\model\CheckOrder;
use app\common\model\Consumption;
use app\common\model\DadaShop as DadaShop;
use app\common\model\Express as ExpressModel;
use app\common\model\Goods;
use app\common\model\IntegralRecord;
use app\common\model\Limit;
use app\common\model\Member;
use app\common\model\MemberCoupon;
use app\common\model\MemberGrowthRecord;
use app\common\model\MemberPacket;
use app\common\model\MemberRank;
use app\common\model\Message as MessageModel;
use app\common\model\Order as OrderModel;
use app\common\model\OrderAttach;
use app\common\model\OrderAttach as OrderAttachModel;
use app\common\model\OrderGoods;
use app\common\model\OrderGoods as OrderGoodsModel;
use app\common\model\OrderGoodsRefund as OrderGoodsRefundModel;
use app\common\model\Products;
use app\common\model\Store as StoreModel;
use app\common\model\StoreCapital;
use app\common\service\Beanstalk;
use app\common\service\Dada as DadaService;
use app\common\service\Lock;
use app\common\service\TemplateMessage;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;
use app\common\model\Invoice;

class Order extends Controller
{
    /**
     * 订单列表
     *
     * @param Request $request
     * @param OrderAttachModel $orderAttach
     * @param OrderGoodsModel $orderGoods
     * @param CheckOrder $checkOrder
     *
     * @return array|mixed
     */
    public function index(Request $request, OrderAttachModel $orderAttach, OrderGoodsModel $orderGoods, CheckOrder $checkOrder)
    {
        try {
            // 获取数据
            $param = $request::get();

            // 筛选条件
            $condition = [['order_attach.pay_channel', '<>', 5]];
            $field = 'order_attach.create_time';
            if (array_key_exists('origin_type', $param) && $param['origin_type'][0] != 0) {
                $condition[] = ['origin_type', 'in', implode(',', $param['origin_type'])]; // 订单来源
            } else {
                $condition[] = ['origin_type', 'in', '1,2,3,4']; // 订单来源
            }
            if (array_key_exists('order_type', $param) && $param['order_type'][0] != 0) {
                $condition[] = ['order_type', 'in', implode(',', $param['order_type'])];// 订单类型
            } else {
                $condition[] = ['order_type', 'in', '1,2,3,4'];
            }
            if (array_key_exists('distribution_type', $param) && $param['distribution_type'][0] != 0) {
                $condition[] = ['distribution_type', 'in', implode(',', $param['distribution_type'])];// 配送方式
            } else {
                $condition[] = ['distribution_type', 'in', '1,2,3'];
            }

            $whereOr = [];
            if (array_key_exists('status', $param) && $param['status'][0] != -1) {
                // 存在退款中订单
                if (in_array(5, $param['status'])) {
                    $orderGoodsCol = $orderGoods->where(
                        [
                            ['status', 'in', '5.1,5.2,5.3,5.4'],
                        ]
                    )->column('order_attach_id');
                    if (count($param['status']) == 1) {
                        // 仅查询退款
                        array_push(
                            $condition,
                            ['order_attach.order_attach_id', 'in', implode(',', array_unique($orderGoodsCol))]
                        );
                    } else {
                        if ($orderGoodsCol) {
                            array_push(
                                $whereOr, [
                                    'order_attach.order_attach_id',
                                    'in',
                                    implode(',', array_unique($orderGoodsCol)),
                                ]
                            );
                        }
                        $condition[] = ['order_attach.status', 'in', implode(',', $param['status'])];// 订单状态
                    }
                } else {
                    $condition[] = ['order_attach.status', 'in', implode(',', $param['status'])];// 订单状态
                }
            } else {
                $condition[] = ['order_attach.status', 'in', '0,1,2,3,4,5,6'];
            }

            if (array_key_exists('pay_channel', $param) && $param['pay_channel'][0] != -1) {
                $condition[] = ['pay_channel', 'in', implode(',', $param['pay_channel'])];// 支付方式
            } else {
                $condition[] = ['pay_channel', 'in', '0,1,2,3,4,5,6'];
            }

            $param['start_date'] = !empty($param['start_date']) ? $param['start_date'] : '1968-01-01 00:00:00'; // 开始时间
            $param['end_date'] = !empty($param['end_date']) ? $param['end_date'] : date('Y-m-d H:i:s', time()); // 结束时间
            $param['keywords'] = !empty($param['keywords']) ? $param['keywords'] : ''; // 关键词
            $param['keywords_type'] = !empty($param['keywords_type']) ? $param['keywords_type'] : 0; // 关键词
            if (array_key_exists('type', $param) && $param['type'] != 0) {
                $condition[] = ['distribution_type', 'eq', $param['type']];
            } // 支付方式
            if (array_key_exists('pay_type', $param)) {
                $condition[] = ['pay_type', 'eq', $param['pay_type']];
            } // 支付类型 1线上 2货到付款

            // 查询时间类型
            if (array_key_exists('time_type', $param) && $param['time_type'] != 0) {
                switch ($param['time_type']) {
                    case 1: //成交时间
                        $field = 'deal_time';
                        break;
                    case 2: //支付时间
                        $field = 'pay_time';
                        break;
                    case 3: //发货时间
                        $field = 'deliver_time';
                        break;
                }
            }

            // 关键词
            if (array_key_exists(
                    'keywords_type', $param
                ) && $param['keywords_type'] != 0 && $param['keywords_type'] != 1 && $param['keywords_type'] != 2 && $param['keywords'] != '') {

                switch ($param['keywords_type']) {
                    case 3: //客户昵称
                        $condition[] = ['nickname', 'like', '%' . $param['keywords'] . '%'];
                        break;
                    case 4: //收货人姓名
                        $condition[] = ['consignee_name', 'like', '%' . $param['keywords'] . '%'];
                        break;
                    case 5: //收货人联系方式
                        $condition[] = ['consignee_phone', 'like', '%' . $param['keywords'] . '%'];
                        break;
                    case 6: //订单号
                        $condition[] = ['order_attach_number', 'like', '%' . $param['keywords'] . '%'];
                        break;
                    case 7: //第三方支付单号
                        $condition[] = ['trade_no', 'like', '%' . $param['keywords'] . '%'];
                        break;
                }
            } else {
                if ($param['keywords_type'] == 1 && $param['keywords'] != '') {
                    //  1.商品名称
                    $orderGoodsCol = $orderGoods->withTrashed()->where(
                        [
                            ['goods_name', 'like', '%' . $param['keywords'] . '%'],
                        ]
                    )->column('order_attach_id');
                    array_push(
                        $condition, ['order_attach.order_attach_id', 'in', implode(',', array_unique($orderGoodsCol))]
                    );

                } else {
                    if ($param['keywords_type'] == 2 && $param['keywords'] != '') {
                        //  2 .商品货号
                        $orderGoodsCol = $orderGoods->withTrashed()->where(
                            [
                                ['goods_sn', 'like', '%' . $param['keywords'] . '%'],
                            ]
                        )->column('order_attach_id');
                        array_push(
                            $condition,
                            ['order_attach.order_attach_id', 'in', implode(',', array_unique($orderGoodsCol))]
                        );

                    } else {
                        $condition[] = [
                            'nickname|consignee_name|consignee_phone|order_attach_number',
                            'like',
                            '%' . $param['keywords'] . '%',
                        ];
                    }
                }
            }
            // 获取数据
            $data = $orderAttach::withTrashed()
                ->alias('order_attach')
                ->join('order order', 'order.order_id = order_attach.order_id', 'left')
                ->join('member member', 'member.member_id = order.member_id')
                ->join(
                    'group_activity group_activity',
                    'group_activity.group_activity_id = order_attach.group_activity_id', 'left'
                )
//                ->with(['orderGoods' => function ($query) use ($param) {
//                    $query->where(['store_id' => Session::get('client_store_id')]);
//                }])
                ->whereTime('' . $field . '', 'between', [$param['start_date'], $param['end_date']])
                ->where($condition)
                ->field(
                    'order_attach.*,member.nickname,order_attach.pay_channel,order_attach.status,order_attach.order_attach_number,member.phone,
                order.order_type,group_activity.status as group_activity_status,address_province,address_city,address_area,address_street,
                address_details,consignee_name,consignee_phone,order.origin_type'
                )
                ->where(['order_attach.store_id' => Session::get('client_store_id')])
                ->whereOr($whereOr)
                ->order(['order.create_time' => 'desc']);
            if (isset($param['dc'])) {
                $data1 = $data->select();
                $_data = [];
                foreach ($data1 as $order_attach) {
                    $orderGoodsArr = $orderGoods::withTrashed()
                        ->where(
                            [
                                'order_attach_id' => $order_attach['order_attach_id'],
                                'store_id'        => Session::get('client_store_id'),
                            ]
                        )
                        ->select();
                    $order_attach['order_goods'] = $orderGoodsArr;

                    switch ($order_attach['order_type']) {
                        case '1' :
                            $order_type = '普通';
                            break;
                        case '2' :
                            switch ($order_attach['group_activity_status']) {
                                case '1' :
                                    $order_type = '拼团（进行中）';
                                    break;
                                case '2' :
                                    $order_type = '拼团（成功）';
                                    break;
                                case '3' :
                                    $order_type = '拼团（失败）';
                                    break;
                                default :
                                    $order_type = '拼团';
                                    break;
                            }
                            break;
                        case '3' :
                            $order_type = '砍价';
                            break;
                        case '4' :
                            $order_type = '限时抢购';
                            break;
                        case '5' :
                            $order_type = '线下';
                            break;
                    }
                    //支付方式
                    switch ($order_attach['pay_channel']) {
                        case 0:
                            $pay_channel = '未支付';
                            break;
                        case 1:
                            $pay_channel = '微信';
                            break;
                        case 2:
                            $pay_channel = '支付宝';
                            break;
                        case 3:
                            $pay_channel = '余额';
                            break;
                        case 4:
                            $pay_channel = '银行卡';
                            break;
                        case 5:
                            $pay_channel = '线下';
                            break;
                        case '6' :
                            $pay_channel = '货到付款';
                            break;
                        default :
                            $pay_channel = '';
                    }
                    //订单状态
                    switch ($order_attach['status']) {
                        case 0:
                            $status = '待付款';
                            break;
                        case 1:
                            switch ($order_attach['distribution_type']) {
                                case 1:
                                    $status = '待配送';
                                    break;
                                default :
                                    $status = '待发货';
                            }
                        case 2:
                            switch ($order_attach['distribution_type']) {
                                case 1:
                                    $status = '配送中';
                                    break;
                                case 2:
                                    $status = '待自提';
                                    break;
                                default :
                                    $status = '待收货';
                            }
                        case 3:
                            $status = '已完成';
                            break;
                        case 4:
                            $status = '已关闭';
                            break;
                        case 5:
                            $status = '退款中';
                            break;
                        case 6:
                            $status = '已取消';
                            break;
                        default :
                            $status = '';
                    }
                    foreach ($order_attach['order_goods'] as $order_goods) {
                        //退款状态
                        switch ($order_goods['status']) {
                            case 4.2:
                                $order_goods_status = '（退款成功）';
                                break;
                            case 4.3:
                                $order_goods_status = '（退货成功）';
                                break;
                            case 5.1:
                            case 5.2:
                                $order_goods_status = '（申请退款中）';
                                break;
                            case 5.3:
                                $order_goods_status = '（同意退货（等待填写物流））';
                                break;
                            case 5.4:
                                $order_goods_status = '（申请退货中）';
                                break;
                            case 5.5:
                            case 5.7:
                                $order_goods_status = '（退款失败）';
                                break;
                            case 5.6:
                                $order_goods_status = '（退货失败）';
                                break;
                            default :
                                $order_goods_status = '未退货';
                        }
                        $_data[] = [
                            $order_goods['goods_name'],
                            $order_goods_status,
                            $order_goods['single_price'],
                            $order_goods['quantity'],
                            //                            $order_type,
                            $order_attach['order_attach_number'],
                            $pay_channel,
                            $order_attach['nickname'],
                            $order_attach['create_time'],
                            $order_attach['subtotal_price'],
                            $status,
                            $order_attach['address_province'] . $order_attach['address_city'] . $order_attach['address_area'] . $order_attach['address_street'] . $order_attach['address_details'],
                            $order_attach['consignee_name'],
                            $order_attach['consignee_phone'],
                        ];
                    }
                }
                $checkOrder->create_exoirt('master_order_list', $_data, '店铺账单明细');
                return;
            } else {
                $data = $data->paginate(10, FALSE, ['query' => $param]);
                foreach ($data as $order_attach) {
                    $orderGoodsArr = $orderGoods::withTrashed()
                        ->where(
                            [
                                'order_attach_id' => $order_attach['order_attach_id'],
                                'store_id'        => Session::get('client_store_id'),
                            ]
                        )
                        ->select()
                        ->toArray();
                    $order_attach['order_goods'] = $orderGoodsArr;
                }
            }
        } catch (\Exception $e) {
            halt($e->getMessage());
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch(
            '', [
                'data'              => $data,
                'render'            => $data->render(),
                'origin_type'       => implode(',', Request::instance()->param('origin_type', [])),
                'order_type'        => implode(',', Request::instance()->param('order_type', [])),
                'distribution_type' => implode(',', Request::instance()->param('distribution_type', [])),
                'status'            => implode(',', Request::instance()->param('status', [])),
                'pay_channel'       => implode(',', Request::instance()->param('pay_channel', [])),
                'start_date'        => $param['start_date'],
                'end_date'          => $param['end_date'],
                'type'              => Request::instance()->param('type', 0),
            ]
        );
    }


    /**
     * 订单审核
     *
     * @param Request $request
     * @param OrderModel $order
     * @param OrderAttachModel $orderAttach
     * @param ExpressModel $express
     * @param StoreModel $store
     * @param OrderGoods $orderGoods
     * @param MessageModel $message
     *
     * @param Invoice $invoice
     * @return array|mixed
     */
    public function examine(Request $request, OrderModel $order, OrderAttachModel $orderAttach, ExpressModel $express, StoreModel $store, OrderGoodsModel $orderGoods, MessageModel $message, Invoice $invoice)
    {
        if ($request::isPost()) {
            Db::startTrans();

            try {
                $param = $request::post();

                $attach = $orderAttach
                    ->where(['order_attach_id' => $param['order_attach_id']])
                    ->field(
                        'order_number,dada,order_attach_number,distribution_tel,
                    prepay_id,express_value,express_number,client_id'
                    )
                    ->find();

                $param['delivery_method'] = isset($param['delivery_method']) ? $param['delivery_method'] : '';
                $check = ['code' => ''];
                if ($param['distribution_type'] == 3) {
                    // 快递邮寄
                    $check = $orderAttach->valid($param, 'examine');
                } else {
                    if ($param['distribution_type'] == 1) {
                        // 同城速递
                        if ($param['delivery_method'] == 1) {
                            // 快递配送
                            $check = $orderAttach->valid($param, 'examine');
                        } else {
                            if ($param['delivery_method'] == 2) {
                                // 自主配送
                                $check = $orderAttach->valid($param, 'local');
                            } else {
                                if ($attach['dada'] == 0) {
                                    // 达达配送
                                    $check = $orderAttach->valid($param, 'dada');
                                }
                            }
                        }
                    }
                }

                if ($check['code']) {
                    return $check;
                }

                $orderGoodsStr = implode(',', $param['order_goods_id']);
                foreach (Db::name('order_goods')->where('order_goods_id', 'in', $orderGoodsStr)->column(
                    'status'
                ) as $k => $v) {
                    if ($v == 5.1 || $v == 5.2 || $v == 5.3 || $v == 5.4 || $v == 5.6) {
                        return ['code' => -100, 'message' => '有未处理订单，暂时不能发货'];
                    }
                }

                // 处理发货
                $result = $orderAttach->examineOrder($param);

                if ($result['code'] == 0) {
                    // 计划任务自动收货
                    Env::load(Env::get('app_path') . 'common/ini/.config');
                    $collectTime = Env::get('confirm_receipt') * 86400;
                    (new Beanstalk())->put(
                        json_encode(
                            [
                                'queue' => 'autoCollect',
                                'id'    => $param['order_attach_id'],
                                'time'  => date('Y-m-d H:i:s'),
                            ]
                        ), $collectTime
                    );
                    Db::commit();
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/order/index'];
                } else {
                    return $result;
                }
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        try {
            $item = $orderAttach::withTrashed()
                ->alias('order_attach')
                ->join('order order', 'order.order_id=order_attach.order_id', 'left')
                ->join('member member', 'member.member_id = order_attach.member_id', 'left')
                ->join(
                    'group_activity group_activity', 'order_attach.group_activity_id = group_activity.group_activity_id',
                    'left'
                )
                ->join('member member1', 'group_activity.owner = member1.member_id', 'left')
                ->join('group_goods group_goods', 'group_goods.group_goods_id = group_activity.group_goods_id', 'left')
                ->join('take take', 'order_attach.take_id = take.take_id', 'left')
                ->join('store store', 'store.store_id = order_attach.store_id', 'left')
                ->relation('orderGoods')
                ->where(['order_attach.order_attach_id' => $request::get('id')])
                ->field(
                    'order_attach.*,member.username,member.username,member.nickname,order.*,group_activity.surplus_num,group_activity.status as group_activity_status,
            member1.nickname as owner_nickname,group_goods.group_num,member1.phone as owner_phone,take.take_name,take.province as take_province,take.city as take_city,
            take.area as take_area,take.address as take_address,store.is_pay_delivery'
                )
                ->find();

            $goods = $orderGoods
                ->withTrashed()
                ->with(
                    [
                        'orderGoodsRefund' => function ($e) {
                            $e->field('is_get_goods,refuse_reason,order_goods_id');
                        },
                    ]
                )
                ->where(
                    [
                        'order_goods.store_id'        => Session::get('client_store_id'),
                        'order_goods.order_attach_id' => $request::get('id'),
                    ]
                )
                ->alias('order_goods')
                ->join(
                    'goods_evaluate goods_evaluate', 'order_goods.order_goods_id = goods_evaluate.order_goods_id', 'left'
                )
                ->field(
                    'order_goods.*,(single_price*quantity) as goodsTotal,goods_evaluate_id,goods_evaluate.create_time as goods_evaluate_create_time'
                )
                ->select();

            $comment = $member_discount_price = 0;
            $time = '';
            foreach ($item['orderGoods'] as $k => $v) {
                if ($v['status'] == 4.1) {
                    $comment = 1;
                }
                if (!empty($goods[0]['goods_evaluate_id'])) {
                    $time = $goods[0]['goods_evaluate_create_time'];
                }
                $member_discount_price += ($v['discount_price'] * $v['quantity']);
            }

            // join express数据类型不一致
            $expressName = Db::name('express')->where(['code' => $item['express_value']])->value('name');

            // 快递100
            $data['customer'] = config('user.common.express.customer');
            $data['param'] = json_encode(['com' => $item['express_value'], 'num' => $item['express_number']]);
            $data["sign"] = strtoupper(md5($data['param'] . config('user.common.express.sign') . $data['customer']));

            $logistics = express_post($data, 'http://poll.kuaidi100.com/poll/query.do');

            $logistics['returnCode'] = isset($logistics['returnCode']) ? $logistics['returnCode'] : '0';
            if ($logistics['returnCode'] == 400 || $logistics['returnCode'] == 500 || $logistics['returnCode'] == 504) {
                $logistics['data'] = [];
            }
            if (empty($logistics['com'])) {
                $logistics['com'] = '暂无记录';
            }

            $store = $store->alias('a')
                ->join('dada_merchant dada_merchant', 'dada_merchant.store_id = a.store_id', 'left')
                ->where(['a.store_id' => Session::get('client_store_id')])
                ->field('a.province,a.city,a.area,a.address,dada_merchant.source_id')
                ->find();

            // 门店列表
            $dadaShop = (new DadaShop())->where(['store_id' => Session::get('client_store_id'), 'status' => 1])->order(
                ['create_time' => 'desc']
            )->select();
            // 达达城市code
            $city_code['data'] = [];
            $dadaExpress = [];
            if (!empty($store['source_id'])) {
                $Data = new DadaService($store['source_id'], '');
                $city_code = $Data->request('api/cityCode/list');

                // 达达配送
                if ($item['dada'] == 1) {
                    $Data = new DadaService($store['source_id'], ['order_id' => $item['order_attach_id']]);
                    $dadaExpress = $Data->request('api/order/status/query');
                }
            }
            $invoiceArr = $invoice->where([
                ['order_attach_id', '=', $request::get('id')],
                ['billing_type', 'in', '0,1'],
            ])->find();
            return $this->fetch(
                '', [
                    'item'                  => $item,
                    'express'               => $express::all(),
                    'store'                 => $store,
                    'logistics'             => $logistics,
                    'express_name'          => $expressName,
                    'comment'               => $comment,
                    'time'                  => $time,
                    'goods'                 => $goods,
                    'shop'                  => $dadaShop,
                    'city_code'             => $city_code['data'],
                    'dadaExpress'           => $dadaExpress,
                    'invoiceArr'            => $invoiceArr,
                    'member_discount_price' => $member_discount_price,
                ]
            );
        } catch (\Exception $e) {
            $this->error('网络异常请重试');
        }
    }

    /**
     * 获得订单信息打印
     *
     * @param Request $request
     * @param OrderGoods $orderGoods
     * @param OrderAttach $orderAttach
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_examine(Request $request, OrderGoodsModel $orderGoods, OrderAttachModel $orderAttach)
    {
        try {
            $order_attach_id = $request::post('id');
            $item = $orderAttach::with(
                [
                    'orders' => function ($query) {
                        $query->field('order_id,consignee_phone,consignee_name');
                    },
                    'member' => function ($query) {
                        $query->field('member_id,sex,phone');
                    }
                    ,
                    'store'  => function ($query) {
                        $query->field('store_id,store_name');
                    },
                ]
            )
                ->where(['order_attach_id' => $order_attach_id])
                ->field(
                    'member_id,order_id,create_time,subtotal_price,subtotal_share_platform_coupon_price,subtotal_share_platform_packet_price,subtotal_coupon_price,subtotal_promotion_price,subtotal_fullSub_price,store_id'
                )
                ->find();
            $goods = $orderGoods
                ->where(['order_attach_id' => $order_attach_id])
                ->field('goods_name,single_price,quantity')
                ->select();
            $item['orders']['consignee_name'] = mb_substr(
                    $item['orders']['consignee_name'], 0, 1
                ) . ($item['member']['sex'] == 0 ? '女士' : '先生');
            return ['code' => 0, 'goods' => $goods, 'item' => $item ?? []];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }


    /**
     * 退款/退货详情
     *
     * @param Request $request
     * @param OrderGoods $goods
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function refunds_details(Request $request, OrderGoodsModel $goods, Invoice $invoice)
    {
        if ($request::isPost()) {

            try {
                $param = $request::post();

                return $goods->refundsGoods($param);

            } catch (\ Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }

        // 退款退货信息
        $item = $goods
            ->alias('order_goods')
            ->join('order_attach order_attach', 'order_attach.order_attach_id = order_goods.order_attach_id')
            ->join('member member', 'order_goods.member_id = member.member_id')
            ->relation(
                [
                    'orderGoodsRefund' => function ($query) {
                        $query->field([
                            'create_time',
                            'refund_amount',
                            'return_type',
                            'reason',
                            'multiple_file',
                            'return_multiple_file',
                            'status', 'is_get_goods',
                            'origin_refund_amount',
                            'express_name',
                            'express_number',
                        ]);
                    },
                ]
            )
            ->where('order_goods.order_goods_id', $request::get('id'))
            ->field([
                'order_goods.order_attach_id'                                                                                                                                                                  => 'order_attach_id',
                'order_goods.order_goods_id'                                                                                                                                                                   => 'order_goods_id',
                'order_goods.member_id'                                                                                                                                                                        => 'member_id',
                'order_goods.attr'                                                                                                                                                                             => 'attr',
                'order_goods.quantity'                                                                                                                                                                         => 'quantity',
                'order_goods.single_price'                                                                                                                                                                     => 'single_price',
                '((single_price*quantity)-order_goods.sub_share_shop_coupon_price-order_goods.sub_share_platform_coupon_price-order_goods.subtotal_share_platform_packet_price)'                               => 'goodsTotal',
                '((single_price*quantity)+order_goods.sub_freight_price-order_goods.sub_share_shop_coupon_price-order_goods.sub_share_platform_coupon_price-order_goods.subtotal_share_platform_packet_price)' => 'goodsTotal1',
                'order_goods.sub_freight_price'                                                                                                                                                                => 'sub_freight_price',
                'order_goods.goods_name'                                                                                                                                                                       => 'goods_name',
                'order_goods.file'                                                                                                                                                                             => 'file',
                'order_goods.status'                                                                                                                                                                           => 'status',
                'member.nickname'                                                                                                                                                                              => 'nickname',
                'order_goods.redo_status'                                                                                                                                                                      => 'redo_status',
                'order_attach.distribution_type'                                                                                                                                                               => 'distribution_type',
            ])
            ->find();

        // 如果 售后订单有值的话 则 吧字符串 换成数组
        if (
            isset($item['orderGoodsRefund'])
            && isset($item['orderGoodsRefund']['return_multiple_file'])
            && !empty($item['orderGoodsRefund']['return_multiple_file'])
        ) {
            $imageArr = [];

            foreach (explode(',', $item['orderGoodsRefund']['return_multiple_file']) as $value) {
                $imageArr[] = $goods->getOssUrl($value);
            };

            $item['orderGoodsRefund']['return_multiple_file'] = $imageArr;
        }

        // 发票信息
        $invoiceArr = $invoice->where([
            ['order_attach_id', '=', $item['order_attach_id']],
            ['billing_type', '=', 1],
        ])->find();

        return $this->fetch(
            '', [
                'item'       => $item,
                'invoiceArr' => $invoiceArr,
            ]
        );
    }

    /**
     * 检查退货状态
     *
     * @param Request $request
     * @param OrderGoodsRefundModel $orderGoodsRefund
     *
     * @return array
     */
    public function checkRefunds(Request $request, OrderGoodsRefundModel $orderGoodsRefund)
    {
        if ($request::isPost()) {

            try {
                $param = $request::post();

                if (empty($orderGoodsRefund->where(['order_goods_id' => $param['id']])->find())) {
                    return ['code' => -100, 'message' => '订单状态错误，请刷新页面'];
                }

                return ['code' => 0];
            } catch (\ Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 修改价格
     *
     * @param Request $request
     * @param AlterPriceLog $alterPriceLog
     *
     * @return array|mixed
     */
    public function editPrice(Request $request, AlterPriceLog $alterPriceLog)
    {
        if ($request::isPost()) {

            try {
                $param = $request::post();
                $alterPriceLog->valid($param, 'store');
                Db::startTrans();
                $alterPriceLog->storeUpdateOrderPrice($param);
                Db::commit();
//                $orderAttach->isUpdate(TRUE)->allowField(TRUE)->save($param);
                return ['code' => 0, 'message' => '修改成功'];
            } catch (\ Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }

        return $this->fetch(
            '', [
                //'item' => $orderGoods->where(['order_goods_id' => $request::get('order_goods_id')])->field('order_attach_id,sub_freight_price,subtotal_price')->find(),
            ]
        );
    }

    /**
     * 核销提货码
     *
     * @param Request $request
     * @param OrderAttach $orderAttach
     *
     * @return array
     */
    public function checkTakeCode(Request $request, OrderAttachModel $orderAttach)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                $param = $request::post();
                $find = $orderAttach
                    ->where(['order_attach_id' => $param['order_attach_id']])
                    ->field('order_attach_id,take_code,after_sale_times,member_id')
                    ->find();
                if ($param['take_code'] != $find['take_code']) {
                    return ['code' => -100, 'message' => '提货码错误，请重新输入'];
                }
                $status = Db::name('order_goods')
                    ->where('order_attach_id', 'eq', $param['order_attach_id'])
                    ->field('order_goods_id,status')
                    ->select();
                $orderGoodsId = [];
                foreach ($status as $k => $v) {
                    // 售后状态
                    if ($v['status'] >= 5 && $v['status'] < 6) {
                        return ['code' => -100, 'message' => '有未处理订单，暂时不能核销提货码'];
                    }
                    if ($v['status'] == 2.1) {
                        array_push($orderGoodsId, $v['order_goods_id']);
                    }
                }
                $param['status'] = 3;
                $param['deal_time'] = date('Y-m-d H:i:s');
                $orderAttach->isUpdate(TRUE)->allowField(TRUE)->save($param);
                Db::name('order_goods')
                    ->where('order_attach_id', 'eq', $param['order_attach_id'])
                    ->where('status', 'eq', '2.1')
                    ->update(['status' => '3.1']);
                Db::commit();
                // 计划任务自动评价
                Env::load(Env::get('app_path') . 'common/ini/.config');
                (new Beanstalk())->put(
                    json_encode(
                        [
                            'queue' => 'autoEvaluate',
                            'id'    => $orderGoodsId,
                            'uid'   => $find['member_id'],
                            'time'  => date('Y-m-d H:i:s'),
                        ]
                    ), Env::get('good_reputation') * 86400
                );
                // 关闭售后通道
                (new Beanstalk())->put(
                    json_encode(
                        [
                            'queue' => 'autoCloseSaleAfter',
                            'id'    => $param['order_attach_id'],
                            'time'  => date('Y-m-d H:i:s'),
                        ]
                    ), $find['after_sale_times'] * 86400
                );
                return ['code' => 0, 'message' => '核销提货码成功'];

            } catch (\ Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }
    }

    /**
     * 关闭订单
     *
     * @param Request $request
     * @param OrderAttach $orderAttach
     * @param OrderGoods $orderGoods
     * @param Goods $goods
     * @param Products $products
     * @param Lock $lock
     * @param MemberCoupon $memberCoupon
     * @param Limit $limit
     *
     * @return array
     */
    public function closeOrder(Request $request, OrderAttach $orderAttach, OrderGoods $orderGoods, Goods $goods, Products $products, Lock $lock, MemberCoupon $memberCoupon, Limit $limit)
    {
        try {
            Db::startTrans();
            $args = $request::post();
            $check = $orderAttach->valid($args, 'cancel');
            if ($check['code']) {
                return $check;
            }
            $where = [
                ['order_attach_id', '=', $args['order_attach_id']],
                ['status', '=', 0],  //仅支持取消待支付订单
            ];
            $field = 'order_attach_id,order_id,used_shop_member_coupon_id';
            $orderAttachData = $orderAttach
                ->where($where)
                ->field($field)
                ->with(['orderGoodsCancel', 'orderCancel'])
                ->find();
            if (!$orderAttachData) {
                return ['code' => -1, 'message' => config('message.')[-11][-3]];
            }
            if (!$orderAttachData['order_goods_cancel']) {
                return ['code' => -6, 'message' => config('message.')[-11][-6]];
            }
            // 回滚库存
            $inventory = ['goods_id' => [], 'products_id' => [], 'limit_id' => []];
            $lockKey = $updateOrderGoods = $couponUpdate = [];
            foreach ($orderAttachData['order_goods_cancel'] as $key => $value) {
                $updateKey = $value['is_limit'] ? 'time_limit_number' : 'goods_number';
                // 增加商品库存[正常/限时抢购]
                array_push(
                    $inventory['goods_id'], [
                        'goods_id'     => $value['goods_id'],
                        'goods_number' => Db::raw('goods_number + ' . $value['quantity']),
                    ]
                );
                // 增加商品行锁
                array_push($lockKey, 'goods_id_' . $value['goods_id']);
                if ($value['is_limit']) {
                    array_push(
                        $inventory['limit_id'], [
                            'limit_id'     => $value['limit_id'],
                            'exchange_num' => Db::raw('exchange_num + ' . $value['quantity']),
                        ]
                    );
                    array_push($lockKey, 'limit_id_' . $value['limit_id']);
                }
                // 增加规格商品库存[正常/限时抢购]
                if ($value['products_id']) {
                    array_push(
                        $inventory['products_id'], [
                            'products_id'        => $value['products_id'],
                            'attr_' . $updateKey => Db::raw(
                                'attr_' . $updateKey . ' + ' . $value['quantity']
                            ),
                        ]
                    );
                    // 增加规格商品行锁
                    array_push($lockKey, 'products_id_' . $value['products_id']);
                }
                array_push($updateOrderGoods, $value['order_goods_id']);
            }
            // 加锁 
            $lockData = $lock->lock($lockKey, 10000);
            if (!$lockData) {
                return ['code' => -4, 'message' => config('message.')[-11][-4]];
            }
            if ($inventory['goods_id']) {
                $goods->allowField(TRUE)->isUpdate(TRUE)->saveAll($inventory['goods_id']);
            }
            if ($inventory['products_id']) {
                $products->allowField(TRUE)->isUpdate(TRUE)->saveAll($inventory['products_id']);
            }
            if ($inventory['limit_id']) {
                $limit->allowField(TRUE)->isUpdate(TRUE)->saveAll($inventory['limit_id']);
            }
            // 解锁
            $lock->unlock($lockData);
            // 更改订单附表为已关闭
            $orderAttachData->status = 6;
            $orderAttachData->save();
            // 更改订单商品数据为已取消
            if ($updateOrderGoods) {
                $orderGoods
                    ->allowField(TRUE)
                    ->isUpdate(TRUE)
                    ->save(['status' => 6.1], [['order_goods_id', 'in', implode(',', $updateOrderGoods)]]);
            }
            // 含有店铺优惠券,则退还优惠券
            if ($orderAttachData['used_shop_member_coupon_id']) {
                array_push($couponUpdate, $orderAttachData['used_shop_member_coupon_id']);
            }
            if ($orderAttachData['order_cancel']['used_platform_member_coupon_id']) {
                // 检测是否所有同源商品订单都已取消
                $otherOrderGoodsData = $orderGoods
                    ->where(
                        [
                            ['order_id', '=', $orderAttachData['order_id']],
                            ['order_attach_id', '<>', $args['order_attach_id']],
                            ['status', '<>', 6.1],
                        ]
                    )
                    ->column('order_goods_id');
                // 若其他状态订单商品不存在,则退回用户使用的平台优惠
                if (empty($otherOrderGoodsData)) {
                    array_push($couponUpdate, $orderAttachData['order_cancel']['used_platform_member_coupon_id']);
                }
            }
            if ($couponUpdate) {
                $memberCoupon
                    ->allowField(TRUE)
                    ->isUpdate(TRUE)
                    ->save(
                        ['status' => 0, 'used_time' => NULL],
                        [['member_coupon_id', 'in', implode(',', $couponUpdate)]]
                    );
            }
            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0]];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    //线下订单支付
    public function offline_payment_pay(Session $session, Request $request, MemberPacket $memberPacket, MemberCoupon $memberCoupon, OrderModel $order, OrderAttach $orderAttach, OrderGoods $orderGoods, StoreCapital $storeCapital, Consumption $consumption, Member $member, IntegralRecord $integralRecord, MemberGrowthRecord $growthRecord)
    {
        if ($request::isPost()) {
            //积分
            Env::load(Env::get('APP_PATH') . 'common/ini/.config');
            $integral_conversion = Env::get('integral_conversion', 100);
            $data = $request::param();
            $data['pay_price'] = round($data['pay_price'], 2);
            $data['original_price'] = round($data['original_price'], 2);
            $client_store_id = $session::get('client_store_id');
            $time = date('Y-m-d H:i:s');
            $member_info = $member->get($data['member_id']);
            if ($data['pay_price'] > $member_info->usable_money) {
                return ['code' => '-100', 'message' => '可用余额不足'];
            }
            if ($data['pay_price'] <= 0 || $data['pay_price'] >= 10000000) {
                return ['code' => '-100', 'message' => '付款金额有误'];
            }
            try {
                Db::startTrans();
                //如果使用优惠券和红包更改对应状态
                $member_coupon_status = $memberCoupon->where(
                    [['member_coupon_id', '=', $data['member_coupon_id']], ['member_id', '=', $data['member_id']]]
                )->update(['status' => 1, 'use_time' => date('Y-m-d H:i:s')]);
                $memberPacket->where(
                    [['member_packet_id', '=', $data['member_packet_id']], ['member_id', '=', $data['member_id']]]
                )->update(['status' => 1, 'use_time' => date('Y-m-d H:i:s')]);
                $order_number = get_order_sn();
                //计算积分
                $total_back_integral = ceil($data['pay_price'] * $integral_conversion / 100);
                //主订单数据
                $order_data = [
                    'order_number'        => $order_number,
                    'member_id'           => $data['member_id'],
                    'total_price'         => $data['pay_price'],
                    'total_coupon_price'  => $data['coupon'],
                    'total_packet_price'  => $data['packet'],
                    'total_back_integral' => $total_back_integral,
                    'origin_type'         => 5,
                    'order_type'          => 5,
                ];
                $status[] = $order->save($order_data);
                //店铺订单数据
                $order_attach_data = [
                    'order_id'                             => $order['order_id'],
                    'order_number'                         => $order_number,
                    'order_attach_number'                  => get_order_sn(),
                    'member_id'                            => $data['member_id'],
                    'store_id'                             => $client_store_id,
                    'subtotal_price'                       => $data['pay_price'],
                    'subtotal_coupon_price'                => $data['coupon'],
                    'subtotal_back_integral'               => $total_back_integral,
                    'subtotal_share_platform_packet_price' => $data['packet'],
                    'distribution_type'                    => 4,
                    'pay_channel'                          => 5,
                    'status'                               => 4,
                    'sale_after_status'                    => 0,
                    'deal_time'                            => $time,
                    'pay_time'                             => $time,
                ];
                empty($member_coupon_status) ?: $order_attach_data['used_shop_member_coupon_id'] = $data['member_coupon_id'];
                $status[] = $orderAttach->save($order_attach_data);
                //子订单数据
                $order_goods_data = [
                    'member_id'                            => $data['member_id'],
                    'store_id'                             => $client_store_id,
                    'order_id'                             => $order['order_id'],
                    'order_attach_id'                      => $orderAttach['order_attach_id'],
                    'single_price'                         => $data['pay_price'],
                    'discount'                             => $data['discount'],
                    'original_price'                       => $data['original_price'],
                    'sub_share_shop_coupon_price'          => $data['coupon'],
                    'subtotal_share_platform_packet_price' => $data['packet'],
                    'spell_goods_name'                     => '线下支付',
                    'status'                               => 4.1,
                ];
                $status[] = $orderGoods->save($order_goods_data);
                //积分明细表
                $integral_record_data = [
                    'member_id'    => $data['member_id'],
                    'type'         => 0,
                    'origin_point' => 2,
                    'integral'     => $total_back_integral,
                    'describe'     => '线下支付返积分',
                    'create_time'  => date('Y-m-d H:i:s'),
                ];
                $status[] = $integralRecord->save($integral_record_data);
                //店铺资金明细表
                $store_capital_data = [
                    'store_id'        => $client_store_id,
                    'price'           => $client_store_id,
                    'order_attach_id' => $orderAttach['order_attach_id'],
                    'status'          => 2,
                    'create_time'     => $time,
                ];
                $status[] = $storeCapital->save($store_capital_data);
                //余额消费明细
                $consumption_data = [
                    'member_id'           => $data['member_id'],
                    'type'                => 2,
                    'order_number'        => $order_attach_data['order_number'],
                    'order_attach_number' => $order_attach_data['order_attach_number'],
                    'price'               => $order_attach_data['subtotal_price'],
                    'balance'             => $member_info->usable_money - $order_attach_data['subtotal_price'],
                    'way'                 => 5,
                ];
                //更新会员余额
                $member_info->usable_money -= $order_attach_data['subtotal_price'];
                //更新会员积分
                $member_info->pay_points += $total_back_integral;
                //更新会员成长值明细
                $growth_value = $growthRecord->where(
                    [
                        ['member_id', '=', $data['member_id']],
                        ['create_time', '>=', date('Y-m-d 0:0:0')],
                        ['create_time', '<', date('Y-m-d', strtotime('+1 day ' . date('Y-m-d')))],
                        ['describe', '=', '余额支付'],
                    ]
                )->sum('growth_value');
                $growth_balance = Env::get('growth_balance', 0);//余额支付赠送成长值
                $growth_balance_number = Env::get('growth_balance_number', 0);//每天最多成长值
                if ($growth_balance_number >= $growth_value + $growth_balance && $growth_balance != 0) {
                    $status[] = $growthRecord->save(
                        [
                            'type'         => 1,
                            'member_id'    => $data['member_id'],
                            'growth_value' => $growth_balance,
                            'describe'     => '余额支付',
                        ]
                    );//describe 描述值固定不可改变
                }
                $member_info->isUpdate(TRUE)->save();
                $status[] = $consumption->save($consumption_data);
                if (array_diff($status, [TRUE])) {
                    return ['code' => '-100', 'message' => config('message.')['-1']];
                }
                Db::commit();
                return ['code' => 200, 'message' => '成功'];
            } catch (Exception $e) {
                Db::rollback();
                return ['code' => '-100', 'message' => config('message.')['-1']];
            }

        }
    }

    /**
     * 线下付款
     *
     * @param Request $request
     * @param Cache $cache
     * @param Member $member
     * @param MemberRank $memberRank
     * @param MemberGrowthRecord $memberGrowthRecord
     *
     * @return array|mixed|\PDOStatement|string|\think\Model|null
     */
    public function offline_payment_info(Request $request, Cache $cache, Member $member, MemberRank $memberRank, MemberGrowthRecord $memberGrowthRecord)
    {
        if ($request::isPost()) {
            try {
                $code = $request::param();
                $field = ['member_id', 'phone', 'usable_money'];
                $flag = 0;
                if (isset($code['payment_code'])) {
                    if (empty($code['payment_code'])) {
                        return ['code' => '-100', 'message' => '付款码不可为空'];
                    }
                    if (count(explode(' ', $code['payment_code'])) > 1) {
                        $where = [['member_id', '=', $cache::get($code['payment_code'], 0)]];
                    } else {
                        $where = [['card_number', '=', $code['payment_code']]];
                        $flag = 1;
                    }
                    $user_info = $member->where($where)->append(['rank_name'])->field($field)->find();
                }
                if (empty($user_info)) {
                    return ['code' => '-100', 'message' => $flag ? '用户不存在,请核对后重新输入' : '付款码已失效,请重新输入'];
                }
                $growth_value = $memberGrowthRecord->where('member_id', $user_info['member_id'])->value('growth_value', 0);
                $discount = $memberRank::where(
                    [['min_points', '<=', $growth_value], ['max_points', '>=', $growth_value]]
                )->value('discount');
                $user_info['discount'] = ($discount > 0 ? $discount : 100) / 10;
                $user_info['code'] = 200;
                return $user_info;
            } catch (\Exception $e) {
                return ['code' => '-100', 'message' => '网络繁忙'];
            }
        }
        return $this->fetch();
    }

    //获得付款信息
    public function get_offline_payment_info(Member $member, Request $request, MemberGrowthRecord $memberGrowthRecord, MemberRank $memberRank, MemberCoupon $memberCoupon, MemberPacket $memberPacket)
    {
        if ($request::isPost()) {
            try {
                $client_store_id = Session::get('client_store_id');
                $data = $request::param();
                $price = $data['price'] = isset($data['price']) ? round($data['price'], 2) : 0;

                $member_info = $member->get($data['member_id']);
                if (empty($member_info)) {
                    return ['code' => '-100', 'message' => '用户不存在'];
                }
                if ($price > $member_info->usable_money) {
                    return ['code' => '-100', 'message' => '可用余额不足'];
                }
                if ($price >= 10000000) {
                    return ['code' => '-100', 'message' => '付款金额有误'];
                }
                $discount_price = round($price - $data['price'], 2);

                //保存优惠券查询对象
                $memberCouponTransfer = $memberCoupon->alias('a')
                    ->join('coupon b', 'a.coupon_id=b.coupon_id')
                    ->where(
                        [
                            ['a.member_id', '=', $data['member_id']],
                            ['a.status', '=', 0],
                            ['b.type', '=', 0],
                            ['b.classify_str', '=', $client_store_id],
                        ]
                    )
                    ->whereTime('a.end_time', '>=', date('Y-m-d H:i:s'))
                    ->field('a.full_subtraction_price,a.actual_price,a.member_coupon_id')
                    ->order('a.actual_price', 'desc');
                //保存红包查询对象
                $memberPacketTransfer = $memberPacket->alias('a')
                    ->where([['a.member_id', '=', $data['member_id']], ['a.status', '=', 0]])
                    ->whereTime('a.end_time', '>=', date('Y-m-d H:i:s'))
                    ->field('a.full_subtraction_price,a.actual_price,a.member_packet_id')
                    ->order('a.actual_price', 'desc');
                //计算不同组合优惠
                $discount_coupon1 = $memberCouponTransfer
                    ->where([['a.full_subtraction_price', '<=', $data['price']]])
                    ->find() ?: ['actual_price' => 0, 'full_subtraction_price' => 0, 'member_coupon_id' => 0];
                $red_packet_list1 = $memberPacketTransfer
                    ->where([['a.full_subtraction_price', '<=', $data['price'] - $discount_coupon1['actual_price']]])
                    ->find() ?: ['actual_price' => 0, 'full_subtraction_price' => 0, 'member_packet_id' => 0];
                $red_packet_list2 = $memberPacketTransfer
                    ->removeWhereField('a.full_subtraction_price')
                    ->where([['a.full_subtraction_price', '<=', $data['price']]])
                    ->find() ?: ['actual_price' => 0, 'full_subtraction_price' => 0, 'member_packet_id' => 0];
                $discount_coupon2 = $memberCouponTransfer
                    ->removeWhereField('a.full_subtraction_price')
                    ->where([['a.full_subtraction_price', '<=', $data['price'] - $red_packet_list2['actual_price']]])
                    ->find() ?: ['actual_price' => 0, 'full_subtraction_price' => 0, 'member_coupon_id' => 0];
                if ($discount_coupon1['actual_price'] + $red_packet_list1['actual_price'] > $discount_coupon2['actual_price'] + $red_packet_list2['actual_price']) {
                    $coupon = $discount_coupon1['actual_price'];
                    $member_coupon_id = $discount_coupon1['member_coupon_id'];
                    $packet = $red_packet_list1['actual_price'];
                    $member_packet_id = $red_packet_list1['member_packet_id'];
                } else {
                    $coupon = $discount_coupon2['actual_price'];
                    $member_coupon_id = $discount_coupon2['member_coupon_id'];
                    $packet = $red_packet_list2['actual_price'];
                    $member_packet_id = $red_packet_list2['member_packet_id'];
                }
                return [
                    'coupon'           => $coupon,
                    'member_coupon_id' => $member_coupon_id,
                    'packet'           => $packet,
                    'member_packet_id' => $member_packet_id,
                    'pay_price'        => round($data['price'] - $coupon - $packet, 2),
                    'discount'         => 1,
                    'price'            => $price,
                    'discount_price'   => $discount_price,
                    'discounts'        => $packet + $coupon + $discount_price,
                ];
            } catch
            (\Exception $e) {
                return ['code' => '-100', 'message' => '网络繁忙'];
            }
        }
    }

    //线下付款订单列表
    public function offline_payment_list(OrderAttachModel $orderAttach, CheckOrder $checkOrder)
    {
        $where = [
            'pay_channel' => 5,
            'store_id'    => Session::get('client_store_id'),
        ];
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $orderAttach = $orderAttach->whereTime(
                'create_time', [
                    !empty($param['start_date']) ? $param['start_date'] : '1968-01-01 00:00:00',
                    !empty($param['end_date']) ? $param['end_date'] : date('Y-m-d H:i:s', time()),
                ]
            );
        }
        $data = $orderAttach->with(
            [
                'member'     => function ($query) {
                    $query->field('nickname,member_id');
                },
                'orderGoods' => function ($query) {
                    $query->field('original_price,order_attach_id');
                },
            ]
        )->where($where)
            ->field(
                'order_attach_number,create_time,subtotal_price,subtotal_coupon_price,subtotal_share_platform_packet_price,order_attach_id,member_id'
            )
            ->order('pay_time', 'desc');
        if ($this->request->has('dc')) {
            $haderText = ['订单号', '支付方式', '买家', '下单时间', '总金额', '实收款', '使用店铺优惠券金额', '使用商家红包金额', '订单状态'];
            $dataArr = [];
            $data = $data->select();
            foreach ($data as $vv) {
                $dataArr[] = [
                    $vv['order_attach_number'],
                    '线下',
                    $vv['member']['nickname'],
                    $vv['create_time'],
                    $vv['order_goods'][0]['original_price'],
                    $vv['subtotal_price'],
                    $vv['subtotal_coupon_price'],
                    $vv['subtotal_share_platform_packet_price'],
                    '已完成',
                ];
            }
            $checkOrder->putCsv1('线下订单.xls', $dataArr, $haderText);

        } else {
            $data = $data->paginate(10);
        }
        return $this->fetch(
            '', [
                'data'   => $data,
                'render' => $data->render(),
            ]
        );
    }

    /**
     * 达达回调接口
     */
    public function dadaCallback(OrderAttachModel $orderAttach, OrderGoodsModel $orderGoods)
    {
//        $_data = [
//            'order_status' => 1,
//            'cancel_reason' => '',
//            'update_time' => '1543308263',
//            'cancel_from' => 0,
//            'dm_id' => 0,
//            'signature' => '42f04e12afd71f907bebad21746552ff',
//            'dm_name' => '',
//            'order_id' => '1677',
//            'client_id' => '277072678852566',
//            'dm_mobile' => '',
//        ];

        $_data = json_decode(file_get_contents("php://input"), TRUE);
        try {

            //订单状态(待接单＝1 待取货＝2 配送中＝3 已完成＝4 已取消＝5 已过期＝7 指派单=8 妥投异常之物品返回中=9 妥投异常之物品返回完成=10 创建达达运单失败=1000
            if ($_data['order_status'] == 2) {
                $orderGoodsArr = $orderGoods->where(['order_attach_id' => $_data['order_id']])->column(
                    'order_goods_id'
                );
                $orderGoodsStr = implode(',', $orderGoodsArr);

                // 更改订单状态已发货
                $orderAttach->where(['order_attach_id' => $_data['order_id']])
                    ->update(
                        ['status' => 2, 'deliver_time' => date('Y-m-d H:i:s'), 'client_id' => $_data['client_id']]
                    );

                // 查找同源商品更改状态
                Db::name('order_goods')
                    ->where('order_goods_id', 'in', $orderGoodsStr)
                    ->where('status', 'eq', '1.1')
                    ->update(['status' => '2.1']);

                unset($orderGoodsStr);
            }

            if ($_data['order_status'] == 4) {
                $orderGoodsArr = $orderGoods->where(['order_attach_id' => $_data['order_id']])->column(
                    'order_goods_id'
                );
                $orderGoodsStr = implode(',', $orderGoodsArr);

                // 更改订单状态已完成
                $orderAttach->where(['order_attach_id' => $_data['order_id']])->update(
                    ['status' => 3, 'deal_time' => date('Y-m-d H:i:s')]
                );

                // 查找同源商品更改状态
                Db::name('order_goods')
                    ->where('order_goods_id', 'in', $orderGoodsStr)
                    ->where('status', 'eq', '2.1')
                    ->update(['status' => '3.1']);

                unset($orderGoodsStr);
            }

            if ($_data['order_status'] == 7) {
                $orderGoodsArr = $orderGoods->where(['order_attach_id' => $_data['order_id']])->column(
                    'order_goods_id'
                );
                $orderGoodsStr = implode(',', $orderGoodsArr);

                // 更改订单状态待配送
                $orderAttach->where(['order_attach_id' => $_data['order_id']])->update(['status' => 1, 'dada' => 2]);

                // 查找同源商品更改状态
                Db::name('order_goods')
                    ->where('order_goods_id', 'in', $orderGoodsStr)
                    ->update(['status' => '1.1']);

                unset($orderGoodsStr);
            }


        } catch (\Exception $e) {
            file_put_contents('.1.txt', "{$e->getLine()}-{$e->getMessage()}\n", FILE_APPEND);
        }
    }

// public function statistics(OrderModel $order, OrderAttachModel $orderAttach, GoodsModel $goods)
// {
//     $storeId = Session::get('client_store_id');
//
//     // 有效订单总金额
//     $totalEffectiveOrders = $orderAttach
//         ->where(['store_id' => $storeId, 'status' => 3])
//         ->sum('subtotal_price');
//
//     // 收藏总数
//     $totalCollection = $goods
//         ->where(['store_id' => $storeId, 'review_status' => 1])
//         ->sum('collect_number');
//
//     // 订单数
//     $orderNumber = $orderAttach
//         ->where(['store_id' => $storeId, 'status' => 3])
//         ->count();
//
//     $totalSales = $goods
//         ->where(['store_id' => $storeId, 'review_status' => 1])
//         ->sum('sales_volume');
//
//     return $this->fetch('', [
//         'total_effective_orders' => $totalEffectiveOrders,
//         'total_collection' => $totalCollection,
//         'order_number' => $orderNumber,
//         'total_sales' => $totalSales
//     ]);
// }
//
// public function survey()
// {
//     return $this->fetch('', [
//
//     ]);
// }
//
// public function detail()
// {
//     return $this->fetch('', [
//
//     ]);
// }
//
// public function rank()
// {
//     return $this->fetch('', [
//
//     ]);
// }
}