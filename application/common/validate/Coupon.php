<?php
// 优惠券验证器
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class Coupon extends Validate
{
    // 验证规则
    protected $rule = [
        'member_id|会员信息'               => 'require',
        'store_id|店铺信息'                => 'require',
        'coupon_id|优惠券编号'              => 'require',
        'title|优惠券标题'                  => 'require|max:12',
        'classify_str|所属店铺'       => 'require',
        'file|缩略图'                      => 'require',
        'full_subtraction_price|满减金额'  => ['require', 'reg' => '(^[0-9]\d*$)', 'checkFullSubtractionPrice' => 'actual_price,is_no_threshold', 'max' => 5, 'checkIsNoThreshold' => 'actual_price,is_no_threshold'],
        'actual_price|优惠券金额'           => ['require', 'reg' => '(^[0-9]\d*$)', 'max' => 5],
        'total_num|优惠券数量'              => 'require|number',
        'limit_num|每人限领数量'             => 'require|gt:0',
        'start_time|使用开始日期'            => 'require',
        'end_time|使用结束日期'              => 'require|egt:start_time',
        'receive_start_time|领取开始日期' => 'require|elt:start_time',
        'receive_end_time|领取结束日期'   => 'require|egt:receive_start_time|elt:end_time',
    ];

    // 验证信息
    protected $message = [
        'member_id.require'              => '不可为空',
        'store_id.require'               => '不可为空',
        'coupon_id.require'              => '不可为空',
        'limit_num.require'              => '不可为空',
        'limit_num.gt'                   => '限领数必须大于0',
        'classify_str.require'           => '不可为空',
        'file.require'                   => '不可为空',
        'title.require'                  => '不可为空',
        'title.max'                      => '不能超过20字',
        'actual_price.require'           => '不能为空',
        'actual_price.reg'               => '正整数(包含0)',
        'actual_price.max'               => '不能超过5位数',
        'full_subtraction_price.require' => '不能为空',
        'full_subtraction_price.reg'     => '正整数(包含0)',
        'full_subtraction_price.checkFullSubtractionPrice'     => '大于优惠金额',
        'full_subtraction_price.checkIsNoThreshold'     => '只能为0',
        'full_subtraction_price.max'     => '不能超过5位数',
        'total_num.require'              => '不能为空',
        'start_time.require'             => '不能为空',
        'end_time.require'               => '不能为空',
        'receive_start_time.require'     => '不能为空',
        'receive_end_time.require'       => '不能为空',
        // 大禹添加
        'full_subtraction_price.egt'      => '必须大于优惠券金额',
        'end_time.egt'                   => '错误',
        'receive_start_time.elt'         => '必须小于等于使用开始时间',
        'receive_end_time.egt'           => '必须大于等于领取开始时间',
        'receive_end_time.elt'           => '必须小于等于使用结束时间',
        'actual_price.float'             => '格式错误',
        'actual_price.gt'                => '必须大于0',
        'full_subtraction_price.float'   => '格式错误',
        'total_num.number'               => '不能为空',
        'total_num.gt'                   => '不能为空',
        'limit_num.number'               => '格式错误',
        'description.require'            => '不可为空',
        'description.max'                => '不能超过100字',
        'is_integral_exchage.require'    => '不可为空',
        'is_integral_exchage.in'         => '格式错误',
        'integral.requireIf'             => '不可为空',
        'integral.number'                => '格式错误',
        'integral.gt'                    => '必须大于0',
        'is_recommend.require'           => '不能为空',
        'is_recommend.in'                => '格式错误',
        'status.require'                 => '不能为空',
        'status.in'                      => '格式错误',
        'start_time.dateFormat'          => '格式错误',
        'end_time.dateFormat'            => '格式错误',
        'receive_start_time.dateFormat'  => '格式错误',
        'receive_end_time.dateFormat'    => '格式错误',
    ];

    // 验证场景
    protected $scene = [
        'create'           => [
            'title',
            'actual_price',
            'full_subtraction_price',
            'total_num',
            'start_time',
            'end_time',
            'receive_start_time',
            'receive_end_time',
            'classify_str',
            'limit_num',
            'file',
        ],
        'edit'             => [
            'coupon_id',
            'title',
            'actual_price',
            'full_subtraction_price',
            'total_num',
            'start_time',
            'end_time',
            'receive_start_time',
            'receive_end_time',
            'classify_str',
            'limit_num',
            'file',
        ],
        'exchange_view'    => ['coupon_id'],
        'goods_list'       => ['coupon_id'],
        'coupon_list'      => ['store_id'],
        'gift_coupon_list' => ['member_id'],

        'client_create' => [
            // 优惠券标题
            'title|优惠券标题'                      => [
                'require',
                'max:20',
            ],
            // 优惠券金额
            'actual_price|优惠券金额'               => [
                'require',
                'float',
                'gt:0',
            ],
            // 优惠券满减条件金额
            'full_subtraction_price|优惠券满减条件金额' => [
                'require',
                'gt:actual_price',
                'float',
            ],
            // 优惠券总数量
            'total_num|优惠券总数量'                 => [
                'require',
                'number',
                'gt:0',
            ],
            // 每人限领数量
            'limit_num|每人限领数量'                 => [
                'require',
                'number',
                'gt:0',
            ],
            // 描述
            'description|描述'                   => [
                'require',
                'max:100',
            ],
            // 是否积分兑换
            'is_integral_exchage|是否积分兑换'       => [
                'require',
                'in:0,1',
            ],
            // 需要兑换的积分
            'integral|需要兑换的积分'                 => [
                'requireIf:is_integral_exchage,1',
                'number',
                'gt:0',
            ],
            // 启用状态
            'status|启用状态'                      => [
                'require',
                'in:0,1',
            ],
            // 使用开始时间
            'start_time|使用开始时间'                => [
                'require',
                'dateFormat:y-m-d',
            ],
            // 使用结束时间
            'end_time|使用结束时间'                  => [
                'require',
                'dateFormat:y-m-d',
                'egt:start_time',
            ],
            // 领取开始时间
            'receive_start_time|领取开始时间'        => [
                'require',
                'dateFormat:y-m-d',
                'elt:start_time',
            ],
            // 领取结束时间
            'receive_end_time|领取结束时间'          => [
                'require',
                'dateFormat:y-m-d',
                'egt:receive_start_time',
                'elt:end_time',
            ],
        ],
    ];

    protected function checkFullSubtractionPrice($value, $rule, $data, $field_name, $message)
    {
        if ($data['is_no_threshold'] != 1) {
            if ($value <= $data['actual_price']) return false;
        }
        return true;
    }

    protected function checkIsNoThreshold ($value, $rule, $data, $field_name, $message) {

        if ($data['is_no_threshold'] == 1) {
            if ($value != 0) return false;
        }
        return true;
    }
}