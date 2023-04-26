<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 文章附表模型
 * Class StoreArticleAttach
 * @package app\common\model
 */
class ArticleAttach extends BaseModel
{
    public function goodsBlt(){
        return $this->belongsTo('Goods','goods_id','goods_id');
    }
}