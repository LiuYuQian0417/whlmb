<?php
// 砍价活动验证
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class CutGoods extends Validate
{
    // 验证条件
    protected $rule = [
        'goods_id|商品信息'         => 'require',
        'cut_goods_id|编号'       => 'require',
        'cut_price|砍价最低值' => ['require', 'reg' => '(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)', 'lt' => 'shop_price'],
        'up_shelf_time|生效日期'    => 'require',
        'down_shelf_time|失效日期'  => ['require', 'gt' => 'up_shelf_time', 'checkEndTime' => 'down_shelf_time'],
        'continue_time|活动有效时间'  => ['require', 'reg' => '(^[1-9]\d*$)', 'checkTime' => 'up_shelf_time,down_shelf_time'],
        'available_sale|售卖上限'   => ['require', 'reg' => '(^[+]{0,1}(\d+)$)', 'lt' => 'goods_number'],
        'single_cut_min|砍价单刀最低值' => 'require|lt:shop_price',
        'single_cut_max|砍价单刀最高值' => 'require|gt:single_cut_min',
        'status|审核状态'           => 'require',
        'reason|未通过原因'         => 'requireCallback:check_require',
        'price|价格'              => 'require',
    ];


    // 验证信息
    protected $message = [
        'cut_goods_id.require'    => '不能为空',
        'goods_id.require'        => '不能为空',
        'cut_price.require'       => '不能为空',
        'cut_price.reg'           => '正整数或两位小数',
        'cut_price.lt'            => '小于商品原价',
        'up_shelf_time.require'   => '不能为空',
        'down_shelf_time.require' => '不能为空',
        'down_shelf_time.gt'     => '应大于生效日期',
        'down_shelf_time.checkEndTime'     => '应大于当前日期',
        'continue_time.require'   => '不能为空',
        'continue_time.checkTime' => '不能大于生效至失效日期范围',
        'continue_time.reg'       => '正整数（不含0）',
        'available_sale.require'  => '不能为空',
        'available_sale.reg'      => '正整数',
        'available_sale.lt'       => '不能大于商品库存',
        'single_cut_min.require'  => '不能为空',
        'single_cut_min.lt'       => '小于商品原价',
        'single_cut_max.require'  => '不能为空',
        'single_cut_max.gt'       => '大于砍价单刀最低值',
        'status.require'          => '不能为空',
        'reason.require'          => '不能为空',
        'price.require'           => '不能为空',
    ];

    // 场景验证
    protected $scene = [
        'create'      => ['goods_id', 'cut_price', 'up_shelf_time', 'down_shelf_time', 'single_cut_min', 'single_cut_max', 'status', 'continue_time'
        ,'buy_cum_limit', 'available_sale', 'reason'],
        'edit'        => ['cut_goods_id', 'cut_price', 'goods_id', 'up_shelf_time', 'down_shelf_time', 'single_cut_min', 'single_cut_max', 'status', 'continue_time'
        ,'buy_cum_limit', 'available_sale', 'reason'],
        'immediately' => ['goods_id', 'price']
    ];

    protected function check_require($value, $data)
    {
        // 未通过
        if ($data['status'] == 0 && $data['status'] != '') {
            return true;
        }
        return false;
    }

    protected function checkTime($value, $rule, $data, $field_name, $message)
    {
        $hour = floor((strtotime($data['down_shelf_time'])-strtotime($data['up_shelf_time']))/86400*24);

        if ($value > $hour) {
            return false;
        }
        return true;
    }

    protected function checkEndTime($value, $rule, $data, $field_name, $message) {
        if ($value <= date('Y-m-d')) return false;
        return true;
    }
}
