<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Invite extends Validate
{
    protected $rule = [
        'member_id|会员信息' => 'require',
        'phone|手机号'      => ['require', 'unique:member', 'mobile'],
        'code|验证码'       => 'require',
    ];

    protected $message = [
        'member_id.require' => '不可为空',
        'phone.require'     => '不可为空',
        'code.require'      => '不可为空',
    ];

    protected $scene = [
        'register' => ['member_id', 'phone', 'code'],
    ];
}