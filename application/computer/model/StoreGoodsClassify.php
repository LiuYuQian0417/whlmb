<?php
declare(strict_types=1);

namespace app\computer\model;

use \app\common\model\StoreGoodsClassify as StoreGoodsClassifyModel;

class StoreGoodsClassify extends StoreGoodsClassifyModel
{
    /**
     * 店铺分类下商品
     * @return \think\model\relation\HasMany
     */
    public function goods()
    {
        return $this->hasMany('Goods', 'store_goods_classify_id', 'store_goods_classify_id')
            ->where([['review_status','=',1],['is_putaway','=',1]])
            ->field('store_goods_classify_id,goods_id,shop_price,shop_price as goods_price,goods_name,file,is_group,is_bargain,is_limit,is_vip,group_price,cut_price,time_limit_price')
            ->order(['sort' => 'asc'])->limit(3);
    }

}