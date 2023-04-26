<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 到店自提模型
 * Class Manage
 * @package app\common\model
 */
class Take extends BaseModel
{
    protected $pk = 'take_id';

    public static function init()
    {
        self::beforeWrite(function ($e) {
            if (!empty($e->province)) {
                $e->province = (new Area())->where('area_id', $e->province)->value('area_name');
                $e->city = (new Area())
                    ->where([
                        ['area_id', '=', $e->city],
                    ])->value('area_name');
                $e->area = (new Area())
                    ->where([
                        ['area_id', '=', $e->area],
                    ])->value('area_name');
            }
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

    /**
     * 关联店铺
     * @return \think\model\relation\BelongsTo
     */
    public function store()
    {
        return $this->belongsTo('Store', 'store_id', 'store_id');
    }

    /**
     * 自提点
     * @return \think\model\relation\BelongsTo
     */
    public function takeStore()
    {
        return self::store()->field('store_id,city');
    }


    public function getStatusTextAttr($data, $value)
    {
        $status = [0 => '未开启', 1 => '已开启'];
        return $status[$value['status']];
    }

}