<?php
declare(strict_types=1);

namespace app\computer\model;

use \app\common\model\GoodsClassify as GoodsClassifyModel;

/**
 * 商品分类模型
 * Class Manage
 * @package app\common\model
 */
class GoodsClassify extends GoodsClassifyModel
{
    /**
     * pc获取三级分类商品
     */
    public function ThreeGoods()
    {
        return $this->hasMany('Goods', 'goods_classify_id', 'goods_classify_id');
    }
}