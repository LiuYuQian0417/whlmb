<?php
declare(strict_types = 1);
namespace app\common\validate;

use think\Validate;

/**
 * 订单商品验证类
 * Class OrderGoods
 * @package app\common\validate
 */
class OrderGoods extends Validate
{
    protected $rule = [
        'order_goods_id|商品订单信息' => 'require',
        'star_num|商品评价星数' => 'require|between:1,5',
        'store_star_num|店铺评价星数' => 'require|between:1,5',
        'express_star_num|物流评价星数' => 'require|between:1,5',
        'content|评价内容' => 'require|max:300',
    ];

    protected $message = [
        'order_goods_id.require' => '不可为空',
        'star_num.require' => '不能为空',
        'star_num.between' => '范围错误',
        'store_star_num.require' => '不能为空',
        'store_star_num.between' => '范围错误',
        'express_star_num.require' => '不能为空',
        'express_star_num.between' => '范围错误',
        'content.require' => '不可为空',
        'content.max' => '应少于300字符',
    ];

    protected $scene = [
        // 撤销退货退款
        'revokeApply' => ['order_goods_id'],
        // 发表评价
        'report' => ['store_star_num', 'express_star_num'],
    ];
}