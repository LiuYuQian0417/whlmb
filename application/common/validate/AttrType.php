<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class AttrType extends Validate
{
    protected $rule = [
        'store_id|店铺' => 'require',
        'type_name|商品类型名称'       => 'require',
        'type_name|商品类型'       => 'unique:attr_type,type_name^store_id',
    ];

    protected $message = [
        'store_id.require' => '不可为空',
        'type_name.require'     => '不可为空',
        'type_name.unique'     => '同一店铺名称不能相同',
    ];

    protected $scene = [
        'create'      => ['store_id', 'type_name'],
        'edit'        => ['attr_type_id', 'store_id', 'type_name'],

    ];
}