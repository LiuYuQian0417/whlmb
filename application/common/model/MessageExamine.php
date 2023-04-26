<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 消息查看时间模型
 * Class Message
 * @package app\common\model
 */
class MessageExamine extends BaseModel
{
    protected $pk = 'message_examine_id';

    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }
}