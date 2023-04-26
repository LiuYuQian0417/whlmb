<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 未登录购物车表
 * Class Cart
 * @package app\common\model
 */
class LoginCart extends BaseModel
{

    protected $pk = 'login_cart_id';

    /**
     * 获取库存
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getInventoryAttr($value, $data)
    {
        // 检查库存
        if ($data['goods_attr']) {
            $number = (new Products())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                    ['goods_attr', '=', $data['goods_attr']],
                ])
                ->value('attr_goods_number');
        } else {
            $number = (new Goods())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                ])
                ->value('goods_number');
        }
        return $number;
    }

    /**
     * 获取商品上架状态
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getIsPutawayAttr($value, $data)
    {
        return (new Goods())
            ->where([
                ['goods_id', '=', $data['goods_id']],
            ])
            ->value('is_putaway');
    }

    /**
     * 获取店铺名称
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getStoreNameAttr($value, $data)
    {
        return (new Store())
            ->where([
                ['store_id', '=', $data['store_id']],
            ])
            ->value('store_name');
    }

    /**
     * 获取店铺状态
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getStoreStatusAttr($value, $data)
    {
        return (new Store())
            ->where([
                ['store_id', '=', $data['store_id']],
            ])
            ->value('status');
    }

    /**
     * 获取优惠券状态
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getCouponStatusAttr($value, $data)
    {
        return (new Coupon())
            ->where([
                ['type', '=', 0],
                ['modality', '=', 0],
                ['classify_str', '=', $data['store_id']],
                ['status', '=', 1],
                ['receive_end_time', '>=', date('Y-m-d')],
                ['receive_start_time', '<=', date('Y-m-d')]
            ])
            ->value('coupon_id') ? 1 : 0;
    }
}