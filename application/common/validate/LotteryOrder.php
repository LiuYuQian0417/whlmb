<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/22 0022
 * Time: 10:45
 */

namespace app\common\validate;


use think\Validate;

class LotteryOrder extends Validate
{
    protected $rule = [
        'id|订单编号' => 'require',
        'express_value|快递公司' => 'require',
        'express_number|快递单号' => 'require',
    ];

    protected $message = [
        'id' => '不可为空',
        'express_value' => '不可为空',
        'express_number' => '不可为空',
    ];

    protected $scene = [
        // 发货填写物流
        'examine' => ['id', 'express_value', 'express_number'],
    ];

}