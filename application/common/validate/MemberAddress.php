<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class MemberAddress extends Validate
{
    protected $rule = [
        'member_address_id|收货地址编号' => 'require',
        'name|姓名' => 'require|max:50',
        'phone|手机号' => 'require|mobile',
        'address|地址' => 'require|max:200',
        'province|省份' => 'require|max:30',
        'city|城市' => 'require|max:30',
        'area|地区' => 'require|max:30',
        'location_address|定位地址' => 'require|max:200',
        'lat|经度' => 'require',
        'lng|纬度' => 'require',
    ];
    
    protected $message = [
        'member_address_id.require' => '不可为空',
        'name.require' => '不可为空',
        'name.max' => '长度过大',
        'phone.require' => '不可为空',
        'phone.mobile' => '格式错误',
        'address.require' => '不可为空',
        'address.max' => '长度过大',
        'province.require' => '不可为空',
        'province.max' => '长度过大',
        'city.require' => '不可为空',
        'city.max' => '长度过大',
        'area.require' => '不可为空',
        'area.max' => '长度过大',
        'location_address.require' => '不可为空',
        'location_address.max' => '长度过大',
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