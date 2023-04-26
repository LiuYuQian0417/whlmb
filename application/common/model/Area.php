<?php
declare(strict_types=1);

namespace app\common\model;

use app\common\service\Spell;

/**
 * 城市表
 * Class Area
 * @package app\common\model
 */
class Area extends BaseModel
{
    protected $pk = 'area_id';

    public static function init()
    {
        parent::init();
        self::beforeWrite(function ($e) {
            $e->spell = (new Spell())->convert($e->area_name);
            $e->initials = _getFirstCharter($e->area_name);
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }
}