<?php
declare(strict_types=1);

namespace app\computer\validate;

use  think\Validate;

class Feedback extends Validate
{
    protected $rule = [
        'type|问题类型'    => 'require',
        'content|问题内容' => 'require',
        'contact|联系方式' => 'require'
    ];

    protected $message = [
        'type.require'    => '不可为空',
        'content.require' => '不可为空',
        'contact.require' => '不可为空'
    ];

    protected $scene = [
        'create' => ['type', 'content', 'contact']
    ];
}