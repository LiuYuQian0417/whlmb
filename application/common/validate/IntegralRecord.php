<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class IntegralRecord extends Validate
{
    protected $rule = [
        'member_id|会员信息' => 'require',
    ];

    protected $message = [
        'member_id.require' => '不可为空',
    ];

    protected $scene = [
        'sign'   => ['member_id'],
    ];
}