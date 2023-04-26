<?php
declare(strict_types=1);

namespace app\interfaces\controller\distribution;

use app\common\model\Goods as GoodsModel;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

class Goods extends BaseController
{
    /**
     * 分销商品列表
     * @param RSACrypt $crypt
     * @param GoodsModel $goods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_list(RSACrypt $crypt,
                               GoodsModel $goods)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 可分销商品
        $condition = [
            ['g.review_status', '=', 1],
            ['g.is_putaway', '=', 1],
            ['s.status', '=', 4],
        ];
        if (array_key_exists('is_distribution', $param)) {
            $condition[] = ['g.is_distribution', '=', $param['is_distribution'] ?: 0];
        }
        if (array_key_exists('is_distributor', $param)) {
            $condition[] = ['g.is_distributor', '=', $param['is_distributor'] ?: 0];
        }
        $data = $goods
            ->alias('g')
            ->where($condition)
            ->join('store s', 's.store_id = g.store_id')
            ->field('g.goods_id,g.store_id,goods_name,shop_price,
                    g.sales_volume,freight_status,shop,store_name,g.file,is_group,
                    is_bargain,is_limit,freight_status,shop,group_price,cut_price,group_num,is_vip,
                    attr_type_id,file as cart_file,goods_number,time_limit_price,is_distributor,is_distribution')
            ->orderRand()
            ->order(['g.sort' => 'desc', 'g.goods_id' => 'desc'])
            ->append(['attribute_list', 'limit_state'])
            ->paginate(20, false, $param);
        // 折扣
        $discount = discount($param['member_id']);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $data,
            'discount' => $discount,
        ], true);
    }
}