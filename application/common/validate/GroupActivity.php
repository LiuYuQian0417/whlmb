<?php
// 团购活动
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class GroupActivity extends Validate
{
    // 验证规则
    protected $rule = [
        'group_activity_attach_id|拼团附表信息' => 'require'
    ];

    // 验证信息
    protected $message = [
        'group_activity_attach_id.require' => '不能为空',
    ];

    // 验证场景
    protected $scene = [
        'view' => ['group_activity_attach_id'],
    ];
}