<?php
declare(strict_types = 1);

namespace app\interfaces\controller\order;

use app\common\model\Goods;
use app\common\model\Limit;
use app\common\model\MemberCoupon;
use app\common\model\Message;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\OrderGoodsRefund;
use app\common\model\Products;
use app\common\service\Beanstalk;
use app\common\service\Lock;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;

/**
 * 订单操作
 * Class Operate
 * @package app\interfaces\controller\order
 */
class Operate extends BaseController
{
    /**
     * 取消订单
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @param OrderGoods $orderGoods
     * @param Goods $goods
     * @param Products $products
     * @param Lock $lock
     * @param MemberCoupon $memberCoupon
     * @param Limit $limit
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function cancel(RSACrypt $crypt,
                           OrderAttach $orderAttach,
                           OrderGoods $orderGoods,
                           Goods $goods,
                           Products $products,
                           Lock $lock,
                           MemberCoupon $memberCoupon,
                           Limit $limit)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderAttach->valid($args, 'cancel');
        $where = [
            ['order_attach_id', '=', $args['order_attach_id']],
            ['member_id', '=', $args['member_id']],
            ['status', '=', 0],  //仅支持取消待支付订单
        ];
        $orderAttachData = $orderAttach
            ->where($where)
            ->field('order_attach_id,order_id,used_shop_member_coupon_id,status')
            ->with(['orderGoodsCancel', 'orderCancel'])
            ->find();
        if (!$orderAttachData) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单不存在',
            ], true);
        }
        if (!$orderAttachData['order_goods_cancel']) {
            return $crypt->response([
                'code' => -2,
                'message' => '订单商品不存在',
            ], true);
        }
        // 回滚库存
        $inventory = ['goods_id' => [], 'products_id' => [], 'limit_id' => []];
        $lockKey = $updateOrderGoods = $couponUpdate = [];
        foreach ($orderAttachData['order_goods_cancel'] as $key => $value) {
            $updateKey = $value['is_limit'] ? 'time_limit_number' : 'goods_number';
            // 增加商品库存[正常/限时抢购]
            array_push($inventory['goods_id'], [
                'goods_id' => $value['goods_id'],
                'goods_number' => Db::raw('goods_number + ' . $value['quantity']),
            ]);
            // 增加商品行锁
            array_push($lockKey, 'goods_id_' . $value['goods_id']);
            // 返还限购商品剩余数量
            if ($value['is_limit']) {
                array_push($inventory['limit_id'], [
                    'limit_id' => $value['limit_id'],
                    'exchange_num' => Db::raw('exchange_num + ' . $value['quantity']),
                ]);
                array_push($lockKey, 'limit_id_' . $value['limit_id']);
            }
            // 增加规格商品库存[正常/限时抢购]
            if ($value['products_id']) {
                array_push($inventory['products_id'], [
                    'products_id' => $value['products_id'],
                    'attr_' . $updateKey => Db::raw('attr_' . $updateKey . ' + ' . $value['quantity']),
                ]);
                // 增加规格商品行锁
                array_push($lockKey, 'products_id_' . $value['products_id']);
            }
            array_push($updateOrderGoods, $value['order_goods_id']);
        }
        // 加锁
        $lockData = $lock->lock($lockKey, 10000);
        if (!$lockData) {
            return $crypt->response([
                'code' => -3,
                'message' => '网络繁忙,请重试',
            ], true);
        }
        Db::startTrans();
        if (!empty($inventory['goods_id'])) {
            $goods
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($inventory['goods_id']);
        }
        if (!empty($inventory['products_id'])) {
            $products
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($inventory['products_id']);
        }
        if (!empty($inventory['limit_id'])) {
            $limit
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($inventory['limit_id']);
        }
        $lock->unlock($lockData);
        // 更改订单附表为已关闭
        $orderAttachData->status = 6;
        $orderAttachData->save();
        // 更改订单商品数据为已取消
        if (!empty($updateOrderGoods)) {
            $orderGoods
                ->allowField(true)
                ->isUpdate(true)
                ->save([
                    'status' => 6.1,
                ], [
                    ['order_goods_id', 'in', implode(',', $updateOrderGoods)],
                ]);
        }
        // 含有店铺优惠券,则退还优惠券
        if ($orderAttachData['used_shop_member_coupon_id']) {
            array_push($couponUpdate, $orderAttachData['used_shop_member_coupon_id']);
        }
        // todo 不退还平台优惠券[此处注释,计划任务删除了此逻辑]
        if (false && $orderAttachData['order_cancel']['used_platform_member_coupon_id']) {
            // 检测是否所有同源商品订单都已取消
            $otherOrderGoodsData = $orderGoods
                ->where([
                    ['order_id', '=', $orderAttachData['order_id']],
                    ['order_attach_id', '<>', $args['order_attach_id']],
                    ['status', '<>', 6.1],
                ])
                ->column('order_goods_id');
            // 若其他状态订单商品不存在,则退回用户使用的平台优惠
            if (empty($otherOrderGoodsData)) {
                array_push($couponUpdate, $orderAttachData['order_cancel']['used_platform_member_coupon_id']);
            }
        }
        if ($couponUpdate) {
            $memberCoupon
                ->allowField(true)
                ->isUpdate(true)
                ->save(['status' => 0, 'used_time' => null],
                    [['member_coupon_id', 'in', implode(',', $couponUpdate)]]);
        }
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '取消成功',
        ], true);
        
    }
    
    /**
     * 删除订单
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function destroyOrder(RSACrypt $crypt,
                                 OrderAttach $orderAttach)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderAttach->valid($args, 'destroyOrder');
        $where = [
            ['order_attach_id', '=', $args['order_attach_id']],
            ['member_id', '=', $args['member_id']],
            //3已完成(已收货)4已关闭6已取消的已关闭
            ['status', 'in', '3,4,6'],
        ];
        $orderAttachData = $orderAttach
            ->where($where)
            ->field('order_attach_id,order_id,status,pay_channel')
            ->with(['orderGoodsDestroy'])
            ->find();
        if (!$orderAttachData) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单不存在',
            ], true);
        }
        Db::startTrans();
        // 软删附表数据和订单商品数据
        $orderAttachData
            ->together('order_goods_destroy')
            ->delete();
        // 软删除评论数据
        if ($orderAttachData->order_goods_destroy) {
            foreach ($orderAttachData->order_goods_destroy as $_order_goods_destroy) {
                if ($_order_goods_destroy->order_goods_evaluate) {
                    $_order_goods_destroy->order_goods_evaluate->delete();
                }
            }
        }
        // 交易完成订单赠送积分和成长值
        if (in_array($orderAttachData->status, [3, 4]) && $orderAttachData->order_goods_destroy) {
            // 统计有效金额
            $hookArgs = [
                'integral_total_price' => 0,
                'growth_total_price' => 0,
                'member_id' => $args['member_id'],
            ];
            foreach ($orderAttachData->order_goods_destroy as $item) {
                $valid_price = $item['single_price'] * $item['quantity']
                    - $item['sub_share_shop_coupon_price']
                    - $item['sub_share_platform_coupon_price']
                    - $item['subtotal_share_platform_packet_price']
                    - $item['sub_fullSub_price'];
                if (in_array($item->status, [3.1, 4.1])) {
                    $hookArgs['integral_total_price'] += $valid_price;
                    // 仅余额支付时赠送成长值
                    if ($orderAttachData['pay_channel'] == 3) {
                        $hookArgs['growth_total_price'] += $valid_price;
                    }
                }
            }
            if ($hookArgs['integral_total_price']) {
                Hook::exec(['app\\interfaces\\behavior\\Integral', 'inc'], $hookArgs);
            }
            if ($hookArgs['growth_total_price']) {
                Hook::exec(['app\\interfaces\\behavior\\Growth', 'inc'], $hookArgs);
            }
        }
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '删除成功',
        ], true);
        
    }
    
    
    /**
     * 退款/退款退货(商家需确认)
     * @param RSACrypt $crypt
     * @param OrderGoods $orderGoods
     * @param OrderGoodsRefund $orderGoodsRefund
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function refundAndReturn(RSACrypt $crypt,
                                    OrderGoods $orderGoods,
                                    OrderGoodsRefund $orderGoodsRefund)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderGoodsRefund->valid($args, 'refund');
        $where = [
            ['member_id', '=', $args['member_id']],
            ['order_goods_id', '=', $args['order_goods_id']],
            // 1.1 已支付 1.2拼团进行中(拼团成功->待发货) 2.1 已发货 2.2拼团自提进行中 3.1 已收货 4.1已评价
            // 修改申请 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意) 5.3 商家同意退货(退货第一步的退款成功) 5.4 申请退货(退货第二步,填写物流发货)
            // 重新申请 5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
            ['status', 'in', '1.1,1.2,2.1,2.2,3.1,4.1,5.1,5.2,5.3,5.4,5.5,5.6,5.7'],
        ];
        $orderGoodsData = $orderGoods
            ->where($where)
            ->with(['orderGoodsRefundList', 'orderAttach' => function ($query) {
                $query->field('order_attach_id,status,sale_after_status');
            },])
            ->field('quantity,order_goods_id,single_price,order_attach_id,
            sub_freight_price,sub_fullSub_price,status,sum_alter_goods_price,
            sum_alter_freight_price,subtotal_price')
            ->find();
        if (!$orderGoodsData) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单商品不存在',
            ], true);
        }
        if ($orderGoodsData['order_attach']['sale_after_status'] == 0) {
            return $crypt->response([
                'code' => -2,
                'message' => '售后通道已经关闭',
            ], true);
        }
        // 计算金额
        $amount = $orderGoodsData['subtotal_price']
            + $orderGoodsData['sum_alter_goods_price']  // 发货前可以退运费,发货后不可以退运费
            - ($orderGoodsData['order_attach']['status'] >= 2 ?
                $orderGoodsData['sub_freight_price']: 0)
            - $orderGoodsData['sub_fullSub_price'];
        // 记录回滚状态[审核失败重新审核时回滚状态不改变]
        if (is_null($orderGoodsData->order_goods_refund_list)) {
            $orderGoodsData->redo_status = $orderGoodsData['status'];
        }
        $args['origin_refund_amount'] = $args['refund_amount'];
        // 退款
        if ($args['refund_amount'] > $amount && $args['refund_amount'] > 0.1) {
            return $crypt->response([
                'code' => -3,
                'message' => '订单退款金额错误,请重新填写',
            ], true);
        }
        if ($args['type'] == 1) {
            $orderGoodsData->status = 5.1;      //5.1 申请退款(仅退款)
        } elseif ($args['type'] == 2) {
            $orderGoodsData->status = 5.2;      //5.2 申请退款(退货第一步退款,需商家同意)
        }
        Db::startTrans();
        // 退款退货失败,重新申请,删除原退款退货记录
        if ($orderGoodsData->order_goods_refund_list) {
            OrderGoodsRefund::destroy($orderGoodsData['order_goods_refund_list']['order_goods_refund_id']);
        }
        // 新增订单商品退款/退货数据
        $orderGoodsRefund->allowField(true)->isUpdate(false)->save($args);
        // 更新订单商品
        $orderGoodsData->save();
        // 查询此店铺订单是否全部售后中
//            $other = $orderGoods
//                ->where([
//                    ['order_attach_id', '=', $orderGoodsData['order_attach_id']],
//                    ['order_goods_id', '<>', $orderGoodsData['order_goods_id']],
//                    ['status', '<', 5],
//                ])
//                ->column('order_goods_id');
        // 店铺订单状态更改为售后中
//            if (empty($other)) {
//                $orderAttach->allowField(true)
//                    ->isUpdate(true)
//                    ->save(['order_attach_id' => $orderGoodsData['order_attach_id'], 'status' => 5]);
//            }
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '提交成功',
        ], true);
        
    }
    
    /**
     * 继续退货(商家已确认退款)
     * @param RSACrypt $crypt
     * @param OrderGoodsRefund $orderGoodsRefund
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function returnConfirmed(RSACrypt $crypt,
                                    OrderGoodsRefund $orderGoodsRefund)
    {
        $args = $crypt->request();
        $orderGoodsRefund->valid($args, 'returnConfirmed');
        $orderGoodsRefundData = $orderGoodsRefund
            ->where([
                ['order_goods_refund_id', '=', $args['order_goods_refund_id']],
                ['type', '=', 2],   //退货第一步退款
                ['status', '=', 1]  // 1审核成功(退单成功/退货第一步退款同意/退货第二步的已收货)
            ])
            ->with(['orderGoodsConfirmed'])
            ->field('order_goods_refund_id,order_goods_id,status,type')
            ->find();
        if (!$orderGoodsRefundData || !$orderGoodsRefundData['order_goods_confirmed']) {
            return $crypt->response([
                'code' => -6,
                'message' => '订单商品不存在',
            ]);
        }
        array_walk($args, function ($v, $k) use ($orderGoodsRefundData) {
            $orderGoodsRefundData->$k = $v;
        });
        // 更新退单状态
        $orderGoodsRefundData->type = 3;    //退货
        $orderGoodsRefundData->status = 0;  //商家待收货中
        Db::startTrans();
        $orderGoodsRefundData->save();
        // 更新商品订单状态(申请退货)
        $orderGoodsRefundData->order_goods_confirmed->status = 5.4;
        $orderGoodsRefundData->together('order_goods_confirmed')->save();
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '提交成功',
        ], true);
    }
    
    /**
     * 确认收货
     * 有退货退款的商品订单强制清除状态到收货
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @param OrderGoods $orderGoods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function confirmCollect(RSACrypt $crypt,
                                   OrderAttach $orderAttach,
                                   OrderGoods $orderGoods)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderAttachData = $orderAttach
            ->alias('oa')
            ->where([
                ['oa.member_id', '=', $args['member_id']],
                ['oa.order_attach_id', '=', $args['order_attach_id']],
            ])
            ->join('store s', 's.store_id = oa.store_id')
            ->with(['orderGoodsConfirmCollect'])
            ->field('oa.order_attach_id,oa.status,oa.pay_type,oa.store_id,oa.subtotal_price,oa.express_value,
                oa.express_number,oa.sale_after_status,oa.after_sale_times,s.store_name,oa.distribution_type')
            ->find();
        // 更改(店铺店铺和订单商品)订单状态
        if (!$orderAttachData || !$orderAttachData['order_goods_confirm_collect'] || !in_array($orderAttachData['status'], [2, 3])) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单不存在',
            ], true);
        }
        if ($orderAttachData['status'] == 3) {
            return $crypt->response([
                'code' => -2,
                'message' => '订单已确认收货',
            ], true);
        }
        $orderAttachData->status = 3;   //3已完成
        //收货时间和交易时间
        $orderAttachData->deal_time = date('Y-m-d H:i:s');
        // 如果货到付款  支付方式为货到付款
        if ($orderAttachData->pay_type == 2) {
            $orderAttachData->pay_channel = 6;
        }
        Db::startTrans();
        $orderAttachData->save();
        $orderGoods->isUpdate(true)
            ->save(['status' => 3.1, 'redo_status' => null], [
                ['order_attach_id', '=', $args['order_attach_id']],
                ['status', 'in', '2.1,2.2,5.1,5.2,5.3,5.4,5.5,5.6,5.7']
            ]);
        // 含有退款退货的商品订单,删除退款退货记录
        $refundArr = $orderGoodsId = [];
        $file = '';
        foreach ($orderAttachData['order_goods_confirm_collect'] as $item) {
            if (!$file) {
                $file = $item->getData('file');
            }
            array_push($orderGoodsId, $item['order_goods_id']);
            if ($item->order_goods_refund_list) {
                array_push($refundArr, $item->order_goods_refund_list->order_goods_refund_id);
            }
        }
        if ($refundArr) {
            OrderGoodsRefund::destroy(implode(',', $refundArr));
        }
        // 货到付款订单记录店铺资金记录
        if ($orderAttachData->pay_type == 2) {
            $storeCapitalData = [
                [
                    'store_id' => $orderAttachData['store_id'],
                    'type' => 3,
                    // 交易中
                    'status' => 2,
                    'order_attach_id' => $orderAttachData['order_attach_id'],
                    'price' => $orderAttachData['subtotal_price'],
                ]
            ];
            Hook::exec(['app\\interfaces\\behavior\\StoreCapital', 'record'], $storeCapitalData);
        }
        // 推送消息[不含短信][确认收货]
        $pushServer = app('app\\interfaces\\behavior\\Push');
        $pushServer->send([
            'tplKey' => 'order_state',
            'openId' => Request::param('web_open_id'),
            'subscribe_time' => Request::param('subscribe_time'),
            'microId' => Request::param('micro_open_id'),
            'phone' => Request::param('phone'),
            'data' => [$orderAttachData['distribution_type'] == 2 ? 7 : 6, $orderAttachData['store_name'], Request::param('nickname')],
            'inside_data' => [
                'member_id' => $args['member_id'],
                'type' => 1,
                'jump_state' => '0',
                'attach_id' => $args['order_attach_id'],
                'file' => $file,
                'express_value' => $orderAttachData['express_value'],
                'express_number' => $orderAttachData['express_number'],
            ],
        ], 2);
        Db::commit();
        // 计划任务自动评价
        Env::load(Env::get('app_path') . 'common/ini/.config');
        (new Beanstalk())->put(json_encode([
            'queue' => 'autoEvaluate',
            'id' => $orderGoodsId,
            'uid' => $args['member_id'],
            'time' => date('Y-m-d H:i:s'),
        ]), Env::get('good_reputation') * 86400);
        return $crypt->response([
            'code' => 0,
            'message' => '收货成功',
        ], true);
    }
    
    
    /**
     * 撤销退款/退货订单
     * @param RSACrypt $crypt
     * @param OrderGoods $orderGoods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function revokeApply(RSACrypt $crypt,
                                OrderGoods $orderGoods)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderGoods->valid($args, 'revokeApply');
        $where = [
            ['og.member_id', '=', $args['member_id']],
            ['og.order_goods_id', '=', $args['order_goods_id']],
            //5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意继续退货)
            //5.3 商家同意退货(退货第一步的退款成功) 5.4 申请退货(退货第二步,填写物流发货)
            ['og.status', 'in', '5.1,5.2,5.3,5.4'],
        ];
        $orderGoodsData = $orderGoods
            ->alias('og')
            ->where($where)
            ->with(['orderGoodsRefundList'])
            ->join('order_attach oa', 'oa.order_attach_id = og.order_attach_id')
            ->join('order o', 'o.order_id = og.order_id')
            ->field('og.order_goods_id,og.order_attach_id,og.redo_status,oa.status,
            o.order_type,oa.distribution_type')
            ->find();
        if (is_null($orderGoodsData)) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单商品不存在',
            ], true);
        }
        // 撤销商品订单状态
        if ($orderGoodsData->status == 2) {
            // 已发货[拼团自提下改为2.2,其他2.1]
            $orderGoodsData->status = ($orderGoodsData->order_type == 2 && $orderGoodsData->distribution_type == 2) ? 2.2 : 2.1;
        } else {
            $orderGoodsData->status = $orderGoodsData->redo_status;
        }
        $orderGoodsData->redo_status = null;
        Db::startTrans();
        $orderGoodsData->save();
        // 软删掉退单数据
        if (!is_null($orderGoodsData->order_goods_refund_list)) {
            OrderGoodsRefund::destroy($orderGoodsData->order_goods_refund_list['order_goods_refund_id']);
        }
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '撤销成功',
        ], true);
    }
    
}