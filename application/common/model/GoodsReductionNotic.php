<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 商品降价通知模型
 * Class GoodsReductionNotic
 * @package app\common\model
 */
class GoodsReductionNotic extends BaseModel
{
    protected $pk = 'goods_reduction_notic_id';
    protected $snPrefix = 'iShop';
    protected $goods_sn = '';

    public static function init()
    {
        parent::init();
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            // todo 不明所以,所以注释
//            $e->store_id = 5;
//            $e->store_goods_classify_id = 0;
        });
    }

}