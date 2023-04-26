<?php
declare(strict_types = 1);
namespace app\master\controller;
use app\common\model\Products;
use app\common\model\Store;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Cache;
use think\facade\Request;
use app\common\model\Goods;
use app\common\model\GoodsReductionNotic as GoodsReductionNoticModel;
use app\common\model\GoodsReductionNoticLog;

use think\Queue;

/**
 * 商品降价通知
 * Class GoodsReductionNotic
 * @package app\master\controller
 */
class GoodsReductionNotic extends Controller
{
    /**
     * 自营/店铺商品降价列表
     * @param Request $request
     * @param GoodsModel $goods
     * @return mixed
     * @throws Exception
     */
    public function index(Request $request,GoodsReductionNoticModel $goodsReductionNotic)
    {
        try {
            $param = $request::get();
            $condition = [];
            $min = [];
            $max = [];
            if (array_key_exists('status', $param) && $param['status'] != -1) $condition[] = ['grn.status', '=', $param['status']];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['grn.create_time', 'between time', [$begin, $end]]);
            }
            // 查询金额
            if (isset($param['min']) && $param['min'] != '') $min [] = ['expected_price', '>=', $param['min']];
            if (isset($param['max']) && $param['max'] != '') $max [] = ['expected_price', '<=', $param['max']];

            $data = $goodsReductionNotic
                ->alias('grn')
                ->join('goods g', 'g.goods_id = grn.goods_id')
                ->join('member m', 'm.member_id = grn.member_id')
                ->join('store s','s.store_id = g.store_id')
                ->where($condition)
                ->where('grn.goods_id', $param['goods_id'])
                ->where($min)
                ->where($max)
                ->where('grn.goods_id', $param['goods_id'])
                ->field('g.goods_name,g.shop_price,m.nickname,s.store_name,grn.goods_reduction_notic_id,grn.status,grn.expected_price,grn.create_time,grn.price,m.avatar')
                ->order(['grn.create_time' => 'desc'])
                ->paginate(15, false, ['query' => $param]);

            return $this->fetch('', [
                'data' => $data,
                'goods_id' => $param['goods_id'],
            ]);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 商品降价通知日志
     * @param Request $request
     * @return array
     */
    public function log(Request $request,GoodsReductionNoticLog $goodsReductionNoticLog)
    {
        $param = $request::get();
        try {

            //初始查询自营的降价商品列表
            $condition = [];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['create_time', 'between time', [$begin, $end]]);
            }
            $data = $goodsReductionNoticLog
                ->where($condition)
                ->where('goods_id', $param['goods_id'])
                ->field('goods_reduction_notic_log_id,date_time,create_time,IFNULL(count(member_id), 0) as count')
                ->order(['create_time' => 'desc'])
                ->group('create_time')
                ->paginate(15, false, ['query' => $param]);

        } catch (\Exception $e) {
            halt($e->getMessage());
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'data' => $data,
            'goods_id' => $param['goods_id'],
        ]);
    }
    /**
     * 删除商品降价通知日志
     * @param Request $request
     * id:1 一周之前 2 一个月之前 3三个月之前 4半年之前 5一年之前
     * @return array
     */
    public function logdel(Request $request,GoodsReductionNoticLog $goodsReductionNoticLog){
        if ($request::isPost()) {
            try {
                $param = $request::post();
                switch ($param['id']) {
                    case 1:
                        //一周之前
                        $where = strtotime("-7 day");
                        break;
                    case 2:
                        //一个月之前
                        $where = strtotime("-1 month");
                        break;
                    case 3:
                       //三个月之前
                        $where = strtotime("-3 month");
                        break;
                    case 4:
                       //半年之前
                        $where = strtotime("-6 month");
                        break;
                    case 5:
                        //一年之前
                        $where = strtotime("-1 year");
                        break;
                }
               //模型为什么删不了
                GoodsReductionNoticLog::where('create_time','<',$where)->delete();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 删除商品降价通知
     * @param Request $request
     * @return array
     */
    public function destroy(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

                GoodsReductionNoticModel::destroy($param['id']);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 永久删除
     * @param Request $request
     * @return array
     */
    public function foreverDestroy(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                GoodsReductionNoticModel::destroy($param['id'], true);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 发送详情
     * @param Request $request
     * @param GoodsReductionNoticLog $goodsReductionNoticLog
     * @return array|mixed
     */
    public function details(Request $request,GoodsReductionNoticLog $goodsReductionNoticLog)
    {

        try {
            $param = $request::get();
            $condition = [];
            if (array_key_exists('status', $param) && $param['status'] != -1) $condition[] = ['grn.status', '=', $param['status']];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['a.create_time', 'between time', [$begin, $end]]);
            }

            $data = $goodsReductionNoticLog
                ->alias('a')
                ->join('goods_reduction_notic grn', 'a.goods_reduction_notic_id = grn.goods_reduction_notic_id')
                ->join('goods g', 'g.goods_id = grn.goods_id')
                ->join('member m', 'm.member_id = grn.member_id')
                ->where($condition)
                ->where('goods_reduction_notic_log_id', $param['goods_reduction_notic_log_id'])
                ->field('g.goods_name,g.shop_price,m.nickname,m.avatar,grn.goods_reduction_notic_id,a.status,grn.expected_price,a.create_time,grn.price')
                ->order(['a.create_time' => 'desc'])
                ->paginate(15, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

}