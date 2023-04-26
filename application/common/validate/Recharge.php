<?php
// 优惠券验证器
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class Recharge extends Validate
{
    // 验证规则
    protected $rule = [
        'recharge_id|充值信息'               => 'require',
        'recharge_money|充值金额'           => ['require', 'reg' => '(^[1-9]\d*$)'],
        'award_money|奖励金额'  => ['require', 'reg' => '(^[0-9]\d*$)', 'lt' => 'recharge_money'],
    ];

    // 验证信息
    protected $message = [
        'recharge_id.require'              => '不可为空',
        'recharge_money.require'           => '不可为空',
        'recharge_money.reg'               => '正整数',
        'award_money.require'              => '不可为空',
        'award_money.reg'                  => '正整数',
        'award_money.lt'                   => '应小于充值金额',

    ];

    // 验证场景
    protected $scene = [
        'create'           => ['recharge_money', 'award_money'],
        'edit'             => ['recharge_id', 'recharge_money', 'award_money'],
    ];
}