<?php
// 商品评论表
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

class GoodsEvaluate extends Validate
{
    // 验证规则
    protected $rule = [
        'goods_evaluate_id|编号'  => 'require',
        'goods_id|商品信息'         => 'require',
        'status|审核状态'           => 'require',
        'member_id|用户信息'        => 'require',
        'order_goods_id|商品订单信息' => 'require',
    ];

    // 验证信息
    protected $message = [
        'goods_evaluate_id.require' => '不能为空',
        'goods_id.require'          => '不能为空',
        'status.require'            => '不能为空',
        'member_id.require'         => '不能为空',
        'order_goods_id'            => '不能为空',
    ];

    protected $scene = [
        'edit'          => ['status,goods_evaluate_id'],
        'evaluate_list' => ['goods_id'],
    ];
}