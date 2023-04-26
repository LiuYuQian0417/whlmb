<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: Hhd
 * Date: 2019/3/12
 * Time: 10:51
 */


namespace app\common\validate;

use  think\Validate;


class Icon extends Validate
{
    protected $rule = [
        'title|图标名字'     => 'require',
        'img|图标'     => 'require',
    ];

    protected $message = [
        'title.require'     => '不能为空',
        'img.require'     => '不能为空',
    ];

    protected $scene = [
        'master_create' => ['title', 'img'],
    ];
}