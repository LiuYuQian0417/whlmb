<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 商品属性类型模型
 * Class AttrType
 * @package app\common\model
 */
class AttrType extends BaseModel
{
    protected $pk = 'attr_type_id';
    protected $snPrefix = 'iShop';
    protected $goods_sn = '';

    public static function init()
    {
        parent::init();
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            $e->store_goods_classify_id = 0;
        });
    }

}