<?php
// 限时抢购管理
declare(strict_types=1);

namespace app\client\controller;

use app\common\service\Beanstalk;
use think\Db;
use think\Controller;
use think\Exception;
use think\facade\Request;
use app\common\model\Limit as LimitModel;
use app\common\model\Brand as BrandModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use app\common\model\LimitInterval as LimitIntervalModel;
use think\facade\Session;
use app\common\model\OrderGoods;
use app\common\model\OrderAttach;
use app\common\model\Goods as GoodsModel;

class Limit extends Controller
{
    /**
     * 限时抢购列表
     * @param Request $request
     * @param LimitModel $limit
     * @return array|mixed
     */
    public function index(Request $request, LimitModel $limit)
    {
        try {
            // 获取数据
            $param = $request::post();

            // 筛选条件
            $condition[] = ['limit_id', 'neq', 0];
            if (isset($param['status']) && $param['status'] != -1) $condition[] = ['li.status', 'eq', $param['status']];
            if (isset($param['activity_status']) && $param['activity_status'] != -1) {
                if ($param['activity_status'] == 0) {
                    // 进行中
                    $condition[] = ['down_shelf_time', '>', date('Y-m-d H:i:s')];
                    $condition[] = ['up_shelf_time', '<', date('Y-m-d H:i:s')];
                } else {
                    // 已结束
                    $condition[] = ['down_shelf_time', '<', date('Y-m-d H:i:s')];
                }
            }
            if (array_key_exists('type', $param) && $param['type']) {
                switch ($param['type']) {
                    case 0:
                        $condition[] = ['ss.shop', 'eq', 0];
                        break;
                    case 1:
                        $condition[] = ['ss.shop', 'neq', 0];
                        break;
                    default;
                }
            }
            if (!empty($param['keyword'])) $condition[] = ['go.goods_name', 'like', '%' . $param['keyword'] . '%'];

            // 获取数据
            $data = $limit->alias('li')
                ->join(['ishop_goods' => 'go'], 'go.goods_id=li.goods_id and go.delete_time is null')
                ->where($condition)
                ->where('go.store_id', Session::get('client_store_id'))
                ->field('go.time_limit_price,go.goods_name,go.shop_price,li.available_sale,li.status,li.up_shelf_time,li.down_shelf_time,li.limit_id')
                ->order(['li.create_time' => 'desc'])
                ->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            halt($e->getMessage());
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 限时抢购商品添加
     * @param Request $request
     * @param LimitModel $limit
     * @param LimitIntervalModel $limitInterval
     * @param GoodsClassifyModel $goodsClassify
     * @param BrandModel $brand
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, LimitModel $limit, LimitIntervalModel $limitInterval, GoodsClassifyModel $goodsClassify, BrandModel $brand)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                $check = $limit->valid($param, 'client_create');
                if ($check['code']) return $check;
                // 写入
                $limit->allowField(true)->save($param);

                // 关联新增
                // $limitId = $limit->limit_id;
                // $limits = $limit::get($limitId);
                // $limits->LimitAttach()->save(['interval_id' => $param['classify']]);

                if (array_key_exists('attr_time_limit_price', $param)) $limit->setSub($param);
                $limit->setGoods($param);
                (new Beanstalk())->put(json_encode(['queue' => 'limitGoodsExpireChangeStatus',
                    'id' => $limit->limit_id, 'time' => date('Y-m-d H:i:s')]),
                    (strtotime($param['down_shelf_time']) - time()));
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/limit/index'];

            } catch (\Exception $e) {

                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where([
                ['parent_id', '=', 0],
                ['status', '=', 1]
            ])
            ->field('goods_classify_id,parent_id,title,count')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();

        //查询全部品牌
        $brandData = $brand->where([['brand_id', '>', 0]])->field('brand_id,brand_name,brand_first_char')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();

        //查询全部品牌首字母
        $brandFirstChr = $brand->where([['brand_id', '>', 0]])->distinct(true)->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');


        return $this->fetch('', [
            'categoryOne' => $categoryOne,
            'brand' => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'classify_list' => $limitInterval::all()
        ]);

    }

    /**
     * 限时抢购商品编辑
     * @param Request $request
     * @param LimitModel $limit
     * @param LimitIntervalModel $limitInterval
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, LimitModel $limit, LimitIntervalModel $limitInterval)
    {
        if ($request::isPost()) {

            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                $check = $limit->valid($param, 'client_edit');
                if ($check['code']) return $check;

                // 更新
                $limit->allowField(true)->isUpdate(true)->save($param);
//                $limits = $limit::get($request::get('id'));
//                $limits->LimitAttach()->save(['interval_id' => $param['classify']]);

                $limit->setGoods($param);
                (new Beanstalk())->put(json_encode(['queue' => 'limitGoodsExpireChangeStatus',
                    'id' => $param['limit_id'], 'time' => date('Y-m-d H:i:s')]),
                    (strtotime($param['down_shelf_time']) - time()));
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/limit/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
                // 回滚事务
//                Db::rollback();
            }
        }

        return $this->fetch('create', [
            'classify_list' => $limitInterval::all(),
            'item' => $limit->relation(['Goods' => function ($query) {
                $query->field('goods_id,goods_name,shop_price,time_limit_price');
            }])->where(['limit_id' => $request::get('id')])->find(),

        ]);
    }

    /**
     * 删除限时抢购商品
     * @param Request $request
     * @param LimitModel $limit
     * @return array
     */
    public function destroy(Request $request, LimitModel $limit)
    {
        if ($request::isPost()) {
            try {
                if (empty($request::post('id'))) throw new Exception('无可删除的限时抢购商品', -100);
                // 删除
                $operation = $limit->limitDelete($request::post('id'));

                if ($operation) return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 查看限时抢购商品
     * @param Request $request
     * @param LimitModel $limit
     * @param LimitIntervalModel $limitInterval
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function inspect(Request $request, LimitModel $limit, LimitIntervalModel $limitInterval)
    {
        // 商品信息
        $item = $limit->relation(['Goods' => function ($query) {
            $query->field('goods_id,goods_name,shop_price,time_limit_price');
        }])->where(['limit_id' => $request::get('id')])->find();

        return $this->fetch('', [
            'item' => $item,
            'classify_list' => $limitInterval::all(),
        ]);
    }

    public function view(Request $request, LimitModel $limit, OrderGoods $orderGoods, OrderAttach $orderAttach)
    {
        try {
            // 获取数据
            $param = $request::get();

            $condition = [];
            if (array_key_exists('distribution_type', $param) && $param['distribution_type'] != -1) {
                if ($param['distribution_type'] == 5) {
                    $condition[] = ['pay_type', 'eq', 2]; // 货到付款
                } else {
                    $condition[] = ['distribution_type', 'eq', $param['distribution_type']]; // 订单类型
                }
            }
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['a.create_time', 'between time', [$begin, $end]]);
            }
            if (array_key_exists('status', $param) && $param['status'] != -1) $condition[] = ['a.status', 'eq', $param['status']]; // 活动状态
            $whereOr = [];
            $order_attach_number = '';
            if (array_key_exists('order_status', $param) && $param['order_status'] != -1) {
                // 存在退款中订单
                if ($param['order_status'] == 5) {
                    $orderGoodsCol = $orderGoods->where([
                        ['status', 'in', '5.1,5.2,5.3,5.4']
                    ])->column('order_attach_id');

                    array_push($condition, ['order_attach.order_attach_id', 'in', implode(',', array_unique($orderGoodsCol))]);

                } elseif ($param['order_status'] == -2) {
                    // 未下单
                    $order_attach_number = ' order_attach.order_attach_number is null';
                }else {
                    $condition[] = ['order_attach.status', 'in', $param['order_status']];// 订单状态
                }
            }
            if (array_key_exists('keyword', $param) && $param['keyword']) $condition[] = ['order_attach_number|nickname|phone', 'like', '%' . $param['keyword'] . '%'];

            $data = $orderAttach::withTrashed()
                ->alias('a')
                ->join('member member', 'member.member_id = a.member_id')
                ->where($order_attach_number)
                ->where([
                    ['limit_goods_id', '=', $param['id']]
                ])
                ->where($condition)
                ->whereOr($whereOr)
                ->field('order_attach_number,nickname,member.phone,subtotal_price,number,a.status')
                ->paginate(10,false, ['query' => $param]);

            // 商品信息
            $item = $limit
                ->alias('a')
                ->join('goods goods', 'goods.goods_id = a.goods_id')
                ->field('a.*,goods_name,shop_price,cut_price,store_id,file')
                ->where(['limit_id' => $request::get('id')])
                ->find();

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'item' => $item,
            'data' => $data,
        ]);
    }
}