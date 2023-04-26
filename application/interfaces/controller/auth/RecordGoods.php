<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\RecordGoods as RecordGoodsModel;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Config;

/**
 * 商品浏览记录 - Joy
 * Class Register
 * @package app\interfaces\controller\auth
 */
class RecordGoods extends BaseController
{
    
    /**
     * 商品浏览记录
     * @param RSACrypt $crypt
     * @param RecordGoodsModel $recordGoods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          RecordGoodsModel $recordGoods)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $where = [
            ['rg.member_id', '=', $param['member_id']],
        ];
        if (!self::$oneOrMore) {
            array_push($where, ['s.store_id', '=', self::$oneStoreId]);
        }
        // 读取个人信息
        $result = $recordGoods
            ->alias('rg')
            ->join('goods g', 'g.goods_id = rg.goods_id')
            ->join('store s', 's.store_id = g.store_id')
            ->where($where)
            ->field('record_goods_id,g.goods_id,goods_name,file,shop_price,rg.create_time as date_time,
                is_group,is_bargain,freight_status,group_price,cut_price,group_num,is_vip,attr_type_id,
                g.is_putaway,g.review_status,file as cart_file,goods_number,s.store_id,is_limit,time_limit_price,
                g.market_price,s.delete_time as store_delete_time,s.status,s.end_time,g.delete_time as goods_delete_time')
            ->append(['attribute_list', 'limit_state', 'is_invalid'])
            ->hidden(['store_delete_time', 'status', 'is_putaway', 'review_status', 'end_time', 'goods_delete_time'])
            ->order(['rg.update_time' => 'desc'])
            ->paginate(10, false)
            ->toArray();
        $result['data'] = arrayGrouping($result['data'], 'date_time', 'date', 'list');
        // 折扣
        $discount = discount($param['member_id']);
        // 矫正计数
        if ($result['total'] >= 0) {
            $prefix = Config::get('cache.default')['prefix'];
            Cache::handler()->zAdd($prefix . 'record_goods', $result['total'], $param['member_id']);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'date' => date('Y-m-d'),
            'discount' => $discount,
        ], true);
    }
    
    /**
     * 商品浏览记录删除
     * @param RSACrypt $crypt
     * @param RecordGoodsModel $recordGoods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function delete(RSACrypt $crypt,
                           RecordGoodsModel $recordGoods)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid;
        $redisInstance = Cache::handler();
        $prefix = Config::get('cache.default')['prefix'];
        if ($param['record_goods_id']) {
            $recordGoods::destroy($param['record_goods_id'], true);
            $redisInstance->zIncrBy($prefix . 'record_goods', -count(explode(',', $param['record_goods_id'])), $param['member_id']);
        } else {
            $recordGoods->where('member_id', $param['member_id'])->delete();
            $redisInstance->ZADD($prefix . 'record_goods', 0, $param['member_id']);
        }
        return $crypt->response([
            'code' => 0,
            'message' => $param['record_goods_id'] ? '删除成功' : '清空成功',
        ], true);
    }
}