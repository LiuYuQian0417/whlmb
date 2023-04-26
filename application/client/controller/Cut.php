<?php
// 砍价活动
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\Products;
use app\common\service\Beanstalk;
use think\Db;
use think\Controller;
use think\Exception;
use think\facade\Request;
use app\common\model\Store as StoreModel;
use app\common\model\Brand as BrandModel;
use app\common\model\CutGoods as CutGoodsModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use think\facade\Session;
use app\common\model\Goods as GoodsModel;
use app\common\model\CutActivity;
use app\common\model\OrderGoods;

class Cut extends Controller
{
    /**
     * 砍价商品列表
     * @param Request $request
     * @param CutGoodsModel $cutGoods
     * @return array|mixed
     */
    public function index(Request $request, CutGoodsModel $cutGoods)
    {
        try {
            // 获取数据
            $param = $request::post();
//            dump(Session::get('client_store_id'));
            // 筛选条件
            $condition[] = ['cut.cut_goods_id', 'neq', 0];
            if (!empty($param['keyword'])) $condition[] = ['go.goods_name', 'like', '%' . $param['keyword'] . '%'];
            if (isset($param['status']) && $param['status'] > -1) $condition[] = ['cut.status', 'eq', $param['status']];
            // 获取数据
            $data = $cutGoods->alias('cut')
                ->join('goods go', 'go.goods_id = cut.goods_id  and go.delete_time is null')
                ->join('store ss', 'ss.store_id = go.store_id and ss.status = 4 and ss.delete_time is null')
                ->where('ss.store_id', Session::get('client_store_id'))
                ->field('cut.cut_goods_id,cut.goods_id,cut.up_shelf_time,cut.down_shelf_time,cut.status,cut.continue_time,
                go.goods_name,go.cut_price,go.shop_price,go.goods_number,ss.store_name,ss.shop')
                ->where($condition)
                ->order(['cut.create_time' => 'desc'])
                ->paginate(10, false, ['query' => $param]);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 砍价商品添加
     * @param Request $request
     * @param CutGoodsModel $cutGoods
     * @param GoodsClassifyModel $goodsClassify
     * @param BrandModel $brand
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, CutGoodsModel $cutGoods, GoodsClassifyModel $goodsClassify, BrandModel $brand)
    {
        if ($request::isPost()) {

            Db::startTrans();

            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证信息
                $check = $cutGoods->valid($param, 'create');
                if ($check['code']) return $check;
                // 写入
                $operation = $cutGoods->allowField(true)->save($param);
                if (array_key_exists('attr_cut_price', $param)) {
                    foreach ($param['products_id'] as $key => $value) {
                        $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                        if (!preg_match($reg, $param['attr_cut_price'][$key])) return ['code' => -100, 'message' => '属性砍价低价为正整数或保留两位小数'];
                        if (!preg_match($reg, $param['attr_single_cut_min'][$key])) return ['code' => -100, 'message' => '属性砍价单刀最低值为正整数或保留两位小数'];
                        if (!preg_match($reg, $param['attr_single_cut_max'][$key])) return ['code' => -100, 'message' => '属性砍价单刀最高值为正整数或保留两位小数'];

                        if ((new Products())->where('products_id', $value)->value('attr_shop_price') < $param['attr_cut_price'][$key]) return ['code' => -100, 'message' => '属性砍价底价小于属性原价'];
                        if ($param['attr_single_cut_min'][$key] > $param['cut_price']) return ['code' => -100, 'message' => '属性砍价单刀最低值小于等于砍价最低值'];
                        if ($param['attr_single_cut_min'][$key] > $param['attr_single_cut_max'][$key]) return ['code' => -100, 'message' => '属性砍价单刀最低值小于属性砍价单刀最高值'];
                    }

                    $cutGoods->setSub($param);
                }
                $cutGoods->setGoods($param);
                $time = strtotime($param['down_shelf_time']) - time();
                (new Beanstalk())->put(json_encode([
                    'queue' => 'cutGoodsExpireChangeStatus',
                    'id' => $cutGoods->cut_goods_id,
                    'time' => date('Y-m-d H:i:s'),
                ]), $time > 0 ? $time : 5);
                // 提交事务
                Db::commit();
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/cut/index'];

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
        ]);
    }

    /**
     * 砍价商品编辑
     * @param Request $request
     * @param CutGoodsModel $cutGoods
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, CutGoodsModel $cutGoods)
    {
        if ($request::isPost()) {

            Db::startTrans();

            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证信息
                $check = $cutGoods->valid($param, 'edit');
                if ($check['code']) return $check;

                // 更新
                $operation = $cutGoods->allowField(true)->isUpdate(true)->save($param);
                if (array_key_exists('attr_cut_price', $param)) {
                    foreach ($param['products_id'] as $key => $value) {
                        $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                        if (!preg_match($reg, $param['attr_cut_price'][$key])) return ['code' => -100, 'message' => '属性砍价低价为正整数或保留两位小数'];
                        if (!preg_match($reg, $param['attr_single_cut_min'][$key])) return ['code' => -100, 'message' => '属性砍价单刀最低值为正整数或保留两位小数'];
                        if (!preg_match($reg, $param['attr_single_cut_max'][$key])) return ['code' => -100, 'message' => '属性砍价单刀最高值为正整数或保留两位小数'];

                        if ((new Products())->where('products_id', $value)->value('attr_shop_price') < $param['attr_cut_price'][$key]) return ['code' => -100, 'message' => '属性砍价底价小于属性原价'];
                        if ($param['attr_single_cut_min'][$key] > $param['cut_price']) return ['code' => -100, 'message' => '属性砍价单刀最低值小于等于砍价最低值'];
                        if ($param['attr_single_cut_min'][$key] > $param['attr_single_cut_max'][$key]) return ['code' => -100, 'message' => '属性砍价单刀最低值小于属性砍价单刀最高值'];
                    }

                    $cutGoods->setSub($param);
                }
                $cutGoods->setGoods($param);
                $time = strtotime($param['down_shelf_time']) - time();
                (new Beanstalk())->put(json_encode([
                    'queue' => 'cutGoodsExpireChangeStatus',
                    'id' => $param['cut_goods_id'],
                    'time' => date('Y-m-d H:i:s'),
                ]), $time > 0 ? $time : 5);
                // 提交事务
                Db::commit();
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/cut/index'];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch('create', [
            'item' => $cutGoods->relation(['Goods' => function ($query) {
                $query->field('goods_id,goods_name,shop_price,cut_price');
            }])->where(['cut_goods_id' => $request::get('id')])->find(),
        ]);
    }

    /**
     * 查看砍价商品
     * @param Request $request
     * @param CutGoodsModel $cutGoods
     * @param StoreModel $store
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function inspect(Request $request, CutGoodsModel $cutGoods, CutActivity $cutActivity, OrderGoods $orderGoods)
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
                } else {
                    $condition[] = ['order_attach.status', 'in', $param['order_status']];// 订单状态
                }
            }
            if (array_key_exists('keyword', $param) && $param['keyword']) $condition[] = ['order_attach_number|nickname|phone', 'like', '%' . $param['keyword'] . '%'];

            $data = $cutActivity
                ->alias('a')
                ->join('member member', 'member.member_id = a.owner')
                ->join('order_attach order_attach', 'order_attach.cut_activity_id = a.cut_activity_id', 'left')
                ->join('cut_goods cut_goods', 'cut_goods.cut_goods_id = a.cut_goods_id', 'left')
                ->where($order_attach_number)
                ->relation(['cut_activity_attach' => function ($e) {
                    $e->field('IFNULL(sum(cut_price),0) as cutTotal,IFNULL(count(helper),0) as memberCount')->find();
                }])
                ->where([
                    ['a.cut_goods_id', '=', $param['id']]
                ])
                ->where($condition)
                ->whereOr($whereOr)
                ->field('order_attach.order_attach_number,member.nickname,member.phone,a.cut_activity_id,order_attach.subtotal_price,a.status,
                order_attach.status as order_status,distribution_type,order_attach.create_time,a.cut_activity_id')
                ->paginate(10, false, ['query' => $param]);

            // 商品信息
            $item = $cutGoods
                ->alias('a')
                ->join('goods goods', 'goods.goods_id = a.goods_id')
                ->field('a.*,goods_name,shop_price,cut_price,store_id,file')
                ->where(['cut_goods_id' => $request::get('id')])
                ->find();

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'item' => $item,
            'data' => $data,
        ]);
    }

    /**
     * 砍价商品删除
     * @param Request $request
     * @param CutGoodsModel $cutGoods
     * @return array
     */
    public function destroy(Request $request, CutGoodsModel $cutGoods)
    {
        if ($request::isPost()) {
            try {
                if (empty($request::post('id'))) throw new Exception('无可删除的砍价商品', -100);
                // 删除
                $operation = $cutGoods->cutDelete($request::post('id'));

                if ($operation) return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}