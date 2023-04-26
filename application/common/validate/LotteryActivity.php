<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class LotteryActivity extends Validate
{
    protected $rule = [
        'activity_id|活动标题'           => 'require',
        'title|活动标题'                 => 'require',
        'action_rule|活动规则'           => 'require',
        'integral|消耗积分'              => 'require|reg:(^[1-9]\d*$)',
        'is_compensation|是否补偿积分'     => 'require|in:0,1',
        'copy_writer|文案'             => 'require',
    ];

    protected $message = [
        'activity_id.require'             => '不可为空',
        'title.require'                   => '不可为空',
        'action_rule.require'             => '不可为空',
        'integral.require'                => '不可为空',
        'integral.reg'                    => '正整数',
        'is_compensation.require'         => '必须选择',
        'is_compensation.in'              => '格式错误',
        'compensation_integral.requireIf' => '不可为空',
        'compensation_integral.reg'       => '正整数',
        'copy_writer.require'             => '不可为空',
    ];

    protected $scene = [
        'create' => ['title', 'action_rule', 'integral', 'copy_writer', 'is_compensation'],
        'edit'   => ['activity_id', 'title', 'action_rule', 'integral', 'copy_writer', 'is_compensation'],
    ];


    protected function check_compensation_integral_require($_, $data)
    {
        // 是否补偿积分 是否为 是
        return $data['is_compensation'] == 1;
    }



}