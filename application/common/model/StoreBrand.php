<?php
// 店铺品牌
declare(strict_types=1);

namespace app\common\model;

class StoreBrand extends BaseModel
{
    protected $pk = 'store_brand_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s', time());
            $e->update_time = date('Y-m-d H:i:s', time());
        });

        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s', time());
        });
    }

    public function getExtraAttr($value, $data)
    {
        return $data['brand_logo'];
    }
}