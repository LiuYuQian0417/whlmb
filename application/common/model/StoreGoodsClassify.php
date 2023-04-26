<?php
declare(strict_types=1);

namespace app\common\model;

class StoreGoodsClassify extends BaseModel
{
    protected $pk = 'store_goods_classify_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s', time());
        });

        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s', time());
            $e->count = self::where([
                    ['store_goods_classify_id', '=', $e->parent_id],
                ])->value('count') + 1;
        });
    }


    /**
     * 接口获取下级分类
     */
    public function subset()
    {
        return $this
            ->hasMany('StoreGoodsClassify', 'parent_id', 'store_goods_classify_id')
            ->where([
                ['status', '=', 1],     // 是否显示
            ])
            ->field('store_goods_classify_id,title,parent_id')
            ->order(['sort' => 'asc', 'store_goods_classify_id' => 'asc']);
    }

    public function childrenCount()
    {
        return $this->where('parent_id', $this->store_goods_classify_id)->count();
    }

}