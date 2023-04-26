<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Manage extends Validate
{
    protected $rule = [
        'password|密码' => 'require',
        'nickname|账号' => 'require|alphaNum',
        'phone|手机号'   => 'require|mobile',
    ];

    protected $message = [
        'password.require'  => '不可为空',
        'nickname.require'  => '不可为空',
        'nickname.alphaNum' => '格式不正确',
        'phone.require'     => '不可为空',
        'phone.mobile'      => '格式不正确',
    ];

    protected $scene = [
        'login'  => ['nickname', 'password'],
        'create' => ['password', 'nickname', 'phone'],
        'edit'   => ['nickname', 'phone'],
    ];
}