<?php
declare(strict_types=1);

namespace app\computer\model;

use \app\common\model\GoodsEvaluate as GoodsEvaluateModel;

/**
 * 商品评价表模型
 * Class GoodsEvaluate
 * @package app\common\model
 */
class GoodsEvaluate extends GoodsEvaluateModel
{

    /**
     * 获得商品评价各条件数量
     * @param $goods_id
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function evaluationNumber($goods_id)
    {
        return self::where([
                               ['goods_id', '=', $goods_id],
                               ['status', '=', 1],
                           ])->field(
            'COUNT(*) all_number,
            SUM(IF(LENGTH(trim(multiple_file)) < 1,0,1)) have_file,
            SUM(IF(LENGTH(trim(video)) < 1 ,0,1)) video_num,
            SUM(IF(star_num < 3,1,0)) negative,
            COUNT(CASE WHEN star_num = 3 AND star_num <= 4 THEN 1 END ) medium,
            COUNT(CASE WHEN star_num > 3 THEN 1 END) good,
            IFNULL(0+cast(round(sum(star_num)/(count(goods_id)*5)* 100,2) as char),0) good_percent'
        )
            ->find();
    }
    /**
     * 店铺评价
     * 店铺总平均分   对比  所有店铺总平均分
     * @param $store_id
     * @return array|null|\PDOStatement|string|\think\Model
     */
    public function storeEvaluate($store_id)
    {
        //当前店铺平均评分
        $self_score = self::where([['store_id', '=', $store_id]])
            ->value('IFNULL(0+cast(round(SUM(store_star_num) / (count(*) * max(store_star_num)) * 5,2) as char),0) store_percent');
        //其他店铺平均分
        $sum_score = self::where([['store_id', '<>', $store_id]])
            ->value('IFNULL(0+cast(round(SUM(store_star_num) / (count(*) * max(store_star_num)) * 5,2) as char),0) store_percent');
        //趋势   -1下降 0相等 1上升
        $trend = $self_score <=> $sum_score;
        return [
            'self_score' => $self_score,
            'trend'      => $trend,
        ];
    }
}