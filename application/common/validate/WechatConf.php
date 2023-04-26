<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/21 0021
 * Time: 8:42
 */

namespace app\common\validate;


use think\Validate;

class WechatConf extends Validate
{
    protected $rule = [
        'content|内容' => 'require',
    ];

    protected $message = [
        'content.require' => '不可为空',
    ];

    protected $scene = [
        'create' => 'content'
    ];
}