<?php
declare(strict_types = 1);
namespace app\common\validate;

use think\Validate;

/**
 * 权限组
 * Class AuthGroup
 * @package app\common\validate
 */
class AuthGroup extends Validate
{
    protected $rule = [
        'title|组标题' =>  'require|max:10',
        'describe|组描述'  =>  'require|max:300'
    ];

    protected $message = [
        'title.require' =>  '不能为空',
        'title.max' =>  '不能超过10字符',
        'describe.require'  =>  '不能为空',
        'describe.max'  =>  '不能超过300字符',
    ];

    protected $scene = [
        'create'    =>  ['title','describe'],
        'edit'    =>  ['title','describe'],
    ];
}