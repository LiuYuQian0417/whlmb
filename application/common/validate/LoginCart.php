<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class LoginCart extends Validate
{
    protected $rule = [
        'identification|标识信息' => 'require',
        'store_id|店铺信息'       => 'require',
        'goods_id|商品信息'       => 'require',
        'number|数量'           => 'require',
        'goods_name|商品名称'     => 'require',
        'file|商品图片'           => 'require',
    ];

    protected $message = [
        'identification.require' => '不可为空',
        'store_id.require'       => '不可为空',
        'goods_id.require'       => '不可为空',
        'number.require'         => '不可为空',
        'goods_name.require'     => '不可为空',
        'file.require'           => '不可为空',
    ];

    protected $scene = [
        'login_create'        => ['identification', 'store_id', 'goods_id', 'number', 'goods_name'],
        'login_index'         => ['identification'],
        'login_add_number'    => ['identification', 'cart_id', 'number'],
        'login_reduce_number' => ['identification', 'cart_id', 'number'],
        'login_delete'        => ['cart_id'],
        'login_update'        => ['cart_id', 'identification', 'goods_id', 'number', 'goods_name'],
    ];
}