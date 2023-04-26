<?php
// 团购商品
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class GroupGoods extends Validate
{
    // 验证规则
    protected $rule = [
        'group_goods_id|拼团信息' => 'require',
        'goods_id|商品信息' => 'require',
        'group_classify_id|拼团商品分类' => 'require',
        'group_price|拼团默认价格' => ['require', 'reg' => '(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)', 'lt' => 'shop_price'],
        'up_shelf_time|生效日期' => 'require',
        'down_shelf_time|失效日期'  => ['require', 'gt' => 'up_shelf_time', 'checkEndTime' => 'down_shelf_time'],
        'continue_time|拼团持续时间' => 'require|min:1|checkTime:up_shelf_time,down_shelf_time',
        'buy_cum_limit|售卖上限' => ['require', 'reg' => '(^[+]{0,1}(\d+)$)', 'lt' => 'goods_number'],
        'group_num|拼团人数上限' => 'require|egt:2',
        'status|审核状态' => 'require',
        'reason|未通过原因' => 'requireCallback:check_require',
        'is_best|精选状态' => 'require',
        'is_auto|到期自动成团' => 'require',
    ];

    // 验证信息
    protected $message = [
        'group_goods_id.require' => '不能为空',
        'goods_id.require' => '不能为空',
        'group_classify_id.require' => '不能为空',
        'group_price.require' => '不能为空',
        'group_price.reg' => '正整数或两位小数',
        'group_price.lt' => '小于商品原价',
        'buy_cum_limit.require' => '不能为空',
        'buy_cum_limit.reg' => '正整数',
        'buy_cum_limit.lt' => '不能大于商品库存',
        'continue_time.require' => '不能为空',
        'continue_time.checkTime' => '不能大于活动有效时间',
        'group_num.require' => '不能为空',
        'group_num.egt' => '人数大于2',
        'up_shelf_time.require' => '不能为空',
        'down_shelf_time.require' => '不能为空',
        'down_shelf_time.gt' => '应大于生效日期',
        'down_shelf_time.checkEndTime'     => '应大于当前日期',
        'status.require' => '不能为空',
        'reason.require' => '不能为空',
        'is_best.require' => '不能为空',
        'is_auto.require' => '不能为空',
    ];

    // 验证场景
    protected $scene = [
        'create' => ['goods_id', 'group_classify_id', 'buy_cum_limit', 'continue_time', 'group_price'
            , 'group_num', 'up_shelf_time', 'down_shelf_time', 'status', 'is_auto', 'reason'],
        'edit' => ['group_goods_id', 'goods_id', 'group_classify_id', 'buy_cum_limit', 'continue_time'
            , 'group_num', 'up_shelf_time', 'down_shelf_time', 'status', 'group_price', 'is_auto', 'reason'],
        'inspect' => ['group_goods_id', 'status'],
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