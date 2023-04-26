<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Cache;
use think\facade\Config;

/**
 * 商品收藏模型
 * Class CollectGoods
 * @package app\common\model
 */
class CollectGoods extends BaseModel
{
    protected $pk = 'collect_goods_id';
    
    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
        self::afterInsert(function ($e) {
            (new Goods())
                ->where([
                    ['goods_id', '=', $e->goods_id],
                ])
                ->setInc('collect_number');
            // 写入数量
            $prefix = Config::get('cache.default')['prefix'];
            Cache::handler()->zIncrBy($prefix . 'collect_goods', 1, $e->member_id);
        });
    }
    
    /**
     * 属性列表
     * @param $value
     * @param $data
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAttributeListAttr($value, $data)
    {
        $attrCollect = [];
        $attrArr = (new GoodsAttr())
            ->alias('ga')
            ->where([
                ['ga.goods_id', '=', $data['goods_id']],
            ])
            ->join('attr a', 'a.attr_id = ga.attr_id')
            ->field('ga.goods_attr_id,ga.attr_value,a.attr_id,a.attr_name')
            ->order(['ga.attr_id' => 'asc'])
            ->select();
        if ($attrArr) {
            foreach ($attrArr as $item) {
                if (!array_key_exists($item['attr_id'], $attrCollect)) {
                    $attrCollect[$item['attr_id']] = [
                        'attr_id' => $item['attr_id'],
                        'attr_name' => $item['attr_name'],
                        'goods_attr' => [],
                    ];
                }
                array_push($attrCollect[$item['attr_id']]['goods_attr'], [
                    'attr_id' => $item['attr_id'],
                    'attr_value' => $item['attr_value'],
                    'goods_attr_id' => $item['goods_attr_id'],
                ]);
            }
        }
        return $attrCollect ? array_values($attrCollect) : [];
    }
    
    /**
     * 获取商品有效状态
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsInvalidAttr($value, $data)
    {
        $ret = 0;
        if (!is_null($data['store_delete_time']) || $data['status'] != 4
            || !$data['is_putaway'] || !$data['review_status'] || !is_null($data['goods_delete_time'])
            || (!is_null($data['end_time']) && $data['end_time'] < date('Y-m-d'))) {
            $ret = 1;
        }
        return $ret;
    }
    
}