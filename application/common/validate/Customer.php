<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/19 0019
 * Time: 8:53
 */

namespace app\common\validate;


use think\Validate;

class Customer extends Validate
{
    // 验证规则
    protected $rule = [
        'id|ID'                 => 'require',
        'customer_group_id|客服组' => 'require|number',
        'account|客服账号'          => 'require|length:11',
        'password|密码'           => 'require|max:32',
        'repassword|确认密码'       => 'require|confirm:password',
        'img|头像'                => 'require',
        'name|客服姓名'             => 'require|max:20',
        'nickname|客服昵称'         => 'require|max:20',];

    protected $message = [
        'id'                        => '不能为空',
        'customer_group_id.require' => '必选',
        'customer_group_id.number'  => '格式错误',
        'account.require'           => '必填',
        'account.length'            => '格式错误',
        'password.require'          => '必填',
        'password.max'              => '不能超过32个字符',
        'repassword.require'        => '必填',
        'repassword.confirm'        => '与密码不符',
        'name.require'              => '必填',
        'name.max'                  => '不能超过20个字符',
        'nickname.require'          => '必填',
        'nickname.max'              => '不能超过20个字符',
        'img.require'               => '必须上传',
    ];

    protected $scene = [
        // 创建
        'create'  => [
            'customer_group_id',
            'account',
            'password',
            'repassword',
            'name',
//            'img',
            'nickname',
        ],
        'update'  => [
            'customer_group_id',
            'account',
            'password'   => 'max:32',
            'repassword' => 'requireWith:password|confirm:password',
            'name',
//            'img',
            'nickname',
        ],
        'destroy' => [
            'id',
        ],
        'login'   => [
            'account',
            'password'
        ],
    ];
}