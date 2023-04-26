<?php
declare(strict_types=1);

namespace app\common\model;

use app\common\model\AdvPosition as AdvPositionModel;

/**
 * 广告列表模型
 * Class Manage
 * @package app\common\model
 */
class Adv extends BaseModel
{
    protected $pk = 'adv_id';

    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
            $file = self::upload('image', 'adv/file/' . date('Ymd') . '/');
            if ($file) {
                $e->file = $file;
            }
        });
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

    /**
     * 获取分类名称
     * @param $value
     * @param $data
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getClassifyNameAttr($value, $data)
    {
        $find = (new AdvPositionModel)
            ->where([
                ['adv_position_id', '=', $data['adv_position_id']],
            ])
            ->field('title,type')->find();
//        return $find['title'] . (empty($find['type']) ? '（单图）' : '（多图）');
        return $find['title'];
    }
}