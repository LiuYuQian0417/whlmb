<?php

namespace app\common\model;

use think\facade\Cache;
use think\facade\Request;

class Feedback extends BaseModel
{
    protected $pk = 'feedback_id';

    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            $e->ip = Request::ip();
            Cache::set($e->ip, $e->ip, 300);
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

    /**
     * 意见反馈图片
     * @param $value
     * @return array|mixed|string
     */
    public function getFileAttr($value)
    {
        if (is_null($value) || $value == '') {
            return '';
        } else {
            $value = $value = explode(',', $value);
        }
        $config = config('oss.');
        if (is_array($value)) {
            $image = [];
            foreach ($value as $val) {
                $image[] = $config['prefix'] . $val;
            }
            return $image;
        }
        return $config['prefix'] . $value;
    }

}