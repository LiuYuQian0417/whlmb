<?php
declare(strict_types = 1);

namespace app\interfaces\controller\order;

use app\common\model\IntegralOrder;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\OrderGoodsRefund;
use app\common\model\Store;
use app\common\model\StoreAddress;
use app\common\model\Take;
use app\common\service\OrderAct;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Config;
use app\common\model\Invoice as InvoiceModel;
use think\facade\Env;

/**
 * 订单说明
 * Class Explain
 * @package app\interfaces\controller\order
 */
class Explain extends BaseController
{
    /**
     * 订单列表(快递,自提,同城)[非售后,非线下]
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @param Store $store
     * @param OrderGoods $orderGoods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderList(RSACrypt $crypt,
                              OrderAttach $orderAttach,
                              Store $store,
                              OrderGoods $orderGoods)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderAttach->valid($args, 'orderList');
        $where = [
            ['oa.member_id', '=', $args['member_id']],
        ];
        // 配送方式
        if (array_key_exists('distribution_type', $args) && $args['distribution_type']) {
            array_push($where, ['oa.distribution_type', 'in', $args['distribution_type'] ?: 1]);
        }
        $whereOrStr = '';
        // 关键词搜索
        if ($args['keyword'] !== '') {
            $keyword = trim($args['keyword']);
            $whereOrStr = "oa.order_attach_number like '" . '%' . $keyword . '%' . "'";
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
                ->where([
                    ['member_id', '=', $args['member_id']],
                    ['goods_name|spell_goods_name', 'like', '%' . $keyword . '%'],
                ])
                ->column('order_attach_id');
            if ($orderGoodsCol) {
                $whereOrStr .= ' OR oa.order_attach_id in (' . implode(',', array_unique($orderGoodsCol)) . ')';
            }
            // 存入用户订单搜索历史列表
            self::searchHistoryAct($args['member_id'], -1, $args['keyword'], 0);
        }
        // 单独查找某一状态
        if (!is_null($args['status']) && $args['status'] !== '') {
            if ($args['status'] == 5) {
                // 查询当前用户售后中订单商品
                $asg = $orderGoods
                    ->where([
                        ['member_id', '=', $args['member_id']],
                        ['status', 'between', ['4.3', '6']],
                    ])
                    ->column('order_attach_id');
                if (!empty($asg)) {
                    array_push($where, ['oa.order_attach_id', 'in', implode(',', array_unique($asg))]);
                } else {
                    $where = [['oa.order_attach_id', '<', 0]];
                }
            } else {
                array_push($where, ['oa.status', 'in', $args['status'] ?: 0]);
            }
        }
        $field = 'oa.order_attach_id,oa.order_attach_number,oa.subtotal_price,o.order_type,oa.dada,oa.distribution_type,
            oa.number,oa.subtotal_freight_price,oa.store_id,oa.status,oa.express_value,oa.express_number,oa.create_time,
            oa.pay_type,oa.is_invoice';
        $order = ['oa.update_time' => 'desc', 'order_attach_id' => 'desc'];
        $data = $orderAttach
            ->alias('oa')
            ->join('order o', 'o.order_id = oa.order_id')
            ->with(['storeList', 'orderGoodsList'])
            ->where([$where])
            ->where($whereOrStr)
            ->field($field)
            ->order($order)
            ->paginate($orderAttach->pageLimits, false);
        if ($data->count()) {
            foreach ($data as $item) {
                if ($item->order_goods_list) {
                    // 是否含有退款退货商品标识
                    $item['has_refund'] = 0;
                    // 是否为拼团自提
                    $item['group_take'] = 0;
                    foreach ($item->order_goods_list as $_item) {
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
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $data,
        ], true);
    }


    /**
     * 售后订单列表
     * @param RSACrypt $crypt
     * @param OrderGoods $orderGoods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderAfterSaleList(RSACrypt $crypt,
                                       OrderGoods $orderGoods)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderAfterSaleData = $orderGoods
            ->alias('og')
            ->where([
                ['og.member_id', '=', $args['member_id']],
                //4.2 退款成功 4.3 退货成功 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意)
                //5.3 商家同意退货 5.4 申请退货(退货第二步,填写物流)
                //5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
                ['og.status', 'in', '4.2,4.3,5.1,5.2,5.3,5.4,5.5,5.6,5.7'],
            ])
            ->with(['orderGoodsRefundList', 'orderGoodsRefundStore'])
            ->join('order_attach oa', 'oa.order_attach_id = og.order_attach_id')
            ->field('og.order_goods_id,og.store_id,og.goods_name,og.goods_name_style,og.file,
        og.single_price,og.quantity,og.goods_attr,og.attr,og.status,oa.distribution_type,oa.status as order_attach_status')
            ->order(['og.update_time' => 'desc', 'og.order_goods_id' => 'desc'])
            ->paginate($orderGoods->pageLimits, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $orderAfterSaleData,
        ], true);
    }

    /**
     * 待评价订单列表
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderEvaluateList(RSACrypt $crypt,
                                      OrderAttach $orderAttach)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderEvaluateData = $orderAttach
            ->where([
                ['member_id', '=', $args['member_id']],
                ['status', '=', 3],     // 3已完成
            ])
            ->with(['storeList', 'orderGoodsEvaluate'])
            ->field('order_attach_id,store_id')
            ->order(['update_time' => 'desc', 'order_attach_id' => 'desc'])
            ->paginate($orderAttach->pageLimits, false);
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
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $orderEvaluateData,
        ], true);
    }

    /**
     * 线下订单列表
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderUnderLineList(RSACrypt $crypt,
                                       OrderAttach $orderAttach)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $where = [
            ['oa.member_id', '=', $args['member_id']],
            ['o.order_type', '=', 5],
        ];
        $orderUnderLineData = $orderAttach
            ->alias('oa')
            ->where($where)
            ->join('order o', 'o.order_id = oa.order_id')
            ->join('store s', 's.store_id = oa.store_id')
            ->field('oa.order_attach_id,s.store_name,o.total_price')
            ->order(['oa.update_time' => 'desc', 'order_attach_id' => 'desc'])
            ->paginate($orderAttach->pageLimits, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $orderUnderLineData,
        ], true);
    }

    /**
     * 用户搜索订单历史列表
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function searchHistoryList(RSACrypt $crypt)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $data = self::searchHistoryAct($args['member_id']);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $data ?: [],
        ], true);
    }

    /**
     * 清除用户搜索订单历史数据
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function destroySearchHistory(RSACrypt $crypt)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        self::searchHistoryAct($args['member_id'], $args['index'], '', 0);
        return $crypt->response([
            'code' => 0,
            'message' => '清除成功',
        ], true);
    }

    /**
     * 输入/输出/删除用户订单搜索历史数据
     * @param $member_id
     * @param $index int 正序序号 -1 不执行删除操作 其他按序号删除 all为全删
     * @param $data string 历史数据
     * @param int $outOrin 1输出 0其他操作
     * @return array|mixed
     */
    private function searchHistoryAct($member_id,
                                      $index = -1,
                                      $data = '',
                                      $outOrin = 1)
    {
        // 查询会员搜索订单历史
        $historyList = Cache::get('orderSearch_' . $member_id, []);
        if ($outOrin) {
            return $historyList;
        }
        if ($index !== -1) {
            if ($index == 'all') return Cache::rm('orderSearch_' . $member_id);
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
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderDetails(RSACrypt $crypt,
                                 OrderAttach $orderAttach)
    {
        $args = $crypt->request();
        $orderAttach->valid($args, 'orderDetails');
        $where = [
            ['oa.order_attach_id', '=', $args['order_attach_id']],
        ];
        // 订单附表
        $field = 'oa.order_attach_id,oa.order_attach_number,oa.store_id,oa.subtotal_price,oa.distribution_type,oa.pay_type,
            oa.take_id,oa.express_value,oa.express_number,oa.subtotal_freight_price,oa.subtotal_coupon_price,oa.number,deal_time,
            oa.subtotal_back_integral,oa.status,oa.group_activity_attach_id,oa.cut_activity_id,oa.take_time,oa.take_code,
            oa.subtotal_share_platform_coupon_price,oa.sale_after_status,oa.message,oa.subtotal_share_platform_packet_price,
            oa.distribution_tel,oa.dada,oa.client_id,oa.deliveryFee,after_sale_times,oa.is_invoice,oa.message,oa.order_attach_number';
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
        if (is_null($temporary)) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单不存在',
            ], true);
        }
        if ($temporary->order_type == 2 && $temporary->group_activity_attach_id) {
            // 拼团活动主表
            $gaField = 'ga.status as group_activity_status';
            $orderAttachData = $orderAttachData
                ->join('group_activity_attach gaa', 'gaa.group_activity_attach_id = oa.group_activity_attach_id', 'left')
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
                t.street as take_street,t.address as take_address,t.lng as take_lng,t.lat as take_lat';
            $orderAttachData = $orderAttachData
                ->join('take t', 't.take_id = oa.take_id', 'left')
                ->field($tField);
        }
        $result = $orderAttachData->find();
        $result['active_price'] = $result['subtotal_original_price'] = $result['subtotal_discount_price'] = 0;
        foreach ($result->order_goods_details as $item) {
            $sum_alter = $item['sum_alter_goods_price'];    // 里面已经含有快递改价
            $item['subtotal_price'] = fmtPrice($item['subtotal_price'] += $sum_alter);
            // 是否含有退款退货商品标识
            $result['has_refund'] = 0;
            // 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意) 5.3 商家同意退货(退货第一步的退款成功)
            // 5.4 申请退货(退货第二步,填写物流发货) 5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
            if (in_array($item['status'], [5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7])) {
                $result['has_refund'] = 1;
            }
            $result['active_price'] += $item['active_price'] = !in_array($result->order_type, [1, 5]) ?
                fmtPrice(abs($item['original_price'] - $item['single_price']) * $item['quantity']) : 0;
            $result['subtotal_original_price'] += $item['original_price'] * $item['quantity'] + $item['sum_alter_goods_price'] - $item['sum_alter_freight_price'];
            $result['subtotal_discount_price'] += $item['discount_price'] * $item['quantity'];
        }
        $result['subtotal_original_price'] = fmtPrice($result['subtotal_original_price']);
        $result['active_price'] = fmtPrice($result['active_price']);
        $result['subtotal_discount_price'] = fmtPrice($result['subtotal_discount_price']);
        if (!self::$oneOrMore) {
            // 单店铺更改店铺联系方式
            Env::load(Env::get('app_path') . 'common/ini/.config');
            $result->store_list->phone = Env::get('phone', '');
        }

        // 将店铺优惠券和平台优惠券金额合并
        $result->subtotal_coupon_price += $result->subtotal_share_platform_coupon_price;
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }


    /**
     * 退单显示金额
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function refundMoney(RSACrypt $crypt,
                                OrderGoods $orderGoods)
    {
        $args = $crypt->request();
        $orderGoods->valid($args, 'revokeApply');
        $result = $orderGoods
            ->alias('o_g')
            ->join('order_attach o_a', 'o_g.order_attach_id=o_a.order_attach_id')
            ->where([['o_g.order_goods_id', '=', $args['order_goods_id']]])
            ->field(
                'o_g.subtotal_price,o_g.order_attach_id,o_g.sum_alter_freight_price,o_g.sub_fullSub_price,o_g.goods_name,o_g.file,o_g.goods_id,o_g.attr,o_g.quantity,o_g.order_goods_id,o_g.single_price,o_g.quantity,o_g.sub_freight_price,o_g.sub_share_platform_coupon_price,o_g.sub_share_shop_coupon_price,o_g.subtotal_share_platform_packet_price,o_g.sum_alter_goods_price,o_a.distribution_type,o_a.status'
            )
            ->find();
        $data = [];
        $data['max_total'] = fmtPrice($result['subtotal_price']
            + $result['sum_alter_goods_price']  // 发货前可以退运费,发货后不可以退运费
            - ($result['order_attach']['status'] >= 2 ?
                $result['sub_freight_price'] : 0)
            - $result['sub_fullSub_price']);
        $data['sub_freight_price'] = $result['order_attach']['status'] >= 2 ? 0 : $result['sub_freight_price'];
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $data,
        ], true);
    }

    /**
     * 线下订单详情
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderUnderLineDetails(RSACrypt $crypt,
                                          OrderAttach $orderAttach)
    {
        $args = $crypt->request();
        $where = [
            ['oa.order_attach_id', '=', $args['order_attach_id']],
        ];
        $orderUnderLineDetailsData = $orderAttach
            ->alias('oa')
            ->where($where)
            ->with(['storeList'])
            ->join('order o', 'o.order_id = oa.order_id')
            ->field('oa.order_attach_id,oa.status,o.total_price,o.total_coupon_price,
            o.total_packet_price,subtotal_back_integral,oa.store_id,oa.order_attach_number,
            oa.create_time,oa.subtotal_share_platform_packet_price')
            ->find();
        if (!$orderUnderLineDetailsData) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单不存在',
            ], true);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $orderUnderLineDetailsData,
        ], true);

    }

    /**
     * 退货/退款详情
     * @param RSACrypt $crypt
     * @param OrderGoodsRefund $orderGoodsRefund
     * @param Take $take
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function refundDetails(RSACrypt $crypt,
                                  OrderGoodsRefund $orderGoodsRefund, StoreAddress $storeAdress,
                                  Take $take)
    {
        $args = $crypt->request();
        $orderGoodsRefund->valid($args, 'refundDetails');
        $refundInfo = $orderGoodsRefund
            ->alias('ogr')
            ->join('order_goods og', 'og.order_goods_id = ogr.order_goods_id')
            ->join('order_attach oa', 'oa.order_attach_id = og.order_attach_id')
            ->join('store s', 's.store_id = og.store_id')
            ->where([['ogr.order_goods_id', '=', $args['order_goods_id']]])
            ->field('ogr.status,refuse_reason,og.order_goods_id,s.phone,ogr.reason,ogr.type,
            ogr.refund_amount,ogr.create_time,oa.order_attach_id,s.store_id,og.goods_name,og.subtotal_price,
            og.goods_name_style,og.file,og.attr,oa.pay_channel,ogr.express_value,ogr.express_number,oa.status as order_attach_status,
            og.single_price,og.quantity,ogr.express_name,date_format(ogr.create_time,"%Y-%m-%d %H:%i") 
            as create_time_format,og.status as order_goods_status,s.is_shop,og.sub_freight_price,og.sum_alter_goods_price,
            ogr.order_goods_refund_number,ogr.order_goods_refund_id,date_format(ogr.update_time,"%Y-%m-%d %H:%i")
             as update_time_format,s.store_name,s.address,s.store_id,ogr.return_type,ogr.take_id,og.sub_fullSub_price,
             og.sub_share_platform_coupon_price,og.sub_share_shop_coupon_price,og.subtotal_share_platform_packet_price,
             oa.distribution_type')
            ->hidden(['create_time'])
            ->append(['remaining_time'])
            ->find();
        if (!$refundInfo) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单不存在',
            ], true);
        }

        //   查找退货地址
        $refundAdress = $storeAdress->where([
                ['store_id', '=', $refundInfo['store_id']],
                ['return_address', '=', 1],
                ['default_return_address', '=', 1],
            ])->field('address_location_text,telephone,phone_number')->find() ?? $storeAdress->where([
                ['store_id', '=', $refundInfo['store_id']],
                ['return_address', '=', 1],
            ])->field('address_location_text,telephone,phone_number')->find();

        if (!empty($refundAdress)) {
            $refundInfo['address'] = $refundAdress['address_location_text'];
            $refundInfo['phone'] = $refundAdress['telephone'] ?: $refundAdress['phone_number'];

        }
        // 查询物流信息
        if ($refundInfo['express_value'] && $refundInfo['express_number']) {
            // 快递100
            $data['customer'] = config('user.common.express.customer');
            $data['param'] = json_encode(['com' => $refundInfo['express_value'], 'num' => $refundInfo['express_number']]);
            $data["sign"] = strtoupper(md5($data['param'] . config('user.common.express.sign') . $data['customer']));
            $result = express_post($data, 'http://poll.kuaidi100.com/poll/query.do');
            // 取第一条详情信息
            $refundInfo['express_details'] = ($result['message'] !== 'ok') ? null : (reset($result['data']) ?: null);
        }
        // 退货至门店自提
        if ($refundInfo['return_type'] == 2 && !is_null($refundInfo['take_id'])) {
            $refundInfo['take_details'] = $take
                ->where([
                    ['take_id', '=', $refundInfo['take_id']],
                    ['status', '=', 1],
                ])
                ->field('take_name,contacts_phone,address')
                ->find();
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $refundInfo,
        ], true);

    }

    /**
     * 拼团信息列表
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function groupMsgList(RSACrypt $crypt)
    {
        $args = $crypt->request();
        $redisService = Cache::handler();
        $redisService->select(1);
        $begin = strtotime(date('Y-m-d'));
        $prefix = Config::get('cache.default')['prefix'];
        $list = $redisService->zRevRangeByScore($prefix . 'goods_' . $args['goods_id'],
            strval($begin + 86400) . '2', strval($begin) . '0', ['withscores' => true]);
        $result = [];
        if (count($list)) {
            $text = ['已开团', '已参团', '开团成功'];
            foreach ($list as $key => $item) {
                $item = strval($item);
                $keyArr = explode('@_@', $key);
                if ($keyArr) {
                    // 去掉拼团活动id标识,余用户昵称
                    $group_activity_id = array_shift($keyArr);
                    $status = $item[strlen($item) - 1];
                    array_push($result, [
                        'group_activity_id' => $group_activity_id,
                        'status' => $status,
                        'cTime' => substr($item, 0, strlen($item) - 1),
                        'user' => implode('_', $keyArr),
                        'state' => $text[$status],
                    ]);
                }
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }

    /**
     * 获取订单状态
     * @param RSACrypt $crypt
     * @param OrderAttach $attach
     * @param IntegralOrder $integralOrder
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderState(RSACrypt $crypt,
                                  OrderAttach $attach,
                                  IntegralOrder $integralOrder)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        switch ($args['type']) {
            case 1:
                // 普通订单
                $arr = $attach
                    ->alias('oa')
                    ->where([
                        ['oa.order_attach_number|oa.order_number', '=', $args['number']],
                    ])
                    ->join('order_goods og', 'og.order_attach_id = oa.order_attach_id')
                    ->join('order o', 'o.order_id = oa.order_id')
                    ->join('goods g', 'g.goods_id = og.goods_id')
                    ->join('limit l', 'l.goods_id = og.goods_id and l.delete_time is null and l.status = 1 and unix_timestamp(l.down_shelf_time) >= unix_timestamp(now())', 'left')
                    ->join('products p', 'p.products_id = og.products_id', 'left')
                    ->field('oa.order_attach_id,oa.status,o.order_type,og.goods_id,og.products_id,
                p.attr_time_limit_number,p.attr_goods_number,l.exchange_num,g.goods_number,og.quantity,
                l.create_time as limit_create_time,l.limit_purchase,oa.subtotal_price,o.total_price')
                    ->select();
                if ($arr->isEmpty()) {
                    return $crypt->response([
                        'code' => -1,
                        'message' => '订单不存在',
                        'data' => [
                            'status' => 1,
                        ],
                    ], true);
                }
                foreach ($arr as $_arr) {
                    // 已过期
                    if ($_arr['status'] == 6) {
                        // 检测库存是否足够
                        // 1普通线上 2拼团 3砍价 4限时抢购 5普通线下
                        $flag = $_arr['products_id'] ?
                            ($_arr['order_type'] == 4 ? 'attr_time_limit_number' : 'attr_goods_number') :
                            ($_arr['order_type'] == 4 ? 'exchange_num' : 'goods_number');
                        if ($_arr[$flag] < $_arr['quantity']) {
                            $msg = '商品：' . $_arr['goods_name'] . '，';
                            if ($_arr['products_id']) {
                                $msg .= '规格：' . $_arr['goods_attr'] . '，';
                            }
                            return $crypt->response([
                                'code' => -1,
                                'message' => $msg . '库存不足',
                                'data' => [
                                    'status' => 2,
                                ],
                            ], true);
                        }
                        if ($_arr['order_type'] == 4) {
                            $limitBuyTimes = (new OrderAct())->getLimitTimes($args['member_id'], $_arr['limit_create_time'], $_arr['goods_id']);
                            if (!empty($limitBuyTimes) && ($limitBuyTimes['count'] + $_arr['quantity']) >= $_arr['limit_purchase']) {
                                return [
                                    'code' => -1,
                                    'message' => '商品已达限购次数',
                                    'data' => [
                                        'status' => 3,
                                    ],
                                ];
                            }
                        }
                    }
                }
                // 检测金额是否被改(包括客服改价)
                if ($args['price'] != ($arr->count() == 1 ? $arr[0]['subtotal_price'] : $arr[0]['total_price'])) {
                    return [
                        'code' => -1,
                        'message' => '订单不存在或商家变动订单,请确认好订单状态再进行付款',
                        'data' => [
                            'status' => 3,
                        ],
                    ];
                }
                break;
            case 2:
                // 积分混购
                $arr = $integralOrder
                    ->alias('io')
                    ->where([
                        ['order_number', '=', $args['number']],
                    ])
                    ->join('integral i', 'i.integral_id = io.integral_id and i.delete_time is null')
                    ->field('io.integral_order_id,io.status,io.price,i.integral_number')
                    ->find();
                if (is_null($arr)) {
                    return [
                        'code' => -1,
                        'message' => '订单或商品已失效,请重试',
                        'data' => [
                            'status' => 1,
                        ],
                    ];
                }
                if ($arr['integral_number'] <= 0) {
                    return [
                        'code' => -1,
                        'message' => '商品库存不足',
                        'data' => [
                            'status' => 2,
                        ],
                    ];
                }
                if ($arr['price'] != $args['price']) {
                    return [
                        'code' => -1,
                        'message' => '订单不存在或商家变动订单,请确认好订单状态再进行付款',
                        'data' => [
                            'status' => 2,
                        ],
                    ];
                }
                break;
        }
        return $crypt->response([
            'code' => 0,
            'message' => '可以支付',
            'data' => [
                'status' => 0,
            ],
        ], true);
    }
}