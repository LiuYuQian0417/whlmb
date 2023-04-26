<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/13 0013
 * Time: 9:44
 */

namespace app\daemonService\customer;


use think\Validate as ThinkValidate;

class Validate extends ThinkValidate
{
    protected $rule = [
        'type'             => 'require|in:TEXT,LOGIN,IMAGE,GOODS,ORDER,SHOW_CLIENTS,SHOW_CUSTOMER_TABLE,SHOW_USER_TABLE',
        'data'             => 'require',
        'member_id'        => 'require|number|max:25',
        'user_type'        => 'require|in:CUSTOMER,USER',
        'message'          => 'require',
        'image_url'        => 'require',
        'goods_id'         => 'require',
        'target_type'      => 'require|in:USER,CUSTOMER',
        'target_member_id' => 'require|number|max:25',
        'target_store_id'  => 'requireIf:target_type,CUSTOMER',
        'self_type'        => 'require|in:USER,CUSTOMER',
        'self_member_id'   => 'require|number|max:25',
        'self_store_id'    => 'requireIf:self_type,CUSTOMER',

    ];

    protected $message = [
        'type.require' => '错误的消息格式',
        'type.in'      => '错误的消息类型',
        'data.require' => '错误的消息类型',

        'member_id.require' => '缺少参数<member_id>',
        'member_id.number'  => '<member_id>参数格式错误',
        'member_id.max'     => '<member_id>参数格式错误',

        'user_type.require' => '缺少参数<message_type>',
        'user_type.in'      => '<message_type>参数格式错误',

        'message.require' => '',

        'image_url.require' => '图片格式错误',

        'goods_id.require' => '',
    ];

    protected $scene = [
        // 数据
        'type'                => [
            'type',
            'data',
        ],
        // 全局字段
        'GLOBAL_KEY'          => [
            'member_id',
            'user_type',
        ],
        // 登录
        'LOGIN'               => [
            'member_id',
            'user_type',
        ],
        // 文字
        'TEXT'                => [
            // 消息
            'message',
            // 对方的类型
            'target_type',
            // 对方的店铺ID
            'target_store_id',
            // 对方的member_id
            'target_member_id',
            // 自己的类型
            'self_type',
            // 自己的店铺ID
            'self_store_id',
            // 自己的member_id
            'self_member_id',
        ],
        // 图片
        'IMAGE'               => [
            'image_url',
        ],
        // 商品
        'GOODS'               => [
            'goods_id',
        ],
        // 打印用户table
        'SHOW_USER_TABLE'     => [
            'message',
        ],
        // 打印商家table
        'SHOW_CUSTOMER_TABLE' => [
            'message',
        ],
    ];

}