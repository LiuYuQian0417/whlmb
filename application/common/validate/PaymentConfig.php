<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class PaymentConfig extends Validate
{
    protected $rule = [
        'name|支付方式名称'     => 'require',
        'describe|支付方式描述' => 'require',
    ];

    protected $message = [
        'name.require'     => '不可为空',
        'describe.require' => '不可为空',
    ];

    protected $scene = [
        'create' => ['name', 'describe'],
        'edit'   => ['name', 'describe'],
    ];
}