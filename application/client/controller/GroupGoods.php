<?php
// 拼团商品管理
declare(strict_types = 1);

namespace app\client\controller;

use app\common\model\Products;
use app\common\service\Beanstalk;
use think\Db;
use think\Exception;
use think\Controller;
use think\facade\Request;
use think\facade\Session;
use app\common\model\Brand as BrandModel;
use app\common\model\Store as StoreModel;
use app\common\model\GroupGoods as GroupGoodsModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use app\common\model\GroupClassify as GroupClassifyModel;
use app\common\model\Goods as GoodsModel;


class GroupGoods extends Controller
{
    /**
     * 拼团商品列表
     * @param Request $request
     * @param GroupGoodsModel $groupGoods
     * @param GroupClassifyModel $groupClassify
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function index(Request $request, GroupGoodsModel $groupGoods, GroupClassifyModel $groupClassify)
    {
        try {
            // 获取参数
            $param = $request::get();

            // 筛选条件
            $condition[] = ['g.group_goods_id', 'neq', 0];

            // 店铺平台商品切换（待优化）
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
            if (isset($param['status']) && $param['status'] >= 0) $condition[] = ['g.status', 'eq', $param['status']];
            if (isset($param['group_classify_id']) && $param['group_classify_id'] > 0)
                $condition[] = ['g.group_classify_id', 'eq', $param['group_classify_id']];

            // 获取数据
            $data = $groupGoods->alias('g')
                ->join(['ishop_goods' => 'go'], 'go.goods_id = g.goods_id and go.is_putaway = 1 and go.delete_time is null')
                ->join(['ishop_store' => 'ss'], 'ss.store_id = go.store_id and ss.status = 4 and ss.delete_time is null')
                ->where('ss.store_id', Session::get('client_store_id'))
                ->field('g.group_goods_id,go.goods_name,go.group_price,go.shop_price,go.goods_id,
                go.goods_number,go.group_num,g.status,g.up_shelf_time,g.down_shelf_time,ss.shop,ss.store_name')
                ->where($condition)
                ->order(['g.create_time' => 'desc'])
                ->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data'       => $data,
            'count'      => $groupGoods->countGroup(),
            'classifies' => $groupClassify::all(),
        ]);
    }


    /**
     * 添加拼团商品
     * @param Request $request
     * @param GroupGoodsModel $groupGoods
     * @param GroupClassifyModel $groupClassify
     * @param GoodsClassifyModel $goodsClassify
     * @param BrandModel $brand
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, GroupGoodsModel $groupGoods, GroupClassifyModel $groupClassify, GoodsClassifyModel $goodsClassify, BrandModel $brand)
    {
        if ($request::isPost()) {

            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                $check = $groupGoods->valid($param, 'create');
                if ($check['code']) return $check;

                $_groupGoodsData = $param;

                $_groupGoodsData['up_shelf_time'] = date('Y-m-d',strtotime($param['up_shelf_time']));
                $_groupGoodsData['down_shelf_time'] = date('Y-m-d',strtotime($param['down_shelf_time']));

                // 写入
                $groupGoods->allowField(true)->save($_groupGoodsData);

                if (array_key_exists('attr_group_price', $param)) {
                    foreach ($param['products_id'] as $key => $value) {
                        $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                        if (!preg_match($reg, $param['attr_group_price'][$key])) return ['code' => -100, 'message' => '属性拼团价为正整数或保留两位小数'];
                        if ((new Products())->where('products_id', $value)->value('attr_shop_price') < $param['attr_group_price'][$key]) return ['code' => -100, 'message' => '属性拼团价小于属性原价'];
                    }
                    $groupGoods->setSub($param);
                }
                $groupGoods->setGoods($param);
                (new Beanstalk())->put(json_encode(['queue' => 'groupGoodsExpireChangeStatus',
                    'id' => $groupGoods->group_goods_id, 'time' => date('Y-m-d H:i:s')]),
                    (strtotime($param['down_shelf_time']) - time()));

                // 提交事务
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/group_goods/index'];

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
            'categoryOne'   => $categoryOne,
            'brand'         => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'classify_list'    => find_level($groupClassify->field('group_classify_id,title,parent_id')->select()->toArray(), 'group_classify_id'),
        ]);
    }

    /**
     * 编辑拼团商品
     * @param Request $request
     * @param GroupGoodsModel $groupGoods
     * @param GoodsClassifyModel $goodsClassify
     * @param GroupClassifyModel $groupClassify
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, GroupGoodsModel $groupGoods, GoodsClassifyModel $goodsClassify,GroupClassifyModel $groupClassify)
    {
        if ($request::isPost()) {

            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                $check = $groupGoods->valid($param, 'edit');
                if ($check['code']) return $check;

                // 更新
                $groupGoods->allowField(true)->isUpdate(true)->save($param);
                if (array_key_exists('attr_group_price', $param)) {
                    foreach ($param['products_id'] as $key => $value) {
                        $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                        if (!preg_match($reg, $param['attr_group_price'][$key])) return ['code' => -100, 'message' => '属性拼团价为正整数或保留两位小数'];
                        if ((new Products())->where('products_id', $value)->value('attr_shop_price') < $param['attr_group_price'][$key]) return ['code' => -100, 'message' => '属性拼团价小于属性原价'];
                    }
                    $groupGoods->setSub($param);
                }
                $groupGoods->setGoods($param);
                (new Beanstalk())->put(json_encode(['queue' => 'groupGoodsExpireChangeStatus',
                    'id' => $param['group_goods_id'], 'time' => date('Y-m-d H:i:s')]),
                    (strtotime($param['down_shelf_time']) - time()));
                // 提交事务
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/group_goods/index'];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify->where('parent_id', 0)->field('goods_classify_id,parent_id,title,count')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();

        return $this->fetch('create', [
            'categoryOne' => $categoryOne,
            'item'        => $groupGoods->relation(['Goods' => function ($query) {
                $query->field('goods_id,goods_name,shop_price,group_price');
            }])->where(['group_goods_id' => $request::get('id')])->find(),
            'classify_list'    => find_level($groupClassify->field('group_classify_id,title,parent_id')->select()->toArray(), 'group_classify_id'),
        ]);
    }

    /**
     * 查看拼团商品
     * @param Request $request
     * @param GroupGoodsModel $groupGoods
     * @param GroupClassifyModel $groupClassify
     * @param StoreModel $store
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function inspect(Request $request, GroupGoodsModel $groupGoods, GroupClassifyModel $groupClassify,StoreModel $store)
    {
        // 商品信息
        $item = $groupGoods->relation(['Goods' => function ($query) {
            $query->field('goods_id,goods_name,shop_price,group_price,store_id');
        }])->where(['group_goods_id' => $request::get('id')])->find();
        $item['shop_name'] = $store->where('store_id',$item['Goods']['store_id'])->value('store_name');

        return $this->fetch('',[
            'classify_list'    => find_level($groupClassify->field('group_classify_id,title,parent_id')->select()->toArray(), 'group_classify_id'),
            'item'        => $item
        ]);
    }

    /**
     * 删除拼团商品
     * @param Request $request
     * @param GroupGoodsModel $groupGoods
     * @return array
     */
    public function destroy(Request $request, GroupGoodsModel $groupGoods)
    {
        if ($request::isPost()) {
            try {
                if (empty($request::post('id'))) throw new Exception('无可删除的砍价商品', -100);
                // 删除
                $operation = $groupGoods->groupDelete($request::post('id'));

                if ($operation) return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}