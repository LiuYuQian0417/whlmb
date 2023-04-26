<?php
declare(strict_types = 1);
namespace app\computer\validate;

use think\Validate;

class OrderGoodsRefund extends Validate
{
    protected $rule = [
        'order_goods_id|订单商品信息' => 'require',
        'type|退单类型' => 'require',
        'refund_amount|退款金额' => 'require',
        'reason|退款原因说明' => 'require',
        'order_goods_refund_id|退单信息' => 'require',
        'return_type|退回方式' => 'require',
        'phone|联系电话' => 'require|mobile',
        'is_get_goods|仅退款下是否收到货' => 'require',
    ];

    protected $message = [
        'order_goods_id.require' => '不可为空',
        'type.require' => '不可为空',
        'refund_amount.require' => '不可为空',
        'reason.require' => '不可为空',
        'order_goods_refund_id.require' => '不可为空',
        'return_type.require' => '不可为空',
        'phone.require' => '不可为空',
        'phone.mobile' => '格式错误',
        'is_get_goods.require' => '数据错误',
    ];

    protected $scene = [
        // 退款[退货第一步]
        'refund' => ['order_goods_id', 'type', 'refund_amount', 'reason',],
        // 退货
        'returnConfirmed' => ['order_goods_refund_id', 'return_type', 'phone'],
        // 退货/退款详情
        'refundDetails' => ['order_goods_id'],
    ];
}