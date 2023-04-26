<?php
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

class Limit extends Validate
{
    protected $rule = [
        'member_id|会员信息' => 'require',
        'interval_id|分类信息' => 'require',
        'limit_id' => 'require',
        'goods_id' => 'require',
        'limit_price' => 'require',
        // 'effective_time' => 'require',
        'available_sale|可售数量' => 'require|lt:goods_number',
        'up_shelf_time|上架时间' => 'require',
        'down_shelf_time|下架时间' => 'require',
        'status' => 'require',
//        'classify'         => 'require'
    ];
    // 验证规则

    // 验证信息
    protected $message = [
        'interval_id.require' => '不能为空',
        'member_id.require' => '不能为空',
        'limit_id.require' => '不能为空',
        'goods_id.require' => '不能为空',
        'limit_price.require' => '不能为空',
        // 'effective_time.require' => '不能为空',
        'available_sale.require' => '不能为空',
        'available_sale.lt' => '不能大于商品库存',
        'up_shelf_time.require' => '不能为空',
        'down_shelf_time.require' => '不能为空',
        'status.require' => '不能为空',
//        'classify.require'        => '不能为空',
    ];

    // 验证环境
    protected $scene = [
        'create' => ['goods_id', 'limit_price', 'effective_time',
            'available_sale', 'up_shelf_time', 'down_shelf_time', 'status'],
        'edit' => ['goods_id', 'limit_price', 'effective_time',
            'available_sale', 'up_shelf_time', 'down_shelf_time', 'status', 'classify', 'limit_id'],
        'index' => ['interval_id']
    ];
}