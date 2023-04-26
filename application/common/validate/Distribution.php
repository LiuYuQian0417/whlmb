<?php
declare(strict_types = 1);

namespace app\common\validate;

use think\Validate;

/**
 * 分销商验证类
 * Class Distribution
 * @package app\common\validate
 */
class Distribution extends Validate
{
    protected $rule = [
        'real_name|姓名' => 'max:20',
        'sex|姓名' => 'in:1,2',
        'phone|联系方式' => 'mobile',
        'wechat_no|微信号' => 'max:50',
        'id_card|身份证号' => 'idCard',
        'address|地址' => 'max:200',
    ];
    
    protected $message = [
        'real_name.max' => '不可超过20字符',
        'sex.in' => '不在有效范围内',
        'phone.mobile' => '请输入正确的格式',
        'wechat_no.max' => '不可超过50字符',
        'id_card.idCard' => '请输入正确的格式',
        'address.max' => '不可超过200字符',
    ];
    
    protected $scene = [
        'apply' => ['name', 'sex', 'phone', 'wechat_no', 'id_card', 'address'],
    ];
}