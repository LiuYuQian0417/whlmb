<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class StoreClassify extends Validate
{
    protected $rule = [
        'title|主营类目名称'       => 'require',
    ];

    protected $message = [
        'title.require'     => '不可为空',
    ];

    protected $scene = [
        'create'      => ['title'],
        'edit'        => ['store_classify_id', 'title'],
    ];
}