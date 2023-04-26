<?php
declare(strict_types=1);

namespace app\common\validate;


use think\Validate;

/**
 * 佣金提现记录
 * Class DistributionWithdraw
 * @package app\common\validate
 */
class DistributionWithdraw extends Validate
{

    protected $rule = [
        'distribution_id|分销商id' => 'require',
        'price|提现金额' => "require",
        'distribution_type|提现方式' => 'require|in:1,2,3',
    ];

    protected $message = [
        'distribution.require' => '不可为空',
        'price.require' => '不可为空',
        'price.egt' => '不可低于1.00元',
        'distribution_type.require' => '不可为空',
        'distribution_type.in' => '范围错误'
    ];

    protected $scene = [
        'to_apply' => ['distribution_id', 'price', 'distribution_type'],
    ];
}