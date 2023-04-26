<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 商品评价表模型
 * Class GoodsEvaluate
 * @package app\common\model
 */
class GoodsEvaluate extends BaseModel
{
    protected $pk = 'goods_evaluate_id';

    /**
     * 关联商品订单
     * @return \think\model\relation\BelongsTo
     */
    public function orderGoods()
    {
        return $this->belongsTo('OrderGoods', 'order_goods_id', 'order_goods_id');
    }

    /**
     * 我的评价列表(关联商品订单)
     * @return \think\model\relation\BelongsTo
     */
    public function orderGoodsMyEvaluate()
    {
        return self::orderGoods()
            ->removeOption('soft_delete')
            ->field('order_goods_id,attr,goods_name,goods_name_style,file,single_price,quantity,subtotal_price');
    }
}