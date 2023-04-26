<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 店铺动态模型
 * Class StoreArticle
 * @package app\common\model
 */
class StoreArticle extends BaseModel
{
    protected $pk = 'store_article_id';


    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
            $file = self::upload('image', 'store_grade/file/' . date('Ymd') . '/');
            if ($file) {
                $e->file = $file;
            }
        });
        self::afterWrite(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

}