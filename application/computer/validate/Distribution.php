<?php
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

/**
 * 分销商验证类
 * Class Distribution
 * @package app\computer\validate
 */
class Distribution extends Validate
{
    protected $rule = [
        'name|姓名' => 'max:20',
        'phone|联系方式' => 'mobile',
        'wechat_no|微信号' => 'max:50',
        'id_card|身份证号' => 'idCard',
        'address|地址' => 'max:200',
    ];

    protected $message = [
        'name.max' => '不可超过20字符',
        'phone.mobile' => '请输入正确的格式',
        'wechat_no.max' => '不可超过50字符',
        'id_card.idCard' => '请输入正确的格式',
        'address.max' => '不可超过200字符',
    ];

    protected $scene = [
        'apply' => ['name', 'phone', 'wechat_no', 'id_card', 'address'],
    ];
}