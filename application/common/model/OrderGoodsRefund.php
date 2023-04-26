<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 订单商品退款/退货表
 * Class OrderGoodsRefund
 * @package app\common\model
 */
class OrderGoodsRefund extends BaseModel
{
    protected $pk = 'order_goods_refund_id';

    public static function init()
    {
        self::beforeWrite(
            function ($e)
            {
                //判断是否有其他时间设置   统一时间
                $e->update_time = $e->dispose_time ?? $e->deliver_time ?? $e->finish_time ?? date('Y-m-d H:i:s');
                //处理退款处理流程时间
                if (isset($e->status))
                {
                    //如果是退款并且处在退货阶段
                    if (isset($e['type']) && $e['type'] == 3)
                    {
                        $e->deliver_time = $e->update_time;
                    }
                } else
                {
                    $e->dispose_time = $e->deliver_time = $e->finish_time = NULL;
                }
                //如果退款状态为退货退款  强制为已收到货
                if(isset($e->type) and $e->type == 2){
                    $e->is_get_goods=2;
                }
            }
        );
        self::beforeInsert(function ($e) {
            $e->status = 0;  // 0申请退单(商家未确认,退货时的未收货) 1审核成功(退单成功/退货第一步退款同意/退货第二步的已收货) 2审核失败(退单失败)
            $e->order_goods_refund_number = get_order_sn();
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

    /**
     * 收货后剩余售后时间
     * @param $value
     * @param $data
     * @return int
     */
    public function getAfterSaleTimeAttr($value, $data)
    {
        $time = -1;
        if ($data['sale_after_status'])
        {
            $time = !is_null($data['deal_time']) ? (86400 * $data['after_sale_times'] + strtotime(
                        $data['deal_time']
                    )) - time() : 0;
        }
        return $time;
    }

    /**
     * 返回退货剩余时间
     * @param $value
     * @param $data
     * @return int
     */
    public function getRemainingTimeAttr($value, $data)
    {
        return 30 * 86400 + strtotime($data['create_time']) - time();
    }

    /**
     * 关联订单商品
     * @return \think\model\relation\BelongsTo
     */
    public function orderGoods()
    {
        return $this->belongsTo('OrderGoods', 'order_goods_id', 'order_goods_id');
    }

    /**
     * 商家已同意会员退货(第一步退款)申请(关联订单商品)
     * @return \think\model\relation\BelongsTo
     */
    public function orderGoodsConfirmed()
    {
        return self::orderGoods()->field('order_goods_id,order_attach_id,status');
    }
}