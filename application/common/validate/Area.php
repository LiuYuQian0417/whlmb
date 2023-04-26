<?php
declare(strict_types = 1);

namespace app\common\validate;

use  think\Validate;

class Area extends Validate
{
    protected $rule = [
        'area_name|地区名称' => 'require',
        'mobile|注册商户手机号' => 'require|mobile',
        'city_name|商户城市名称' => 'require',
        'enterprise_name|企业全称' => 'require',
        'enterprise_address|企业地址' => 'require',
        'contact_name|联系人姓名' => 'require',
        'contact_phone|联系人电话' => 'require|mobile',
        'email|邮箱地址' => 'require|email',
    ];

    protected $message = [
        'title.require' => '不可为空',
    ];

    protected $scene = [
        'create' => ['area_name'],
        // 达达创建商户
        'create_merchant' => ['mobile', 'city_name', 'enterprise_name', 'enterprise_address', 'contact_name', 'contact_phone', 'email'],
    ];
}