<?php
declare(strict_types=1);

namespace app\computer\controller\auth;

use app\computer\model\RecordGoods as RecordGoodsModel;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Request;
use think\facade\Session;

/**
 * 商品浏览记录
 * Class Register
 * @package app\computer\controller\auth
 */
class RecordGoods extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login'=>['except' => '']
    ];

    /**
     * 商品浏览记录
     * @param Request $request
     * @param RSACrypt $crypt
     * @param RecordGoodsModel $recordGoods
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function index(Request $request, RSACrypt $crypt, RecordGoodsModel $recordGoods)
    {
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'];

        // 读取个人信息
        $result = $recordGoods->alias('record_goods')
            ->join('goods goods', 'goods.goods_id = record_goods.goods_id')
            ->where([
                ['record_goods.member_id', '=', $param['member_id']],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1]
            ])
            ->field('record_goods_id,goods.goods_id,goods_name,file,shop_price,record_goods.create_time as date_time,is_group,is_bargain,freight_status,group_price,cut_price,group_num,is_vip,attr_type_id,file as cart_file,goods_number,store_id,is_limit,time_limit_price')
            ->order('record_goods.create_time', 'desc')
            ->append(['attribute_list','limit_state'])
            ->order('record_goods.create_time','desc')
//            ->paginate(12)
            ->select()
            ->toArray();

        $result['data'] = arrayGrouping($result, 'date_time', 'date', 'list');

        // 折扣
        $discount = discount($param['member_id']);

        return $this->fetch('',['code' => 0, 'result' => $result, 'date' => date('Y-m-d'), 'discount' => $discount]);
    }

    /**
     * 商品浏览记录删除
     * @param Request $request
     * @param RSACrypt $crypt
     * @param RecordGoodsModel $recordGoods
     * @return mixed
     */
    public function delete(Request $request, RSACrypt $crypt, RecordGoodsModel $recordGoods)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request()->mid;

                // 删除
                $redisInstance = Cache::handler();
                $prefix = Config::get('cache.default')['prefix'];
                if($param['record_goods_id']){
                    $recordGoods::destroy($param['record_goods_id'], true);
                    $redisInstance->zIncrBy($prefix . 'record_goods', -count(explode(',', (string)$param['record_goods_id'])), $param['member_id']);
                }else{
                    $recordGoods->where('member_id',$param['member_id'])->delete();
                    $redisInstance->ZADD($prefix . 'record_goods', 0, $param['member_id']);
                }

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
}