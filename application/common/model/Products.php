<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 商品属性表模型
 * Class Goods
 * @package app\common\model
 */
class Products extends BaseModel
{
    protected $pk = 'products_id';

    /**
     * 获取是否使用VIP价格
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getIsVipAttr($value, $data)
    {
        return (new Goods())
            ->where([
                ['goods_id', '=', $data['goods_id']],
            ])->value('is_vip');
    }
}