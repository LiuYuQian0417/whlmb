<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 店铺等级模型
 * Class Manage
 * @package app\common\model
 */
class StoreGrade extends BaseModel
{
    protected $pk = 'store_grade_id';

    public function getDefaultStateNameAttr($value, $data)
    {
        $status = [0 => '--', 1 => '默认'];
        return $status[$data['default_state']];
    }

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
            if ($e->default_state) {
                self::where([
                    ['store_grade_id', '<>', $e->store_grade_id],
                ])->update(['default_state' => '0']);
            }
        });
    }

}