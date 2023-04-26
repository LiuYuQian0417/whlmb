<?php
declare(strict_types = 1);

namespace app\computer\controller\order;

use app\computer\model\Express;
use app\computer\model\OrderAttach;
use app\computer\model\OrderGoods;
use app\computer\model\OrderGoodsRefund;
use app\computer\model\Store;
use app\computer\model\StoreAddress;
use app\computer\model\Take;
use app\computer\model\Goods as GoodsModel;
use app\computer\controller\BaseController;
use app\computer\model\Goods;
use common\lib\phpcode\QrCode;
use mrmiao\encryption\RSACrypt;
use function PHPSTORM_META\type;
use think\facade\Env;
use think\facade\Session;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Request;

/**
 * 订单说明
 * Class Explain
 * @package app\computer\controller\order
 */
class Explain extends BaseController
{
    
    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => ''],
    ];
    
    
    /**
     * 订单列表(快递,自提,同城)[非售后,非线下]
     * @param Request $request
     * @param GoodsModel $goodsModel
     * @param OrderAttach $orderAttach
     * @param Store $store
     * @param OrderGoods $orderGoods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderList(Request $request, GoodsModel $goodsModel, OrderAttach $orderAttach, Store $store, OrderGoods $orderGoods)
    {
        
        $args = $request::instance()->param();
        $args['member_id'] = Session::get('member_info')['member_id'];
        $where = [
            ['oa.member_id', '=', $args['member_id']],
            ['o.order_type', '<>', 5],
        ];
        $whereOrStr = '';
        // 关键词搜索
        if (($args['keyword'] ?? '') !== '') {
            $keyword = trim($args['keyword']);
            $whereOrStr = "oa.order_attach_number like '" . '%' . addslashes($keyword) . '%' . "'";
            $storeIdCol = $store
                ->where([
                    ['store_id', '>', 0],
                    ['store_name', 'like', '%' . $keyword . '%'],
                ])
                ->column('store_id');
            if ($storeIdCol) {
                $whereOrStr .= ' OR oa.store_id in (' . implode(',', $storeIdCol) . ')';
            }
            // 利用商品订单快照搜索
            $orderGoodsCol = $orderGoods
                ->where(
                    [
                        ['member_id', '=', $args['member_id']],
                        ['goods_name|spell_goods_name', 'like', '%' . $keyword . '%'],
                    ]
                )
                ->column('order_attach_id');
            if ($orderGoodsCol) {
                $whereOrStr .= ' OR oa.order_attach_id in (' . implode(',', array_unique($orderGoodsCol)) . ')';
            }
            // 存入用户订单搜索历史列表
            self::searchHistoryAct($args['member_id'], -1, $args['keyword'], 0);
        }
        // 单独查找某一状态
        if (!is_null($args['status'] ?? null) && $args['status'] !== '') {
            array_push($where, ['oa.status', 'in', $args['status'] ?: 0]);
        }
        //查找指定时间内订单
        switch ($args['share_time'] ?? '') {
            //近三个月
            case 1:
                $share_time = date('Y-m-d 23:59:59');
                $end_time = date('Y-m-d 00:00:00', strtotime('-3 month'));
                break;
            //今年
            case 2:
                $share_time = date('Y-01-01 00:00:00', strtotime('+1 year'));
                $end_time = date('Y-01-01 00:00:00');
                break;
            //去年
            case 3:
                $share_time = date('Y-01-01 00:00:00');
                $end_time = date('Y-01-01 00:00:00', strtotime('-1 year'));
                break;
            //前年
            case 4:
                $share_time = date('Y-01-01 00:00:00', strtotime('-1 year'));
                $end_time = date('Y-01-01 00:00:00', strtotime('-2 year'));
                break;
            //前前年
            case 5:
                $share_time = date('Y-01-01 00:00:00', strtotime('-2 year'));
                $end_time = date('Y-01-01 00:00:00', strtotime('-3 year'));
                break;
        }
        
        if ($share_time ?? null) {
            array_push($where, ['oa.create_time', '<=', $share_time]);
            array_push($where, ['oa.create_time', '>=', $end_time]);
        }
        $field = 'oa.order_attach_id,oa.is_all_refund,oa.order_attach_number,oa.subtotal_price,o.order_type,oa.pay_type,oa.dada,oa.distribution_type,oa.is_invoice,oa.create_time,oa.sale_after_status,oa.deal_time,oa.after_sale_times,
        oa.number,oa.subtotal_freight_price,oa.store_id,oa.status,oa.express_value,oa.express_number,o.consignee_name,INSERT(o.consignee_phone,4,4,\'****\') consignee_phone,o.address_province,o.address_city,o.address_area,o.address_street,o.address_details';
        $order = ['oa.update_time' => 'desc', 'order_attach_id' => 'desc'];
        $data = $orderAttach
            ->alias('oa')
            ->join('order o', 'o.order_id = oa.order_id')
            ->with([
                'storeList' => function ($query) {
                    $query->field('store_id,shop,store_name,logo');
                },
                'orderGoodsList',
            ])
            ->where([$where])
            ->where($whereOrStr)
            ->field($field)
            ->order($order)
            ->paginate(5, false, ['query' => $args]);
        if ($data->count()) {
            foreach ($data as $item) {
                if ($item->order_goods_list) {
                    // 是否含有退款退货商品标识
                    $item['has_refund'] = 0;
                    // 是否为拼团自提
                    $item['group_take'] = 0;
                    foreach ($item->order_goods_list as $_item) {
                        //处理订单退款操作
                        $_item['refund_operation'] = OrderGoods::refund_operation(
                            [
                                'status' => $_item['status'],
                                'pay_type' => $item['pay_type'],
                                'after_sale_time' => $item['after_sale_time'],
                                'order_goods_id' => $_item['order_goods_id'],
                            ]
                        );
                        // 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意) 5.3 商家同意退货(退货第一步的退款成功)
                        // 5.4 申请退货(退货第二步,填写物流发货) 5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
                        if (in_array($_item['status'], [5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7])) {
                            $item['has_refund'] = 1;
                        }
                        if ($_item['status'] == 2.2) {
                            $item['group_take'] = 1;
                        }
                    }
                }
            }
        }
        $recommend_list = recommend_list($goodsModel, 8, $args['member_id'], 1);
        return $this->fetch(
            '',
            [
                'code' => 0,
                'message' => config('message.')[0][0],
                'recommend_list' => $recommend_list,
                'result' => $data,
            ]
        );
        
    }
    
    
    /**
     * 售后订单列表
     * @param RSACrypt $crypt
     * @param OrderGoods $orderGoods
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function orderAfterSaleList(RSACrypt $crypt, OrderGoods $orderGoods)
    {
        $args = $crypt->request();
        $args['member_id'] = Session::get('member_info')['member_id'];
        $orderAfterSaleData = $orderGoods
            ->where(
                [
                    ['member_id', '=', $args['member_id']],
                    //4.2 退款成功 4.3 退货成功 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意)
                    //5.3 商家同意退货 5.4 申请退货(退货第二步,填写物流)
                    //5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
                    ['status', 'in', '4.2,4.3,5.1,5.2,5.3,5.4,5.5,5.6,5.7'],
                ]
            )
            ->with(
                [
                    'orderGoodsRefundList' => function ($query) {
                        $query->field(
                            'order_goods_refund_id,order_goods_id,status,create_time,order_goods_refund_number'
                        );
                    },
                    'orderGoodsRefundStore',
                ]
            )
            ->field(
                'order_goods_id,store_id,goods_name,goods_name_style,file,single_price,quantity,goods_attr,attr,status'
            )
            ->order(['update_time' => 'desc', 'order_goods_id' => 'desc'])
            ->paginate($orderGoods->pageLimits, false);
        return $this->fetch('', ['result' => $orderAfterSaleData]);
    }
    
    /**
     * 待评价订单列表
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function orderEvaluateList(OrderAttach $orderAttach)
    {
        $orderEvaluateData = $orderAttach
            ->where(
                [
                    ['member_id', '=', Session::get('member_info')['member_id']],
                    ['status', '=', 3],     // 3已完成
                ]
            )
            ->with(
                [
                    'orderGoodsEvaluate' => function ($query) {
                        $query->field('order_goods_id,order_attach_id,goods_name,file,attr,redo_status,status');
                    },
                ]
            )
            ->field('order_attach_id,store_id')
            ->order(['update_time' => 'desc', 'order_attach_id' => 'desc'])
            ->limit(5)
            ->select();
        if ($orderEvaluateData->count()) {
            foreach ($orderEvaluateData as $item) {
                if ($item->order_goods_evaluate) {
                    // 是否含有退款退货商品标识
                    $item['has_refund'] = 0;
                    foreach ($item->order_goods_evaluate as $_item) {
                        // 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意) 5.3 商家同意退货(退货第一步的退款成功)
                        // 5.4 申请退货(退货第二步,填写物流发货) 5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
                        if (in_array($_item['status'], [5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7])) {
                            $item['has_refund'] = 1;
                        }
                    }
                }
            }
        }
        return $this->fetch('', ['result' => $orderEvaluateData]);
    }
    
    /**
     * 线下订单列表
     * @param Request $request
     * @param OrderAttach $orderAttach
     * @param Goods $goodsModel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderUnderLineList(Request $request, OrderAttach $orderAttach, Goods $goodsModel)
    {
        $args = $request::get();
        $args['member_id'] = Session::get('member_info')['member_id'];
        $where = [
            ['oa.member_id', '=', $args['member_id']],
            ['o.order_type', '=', 5],
        ];
        //查找指定时间内订单
        switch ($args['share_time'] ?? '') {
            //近三个月
            case 1:
                $share_time = date('Y-m-d 00:00:00', strtotime('-3 month'));
                break;
            //今年
            case 2:
                $share_time = date('Y-01-01 00:00:00');
                break;
            //去年
            case 3:
                $share_time = date('Y-01-01 00:00:00', strtotime('-1 year'));
                break;
            //前年
            case 4:
                $share_time = date('Y-01-01 00:00:00', strtotime('-2 year'));
                break;
            //前前年
            case 5:
                $share_time = date('Y-01-01 00:00:00', strtotime('-3 year'));
                break;
        }
        if ($share_time ?? null) {
            array_push($where, ['oa.create_time', '>=', $share_time]);
        }
        $orderUnderLineData = $orderAttach
            ->alias('oa')
            ->where($where)
            ->join('order o', 'o.order_id = oa.order_id')
            ->join('store s', 's.store_id = oa.store_id')
            ->field('oa.order_attach_id,s.store_name,o.total_price,order_attach_number,oa.store_id,oa.status')
            ->order(['oa.update_time' => 'desc', 'order_attach_id' => 'desc'])
            ->paginate($orderAttach->pageLimits, false);
        $recommend_list = recommend_list($goodsModel, 8, $args['member_id'], 1);
        return $this->fetch(
            '',
            [
                'result' => $orderUnderLineData,
                'recommend_list' => $recommend_list,
            ]
        );
    }
    
    /**
     * 输入/输出/删除用户订单搜索历史数据
     * @param $member_id
     * @param $index int 正序序号 -1 不执行删除操作 其他按序号删除 all为全删
     * @param $data string 历史数据
     * @param int $outOrin 1输出 0其他操作
     * @return array|mixed
     */
    private function searchHistoryAct($member_id, $index = -1, $data = '', $outOrin = 1)
    {
        // 查询会员搜索订单历史
        $historyList = Cache::get('orderSearch_' . $member_id, []);
        if ($outOrin) {
            return $historyList;
        }
        if ($index !== -1) {
            if ($index == 'all') {
                return Cache::rm('orderSearch_' . $member_id);
            }
            unset($historyList[$index]);
        }
        if ($data) {
            array_unshift($historyList, $data);
        }
        if ($historyList) {
            array_unique($historyList);
            $historyList = array_slice($historyList, 0, 10);
            Cache::set('orderSearch_' . $member_id, $historyList);
        }
        return $historyList;
    }
    
    /**
     * 订单详情[非线下]
     * @param Request $request
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderDetails(Request $request, OrderAttach $orderAttach)
    {
        $args = $request::instance()->param();
        $orderAttach->valid($args, 'orderDetails');
        $where = [
            ['oa.order_attach_id', '=', $args['order_attach_id']],
        ];
        
        // 订单附表
        $field = 'oa.order_attach_id,oa.is_all_refund,oa.order_attach_number,oa.store_id,oa.subtotal_price,oa.distribution_type,oa.pay_type,oa.is_invoice,oa.deliver_time,oa.update_time,
        oa.take_id,oa.express_value,oa.express_number,oa.subtotal_freight_price,oa.subtotal_coupon_price,oa.number,deal_time,
        oa.subtotal_back_integral,oa.status,oa.group_activity_attach_id,oa.cut_activity_id,oa.take_time,oa.take_code,
        oa.subtotal_share_platform_coupon_price,oa.sale_after_status,oa.message,oa.pay_time,oa.subtotal_share_platform_packet_price,
        oa.distribution_tel,oa.dada,oa.client_id,oa.deliveryFee,after_sale_times';
        // 订单主表
        $field .= ',o.total_packet_price,o.order_type,o.total_cut_amount,o.consignee_name,o.consignee_phone,
        o.address_province,o.address_city,o.address_area,o.address_street,o.address_details,o.create_time';
        $orderAttachData = $orderAttach
            ->alias('oa')
            ->join('order o', 'o.order_id = oa.order_id')
            ->with(['storeList', 'orderGoodsDetails'])
            ->append(['remaining_time', 'after_sale_time'])
            ->hidden(['after_sale_times'])
            ->where($where)
            ->field($field);
        // 订单类型为拼团并且含有拼团附表信息id
        $temporary = $orderAttachData->find();
        if (!$temporary) {
            return header("Location: /pc2.0/order/order_list");
        }
        if ($temporary->order_type == 2 && $temporary->group_activity_attach_id) {
            // 拼团活动主表
            $gaField = 'ga.status as group_activity_status';
            $orderAttachData = $orderAttachData
                ->join(
                    'group_activity_attach gaa',
                    'gaa.group_activity_attach_id = oa.group_activity_attach_id',
                    'left'
                )
                ->join('group_activity ga', 'ga.group_activity_id = gaa.group_activity_id')
                ->field($gaField);
        }
        // 订单类型为砍价并且有砍价主表信息id
        if ($temporary->order_type == 3 && $temporary->cut_activity_id) {
            // 砍价活动主表
            $caField = 'ca.status as cut_activity_status';
            $orderAttachData = $orderAttachData
                ->join('cut_activity ca', 'ca.cut_activity_id = oa.cut_activity_id')
                ->field($caField);
        }
        // 配送方式为自提并且含有店铺自提点信息
        if ($temporary->distribution_type == 2 && $temporary->take_id) {
            // 店铺自提点
            $tField = 't.take_name,t.province as take_province,t.city as take_city,t.area as take_area,
            t.street as take_street,t.address as take_address,t.contacts_phone take_contacts_phone,t.start_hours,t.end_hours';
            $orderAttachData = $orderAttachData
                ->join('take t', 't.take_id = oa.take_id', 'left')
                ->field($tField);
        }
        $result = $orderAttachData->find();
        //商品折扣价
        $result['subtotal_original_price'] = $result['subtotal_discount_price'] = $result['diff_price'] = 0;
        //如果是快递邮寄订单并且已发货
        $express_info = null;
        if ($result->distribution_type == 3 && !empty($result['express_value'])) {
            $config = config('user.')['common']['express'];
            // 快递100
            $data['customer'] = $config['customer'];
            $data['param'] = json_encode(['com' => $result['express_value'], 'num' => $result['express_number']]);
            $data["sign"] = strtoupper(md5($data['param'] . $config['sign'] . $data['customer']));
            $express_info = express_post($data, 'http://poll.kuaidi100.com/poll/query.do');
            if (!empty($express_info['data'])) {
                $express_info_data = $express_info['data'];
                array_multisort($express_info_data, array_column($express_info['data'], 'time'));
                $express_info['data'] = $express_info_data;
            }
        }
        foreach ($result->order_goods_details as $item) {
            // 是否含有退款退货商品标识
            $result['has_refund'] = 0;
            // 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意) 5.3 商家同意退货(退货第一步的退款成功)
            // 5.4 申请退货(退货第二步,填写物流发货) 5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
            if (in_array($item['status'], [5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7])) {
                $result['has_refund'] = 1;
            }
            //商品原价
            $result['subtotal_original_price'] += $item['original_price'] * $item['quantity'];
            //商品总的折扣价
            $result['subtotal_discount_price'] += $item['discount_price'] * $item['quantity'];
            // 活动差(折扣不计)
            $result['diff_price'] += ($item['original_price'] - $item['single_price'] - $item['discount_price']) * $item['quantity'];
        }
        //处理订单退款操作方式
        foreach ($result['order_goods_details'] as &$v) {
            $v['refund_operation'] = OrderGoods::refund_operation(
                [
                    'status' => $v['status'],
                    'pay_type' => $result['pay_type'],
                    'after_sale_time' => $result['after_sale_time'],
                    'order_goods_id' => $v['order_goods_id'],
                ]
            );
        }
        // 将店铺优惠券和平台优惠券金额合并
        $result->subtotal_coupon_price += $result->subtotal_share_platform_coupon_price;
        return $this->fetch('', ['result' => $result, 'express_info' => $express_info]);
        
    }
    
    /**
     * 申请退款页面展示
     * @param Request $request
     * @param OrderGoods $orderGoods
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function apply_for_after_sale(Request $request, OrderGoods $orderGoods)
    {
        
        $args = $request::instance()->param();
        $orderGoods->valid($args, 'revokeApply');
        $result = $orderGoods
            ->alias('o_g')
            ->join('order_attach o_a', 'o_g.order_attach_id=o_a.order_attach_id')
            ->where([['o_g.order_goods_id', '=', $args['order_goods_id']]])
            ->field(
                'o_g.subtotal_price,o_g.order_attach_id,o_g.sum_alter_freight_price,o_g.sub_fullSub_price,o_g.goods_name,o_g.file,o_g.goods_id,o_g.attr,o_g.quantity,o_g.order_goods_id,o_g.single_price,o_g.quantity,o_g.sub_freight_price,o_g.sub_share_platform_coupon_price,o_g.sub_share_shop_coupon_price,o_g.subtotal_share_platform_packet_price,o_g.sum_alter_goods_price,o_a.distribution_type,o_a.status'
            )
            ->find();
        $result['max_total'] = fmtPrice($result['subtotal_price']
            + $result['sum_alter_goods_price']  // 发货前可以退运费,发货后不可以退运费
            - ($result['order_attach']['status'] >= 2 ?
                $result['sub_freight_price'] : 0)
            - $result['sub_fullSub_price']);
        $result['sub_freight_price'] = $result['order_attach']['status'] >= 2 ? 0 : $result['sub_freight_price'];
        return $this->fetch('', ['result' => $result]);
    }
    
    
    /**
     * 获得条形码
     * @param Request $request
     * @throws \BCGArgumentException
     * @throws \BCGDrawException
     */
    public function getBarCode(Request $request)
    {
        require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
        $data = $request::get();
        QrCode::getBarCode($data['text']);
    }
    
    /**
     * 获得二维码
     * @param Request $request
     */
    public function getQrCode(Request $request)
    {
        require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
        $data = $request::get();
        QrCode::getQrCode('自提二维码', $data['text']);
    }
    
    /**
     * 线下订单详情
     * @param Request $request
     * @param OrderAttach $orderAttach
     * @return mixed
     */
    public function orderUnderLineDetails(Request $request, OrderAttach $orderAttach)
    {
        try {
            $args = $request::get();
            $where = [
                ['oa.order_attach_id', '=', $args['order_attach_id']],
            ];
            $orderUnderLineDetailsData = $orderAttach
                ->alias('oa')
                ->where($where)
                ->with(['storeList'])
                ->join('order o', 'o.order_id = oa.order_id')
                ->field(
                    'oa.order_attach_id,oa.status,o.total_price,o.total_coupon_price,o.total_packet_price,subtotal_back_integral,oa.subtotal_coupon_price,oa.subtotal_price,
                oa.store_id,oa.order_attach_number,oa.create_time,oa.subtotal_share_platform_packet_price'
                )
                ->find();
            if (empty($orderUnderLineDetailsData)) {
                return header("Location: /pc2.0/order/orderunderlinelist");
            }
            return $this->fetch('', ['result' => $orderUnderLineDetailsData]);
        } catch (\Exception $e) {
            //            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
        }
    }
    
    /**
     * 退货/退款详情
     * @param Request $request
     * @param OrderGoodsRefund $orderGoodsRefund
     * @param Take $take
     * @param Express $express
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function refundDetails(Request $request, OrderGoodsRefund $orderGoodsRefund, Take $take, Express $express, StoreAddress $storeAdress)
    {
        $args = $request::get();
        $orderGoodsRefund->valid($args, 'refundDetails');
        $refundInfo = $orderGoodsRefund
            ->alias('ogr')
            ->join('order_goods og', 'og.order_goods_id = ogr.order_goods_id')
            ->join('order_attach oa', 'oa.order_attach_id = og.order_attach_id')
            ->join('store s', 's.store_id = og.store_id')
            ->where([['ogr.order_goods_id', '=', $args['order_goods_id']]])
            ->field(
                'ogr.update_time,ogr.type,ogr.status,refuse_reason,og.order_goods_id,s.phone,ogr.reason,ogr.refund_amount,ogr.create_time,oa.order_attach_id,s.store_id,ogr.dispose_time,ogr.deliver_time,ogr.finish_time,
                og.goods_name,og.goods_name_style,og.file,og.attr,oa.pay_channel,oa.pay_type,oa.deal_time,oa.after_sale_times,oa.sale_after_status,oa.distribution_type,ogr.express_value,ogr.express_number,og.single_price,og.quantity,
                ogr.express_name,date_format(ogr.create_time,"%Y-%m-%d %H:%i") as create_time_format,og.status as order_goods_status,og.sub_freight_price,
                ogr.order_goods_refund_number,ogr.order_goods_refund_id,date_format(ogr.update_time,"%Y年%m月%d日 %H:%i") as update_time_format,s.store_name,s.address,s.store_id,
                ogr.return_type,ogr.take_id'
            )
            ->hidden(['create_time', 'after_sale_times'])
            ->append(['remaining_time', 'after_sale_time'])
            ->find();
        //   查找退货地址
        $refundAdress = $storeAdress->where([
                ['store_id', '=', $refundInfo['store_id']],
                ['return_address', '=', 1],
                ['default_return_address', '=', 1],
            ])->field('address_location_text,telephone,phone_number')->find() ?? $storeAdress->where([
                ['store_id', '=', $refundInfo['store_id']],
                ['return_address', '=', 1],
            ])->field('address_location_text,telephone,phone_number')->find();
        //        dump($refundAdress);
        if (!empty($refundAdress)) {
            $refundInfo['address'] = $refundAdress['address_location_text'];
            $refundInfo['phone'] = $refundAdress['telephone'] ?? $refundAdress['phone_number'];
            
        }
        //如果退款订单处在发货阶段  查询物流和自提点信息
        if ($refundInfo->order_goods_status == 5.3) {
            /*******************************门店自提************************************/
            if (isset($args['lng']) && isset($args['lat'])) {
                // 默认条件
                $condition = [['store_id', '=', $refundInfo['store_id']], ['status', '=', 1]];
                $refundInfo['take_list'] = $take
                    ->where($condition)
                    ->field(
                        'take_id,take_name,contacts_phone,start_hours,end_hours,address,lat,lng,round(st_distance(point(lng,lat),point(' . $args['lng'] . ',' . $args['lat'] . '))*111.195,3) AS distance'
                    )
                    ->order('distance', 'asc')
                    ->select();
            }
            /*******************************快递列表************************************/
            // 获取缓存数据
            $expressData = Cache::get('pc_expressListData', '');
            if ($expressData === '' || empty(unserialize($expressData))) {
                // 查询物流列表
                $expressData = $express
                    ->where(
                        [
                            ['express_id', '>', 0],
                        ]
                    )
                    ->field('delete_time', true)
                    ->select()
                    ->toArray();
                //                $expressArrange = [];
                //                foreach ($expressData as $key => $value)
                //                {
                //                    $expressArrange[$value['brand_first_char']]['prefix'] = $value['brand_first_char'];
                //                    $expressArrange[$value['brand_first_char']]['list'][] = $value;
                //                }
                sort($expressData);
                Cache::set('pc_expressListData', serialize($expressData));
            }
            $refundInfo['express_list'] = is_array($expressData) ? $expressData : unserialize($expressData);
        }
        if (!$refundInfo) {
            exception(config('message.')[-11][-3], -3);
        }
        $refundInfo['take_details'] = $refundInfo['express_details'] = null;
        // 查询物流信息
        if ($refundInfo['express_value'] && $refundInfo['express_number']) {
            $config = config('user.')['common']['express'];
            // 快递100
            $data['customer'] = $config['customer'];
            $data['param'] = json_encode(
                ['com' => $refundInfo['express_value'], 'num' => $refundInfo['express_number']]
            );
            $data["sign"] = strtoupper(md5($data['param'] . $config['sign'] . $data['customer']));
            $result = express_post($data, 'http://poll.kuaidi100.com/poll/query.do');
            // 取第一条详情信息
            $refundInfo['express_details'] = $result['data'][0] ?? null;
        }
        // 退货至门店自提
        if ($refundInfo['return_type'] == 2 && !is_null($refundInfo['take_id'])) {
            $refundInfo['take_details'] = $take
                ->where(
                    [
                        ['take_id', '=', $refundInfo['take_id']],
                        ['status', '=', 1],
                    ]
                )
                ->field('take_name,contacts_phone,address')
                ->find();
        }
        $refundInfo['refund_operation'] = OrderGoods::refund_operation(
            [
                'status' => $refundInfo['order_goods_status'],
                'pay_type' => $refundInfo['pay_type'],
                'after_sale_time' => $refundInfo['after_sale_time'],
                'order_goods_id' => $refundInfo['order_goods_id'],
            ]
        );
        return $this->fetch('', ['result' => $refundInfo]);
    }
    
    
    /*******废弃********/
    
    //
    //    /**
    //     * 拼团信息列表
    //     * @param RSACrypt $crypt
    //     * @return mixed
    //     */
    //    public function groupMsgList(RSACrypt $crypt)
    //    {
    //        try
    //        {
    //            $args = $crypt->request();
    //            $redisService = Cache::handler();
    //            $redisService->select(1);
    //            $begin = strtotime(date('Y-m-d'));
    //            $prefix = Config::get('cache.default')['prefix'];
    //            $list = $redisService->zRevRangeByScore(
    //                $prefix . 'goods_' . $args['goods_id'],
    //                strval($begin + 86400) . '2',
    //                strval($begin) . '0',
    //                ['withscores' => TRUE]
    //            );
    //            $result = [];
    //            if (count($list))
    //            {
    //                $text = ['已开团', '已参团', '开团成功'];
    //                foreach ($list as $key => $item)
    //                {
    //                    $item = strval($item);
    //                    $keyArr = explode('@_@', $key);
    //                    if ($keyArr)
    //                    {
    //                        // 去掉拼团活动id标识,余用户昵称
    //                        $group_activity_id = array_shift($keyArr);
    //                        $status = $item[strlen($item) - 1];
    //                        array_push(
    //                            $result,
    //                            [
    //                                'group_activity_id' => $group_activity_id,
    //                                'status'            => $status,
    //                                'cTime'             => substr($item, 0, strlen($item) - 1),
    //                                'user'              => implode('_', $keyArr),
    //                                'state'             => $text[$status],
    //                            ]
    //                        );
    //                    }
    //                }
    //            }
    //            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'result' => $result], TRUE);
    //        } catch (\Exception $e)
    //        {
    //            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
    //        }
    //    }
    //
    //    /**
    //     * 用户搜索订单历史列表
    //     * @param RSACrypt $crypt
    //     * @return mixed
    //     */
    //    public function searchHistoryList(RSACrypt $crypt)
    //    {
    //        try
    //        {
    //            $args = $crypt->request();
    //            $args['member_id'] = request(0)->mid;
    //            $data = self::searchHistoryAct($args['member_id']);
    //            return $crypt->response(
    //                ['code' => 0, 'message' => config('message.')[0][0], 'result' => $data ?: []],
    //                TRUE
    //            );
    //        } catch (\Exception $e)
    //        {
    //            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
    //        }
    //    }
    //
    //
    //    /**
    //     * 清除用户搜索订单历史数据
    //     * @param RSACrypt $crypt
    //     * @return mixed
    //     */
    //    public function destroySearchHistory(RSACrypt $crypt)
    //    {
    //        try
    //        {
    //            $args = $crypt->request();
    //            $args['member_id'] = request(0)->mid;
    //            self::searchHistoryAct($args['member_id'], $args['index'], '', 0);
    //            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], TRUE);
    //        } catch (\Exception $e)
    //        {
    //            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
    //        }
    //    }
}