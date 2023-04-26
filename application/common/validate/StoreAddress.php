<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/24 0024
 * Time: 17:40
 */

namespace app\common\validate;


use think\Validate;

class StoreAddress extends Validate
{
    protected $rule = [
        'contact_name|联系人' => 'require',
        'phone_number|联系电话' => ['require', 'reg' => '(^1([38][0-9]|4[579]|5[0-3,5-9]|6[6]|7[0135678]|9[89])\d{8}$)'],
        'province|所在省份'    => 'require',
        'city|所在城市'        => 'require',
        'area|所在地区'        => 'require',
        'address|详细地址'     => 'require',
        'lng|经度'           => 'require',
        'lat|纬度'           => 'require',
        'store_id|店铺信息'    => 'require',
    ];

    protected $message = [
        'contact_name.require' => '不可为空',
        'phone_number.require' => '不可为空',
        'phone_number.reg' => '格式错误',
        'province.require'     => '不可为空',
        'city.require'         => '不可为空',
        'area.require'         => '不可为空',
        'address.require'      => '不可为空',
        'lng.require'          => '不可为空',
        'lat.require'          => '不可为空',
        'store_id.require'     => '不可为空',
    ];

    protected $scene = [
        'client_create' => [
            'contact_name',
            'province',
            'city',
            'area',
            'address',
            'lng',
            'lat',
            'phone_number',
        ],
        'master_create' => [
            'store_id',
            'contact_name',
            'province',
            'city',
            'area',
            'address',
            'lng',
            'lat',
            'store_id',
            'phone_number',
        ],
        'client_edit'   => [
            'contact_name',
            'province',
            'city',
            'area',
            'address',
            'lng',
            'lat',
            'store_address_id',
            'phone_number',
        ],
        'master_edit'   => [
            'contact_name',
            'province',
            'city',
            'area',
            'address',
            'lng',
            'lat',
            'store_address_id',
            'phone_number',
        ],
        'edit'          => [
            'contact_name',
            'province',
            'city',
            'area',
            'address',
            'lng',
            'lat',
            'store_id',
            'store_address_id'
        ],
    ];
}