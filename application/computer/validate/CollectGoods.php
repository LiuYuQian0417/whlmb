<?php
// 商品收藏验证
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

class CollectGoods extends Validate
{
    // 验证条件
    protected $rule = [
        'goods_id|商品信息'  => 'require',
    ];


    // 验证信息
    protected $message = [
        'goods_id.require'  => '不能为空'
    ];

    // 场景验证
    protected $scene = [
        'collect_cart' => ['goods_id']
    ];
}
