<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/26 0026
 * Time: 9:09
 */

namespace app\common\validate;


use think\Validate;

class FreightExpressClassify extends Validate
{
    // 验证规则
    protected $rule = [
        'id|分类ID' => 'require',
    ];


    // 验证信息
    protected $message = [
        'freight_express_classify_id.require' => '不能为空',

    ];

    // 验证场景
    protected $scene = [
        'destroy' => [
            'id'
        ],
    ];
}