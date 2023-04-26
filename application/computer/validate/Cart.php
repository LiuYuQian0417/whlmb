<?php
declare(strict_types=1);

namespace app\computer\validate;

use  think\Validate;

class Cart extends Validate
{
    protected $rule = [
        'identification|标识信息' => 'require',
        'member_id|会员信息'      => 'require',
        'store_id|店铺信息'       => 'require',
        'goods_id|商品信息'       => 'require',
        'number|数量'           => 'require|min:1',
        'goods_name|商品名称'     => 'require',
        'file|商品图片'           => 'require',
        'price|价格'            => 'require',
        'discount|折扣'         => 'require',
        'is_vip|折扣状态'         => 'require',
    ];

    protected $message = [
        'identification.require' => '不可为空',
        'member_id.require'      => '不可为空',
        'store_id.require'       => '不可为空',
        'goods_id.require'       => '不可为空',
        'number.require'         => '不可为空',
        'number.min'             => '数量错误',
        'goods_name.require'     => '不可为空',
        'file.require'           => '不可为空',
        'price.require'          => '不可为空',
        'discount.require'       => '不可为空',
        'is_vip.require'         => '不可为空',
    ];

    protected $scene = [
        'pc_create'         => ['store_id', 'goods_id', 'number', 'goods_name', 'price'],
        'create'         => ['store_id', 'goods_id', 'number', 'goods_name', 'price', 'discount', 'goods_weight', 'goods_measure', 'is_vip'],
        'index'          => ['member_id'],
        'add_number'     => ['cart_id', 'number'],
        'reduce_number'  => ['cart_id', 'number'],
        'delete'         => ['cart_id'],
        'update'         => ['cart_id','goods_id', 'number', 'goods_name', 'price'],
        'identification' => ['identification'],
        'confirm_order'  => ['cart_id'],
    ];
}