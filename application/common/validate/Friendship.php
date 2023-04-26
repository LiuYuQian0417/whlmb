<?php
declare(strict_types = 1);

namespace app\common\validate;

use think\Validate;

class Friendship extends Validate
{
    // 验证规则
    protected $rule = [
        'friendship_link_id|编号' => 'require',
        'friendship_title|链接标题' => 'require',
        'friendship_url|链接地址'   => 'require',
    ];


    // 验证信息
    protected $message = [
        'friendship_link_id.require' => '不可为空',
        'friendship_title.require'   => '不可为空',
        'friendship_url.require'     => '不可为空',
    ];


    // 验证场景
    protected $scene = [
        'create' => ['friendship_title,friendship_url'],
        'edit'   => ['friendship_title,friendship_url,friendship_link_id'],
    ];
}