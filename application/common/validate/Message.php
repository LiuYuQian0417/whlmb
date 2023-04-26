<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Message extends Validate
{
    protected $rule = [
        'type|状态'             => 'require',
        'member_id|会员信息'    => 'require',
        'title|标题'            => 'require|max:120',
        'describe|描述'         => 'require',
    ];

    protected $message = [
        'type.require'         => '不可为空',
        'member_id.require'    => '不可为空',
        'title.require'        => '不可为空',
        'describe.require'     => '不可为空',
    ];

    protected $scene = [
        'interfaces_index'      => ['type'],
        'create'                => ['type', 'member_id', 'title', 'describe'],
    ];
}