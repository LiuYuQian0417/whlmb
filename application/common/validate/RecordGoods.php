<?php
// 商品浏览记录验证器
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class RecordGoods extends Validate
{
    // 验证规则
    protected $rule = [
        'record_goods_id|记录信息' => 'require'
    ];

    // 验证信息
    protected $message = [
        'record_goods_id.require' => '不可为空',
    ];

    // 验证场景
    protected $scene = [

    ];
}