<?php
declare(strict_types=1);

namespace app\common\model;

class DistributionCity extends BaseModel
{
    protected $pk = 'distribution_city_id';

    /**
     * 模型事件
     */
    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s', time());
            $e->update_time = date('Y-m-d H:i:s', time());
        });
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s', time());
            if ($e->distribution_type == 2) {
                if (!empty($e->distribution_area_id)) {
                    $e->distribution_area_id = implode(',', $e->distribution_area_id);
                }
            }
        });
    }

    public function storeBlt()
    {
        return $this->belongsTo('Store', 'store_id', 'store_id');
    }
}