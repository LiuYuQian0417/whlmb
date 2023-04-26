<?php
// 限时抢购管理
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\LimitInterval;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\service\Beanstalk;
use think\Db;
use think\Controller;
use think\exception\ValidateException;
use think\facade\Request;
use app\common\model\Limit as LimitModel;
use app\common\model\Brand as BrandModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use app\common\model\LimitInterval as LimitIntervalModel;
use app\common\model\Goods as GoodsModel;

class Limit extends Controller
{
    /**
     * 限时抢购类表
     * @param Request $request
     * @param LimitModel $limit
     * @param LimitIntervalModel $limitInterval
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, LimitModel $limit, LimitInterval $limitInterval)
    {
        $_singleStore = config('user.one_more');
        $_oneStoreId = config('user.one_store_id');
        try {

            // 获取数据
            $param = $request::get();

            // 筛选条件
            $condition[] = ['limit_id', 'neq', 0];
            if (!empty($param['keyword'])) {
                $condition[] = ['go.goods_name', 'like', '%' . $param['keyword'] . '%'];
            }
            if (isset($param['status']) && $param['status'] != -1) {
                $condition[] = ['li.status', 'eq', $param['status']];
            }
            if (!empty($param['screening_id'])) {
                $condition[] = ['li.interval_id', 'like', '%' . $param['screening_id'] . '%'];
            }
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
            if (isset($param['type']) && $param['type'] != -1) {
                if ($param['type'] == 0) {
                    // 自营
                    $condition[] = ['ss.shop', 'eq', 0];
                } else {
                    // 商家
                    $condition[] = ['ss.shop', 'neq', 0];
                }
            }

            // 单店
            if ($_singleStore != 1) {
                $condition[] = [
                    'ss.store_id',
                    '=',
                    $_oneStoreId,
                ];
            }

            // 获取数据
            $data = $limit->alias('li')
                ->join(['ishop_goods' => 'go'], 'go.goods_id=li.goods_id and go.delete_time is null')
                ->join(['ishop_store' => 'ss'], 'ss.store_id = go.store_id and ss.delete_time is null')
                ->field(
                    'li.interval_id,go.time_limit_price,go.goods_name,go.shop_price,li.available_sale,li.status,li.up_shelf_time,li.down_shelf_time,li.limit_id,li.goods_id,ss.shop'
                )
                ->where($condition)
                ->append(['screening'])
                ->order(['li.create_time' => 'desc'])
                ->paginate(10, FALSE, ['query' => $param]);

            $count = $limit->countLimit();

            $limit_screening = $limitInterval->field('limit_interval_id,interval_name')->select();

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch(
            '',
            [
                'data'            => $data,
                'count'           => $count,
                'limit_screening' => $limit_screening,
                'single_store'    => $_singleStore,
                'one_store_id'    => $_oneStoreId,
            ]
        );
    }


    /**
     * 创建限时抢购商品
     * @param Request $request
     * @param LimitModel $limit
     * @param LimitIntervalModel $limitInterval
     * @param GoodsClassifyModel $goodsClassify
     * @param BrandModel $brand
     * @return array|mixed
     * @throws \think\Exception\DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function create(Request $request, LimitModel $limit, LimitIntervalModel $limitInterval, GoodsClassifyModel $goodsClassify, BrandModel $brand)
    {
        $_singleStore = config('user.one_more');
        $_oneStoreId = config('user.one_store_id');
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value(
                    'goods_number'
                );
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                $check = $limit->valid($param, 'create');
                if ($check['code']) {
                    return $check;
                }

                // 写入
                $limit->allowField(TRUE)->save($param);

                if (array_key_exists('products_id', $param)) {
                    foreach ($param['products_id'] as $key => $value) {

                        $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                        $reg1 = '/(^[0-9]\d*$)/';
                        if (!preg_match($reg, $param['attr_time_limit_price'][$key])) {
                            return ['code' => -100, 'message' => '属性限时抢购价为正整数或保留两位小数'];
                        }
                        if (!preg_match($reg1, $param['attr_time_limit_number'][$key])) {
                            return ['code' => -100, 'message' => '属性限时抢购库存为正整数'];
                        }
                        if ($param['attr_time_limit_price'][$key] > $param['shop_price']) {
                            return ['code' => -100, 'message' => '属性限时抢购价小于商品原价'];
                        }

                        $attr_goods_number = Db::name('products')->where('products_id', $value)->value(
                            'attr_goods_number'
                        );

                        if ($param['attr_time_limit_number'][$key] > $attr_goods_number) {
                            return ['code' => -100, 'message' => '限时抢购属性库存小于属性库存'];
                        }

                    }
                    $limit->setSub($param);
                }

                $limit->setGoods($param);
                (new Beanstalk())->put(
                    json_encode(
                        [
                            'queue' => 'limitGoodsExpireChangeStatus',
                            'id'    => $limit->limit_id,
                            'time'  => date('Y-m-d H:i:s'),
                        ]
                    ),
                    (strtotime($param['down_shelf_time']) - time())
                );
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/limit/index'];
            } catch (\Exception $e) {

                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where(
                [
                    ['parent_id', '=', 0],
                    ['status', '=', 1],
                ]
            )
            ->field('goods_classify_id,parent_id,title,count')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();

        //查询全部品牌
        $brandData = $brand->where([['brand_id', '>', 0]])->field('brand_id,brand_name,brand_first_char')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();

        //查询全部品牌首字母
        $brandFirstChr = $brand->where([['brand_id', '>', 0]])->distinct(TRUE)->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');


        return $this->fetch(
            '',
            [
                'categoryOne'   => $categoryOne,
                'brand'         => $brandData,
                'brandFirstChr' => $brandFirstChr,
                'classify_list' => $limitInterval::all(),
                'single_store'  => $_singleStore,
                'one_store_id'  => $_oneStoreId,
            ]
        );

    }


    /**
     * 编辑限时抢购商品
     * @param Request $request
     * @param LimitModel $limit
     * @param LimitIntervalModel $limitInterval
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, LimitModel $limit, LimitIntervalModel $limitInterval, GoodsModel $goods)
    {
        $_singleStore = config('user.one_more');
        $_oneStoreId = config('user.one_store_id');
        if ($request::isPost()) {
            Db::startTrans();
            // 获取数据
            $param = $request::post();
            $activity = $goods->where(
                [
                    ['goods_id', '=', $param['goods_id']],
                    ['is_group', 'in', '0,2'],
                    ['is_bargain', 'in', '0,2'],
                    ['is_limit', 'in', '0,2'],
                ]
            )->find();

            if ($param['status'] == 1 && empty($activity)) {
                return ['code' => -100, 'message' => '该商品处于其他活动中，结束后才可通过'];
            }
            $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
            $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
            // 验证
            $limit->valid($param, 'edit');

//            if ($activity['is_putaway'] == 0){
//                return ['code' => -100, 'message' => '该商品已下架无法提交'];
//            }

            // 将库存修改为 与 售卖上限一致
            $param['exchange_num'] = $param['available_sale'];

            // 更新
            $limit->allowField(TRUE)->isUpdate(TRUE)->save($param);

            if (array_key_exists('products_id', $param)) {
                foreach ($param['products_id'] as $key => $value) {

                    $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                    $reg1 = '/(^[0-9]\d*$)/';
                    if (!preg_match($reg, $param['attr_time_limit_price'][$key])) {
                        return ['code' => -100, 'message' => '属性限时抢购价为正整数或保留两位小数'];
                    }
                    if (!preg_match($reg1, $param['attr_time_limit_number'][$key])) {
                        return ['code' => -100, 'message' => '属性限时抢购库存为正整数'];
                    }
                    if ($param['attr_time_limit_price'][$key] > $param['shop_price']) {
                        return ['code' => -100, 'message' => '属性限时抢购价小于商品原价'];
                    }

                    $attr_goods_number = Db::name('products')->where('products_id', $value)->value('attr_goods_number');

                    if ($param['attr_time_limit_number'][$key] > $attr_goods_number) {
                        return ['code' => -100, 'message' => '限时抢购属性库存小于属性库存'];
                    }

                }
                $limit->setSub($param);
            }

            $limit->setGoods($param);
            (new Beanstalk())->put(
                json_encode(
                    [
                        'queue' => 'limitGoodsExpireChangeStatus',
                        'id'    => $param['limit_id'],
                        'time'  => date('Y-m-d H:i:s'),
                    ]
                ),
                (strtotime($param['down_shelf_time']) - time())
            );
            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/limit/index'];
        }
        return $this->fetch(
            'create',
            [
                'classify_list' => $limitInterval::all(),
                'item'          => $limit->relation(
                    [
                        'Goods' => function ($query) {
                            $query->field('goods_id,goods_name,shop_price,time_limit_price');
                        },
                    ]
                )->where(['limit_id' => $request::get('id')])->find(),
                'single_store'  => $_singleStore,
                'one_store_id'  => $_oneStoreId,

            ]
        );
    }


    /**
     * 删除限时抢购商品
     * @param Request $request
     * @param LimitModel $limit
     * @return array
     */
    public function destroy(Request $request, LimitModel $limit, GoodsModel $goods)
    {
        if ($request::isPost()) {
            try {
                // 更改商品原有限时抢购状态
                $goodsId = $limit->where(
                    [
                        ['limit_id', 'in', $request::post('id')],
                    ]
                )->column('goods_id');

                $goodsIdStr = implode(',', $goodsId);
                $goods->where(['goods_id' => ['in', $goodsIdStr]])->update(['is_limit' => 0]);

                // 删除
                $operation = $limit->limitDelete($request::post('id'));

                if ($operation) {
                    return ['code' => 0, 'message' => config('message.')[0]];
                }

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 抢购明细
     * @param Request $request
     * @param LimitModel $limit
     * @param OrderGoods $orderGoods
     * @param OrderAttach $orderAttach
     * @return array|mixed
     */
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
                if ($begin == $end) {
                    $end = date('Y-m-d', strtotime($end . '+1 days'));
                }
                array_push($condition, ['a.create_time', 'between time', [$begin, $end]]);
            }
            if (array_key_exists('status', $param) && $param['status'] != -1) {
                $condition[] = ['a.status', 'eq', $param['status']];
            } // 活动状态
            $whereOr = [];
            $order_attach_number = '';
            if (array_key_exists('order_status', $param) && $param['order_status'] != -1) {
                // 存在退款中订单
                if ($param['order_status'] == 5) {
                    $orderGoodsCol = $orderGoods->where(
                        [
                            ['status', 'in', '5.1,5.2,5.3,5.4'],
                        ]
                    )->column('order_attach_id');

                    array_push(
                        $condition,
                        ['a.order_attach_id', 'in', implode(',', array_unique($orderGoodsCol))]
                    );

                } else if ($param['order_status'] == -2) {
                    // 未下单
                    $order_attach_number = ' a.order_attach_number is null';
                } else {
                    $condition[] = ['a.status', 'in', $param['order_status']];// 订单状态
                }
            }
            if (array_key_exists('keyword', $param) && $param['keyword']) {
                $condition[] = ['order_attach_number|nickname|phone', 'like', '%' . $param['keyword'] . '%'];
            }

            $data = $orderAttach::withTrashed()
                ->alias('a')
                ->join('member member', 'member.member_id = a.member_id')
                ->where($order_attach_number)
                ->where(
                    [
                        ['limit_goods_id', '=', $param['id']],
                    ]
                )
                ->where($condition)
                ->whereOr($whereOr)
                ->field('order_attach_number,nickname,member.phone,subtotal_price,number,a.status')
                ->paginate(10, FALSE, ['query' => $param]);

            // 商品信息
            $item = $limit
                ->alias('a')
                ->join('goods goods', 'goods.goods_id = a.goods_id')
                ->field('a.*,goods_name,shop_price,cut_price,store_id,file')
                ->where(['limit_id' => $request::get('id')])
                ->find();

        } catch (\Exception $e) {
            return json(['code' => -100, 'message' => $e->getMessage()]);
        }

        return $this->fetch(
            '',
            [
                'item' => $item,
                'data' => $data,
            ]
        );
    }
}