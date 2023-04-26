<?php
// 积分商品分类
declare(strict_types = 1);

namespace app\common\validate;

use think\Validate;

class IntegralClassify extends Validate
{
    // 验证规则
    protected $rule = [
        'integral_classify_id|分类编号' => 'require',
        'title|分类名称'                => 'require|max:20',
    ];

    // 提示信息
    protected $message = [
        'integral_classify_id.require' => '不能为空',
        'title.require'                => '不能为空',
        'title.max'                    => '不能超过20个字'
    ];

    // 验证场景
    protected $scene = [
        'create' => ['title'],
        'edit'   => ['integral_classify_id,title'],
    ];
}