<?php
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class Limit extends Validate
{
    protected $rule = [
        'member_id|会员信息' => 'require',
        'goods_id|商品信息' => 'require',
        'interval_id|所属场次' => 'require',
        'limit_id|限时抢购' => 'require',
        'up_shelf_time|生效日期' => 'require',
        'down_shelf_time|失效日期'  => ['require', 'gt' => 'up_shelf_time', 'checkEndTime' => 'down_shelf_time'],
        'limit_price|抢购默认价格' => 'require|lt:shop_price',
        'available_sale|售卖上限' => ['require', 'reg' => '(^[+]{0,1}(\d+)$)', 'lt' => 'goods_number'],
        'limit_purchase|活动价格限购数量'     => 'require|lt:available_sale',
        'status|活动状态' => 'require',
        'reason|未通过原因'         => 'requireCallback:check_require',
    ];
    // 验证规则

    // 验证信息
    protected $message = [
        'interval_id.require' => '不能为空',
        'member_id.require' => '不能为空',
        'limit_id.require' => '不能为空',
        'goods_id.require' => '不能为空',
        'limit_price.require' => '不能为空',
        'limit_price.lt' => '小于商品原价',
        'available_sale.require' => '不能为空',
        'available_sale.reg' => '正整数',
        'available_sale.lt' => '不能大于商品库存',
        'up_shelf_time.require' => '不能为空',
        'down_shelf_time.gt' => '应大于生效日期',
        'down_shelf_time.require' => '不能为空',
        'down_shelf_time.checkEndTime'     => '应大于当前日期',
        'status.require' => '不能为空',
        'reason.requireCallback'          => '不能为空',
        'limit_purchase.require'        => '不能为空',
        'limit_purchase.lt'        => '需小于售卖上限',
    ];

    // 验证环境
    protected $scene = [
        'create' => ['goods_id', 'interval_id', 'up_shelf_time', 'down_shelf_time', 'limit_price', 'available_sale',
            'limit_purchase', 'status', 'reason'],
        'edit' => ['limit_id', 'goods_id', 'interval_id', 'up_shelf_time', 'down_shelf_time', 'limit_price', 'available_sale',
            'limit_purchase', 'status', 'reason'],
        'index' => ['interval_id'],
        'client_create' => ['goods_id', 'interval_id', 'up_shelf_time', 'down_shelf_time', 'limit_price', 'available_sale',
            'limit_purchase', 'status'],
        'client_edit' => ['limit_id', 'goods_id', 'interval_id', 'up_shelf_time', 'down_shelf_time', 'limit_price', 'available_sale',
            'limit_purchase', 'status'],
    ];

    protected function check_require($value, $data)
    {
        // 未通过
        if ($data['status'] == 0 && $data['status'] != '') {
            return true;
        }
        return false;
    }

    protected function checkEndTime($value, $rule, $data, $field_name, $message) {
        if ($value <= date('Y-m-d')) return false;
        return true;
    }
}