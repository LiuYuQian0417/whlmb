<?php
declare(strict_types=1);

namespace app\computer\validate;

use  think\Validate;

class StoreGoodsClassify extends Validate
{
    protected $rule = [
        'store_id|店铺信息' => 'require'
    ];

    protected $message = [
        'store_id.require' => '不可为空'
    ];

    protected $scene = [
        'classify_list'     => ['store_id'],
        'hot_classify_list' => ['store_id'],
    ];
}