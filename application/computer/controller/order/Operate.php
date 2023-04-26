<?php
declare(strict_types=1);

namespace app\computer\controller\order;

use app\computer\model\Goods;
use app\computer\model\Limit;
use app\computer\model\MemberCoupon;
use app\computer\model\Message;
use app\computer\model\OrderAttach;
use app\computer\model\OrderGoods;
use app\computer\model\OrderGoodsRefund;
use app\computer\model\Products;
use app\common\service\Beanstalk;
use app\common\service\Lock;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;
use think\facade\Session;

/**
 * 订单操作
 * Class Operate
 * @package app\computer\controller\order
 */
class Operate extends BaseController
{


    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => ''],
    ];


    /**
     * 退款/退款退货(商家需确认)
     * @param Request $request
     * @param OrderGoods $orderGoods
     * @param OrderGoodsRefund $orderGoodsRefund
     * @param OrderAttach $orderAttach
     * @return mixed
     */
    public function refundAndReturn(Request $request,
                                    OrderGoods $orderGoods,
                                    OrderGoodsRefund $orderGoodsRefund)
    {
        if ($request::isPost())
        {
            try
            {
                Db::startTrans();
                $args = $request::post();
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
                    ->with(
                        [
                            'orderGoodsRefundList',
                            'orderAttach' => function ($query)
                            {
                                $query->field('order_attach_id,sale_after_status');
                            },
                        ]
                    )
                    ->field(
                        'quantity,order_goods_id,single_price,order_attach_id,sub_freight_price,sub_fullSub_price,status,sum_alter_goods_price'
                    )
                    ->find();
                if (!$orderGoodsData)
                {
                    exception(config('message.')[-11][-6], -6);
                }
                if ($orderGoodsData['order_attach']['sale_after_status'] == 0)
                {
                    exception('售后通道已经关闭', -6);
                }
                // 计算金额
                $amount = $orderGoodsData['single_price'] * $orderGoodsData['quantity'] + $orderGoodsData['sum_alter_goods_price']
                    + $orderGoodsData['sub_freight_price'] - $orderGoodsData['sub_fullSub_price'];
                // 记录回滚状态[审核失败重新审核时回滚状态不改变]
                if (is_null($orderGoodsData->order_goods_refund_list))
                {
                    $orderGoodsData->redo_status = $orderGoodsData['status'];
                }
                $args['origin_refund_amount'] = $args['refund_amount'];
                if ($args['type'] == 1)
                {
                    // 退款
                    if ($args['refund_amount'] > $amount)
                    {
                        exception(config('message.')[-11][-9], -10);
                    }
                    $orderGoodsData->status = 5.1;      //5.1 申请退款(仅退款)
                } else
                {
                    if ($args['type'] == 2)
                    {
                        // 退货第一步退款
                        $orderGoodsData->status = 5.2;      //5.2 申请退款(退货第一步退款,需商家同意)
                    }
                }
                // 退款退货失败,重新申请,删除原退款退货记录
                if ($orderGoodsData->order_goods_refund_list)
                {
                    OrderGoodsRefund::destroy($orderGoodsData['order_goods_refund_list']['order_goods_refund_id']);
                }
                // 新增订单商品退款/退货数据
                $orderGoodsRefund->allowField(TRUE)->isUpdate(FALSE)->save($args);
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
                return json(['code' => 0, 'message' => config('message.')[0][0]], TRUE);
            } catch (\Exception $e)
            {
                Db::rollback();
                return json(
                    [
                        'code'    => $e->getCode() == 0 ? -100 : $e->getCode(),
                        'message' => self::$errMsg ?: $e->getMessage(),
                    ],
                    TRUE
                );
            }
        }
    }


    /**
     * 撤销退款/退货订单
     * @param RSACrypt $crypt
     * @param OrderGoods $orderGoods
     * @return mixed
     */
    public function revokeApply(RSACrypt $crypt, OrderGoods $orderGoods)
    {
        try
        {
            Db::startTrans();
            $args = $crypt->request();
            $args['member_id'] = Session::get('member_info')['member_id'];
            $validRet = $orderGoods->valid($args, 'revokeApply');
            if ($validRet['code'])
            {
                return $crypt->response($validRet, TRUE);
            }
            $where = [
                ['member_id', '=', $args['member_id']],
                ['order_goods_id', '=', $args['order_goods_id']],
                //5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意继续退货)
                //5.3 商家同意退货(退货第一步的退款成功) 5.4 申请退货(退货第二步,填写物流发货)
                ['status', 'in', '5.1,5.2,5.3,5.4'],
            ];
            $orderGoodsData = $orderGoods
                ->where($where)
                ->with(['orderGoodsRefundList'])
                ->field('order_goods_id,order_attach_id,redo_status')
                ->find();
            if (!$orderGoodsData)
            {
                return $crypt->response(['code' => -6, 'message' => config('message.')[-11][-6]], TRUE);
            }
            // 撤销商品订单状态
            $orderGoodsData->status = $orderGoodsData->redo_status;
            $orderGoodsData->redo_status = NULL;
            $orderGoodsData->save();
            // 软删掉退单数据
            if ($orderGoodsData->order_goods_refund_list)
            {
                OrderGoodsRefund::destroy($orderGoodsData->order_goods_refund_list['order_goods_refund_id']);
            }
            Db::commit();
            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], TRUE);
        } catch (\Exception  $e)
        {
            Db::rollback();
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
        }
    }

    /*************废弃**************/

//    /**
//     * 取消订单
//     * @param RSACrypt $crypt
//     * @param OrderAttach $orderAttach
//     * @param OrderGoods $orderGoods
//     * @param Goods $goods
//     * @param Products $products
//     * @param Lock $lock
//     * @param MemberCoupon $memberCoupon
//     * @param Limit $limit
//     * @return mixed
//     */
//    public function cancel(RSACrypt $crypt,
//                           OrderAttach $orderAttach,
//                           OrderGoods $orderGoods,
//                           Goods $goods,
//                           Products $products,
//                           Lock $lock,
//                           MemberCoupon $memberCoupon,
//                           Limit $limit)
//    {
//        try
//        {
//            Db::startTrans();
//            $args = $crypt->request();
//            $args['member_id'] = request(0)->mid;
//            $validRet = $orderAttach->valid($args, 'cancel');
//            if ($validRet['code'])
//            {
//                return $crypt->response($validRet, TRUE);
//            }
//            $where = [
//                ['order_attach_id', '=', $args['order_attach_id']],
//                ['member_id', '=', $args['member_id']],
//                ['status', '=', 0],  //仅支持取消待支付订单
//            ];
//            $orderAttachData = $orderAttach
//                ->where($where)
//                ->field('order_attach_id,order_id,used_shop_member_coupon_id,status')
//                ->with(['orderGoodsCancel', 'orderCancel'])
//                ->find();
//            if (!$orderAttachData)
//            {
//                return $crypt->response(['code' => -1, 'message' => config('message.')[-11][-3]], TRUE);
//            }
//            if (!$orderAttachData['order_goods_cancel'])
//            {
//                return $crypt->response(['code' => -6, 'message' => config('message.')[-11][-6]], TRUE);
//            }
//            // 回滚库存
//            $inventory = ['goods_id' => [], 'products_id' => [], 'limit_id' => []];
//            $lockKey = $updateOrderGoods = $couponUpdate = [];
//            foreach ($orderAttachData['order_goods_cancel'] as $key => $value)
//            {
//                $updateKey = $value['is_limit'] ? 'time_limit_number' : 'goods_number';
//                // 增加商品库存[正常/限时抢购]
//                array_push(
//                    $inventory['goods_id'],
//                    [
//                        'goods_id'     => $value['goods_id'],
//                        'goods_number' => Db::raw('goods_number + ' . $value['quantity']),
//                    ]
//                );
//                // 增加商品行锁
//                array_push($lockKey, 'goods_id_' . $value['goods_id']);
//                // 返还限购商品剩余数量
//                if ($value['is_limit'])
//                {
//                    array_push(
//                        $inventory['limit_id'],
//                        [
//                            'limit_id'     => $value['limit_id'],
//                            'exchange_num' => Db::raw('exchange_num + ' . $value['quantity']),
//                        ]
//                    );
//                    array_push($lockKey, 'limit_id_' . $value['limit_id']);
//                }
//                // 增加规格商品库存[正常/限时抢购]
//                if ($value['products_id'])
//                {
//                    array_push(
//                        $inventory['products_id'],
//                        [
//                            'products_id'        => $value['products_id'],
//                            'attr_' . $updateKey => Db::raw('attr_' . $updateKey . ' + ' . $value['quantity']),
//                        ]
//                    );
//                    // 增加规格商品行锁
//                    array_push($lockKey, 'products_id_' . $value['products_id']);
//                }
//                array_push($updateOrderGoods, $value['order_goods_id']);
//            }
//            // 加锁
//            $lockData = $lock->lock($lockKey, 10000);
//            if (!$lockData)
//            {
//                return $crypt->response(['code' => -4, 'message' => config('message.')[-11][-4]], TRUE);
//            }
//            if ($inventory['goods_id'])
//            {
//                $goods->allowField(TRUE)->isUpdate(TRUE)->saveAll($inventory['goods_id']);
//            }
//            if ($inventory['products_id'])
//            {
//                $products->allowField(TRUE)->isUpdate(TRUE)->saveAll($inventory['products_id']);
//            }
//            if ($inventory['limit_id'])
//            {
//                $limit->allowField(TRUE)->isUpdate(TRUE)->saveAll($inventory['limit_id']);
//            }
//            // 解锁
//            $lock->unlock($lockData);
//            // 更改订单附表为已关闭
//            $orderAttachData->status = 6;
//            $orderAttachData->save();
//            // 更改订单商品数据为已取消
//            if ($updateOrderGoods)
//            {
//                $orderGoods
//                    ->allowField(TRUE)
//                    ->isUpdate(TRUE)
//                    ->save(['status' => 6.1], [['order_goods_id', 'in', implode(',', $updateOrderGoods)]]);
//            }
//            // 含有店铺优惠券,则退还优惠券
//            if ($orderAttachData['used_shop_member_coupon_id'])
//            {
//                array_push($couponUpdate, $orderAttachData['used_shop_member_coupon_id']);
//            }
//            if ($orderAttachData['order_cancel']['used_platform_member_coupon_id'])
//            {
//                // 检测是否所有同源商品订单都已取消
//                $otherOrderGoodsData = $orderGoods
//                    ->where(
//                        [
//                            ['order_id', '=', $orderAttachData['order_id']],
//                            ['order_attach_id', '<>', $args['order_attach_id']],
//                            ['status', '<>', 6.1],
//                        ]
//                    )
//                    ->column('order_goods_id');
//                // 若其他状态订单商品不存在,则退回用户使用的平台优惠
//                if (empty($otherOrderGoodsData))
//                {
//                    array_push($couponUpdate, $orderAttachData['order_cancel']['used_platform_member_coupon_id']);
//                }
//            }
//            if ($couponUpdate)
//            {
//                $memberCoupon
//                    ->allowField(TRUE)
//                    ->isUpdate(TRUE)
//                    ->save(
//                        ['status' => 0, 'used_time' => NULL],
//                        [['member_coupon_id', 'in', implode(',', $couponUpdate)]]
//                    );
//            }
//            Db::commit();
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], TRUE);
//        } catch (\Exception $e)
//        {
//            Db::rollback();
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
//        }
//    }
//
//    /**
//     * 删除订单
//     * @param RSACrypt $crypt
//     * @param OrderAttach $orderAttach
//     * @return mixed
//     */
//    public function destroyOrder(RSACrypt $crypt, OrderAttach $orderAttach)
//    {
//        try
//        {
//            Db::startTrans();
//            $args = $crypt->request();
//            $args['member_id'] = request(0)->mid;
//            $validRet = $orderAttach->valid($args, 'destroyOrder');
//            if ($validRet['code'])
//            {
//                return $crypt->response($validRet, TRUE);
//            }
//            $where = [
//                ['order_attach_id', '=', $args['order_attach_id']],
//                ['member_id', '=', $args['member_id']],
//                //3已完成(已收货)4已关闭6已取消的已关闭
//                ['status', 'in', '3,4,6'],
//            ];
//            $orderAttachData = $orderAttach
//                ->where($where)
//                ->field('order_attach_id,order_id,status,pay_channel')
//                ->with(['orderGoodsDestroy'])
//                ->find();
//            if (!$orderAttachData)
//            {
//                return $crypt->response(['code' => -1, 'message' => config('message.')[-11][-3]], TRUE);
//            }
//            // 软删附表数据和订单商品数据
//            $orderAttachData->together('order_goods_destroy')->delete();
//            // 软删除评论数据
//            if ($orderAttachData->order_goods_destroy)
//            {
//                foreach ($orderAttachData->order_goods_destroy as $_order_goods_destroy)
//                {
//                    if ($_order_goods_destroy->order_goods_evaluate)
//                    {
//                        $_order_goods_destroy->order_goods_evaluate->delete();
//                    }
//                }
//            }
//            // 交易完成订单赠送积分和成长值
//            if (in_array($orderAttachData->status, [3, 4]) && $orderAttachData->order_goods_destroy)
//            {
//                // 统计有效金额
//                $hookArgs = [
//                    'integral_total_price' => 0,
//                    'growth_total_price'   => 0,
//                    'member_id'            => $args['member_id'],
//                ];
//                foreach ($orderAttachData->order_goods_destroy as $item)
//                {
//                    $valid_price = $item['single_price'] * $item['quantity']
//                        - $item['sub_share_shop_coupon_price']
//                        - $item['sub_share_platform_coupon_price']
//                        - $item['subtotal_share_platform_packet_price']
//                        - $item['sub_fullSub_price'];
//                    if (in_array($item->status, [3.1, 4.1]))
//                    {
//                        $hookArgs['integral_total_price'] += $valid_price;
//                        // 仅余额支付时赠送成长值
//                        if ($orderAttachData['pay_channel'] == 3)
//                        {
//                            $hookArgs['growth_total_price'] += $valid_price;
//                        }
//                    }
//                }
//                if ($hookArgs['integral_total_price'])
//                {
//                    Hook::exec(['app\\interfaces\\behavior\\Integral', 'inc'], $hookArgs);
//                }
//                if ($hookArgs['growth_total_price'])
//                {
//                    Hook::exec(['app\\interfaces\\behavior\\Growth', 'inc'], $hookArgs);
//                }
//            }
//            Db::commit();
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], TRUE);
//        } catch (\Exception $e)
//        {
//            Db::rollback();
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
//        }
//    }
//
//    /**
//     * 继续退货(商家已确认退款)
//     * @param RSACrypt $crypt
//     * @param OrderGoodsRefund $orderGoodsRefund
//     * @return mixed
//     */
//    public function returnConfirmed(RSACrypt $crypt, OrderGoodsRefund $orderGoodsRefund)
//    {
//        try
//        {
//            Db::startTrans();
//            $args = $crypt->request();
//            $validRet = $orderGoodsRefund->valid($args, 'returnConfirmed');
//            if ($validRet['code'])
//            {
//                return $crypt->response($validRet, TRUE);
//            }
//            $orderGoodsRefundData = $orderGoodsRefund
//                ->where(
//                    [
//                        ['order_goods_refund_id', '=', $args['order_goods_refund_id']],
//                        ['type', '=', 2],   //退货第一步退款
//                        ['status', '=', 1]  // 1审核成功(退单成功/退货第一步退款同意/退货第二步的已收货)
//                    ]
//                )
//                ->with(['orderGoodsConfirmed'])
//                ->field('order_goods_refund_id,order_goods_id,status,type')
//                ->find();
//            if (!$orderGoodsRefundData || !$orderGoodsRefundData['order_goods_confirmed'])
//            {
//                return $crypt->response(['code' => -6, 'message' => config('message.')[-11][-6]]);
//            }
//            array_walk(
//                $args,
//                function ($v, $k) use ($orderGoodsRefundData)
//                {
//                    $orderGoodsRefundData->$k = $v;
//                }
//            );
//            // 更新退单状态
//            $orderGoodsRefundData->type = 3;    //退货
//            $orderGoodsRefundData->status = 0;  //商家待收货中
//            $orderGoodsRefundData->save();
//            // 更新商品订单状态(申请退货)
//            $orderGoodsRefundData->order_goods_confirmed->status = 5.4;
//            $orderGoodsRefundData->together('order_goods_confirmed')->save();
//            Db::commit();
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], TRUE);
//        } catch (\Exception $e)
//        {
//            Db::rollback();
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
//        }
//    }
//
//    /**
//     * 确认收货
//     * 有退货退款的商品订单强制清除状态到收货
//     * @param RSACrypt $crypt
//     * @param OrderAttach $orderAttach
//     * @param OrderGoods $orderGoods
//     * @param Message $message
//     * @return mixed
//     */
//    public function confirmCollect(RSACrypt $crypt, OrderAttach $orderAttach, OrderGoods $orderGoods, Message $message)
//    {
//        try
//        {
//            Db::startTrans();
//            $args = $crypt->request();
//            $args['member_id'] = request(0)->mid;
//            $orderAttachData = $orderAttach
//                ->where(
//                    [
//                        ['member_id', '=', $args['member_id']],
//                        ['order_attach_id', '=', $args['order_attach_id']],
//                        ['status', '=', 2],   //2配送中
//                    ]
//                )
//                ->with(['orderGoodsConfirmCollect'])
//                ->field(
//                    'order_attach_id,status,pay_type,store_id,subtotal_price,express_value,
//                express_number,sale_after_status,after_sale_times'
//                )
//                ->find();
//            // 更改(店铺店铺和订单商品)订单状态
//            if (!$orderAttachData || !$orderAttachData['order_goods_confirm_collect'])
//            {
//                return $crypt->response(['code' => -6, 'message' => config('message.')[-11][-6]], TRUE);
//            }
//            $orderAttachData->status = 3;   //3已完成
//            //收货时间和交易时间
//            $orderAttachData->deal_time = date('Y-m-d H:i:s');
//            $orderAttachData->save();
//            $orderGoods->isUpdate(TRUE)
//                ->save(
//                    ['status' => 3.1, 'redo_status' => NULL],
//                    [
//                        ['order_attach_id', '=', $args['order_attach_id']],
//                        ['status', 'in', '2.1,2.2,5.1,5.2,5.3,5.4,5.5,5.6,5.7'],
//                    ]
//                );
//            // 含有退款退货的商品订单,删除退款退货记录
//            $refundArr = $orderGoodsId = [];
//            $file = '';
//            foreach ($orderAttachData['order_goods_confirm_collect'] as $item)
//            {
//                if (!$file)
//                {
//                    $file = $item->getData('file');
//                }
//                array_push($orderGoodsId, $item['order_goods_id']);
//                if ($item->order_goods_refund_list)
//                {
//                    array_push($refundArr, $item->order_goods_refund_list->order_goods_refund_id);
//                }
//            }
//            if ($refundArr)
//            {
//                OrderGoodsRefund::destroy(implode(',', $refundArr));
//            }
//            // 货到付款订单记录店铺资金记录
//            if ($orderAttachData->pay_type == 2)
//            {
//                $storeCapitalData = [
//                    [
//                        'store_id'        => $orderAttachData['store_id'],
//                        'type'            => 3,
//                        // 交易中
//                        'status'          => 2,
//                        'order_attach_id' => $orderAttachData['order_attach_id'],
//                        'price'           => $orderAttachData['subtotal_price'],
//                    ],
//                ];
//                Hook::exec(['app\\interfaces\\behavior\\StoreCapital', 'record'], $storeCapitalData);
//            }
//            // 用户订单签收消息
//            $msgData = [
//                'member_id'      => $args['member_id'],
//                // 物流通知
//                'type'           => 1,
//                'jump_state'     => 'express',
//                'attach_id'      => $args['order_attach_id'],
//                'title'          => '您的订单已签收',
//                'file'           => $file,
//                'express_value'  => $orderAttachData['express_value'],
//                'express_type'   => 'order',
//                'express_number' => $orderAttachData['express_number'],
//                'describe'       => '您的订单已签收',
//            ];
//            $message->allowField(TRUE)->isUpdate(FALSE)->save($msgData);
//            // 计划任务自动评价
//            Env::load(Env::get('app_path') . 'common/ini/.config');
//            (new Beanstalk())->put(
//                json_encode(
//                    [
//                        'queue' => 'autoEvaluate',
//                        'id'    => $orderGoodsId,
//                        'uid'   => $args['member_id'],
//                        'time'  => date('Y-m-d H:i:s'),
//                    ]
//                ),
//                Env::get('good_reputation') * 86400
//            );
//            // 关闭售后通道
//            (new Beanstalk())->put(
//                json_encode(
//                    [
//                        'queue' => 'autoCloseSaleAfter',
//                        'id'    => $args['order_attach_id'],
//                        'time'  => date('Y-m-d H:i:s'),
//                    ]
//                ),
//                $orderAttachData['after_sale_times'] * 86400
//            );
//            Db::commit();
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], TRUE);
//        } catch (\Exception $e)
//        {
//            Db::rollback();
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
//        }
//    }


}