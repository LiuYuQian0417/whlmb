<?php
declare(strict_types=1);

namespace app\computer\validate;

use app\common\model\SignTask as SignTaskModel;
use  think\Validate;

class SignTask extends Validate
{
    protected $rule = [
        'member_id|' => ['is_sign'],
    ];

    protected $scene = [
        'sign' => ['member_id'],
    ];

    // 判断是否签到
    protected function is_sign($value, $rule)
    {

        $sign_task_id = (new SignTaskModel())->where(['member_id' => $value, 'create_time' => date('Y-m-d')])->value('sign_task_id');

        // 返回值
        return !empty($sign_task_id) ? '今天签到已完成,请明天再来!' : true;

    }
}