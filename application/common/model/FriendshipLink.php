<?php
// 友情链接表
declare(strict_types=1);

namespace app\common\model;

class FriendshipLink extends BaseModel
{
    protected $pk = 'friendship_link_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
            $file = self::upload('image', 'friendship/file/' . date('Ymd') . '/');
            if ($file) $e->file = $file;
        });
    }


}