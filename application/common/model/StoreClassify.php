<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 商品分类模型
 * Class Manage
 * @package app\common\model
 */
class StoreClassify extends BaseModel
{
    protected $pk = 'store_classify_id';

    /**
     * @param $value
     * @param $data
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsListAttr($value, $data)
    {
        return (new Goods())
            ->where([
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1],
                ['goods_classify_id', 'in', implode(',', array_column(getParCate($data['goods_classify_id'], (new GoodsClassify()), 0), 'goods_classify_id'))]
            ])
            ->field('goods_id,goods_name,file,shop_price')
            ->order([
                'is_best' => 'desc',
                'is_hot' => 'desc',
                'is_popularity' => 'desc',
                'sort' => 'desc'
            ])
            ->limit(4)
            ->select();
    }

    public static function init()
    {
        self::beforeWrite(function ($e) {
            $file = self::upload('image', 'goods_classify/file/' . date('Ymd') . '/');
            if ($file) {
                $e->file = $file;
            }
            $web_file = self::upload('image1', 'goods_classify/file/' . date('Ymd') . '/');
            if ($web_file) {
                $e->web_file = $web_file;
            }
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

    /**
     * 关联店铺费用
     * @return \think\model\relation\HasMany
     */
    public function storeCosts()
    {
        return $this->hasMany('store_costs', 'store_classify_id', 'store_classify_id');
    }


}