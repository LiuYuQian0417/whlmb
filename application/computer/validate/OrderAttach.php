<?php
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

/**
 * 订单附表验证
 * Class OrderAttach
 * @package app\computer\validate
 */
class OrderAttach extends Validate
{
    protected $rule = [
        'distribution_type|配送方式'    => 'require',
        'order_attach_id|订单附表id'    => 'require',
        'order_attach_id|编号信息'      => 'require',
        'order_id|订单编号'             => 'require',
        'status|状态值'                => 'require',
        'express_value|快递公司'        => 'require',
        'express_number|快递单号'       => 'require',
        'distribution_tel|配送人员联系方式' => 'require',
        'shop_no|门店'                => 'require',
        'city_code|所在城市'            => 'require',
        'member_id|会员'              => 'require',
        'store_id|店铺id'             => 'require',
    ];

    protected $message = [
        'distribution_type' => '不可为空',
        'order_attach_id'   => '不可为空',
        'order_id'          => '不可为空',
        'status'            => '不可为空',
        'express_value'     => '不可为空',
        'express_number'    => '不可为空',
        'distribution_tel'  => '不可为空',
        'shop_no'           => '不可为空',
        'city_code'         => '不可为空',
        'member_id'         => '不可为空',
        'store_id'          => '不可为空',
    ];

    protected $scene = [
        // 订单列表
        'orderList'         => ['distribution_type'],
        // 订单详情
        'orderDetails'      => ['order_attach_id'],
        // 取消订单
        'cancel'            => ['order_attach_id'],
        // 删除订单
        'destroyOrder'      => ['order_attach_id'],
        // 发货填写物流
        'examine'           => ['order_attach_id', 'order_id', 'status', 'express_value', 'express_number'],
        // 自主配送
        'local'             => ['order_attach_id', 'order_id', 'status', 'distribution_tel'],
        // 达达配送
        'dada'              => ['order_attach_id', 'order_id', 'status', 'shop_no', 'city_code'],
        //客服订单列表
        'customerList'      => ['store_id', 'member_id'],

    ];
}