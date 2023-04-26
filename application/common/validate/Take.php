<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Take extends Validate
{
    protected $rule = [
        'store_id|店铺信息'        => 'require',
        'take_id|自提点信息'        => 'require',
        'take_name|自提点名称'      => 'require',
        'contacts_name|联系人名称'  => 'require',
        'contacts_phone|联系人电话' => 'require',
        'province|所在省份'        => 'require',
        'city|所在城市'            => 'require',
        'area|所在地区'            => 'require',
        'address|详细地址'         => 'require',
        'lat|纬度'               => 'require',
        'lng|经度'               => 'require',
    ];

    protected $message = [
        'store_id.require'       => '不可为空',
        'take_id.require'        => '不可为空',
        'take_name.require'      => '不可为空',
        'contacts_name.require'  => '不可为空',
        'contacts_phone.require' => '不可为空',
        'province.require'       => '不可为空',
        'city.require'           => '不可为空',
        'area.require'           => '不可为空',
        'address.require'        => '不可为空',
        'lat.require'            => '不可为空',
        'lng.require'            => '不可为空',
    ];

    protected $scene = [
        'create'    => ['take_name', 'contacts_name', 'contacts_phone', 'province', 'city', 'area', 'address', 'lat', 'lng'],
        'edit'      => ['take_id', 'take_name', 'contacts_name', 'contacts_phone', 'province', 'city', 'area', 'address', 'lat', 'lng'],
        'take_list' => ['store_id', 'lat', 'lng'],
    ];
}