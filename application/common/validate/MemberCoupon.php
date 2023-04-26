<?php
// 我的优惠券验证器
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class MemberCoupon extends Validate
{
    // 验证规则
    protected $rule = [
        'coupon_id|优惠券编号' => 'require',
        'status|状态信息'     => 'require',
    ];

    // 验证信息
    protected $message = [
        'coupon_id.require' => '不可为空',
        'status.require'    => '不可为空',

    ];

    // 验证场景
    protected $scene = [
        'get'      => ['coupon_id'],
        'exchange' => ['coupon_id'],
    ];
}