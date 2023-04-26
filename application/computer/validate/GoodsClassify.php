<?php
declare(strict_types=1);

namespace app\computer\validate;

use  think\Validate;

class GoodsClassify extends Validate
{
    protected $rule = [
        'title|账号'       => 'require',
        'parent_id|分类ID' => 'require',
        'sort|排序'        => 'require',
    ];

    protected $message = [
        'title.require'     => '不可为空',
        'parent_id.require' => '不可为空',
        'sort.require'      => '不可为空',
    ];

    protected $scene = [
        'create'      => ['title', 'parent_id', 'sort'],
        'edit'        => ['goods_classify_id', 'title', 'parent_id', 'sort'],
        'subordinate' => ['parent_id'],
    ];
}