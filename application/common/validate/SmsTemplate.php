<?php
declare(strict_types = 1);
namespace app\common\validate;


use think\Validate;

/**
 * 短信模板
 * Class SmsTemplate
 * @package app\common\validate
 */
class SmsTemplate extends Validate
{
    protected $rule = [
        'content|模板内容' => 'require|max:200',
        'temp_id|模板ID'  =>  'require|max:20',
    ];

    protected $message = [
        'content.require'   =>  '不能为空',
        'content.max'   =>  '不能超过200字符',
        'temp_id|require'   =>  '不能为空',
        'temp_id.max'   =>  '不能超过20字符',
    ];

    protected $scene = [
        'createTX'    =>  ['content'],
        'editTX'  =>  ['content'],
        'createAL'  =>  ['content','temp_id'],
        'editAL'  =>  ['content','temp_id'],
    ];
}