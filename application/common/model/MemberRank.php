<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 会员等级模型
 * Class Manage
 * @package app\common\model
 */
class MemberRank extends BaseModel
{
    protected $pk = 'member_rank_id';

    public static function init()
    {
        parent::init();
        self::beforeWrite(function ($e) {
            $file = self::upload('image', 'member_rank/file/' . date('Ymd') . '/');
            if ($file) {
                $e->file = $file;
            }
            $file2 = self::upload('image2', 'member_rank/file/' . date('Ymd') . '/');
            if ($file2) {
                $e->file2 = $file2;
            }
            $e->update_time = date('Y-m-d H:i:s');
        });
    }

}