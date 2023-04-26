<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 积分记录模型
 * Class IntegralRecord
 * @package app\common\model
 */
class IntegralRecord extends BaseModel
{
    protected $pk = 'integral_record_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

}