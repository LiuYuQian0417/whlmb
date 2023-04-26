<?php
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class RedPacket extends Validate
{
    // 验证规则
    protected $rule = [
        'red_packet_id|红包编号'           => 'require',
        'type|归属分类'                    => 'require',
        'genre|红包属性'                   => 'require',
        'end_time|使用时间区间'              => 'gt:start_time',
        'receive_start_time|红包领取时间区间' => 'lt:start_time',
        'receive_end_time|红包领取时间区间'   => 'gt:receive_start_time|lt:end_time',
        'max_actual_price|随机红包最高金额'    => 'lt.min_actual_price'
    ];


    // 验证信息
    protected $message = [
        'coupon_id.require'         => '不可为空',
        'type.require'              => '不可为空',
        'genre.require'             => '不可为空',
        'full_subtraction_price.gt' => '必须大于红包金额',
        'end_time.gt'               => '错误',
        'receive_start_time.lt'     => '错误',
        'receive_end_time.gt'       => '错误',
        'receive_end_time.lt'       => '错误',
        'max_actual_price.lt'       => '错误',
    ];

    protected $scene = [
        'create'          => ['type', 'genre', 'end_time', 'receive_start_time', 'receive_end_time', 'max_actual_price'],
        'platform_create' => ['type', 'genre'],
    ];
}