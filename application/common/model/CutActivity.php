<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 砍价活动主表
 * Class CutActivity
 * @package app\common\model
 */
class CutActivity extends BaseModel
{
    protected $pk = 'cut_activity_id';

    public static function init()
    {
        parent::init();
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

    /**
     * 获取倒计时的秒
     * @param $value
     * @param $data
     * @return false|int
     */
    public function getExpirationTimeAttr($value, $data)
    {
        return strtotime($data['end_time']) - time();
    }

    /**
     * 关联砍价活动附表
     * @return \think\model\relation\HasMany
     */
    public function cutActivityAttach()
    {
        return $this->hasMany('CutActivityAttach', 'cut_activity_id', 'cut_activity_id');
    }

    /**
     * 关联砍价商品
     * @return \think\model\relation\BelongsTo
     */
    public function cutGoods()
    {
        return $this->belongsTo('CutGoods', 'cut_goods_id', 'cut_goods_id');
    }

}