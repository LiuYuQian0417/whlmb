<?php
declare(strict_types=1);

namespace app\computer\model;

use app\common\model\CollectStore as CollectStoreModel;

/**
 * 店铺收藏模型
 * Class CollectStore
 * @package app\common\model
 */
class CollectStore extends CollectStoreModel
{

    /**
     * 收藏店铺推荐商品
     * @return \think\model\relation\HasMany
     */
    public function shopGoods()
    {
        return $this->hasMany('Goods', 'store_id', 'store_id')
            ->where([
                        ['is_putaway', '=', 1]
                    ])
            ->field('file,shop_price,goods_id,goods_name,store_id')
            ->order(['store_particularly_recommend' => 'desc', 'store_recommend' => 'desc'])
            ->limit(4);
    }
}