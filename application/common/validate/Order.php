<?php
declare(strict_types = 1);
namespace app\common\validate;

use think\Validate;

/**
 * 订单表验证
 * Class Order
 * @package app\common\validate
 */
class Order extends Validate
{
    protected $rule = [
        'member_address_id|会员地址' => 'require',
        'pay_channel|下单渠道' => 'require',
        'order_type|订单类型' => 'require',
        'id_set|商品id或购物车id' => 'require',
        'store_set|店铺数据集合' => 'require',
        'pay_password|支付密码' => 'require|max:6',
    ];

    protected $message = [
        'member_address_id.require' => '不可为空',
        'pay_channel.require' => '不可为空',
        'order_type.require' => '不可为空',
        'id_set.require' => '不可为空',
        'store_set.require' => '不可为空',
        'pay_password.require' => '不可为空',
        'pay_password.max' => '支付密码应为6位'
    ];

    protected $scene = [
        // 确认订单
        'confirm' => ['pay_channel', 'id_set', 'order_type', 'store_set'],
        // 余额支付
        'balanceExec' => ['pay_password'],
    ];
}