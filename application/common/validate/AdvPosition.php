<?php
declare(strict_types = 1);
namespace app\common\validate;

use  think\Validate;

class AdvPosition extends Validate
{
    protected $rule = [
        'title|账号'       => 'require',
        'type|图片适用类型'    => 'require',
        'parent_id|投放端' => 'require',
        'width|宽度'       => 'require',
        'height|高度'      => 'require',
    ];

    protected $message = [
        'title.require'     => '不可为空',
        'type.require'      => '不可为空',
        'parent_id.require' => '不可为空',
        'width.require'     => '不可为空',
        'height.require'    => '不可为空',
    ];

    protected $scene = [
        'create' => ['title', 'type', 'parent_id', 'width', 'height'],
        'edit'   => ['adv_position_id', 'title', 'type', 'parent_id', 'width', 'height'],
    ];
}