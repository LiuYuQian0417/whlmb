<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/13 0013
 * Time: 8:43
 */

namespace app\common\validate;

use  think\Validate;

class AlterPriceLog extends Validate
{
    protected $rule = [
        'order_attach_id|子订单号' => 'require',
        'goods_id|商品ID'        => 'require',
        'order_goods_id|订单号'   => 'require',
        'customer_id|客服id'     => 'require',
        'alter_price|金额'       => 'require',
        'reason|原因'            => 'require',
        'freight|运费'           => 'require',
    ];

    protected $message = [
        'order_goods_id.require' => '不可为空',
        'customer_id.require'    => '不可为空',
        'alter_price.require'    => '不可为空',
        'reason.require'         => '不可为空',
        'freight.require'        => '不可为空',
    ];

    protected $scene = [
        'customer' => ['order_attach_id', 'goods_id', 'customer_id', 'alter_price', 'reason', 'freight'],
        'store'    => ['order_goods_id', 'alter_price', 'freight'],
    ];
}