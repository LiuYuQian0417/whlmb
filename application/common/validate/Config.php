<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Config extends Validate
{
    protected $rule = [
        'confirm_receipt|自动确认收货天数'      => ['require', 'reg' => '(^[1-9]\d*$)'],
        'good_reputation|自动好评天数'          => ['require', 'reg' => '(^[1-9]\d*$)'],
        'after_sale|售后期限天数'               => ['require', 'reg' => '(^[1-9]\d*$)'],
        'integral_conversion|积分换算比例'      => ['require', 'reg' => '(^[0-9]\d*$)', 'lt' => 100],
        'integral_info|完善个人信息'            => ['require', 'reg' => '(^[0-9]\d*$)'],
        'integral_phone|绑定手机号码'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'integral_third_party|绑定三方社交账号'  => ['require', 'reg' => '(^[0-9]\d*$)'],
        'integral_sign|签到初始值'                 => ['require', 'reg' => '(^[0-9]\d*$)'],
        'integral_evaluate|评价商品'             => ['require', 'reg' => '(^[0-9]\d*$)'],
        'integral_evaluate_number|评价商品（每日限定次数）'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'integral_share|分享商品或活动'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'integral_share_number|分享商品或活动（每日限定次数）'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'integral_adv|浏览广告'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_conversion|成长值换算比例'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_info|完善个人信息'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_phone|绑定手机号码'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_third_party|绑定三方社交账号'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_monthly_shopping|每月购物3天'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_age_limit|购物年限'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_evaluate|评价商品'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_evaluate_number|评价商品（每日限定次数）'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_share|分享商品或活动'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_share_number|分享商品或活动（每日限定次数）'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_adv|浏览广告'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_balance|使用余额支付'           => ['require', 'reg' => '(^[0-9]\d*$)'],
        'growth_balance_number|使用余额支付（每日限定成长值）'           => ['require', 'reg' => '(^[0-9]\d*$)'],
    ];

    protected $message = [
        'confirm_receipt.require'       => '不可为空',
        'confirm_receipt.reg'           => '为至少为1的正整数',
        'good_reputation.require'       => '不可为空',
        'good_reputation.reg'           => '为至少为1的正整数',
        'good_reputation.lt'            => '不能大于自动确认收货天数',
        'after_sale.require'            => '不可为空',
        'after_sale.reg'                => '为至少为1的正整数',
        'after_sale.lt'                 => '不能大于自动好评天数',
        'integral_conversion.require'      => '不可为空',
        'integral_conversion.reg'      => '为正整数',
        'integral_info.require'      => '不可为空',
        'integral_info.reg'      => '为正整数',
        'integral_phone.require'      => '不可为空',
        'integral_phone.reg'      => '为正整数',
        'integral_third_party.require'      => '不可为空',
        'integral_third_party.reg'      => '为正整数',
        'integral_sign.require'      => '不可为空',
        'integral_sign.reg'      => '为正整数',
        'integral_evaluate.require'      => '不可为空',
        'integral_evaluate.reg'      => '为正整数',
        'integral_evaluate_number.require'      => '不可为空',
        'integral_evaluate_number.reg'      => '为正整数',
        'integral_share.require'      => '不可为空',
        'integral_share.reg'      => '为正整数',
        'integral_share_number.require'      => '不可为空',
        'integral_share_number.reg'      => '为正整数',
        'integral_adv.require'      => '不可为空',
        'integral_adv.reg'      => '为正整数',
        'growth_conversion.require'      => '不可为空',
        'growth_conversion.reg'      => '为正整数',
        'growth_info.require'      => '不可为空',
        'growth_info.reg'      => '为正整数',
        'growth_phone.require'      => '不可为空',
        'growth_phone.reg'      => '为正整数',
        'growth_third_party.require'      => '不可为空',
        'growth_third_party.reg'      => '为正整数',
        'growth_monthly_shopping.require'      => '不可为空',
        'growth_monthly_shopping.reg'      => '为正整数',
        'growth_age_limit.require'      => '不可为空',
        'growth_age_limit.reg'      => '为正整数',
        'growth_evaluate.require'      => '不可为空',
        'growth_evaluate.reg'      => '为正整数',
        'growth_evaluate_number.require'      => '不可为空',
        'growth_evaluate_number.reg'      => '为正整数',
        'growth_share.require'      => '不可为空',
        'growth_share.reg'      => '为正整数',
        'growth_share_number.require'      => '不可为空',
        'growth_share_number.reg'      => '为正整数',
        'growth_adv.require'      => '不可为空',
        'growth_adv.reg'      => '为正整数',
        'growth_balance.require'      => '不可为空',
        'growth_balance.reg'      => '为正整数',
        'growth_balance_number.require'      => '不可为空',
        'growth_balance_number.reg'      => '为正整数',
    ];

    protected $scene = [
        'order_settings'      => ['confirm_receipt', 'good_reputation', 'after_sale'],
        'integral_settings'      => ['integral_conversion', 'integral_info', 'integral_phone', 'integral_third_party',
            'integral_sign', 'integral_evaluate', 'integral_evaluate_number', 'integral_share', 'integral_share_number',
            'integral_adv'],
        'growth_settings'      => ['growth_conversion', 'growth_info', 'growth_phone', 'growth_third_party',
            'growth_evaluate', 'growth_evaluate_number', 'growth_share',
            'growth_share_number', 'growth_adv', 'growth_balance', 'growth_balance_number'],
    ];
}