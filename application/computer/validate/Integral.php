<?php
declare(strict_types=1);

namespace app\computer\validate;

use  think\Validate;

class Integral extends Validate
{
    protected $rule = [
        'member_id|会员信息'              => 'require',
        'integral_id|商品信息'            => 'require',
        'type|类型'                     => 'require',
        'integral_classify_id|积分商品分类' => 'require',
        'integral_name|积分商品名称'        => 'require',
        'integral|所需积分'               => 'require',
        // 'price|所需金额'                  => 'require',
        'integral_number|库存数量'        => 'require',
        'warn_number|库存预警数量'          => 'require',
    ];

    protected $message = [
        'member_id.require'            => '不可为空',
        'integral_id.require'          => '不可为空',
        'type.require'                 => '不可为空',
        'integral_classify_id.require' => '不可为空',
        'integral_name.require'        => '不可为空',
        'integral.require'             => '不可为空',
        // 'integral.price'               => '不可为空',
        'integral_number.require'      => '不可为空',
        'warn_number.require'          => '不可为空'
    ];

    protected $scene = [
        'goods'      => ['type'],
        'view'       => ['integral_id'],
        'conversion' => ['integral_id'],
        'create'     => ['type', 'integral_classify_id', 'integral_name', 'integral', 'integral_number', 'warn_number'],
        'edit'       => ['integral_id', 'integral_classify_id', 'type', 'integral_name', 'integral', 'integral_number', 'warn_number']
    ];
}