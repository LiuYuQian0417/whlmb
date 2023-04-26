<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class GoodsClassify extends Validate
{
    protected $rule = [
        'title|分类名称'       => 'require',
        'parent_id|分类ID' => 'require',
        'keyword|关键字'   => 'checkKeyword',
        'sort|排序'        => 'require',
    ];

    protected $message = [
        'title.require'     => '不可为空',
        'parent_id.require' => '不可为空',
        'sort.require'      => '不可为空',
    ];

    protected $scene = [
        'create'      => ['title', 'parent_id'],
        'edit'        => ['goods_classify_id', 'title', 'parent_id'],
        'subordinate' => ['parent_id'],
    ];

    protected function checkKeyword ($value, $rule, $data) {
        if (count(explode(',', $value)) > 5) return '最多设置5个';
        return true;
    }
}