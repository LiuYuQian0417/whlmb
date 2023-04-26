<?php
declare(strict_types=1);

namespace app\computer\validate;

use  think\Validate;

class MemberAddress extends Validate
{
    protected $rule = [
        'member_address_id|收货地址编号' => 'require',
        'name|姓名' => 'require',
        'phone|手机号' => 'require',
        'address|地址' => 'require',
        'province|省份' => 'require',
        'city|城市' => 'require',
        'area|地区' => 'require',
        'location_address|定位地址' => 'require',
        'lat|经度' => 'require',
        'lng|纬度' => 'require',

    ];

    protected $message = [
        'member_address_id.require' => '不可为空',
        'name.require' => '不可为空',
        'phone.require' => '不可为空',
        'address.require' => '不可为空',
        'province.require' => '不可为空',
        'city.require' => '不可为空',
        'area.require' => '不可为空',
        'location_address.require' => '不可为空',
        'lat.require' => '不可为空',
        'lng.require' => '不可为空'
    ];

    protected $scene = [
        'edit' => ['member_address_id', 'name', 'phone', 'address', 'province', 'city', 'area'],
        'interfaces_create' => ['name', 'phone', 'address', 'province', 'city', 'area', 'location_address', 'lat', 'lng'],
        'interfaces_update' => ['member_address_id', 'name', 'phone', 'address', 'province', 'city', 'area', 'location_address', 'lat', 'lng'],
        'find' => ['member_address_id'],
        'destroy' => ['member_address_id'],
    ];
}