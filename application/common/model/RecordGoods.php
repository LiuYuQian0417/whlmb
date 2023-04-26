<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Cache;
use think\facade\Config;

class RecordGoods extends BaseModel
{
    protected $pk = 'record_goods_id';

    public static function init()
    {
        self::beforeInsert(
            function ($e) {
                $e->create_time = date('Y-m-d');
            }
        );
        self::afterInsert(
            function ($e) {
                // 写入数量
                $prefix = Config::get('cache.default')['prefix'];
                Cache::handler()->zIncrBy($prefix . 'record_goods', 1, $e->member_id);
            }
        );

        self::beforeWrite(
            function ($e) {
                $e->update_time = date('Y-m-d H:i:s');
            }
        );
    }

    /**
     * 限时抢购状态
     * @param $value
     * @param $data
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getLimitStateAttr($value, $data)
    {
        if ($data['is_limit']) {
            $timeSlot = (new Goods())
                ->alias('g')
                ->where([
                    ['g.goods_id', '=', $data['goods_id']],
                ])
                ->join('limit l', 'l.goods_id = g.goods_id and l.status = 1
            and l.exchange_num > 0 and l.up_shelf_time <= \'' . date('Y-m-d') .
                    '\' and l.down_shelf_time >= \'' . date('Y-m-d') . '\' and l.delete_time is null')
                ->join('limit_interval li', 'li.limit_interval_id = l.interval_id and li.delete_time is null')
                ->field('li.start_time,li.end_time')
                ->find();
            if (!is_null($timeSlot) && $timeSlot['start_time'] <= date('H') && $timeSlot['end_time'] >= date('H')) {
                return 1;
            }
        }
        return 0;
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
        // 商品规格
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
                array_push(
                    $attrCollect[$item['attr_id']]['goods_attr'],
                    [
                        'attr_id' => $item['attr_id'],
                        'attr_value' => $item['attr_value'],
                        'goods_attr_id' => $item['goods_attr_id'],
                    ]
                );
            }
        }
        return $attrCollect ? array_values($attrCollect) : [];
    }
}
