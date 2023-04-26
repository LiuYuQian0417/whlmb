<?php
declare(strict_types = 1);
namespace app\common\validate;

use think\Validate;

class DadaShop extends Validate
{
    protected $rule = [
        'station_name|门店名称' => 'require',
        'business|主营业务' => 'require',
        'contact_name|联系人/店长姓名' => 'require',
        'phone|联系电话' => 'require|mobile',
        'station_address|详细地址' => 'require',
        'lng' => 'require',
        'lat' => 'require',
    ];

    protected $message = [
        'station_name.require' => '不可为空',
        'origin_shop_id.require' => '不可为空',
        'business.require' => '不可为空',
        'contact_name.require' => '不可为空',
        'phone.require' => '不可为空',
        'phone.mobile' => '应为手机号',
        'station_address.require' => '不可为空',
        'lng.require' => '不可为空',
        'lat.require' => '不可为空',
    ];

    protected $scene = [
        'create' => ['station_name','business','contact_name','phone','station_address','lng','lat']
    ];
}