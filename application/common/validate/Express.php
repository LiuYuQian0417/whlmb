<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Express extends Validate
{
    protected $rule = [
        'order_attach_id|订单id' => 'require',
    ];

    protected $message = [
        'order_attach_id.require' => '不可为空',
    ];

    protected $scene = [
        'dada' => ['order_attach_id'],
    ];
}