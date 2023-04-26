<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\Area;
use app\common\model\Attr;
use app\common\model\AttrType;
use app\common\model\Brand;
use app\common\model\Cart;
use app\common\model\CollectGoods;
use app\common\model\DistributionLevel;
use app\common\model\GoodsAttr;
use app\common\model\GoodsClassify;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use app\common\model\GoodsOperation;
use app\common\model\GoodsParameter;
use app\common\model\MemberRank;
use app\common\model\Message;
use app\common\model\OrderGoods;
use app\common\model\Products;
use app\common\model\Store;
use app\common\model\StoreGoodsClassify;
use app\common\model\FreightExpressClassify;
use app\common\model\StoreOperation;
use app\computer\controller\goods\Classify;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use app\common\model\Goods as GoodsModel;
use app\common\model\Brand as BrandModel;
use app\common\model\Limit;
use app\common\model\LimitInterval;
use app\common\model\GroupGoods;
use app\common\model\GroupClassify;
use app\common\model\CutGoods;
use app\common\service\Beanstalk;
use app\common\model\Integral as IntegralModel;
use app\common\model\IntegralClassify as IntegralClassifyModel;
use app\common\model\GoodsEvaluate;
use think\facade\Session;
use app\common\model\GoodsReductionNotic;

/**
 * 商品
 * Class Goods
 * @package app\master\controller
 */
class Goods extends Controller
{

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.distribution');
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    /**
     * 商品概况
     * @param GoodsModel $goods
     * @param CollectGoods $collectGoods
     * @param GoodsReductionNotic $goodsReductionNotic
     * @return array|mixed
     */
    public function general(GoodsModel $goods, CollectGoods $collectGoods, GoodsReductionNotic $goodsReductionNotic)
    {
        try {
            $_joinCondition = [
                'store store',
                'store.store_id = a.store_id',
                'left',
            ];

            $_field = 'IFNULL(sum(case when shop = 0 and is_putaway = 1 then 1 else 0 end),0) as self_goods,
                IFNULL(sum(case when shop in (1,2) and is_putaway = 1 then 1 else 0 end),0) as enter_goods,
                IFNULL(sum(case when shop = 0 and review_status = 2 then 1 else 0 end),0) as self_checked,
                IFNULL(sum(case when shop = 0 and review_status = 1 and is_putaway = 1 then 1 else 0 end),0) as self_checked_yes,
                IFNULL(sum(case when shop = 0 and review_status = 0 then 1 else 0 end),0) as self_checked_no,
                IFNULL(sum(case when shop in (1,2) and review_status = 2 then 1 else 0 end),0) as enter_checked,
                IFNULL(sum(case when shop in (1,2) and review_status = 1 and is_putaway = 1 then 1 else 0 end),0) as enter_checked_yes,
                IFNULL(sum(case when shop in (1,2) and review_status = 0 then 1 else 0 end),0) as enter_checked_no,
                IFNULL(sum(case when is_group = 1 and is_putaway = 1 and shop = 0 then 1 else 0 end),0) as group_count,
                IFNULL(sum(case when is_bargain = 1 and is_putaway = 1 and shop = 0 then 1 else 0 end),0) as bargain_count,
                IFNULL(sum(case when is_limit = 1 and is_putaway = 1 and shop = 0 then 1 else 0 end),0) as limit_count,a.goods_id';

            $_singleShop = FALSE;
            $_joinWhere = "";
            // 单店
            if (config('user.one_more') != 1) {
                $_oneStoreId = config('user.one_store_id');

                $_joinCondition = [
                    'store store',
                    'store.store_id = ' . $_oneStoreId,
                ];

                $_singleShop = TRUE;
                $_field = "IFNULL(sum(case when is_putaway = 1 and a.store_id = {$_oneStoreId} then 1 else 0 end),0) as self_goods,
                IFNULL(sum(case when is_group = 1 and is_putaway = 1 and a.store_id = {$_oneStoreId} then 1 else 0 end),0) as group_count,
                IFNULL(sum(case when is_bargain = 1 and is_putaway = 1 and a.store_id = {$_oneStoreId} then 1 else 0 end),0) as bargain_count,
                IFNULL(sum(case when is_limit = 1 and is_putaway = 1 and a.store_id = {$_oneStoreId} then 1 else 0 end),0) as limit_count,a.goods_id";

                $_joinWhere = " AND goods.store_id={$_oneStoreId}";
            }

            // 获取数据
            $data = $goods
                ->alias('a')
                ->join(...$_joinCondition)
                ->field($_field)
                ->cache(TRUE, 60)
                ->find();


            $data['collect_goods'] = $collectGoods->alias('a')
                ->join('goods goods', 'a.goods_id = goods.goods_id' . $_joinWhere)
                ->join('store store', 'goods.store_id = store.store_id')
                ->where('store.shop', '0')
                ->group('a.goods_id')->cache(TRUE, 60)
                ->count();
    
            $data['goods_reduction_notic'] = $goodsReductionNotic->alias('a')
                ->join('goods goods', 'a.goods_id = goods.goods_id' . $_joinWhere.' and isnull(goods.delete_time)')
                ->join('store store', 'goods.store_id = store.store_id')
                ->join('member member', 'member.member_id = a.member_id and isnull(member.delete_time)')
                ->where('store.shop', '0')
                ->group('a.goods_id')->cache(TRUE, 60)
                ->count();

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'item' => $data,
            'single_shop' => $_singleShop,
        ]);
    }

    /**
     * 自营/店铺商品列表
     * @param Request $request
     * @param GoodsModel $goods
     * @param Brand $brand
     * @return mixed
     * @throws Exception
     */
    public function index()
    {
        try {
            // 平台的品牌列表
            $_platFormBrandList = Brand::field([
                'brand_id',
                'brand_name',
            ])->select();

            $_filterWhere = $this->filterWhere();

            // 商品模型类
            $_goodsList = $_filterWhere['GOODS_LIST'];
            // 查询条件
            $_where = $_filterWhere['WHERE'];
            // 分页配置
            $_pageConfig = $_filterWhere['PAGE_CONFIG'];

            // 商品分类列表
            $_goodsClassifyListSourceData = GoodsClassifyModel::where([
                ['status', '=', '1'],
            ])->select();

            // 商品信息
            $_goodsList = $_goodsList
                ->alias('Goods')// 定义别名
                ->with([
                    'groupHO',// 拼团
                    'cutHO',// 砍价
                    'limitHO',// 限时抢购
                    'spec'      // 商品属性
                ])
                ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                ->join('Brand Brand', 'Brand.brand_id = Goods.brand_id', 'left');                       // 品牌

            // 商品分类数据
            $_goodsClassifyList = [];
            // 构建数组下标为分类ID
            foreach ($_goodsClassifyListSourceData as $value) {
                $_goodsClassifyList[$value['goods_classify_id']] = $value;
            }

            // 给页面显示的商品分类
            $_goodsClassifyListForPage = find_level(
                $_goodsClassifyListSourceData,
                'goods_classify_id'
            );

            // 默认的商品排序商品的排序
            $_order = [
                'Goods.create_time' => 'desc'   // 时间倒序
            ];

            // 收藏进入
            if ($this->request->get('is_collect', 0)) {
                $_order = [
                    'Goods.collect_number' => 'desc'   // 收藏倒序
                ];
            }
    
            // 降价通知进入
            if ($this->request->get('reduction_notic', 0))
            {
                $_goodsList = $_goodsList->join('GoodsReductionNotic GoodsReductionNotic', 'GoodsReductionNotic.goods_id = Goods.goods_id')
                    ->join('member member', 'member.member_id = GoodsReductionNotic.member_id and isnull(member.delete_time)')
                    ->group('Goods.goods_id');
            }
            $_goodsNumWarnWhereOr = $_goodsNumWarnWhereOrList = [];
            $product_warn_goods = (new Products())
                ->where([
                    ['attr_goods_number', '<', Db::raw('`attr_warn_number`')],
                ])
                ->column('goods_id');
            if (!empty($product_warn_goods)) {
                $_goodsNumWarnWhereOr[] = ['Goods.goods_id', 'in', implode(',', array_unique($product_warn_goods))];
                if ($this->request->get('status', 0) == 4) {
                    $_goodsNumWarnWhereOrList = $_goodsNumWarnWhereOr;
                }
            }
            $_goodsList = $_goodsList
                ->where($_where)
                ->whereOr($_goodsNumWarnWhereOrList)
                ->order($_order)
                ->field([
                    'Goods.goods_id' => 'goods_id',
                    'Goods.is_limit' => 'is_limit',
                    'Goods.is_group' => 'is_group',
                    'Goods.is_bargain' => 'is_bargain',
                    'Goods.goods_classify_id' => 'goods_classify_id',
                    'Goods.file' => 'file',                     // 商品缩略图
                    'Goods.goods_name' => 'goods_name',         // 商品名称
                    'Store.store_id' => 'store_id',             // 店铺id
                    'Store.store_name' => 'store_name',         // 店铺名称
                    'Goods.shop_price' => 'shop_price',         // 售价
                    'Goods.goods_sn' => 'goods_sn',             // 货号
                    'Goods.goods_number' => 'goods_number',     // 库存
                    'Goods.sales_volume' => 'sales_volume',     // 销量
                    'Goods.collect_number' => 'collect_number', // 收藏
                    'Goods.review_status' => 'review_status',   // 审核状态
                    'Goods.is_putaway' => 'is_putaway',         // 上架状态
                    'Goods.is_freight' => 'is_freight',         // 是否包邮
                    'Goods.freight_status' => 'freight_status', // 快递运费状态
                    'Goods.freight_price' => 'freight_price',   // 快递固定运费价格
                    'Goods.is_distributor' => 'is_distributor',   // 快递固定运费价格
                    'Goods.is_distribution' => 'is_distribution',   // 快递固定运费价格
                    'Brand.brand_name' => 'brand_name',   // 品牌名称
                ])
                ->paginate(NULL, FALSE, [
                    'query' => $_pageConfig,
                ]);


            function in_activity($val)
            {
                return $val->is_group || $val->is_bargain || $val->is_limit;
            }

            function can_distribution($val)
            {
                return $val['is_group'] || $val['is_bargain'] || $val['is_limit'];
            }

            // 商品数据
            $_goodsListData = [];
            // 循环出商品信息
            foreach ($_goodsList as $key => $val) {
                $_goodsListData[$key] = $val->toArray();
                $_goodsListData[$key]['in_activity'] = in_activity($val);
                $_goodsListData[$key]['can_distribution'] = can_distribution($val) ? 0 : 1;
                $_goodsListData[$key]['classify_name'] = [];

                // 商品分类ID
                $_goodsClassifyId = $val['goods_classify_id'];

//            // 如果商品分类ID存在 循环出商品三级分类
                while (isset($_goodsClassifyList[$_goodsClassifyId])) {
                    array_unshift($_goodsListData[$key]['classify_name'], $_goodsClassifyList[$_goodsClassifyId]['title']);
                    $_goodsClassifyId = $_goodsClassifyList[$_goodsClassifyId]['parent_id'];
                }

                if (empty($_goodsListData[$key]['classify_name'])) {
                    $_goodsListData[$key]['classify_name'] = '-';
                } else {
                    $_goodsListData[$key]['classify_name'] = join('/', $_goodsListData[$key]['classify_name']);
                }

            }


            // 将 where 赋值给所有需要计算数量的条件
            $_goodsAllWhere = $_goodsPutAwayWhere = $_goodsPutAwayDownWhere = $_goodsWaitReviewWhere = $_goodsNumWarnWhere = $_goodsDeleteWhere = $_goodsReviewFieldCount = $_where;


            unset($_goodsAllWhere[1]);
            unset($_goodsAllWhere[2]);
            unset($_goodsAllWhere[3]);
            // 全部商品总数
            // 全部商品数量 包含上架商品,下架商品,待审核商品,审核未通过的商品和商品回收站的商品
            $this->assign(
                'goods_all_count',
                (new GoodsModel())
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_goodsAllWhere)
                    ->count('Goods.goods_id')
            );

            // 上架商品总数
            // 上架+审核通过商品
            $_goodsPutAwayWhere[1] = [
                'Goods.is_putaway',
                '=',
                1,
            ];
            unset($_goodsPutAwayWhere[2]);
            unset($_goodsPutAwayWhere[3]);
            $this->assign(
                'goods_put_away_count',
                (new GoodsModel())
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_goodsPutAwayWhere)
                    ->count('Goods.goods_id')
            );


            // 下架商品总数
            unset($_goodsPutAwayDownWhere[2]);
            unset($_goodsPutAwayDownWhere[3]);

            $_goodsPutAwayDownWhere[1] = [
                'Goods.is_putaway',
                '=',
                0,
            ];
            $this->assign(
                'goods_put_away_down_count',
                (new GoodsModel())
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_goodsPutAwayDownWhere)
                    ->count('Goods.goods_id')
            );

            // 待审核商品总数
            unset($_goodsWaitReviewWhere[1]);
            unset($_goodsWaitReviewWhere[3]);
            $_goodsWaitReviewWhere[2] = [
                'Goods.review_status',
                '=',
                2,
            ];
            $this->assign(
                'goods_wait_review_count',
                (new GoodsModel())
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_goodsWaitReviewWhere)
                    ->count('Goods.goods_id')
            );

            // 库存预警商品总数
            // 库存数量少于库存预警数量的商品总数
            unset($_goodsNumWarnWhere[1]);
            unset($_goodsNumWarnWhere[2]);
            $_goodsNumWarnWhere[3] = [
                'Goods.goods_number',
                '<',
                Db::raw('`Goods`.`warn_number`'),
            ];
            $this->assign(
                'goods_num_warn_count',
                (new GoodsModel())
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_goodsNumWarnWhere)
                    ->whereOr($_goodsNumWarnWhereOr)
                    ->count('Goods.goods_id')
            );

            // 商品回收站
            // 所有被删除的商品的数量
            unset($_goodsDeleteWhere[1]);
            unset($_goodsDeleteWhere[2]);
            unset($_goodsDeleteWhere[3]);
            $this->assign(
                'goods_delete_count',
                GoodsModel::onlyTrashed()
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_goodsDeleteWhere)
                    ->count('goods_id')
            );

            // 审核未通过商品
            // 全部审核未通过的商品数量
            unset($_goodsReviewFieldCount[1]);
            unset($_goodsReviewFieldCount[2]);
            unset($_goodsReviewFieldCount[3]);

            $_goodsReviewFieldCount[2] = [
                'Goods.review_status',
                '=',
                '0',
            ];
            $this->assign(
                'goods_review_field_count',
                (new GoodsModel())
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_goodsReviewFieldCount)
                    ->count('Goods.goods_id')
            );

            $this->assign('goods_classify_list', $_goodsClassifyListForPage);
            $this->assign('plat_form_brand_list', $_platFormBrandList);
            $this->assign('shop_type', (int)$this->request->get('shop_type', 1));
            $this->assign('status', (int)$this->request->get('status', 0));
            $this->assign('classify', (int)$this->request->get('classify', 0));
            $this->assign('store_name', (string)$this->request->get('store_name', ''));
            $this->assign('goods_name', (string)$this->request->get('goods_name', ''));
            $this->assign('brand', (int)$this->request->get('brand', 0));
            $this->assign('store_type', (int)$this->request->get('store_type', 0));
            $this->assign('recommend', (int)$this->request->get('recommend', 0));
            $this->assign('activity', (int)$this->request->get('activity', 0));
            $this->assign('distribution', (int)$this->request->get('distribution', 0));
            $this->assign('data', $_goodsListData);
            $this->assign('data_total', $_goodsList->total());
            $this->assign('data_list_rows', $_goodsList->listRows());
            $this->assign('data_paginate', $_goodsList->render());
            $this->assign('single_shop', config('user.one_more'));
            return $this->fetch();
        } catch (\Exception $e) {
            $this->error('系统出错,请稍后再试');
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 查看商品是否在活动中
     * @param Request $request
     * @param GoodsModel $goods
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getActive(Request $request, GoodsModel $goods)
    {

        //看商品是否处于活动中
        $param = $request::post();
        $data = $goods->where(['goods_id' => $param['id'], 'is_group' => 0, 'is_bargain' => 0, 'is_limit' => 0])->find();
        if ($data != '') {
            return ['code' => 0];  //正常商品
        } else {
            return ['code' => -100];//活动中商品
        }
    }

    /**
     * 智能权重展示与编辑
     * @param Request $request
     * @param GoodsModel $goods
     * @return mixed
     * @throws \Exception
     */
    public function weight(Request $request)
    {
        try {
            $param = $request::get();

            $goods = GoodsModel::withTrashed()
                ->where([
                    ['goods_id', '=', $param['id']],
                ])
                ->field([
                    'goods_id', 'store_id', 'collect_number', 'sort', 'goods_name',
                ])->find();

            // 购买数量
            $_data['buy_num'] = OrderGoods::where([
                ['goods_id', '=', $goods['goods_id']],
                ['status', 'in', '4.1, 3.1, 2.1, 1.1, 1.2'],
            ])->field([
                'COUNT(quantity)' => 'quantity',
            ])->find()['quantity'];


            // 退换货的数量
            $_data['refund'] = OrderGoods::where([
                ['goods_id', '=', $goods['goods_id']],
                ['status', 'in', '4.3,4.2'],
            ])->count('quantity');


            // 购买会员的数量
            $_data['buyer_num'] = OrderGoods::where([
                ['goods_id', '=', $goods['goods_id']],
            ])->group('member_id')->count();

            // 评价的数量
            $_data['eva_num'] = GoodsEvaluate::where([
                ['goods_id', '=', $goods['goods_id']],
            ])->count();

            // 对商家的评价数量
            $_data['eva_to_store'] = GoodsEvaluate::where([
                ['store_id', '=', $goods['store_id']],
            ])->count();
            // 关注数量
            $_data['collect_number'] = $goods['collect_number'];
            // 人工干预值
            $_data['sort'] = $goods['sort'];
            // 商品名称
            $_data['goods_name'] = $goods['goods_name'];


            return $this->fetch('', [
                'data' => $_data,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function status(Request $request, GoodsModel $goods)
    {
        //        try {
        $param = $request::get();
        $data = $goods->where('goods_id', $param['id'])
            ->field('goods_id,review_status')
            ->find();

        return $this->fetch('', [
            'data' => $data,
        ]);
        //        } catch (\Exception $e) {
        //            throw new \Exception($e->getMessage());
        //        }
    }

    /**
     * 规格库存展示
     * @param Request $request
     * @param Products $products
     * @return mixed
     * @throws \Exception
     */
    public function productShow(Request $request, Products $products)
    {
        try {
            $param = $request::get();
            $data = $products
                ->where(['goods_id' => $param['id']])
                ->field('products_id,goods_attr,attr_shop_price,attr_warn_number,attr_goods_number,attr_goods_sn')
                ->order(['products_id' => 'asc'])
                ->select();
            return $this->fetch('productShow', [
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 单修改参数
     * @param Request $request
     * @param GoodsModel $goods
     * @param Products $products
     * @param Store $store
     * @return array
     */
    public function editVal(Request $request, GoodsModel $goods, Products $products, Store $store)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $model = $goods;
                if (array_key_exists('products_id', $param)) {
                    $model = $products;
                }
                if (array_key_exists('switchType', $param)) {
                    $changeVal = ['is_distributor', 'is_distribution', 'is_popularity', 'is_preference', 'is_putaway', 'store_particularly_recommend',
                        'store_recommend', 'store_poster', 'store_banner'];
                    $param[$changeVal[$param['switchType']]] = $param['checked'];
                }
                //如果是开关轮播推荐则判断是否开启其他
                if (isset($param['store_banner']) && $param['checked'] == 1) {
                    $store_banner_count = $model->where([['store_id', '=', $param['store_id']], ['store_banner', '=', 1]])->count();
                    if ($store_banner_count > 0) {
                        return ['code' => -201, 'message' => '轮播推荐已经有一个开启，是否关闭其他推荐开启当前'];
                    }
                }
                $model->allowField(TRUE)->isUpdate(TRUE)->save($param);
                $store->countGoodsNum($param['goods_id']);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    //关闭当前店铺所有其他轮播
    public function editValStoreBanner(Request $request, GoodsModel $goods, Store $store)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $goods->where([['store_id', '=', $param['store_id']], ['store_banner', '=', 1]])->setField('store_banner', '0');
                $goods->where([['store_id', '=', $param['store_id']], ['goods_id', '=', $param['goods_id']]])->setField('store_banner', '1');
                $store->countGoodsNum($param['goods_id']);
            } catch (\Exception $e) {

            }
        }
    }

    /**
     * 删除商品
     * @param Request $request
     * @param GoodsModel $goods
     * @return array
     */
    public function destroy(Request $request, GoodsModel $goods, Store $store)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                $param = $request::post();
                $data = $goods->where([['goods_id', 'in', $param['id']],
                    ['is_group', 'eq', 0],
                    ['is_bargain', 'eq', 0],
                    ['is_limit', 'eq', 0],
                ])->select();
                if (count($data) == 0) {
                    return ['code' => -100, 'message' => '商品处于活动中，请先删除活动商品'];//活动中商品
                } else {
                    $result = 0;
                    foreach ($data as $k => $v) {
                        if ($v == '') $result = 1;
                    }
                    if ($result == 1) return ['code' => -100, 'message' => '商品处于活动中，请先删除活动商品'];
                }
                //验证数据
                $valid = $goods->valid($param, 'destroy');
                if ($valid['code']) return $valid;


                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        // 删除时间
                        'delete_time' => date('Y-m-d H:i:s'),
                        // 砍价
                        'is_bargain' => 0,
                        'cut_price' => 0,
                        // 拼团
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                        // 限时抢购
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];


                $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = '主商品被删除';

                // 商品
                GoodsModel::where([
                    ['goods_id', 'in', $param['id']],
                ])->update($_saveData['GOODS']);

                // 拼团
                GroupGoods::where([
                    ['goods_id', 'in', $param],
                    ['status', '<>', 0],
                ])->update($_saveData['GROUP_GOODS']);

                // 砍价
                CutGoods::where([
                    ['goods_id', 'in', $param],
                    ['status', '<>', 0],
                ])->update($_saveData['CUT_GOODS']);

                // 限时抢购
                Limit::where([
                    ['goods_id', 'in', $param],
                    ['status', '<>', 0],
                ])->update($_saveData['LIMIT']);

                $store->countGoodsNum($param['id']);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 永久删除商品
     * @param Request $request
     * @param GoodsModel $goods
     * @param Store $store
     * @return array
     */
    public function foreverDestroy(Request $request, GoodsModel $goods, Store $store)
    {
        if ($request::isPost()) {
            try {
                Db::startTrans();
                $param = $request::post();
                //验证数据
                $valid = $goods->valid($param, 'destroy');
                if ($valid['code']) return $valid;

                //                // 先删除商品的参数
                //                Db::name('GoodsParameter')->where(['goods_id' => $param['id']])->delete();
                //                // 再删除商品的浏览记录
                //                Db::name('RecordGoods')->where(['goods_id' => $param['id']])->delete();
                //                // 最后删除商品
                //                Db::name('Goods')->where(['goods_id' => $param['id']])->delete();

                //                GoodsModel::destroy($param['id'], true);
                Db::name('goods')
                    ->where([['goods_id', 'in', $param['id']]])
                    ->update(['forever_del_status' => 1]);
                $store->countGoodsNum($param['id']);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 恢复商品数据
     * @param Request $request
     * @param GoodsModel $goods
     * @return array
     */
    public function recover(Request $request, GoodsModel $goods, Store $store)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $data = $goods::onlyTrashed()
                    ->where([['goods_id', 'in', $param['id']]])
                    ->field('goods_id')
                    ->select();
                if (count($data) > 0)
                    foreach ($data as $item) {
                        $item->restore();
                    }
                $store->countGoodsNum($param['id']);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 创建商品展示
     * @param \think\Request $request
     * @param FreightExpressClassify $freightExpressClassify
     * @param StoreGoodsClassify $StoreGoodsClassify
     * @param GoodsModel $goods
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param AttrType $attrType
     * @param MemberRank $memberRank
     * @param Store $store
     * @param DistributionLevel $distributionLevel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(\think\Request $request,
                           FreightExpressClassify $freightExpressClassify,
                           StoreGoodsClassify $StoreGoodsClassify,
                           GoodsModel $goods, GoodsClassify $goodsClassify,
                           Brand $brand, AttrType $attrType,
                           MemberRank $memberRank,
                           Store $store,
                           DistributionLevel $distributionLevel)
    {
        $param = $request->get();

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where([
                ['parent_id', '=', 0],
                ['status', '=', 1],
            ])
            ->field('goods_classify_id,parent_id,title,count')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])
            ->select();

        //查询全部品牌
        $brandData = $brand
            ->where([['brand_id', '>', 0]])
            ->field('brand_id,brand_name,brand_first_char')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])
            ->select();

        //查询全部品牌首字母
        $brandFirstChr = $brand
            ->where([['brand_id', '>', 0]])
            ->distinct(TRUE)
            ->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');
        // 当前会员等级名称
        $rank_name = $memberRank
            ->where([['member_rank_id', '>', 0]])
            ->field('rank_name,discount')
            ->select();

        // 自营店铺
        if ($param['shop'] == 0) {
            $storeData = $store
                ->where([
                    ['shop', '=', $param['shop']],
                    ['status', '=', 4],
                ])
                ->field('store_id,member_id,store_name')
                ->select();
        }
        if (array_key_exists('id', $param) && $param['id']) {
            $goodsData = self::editGoodsShow($param['id'], $goods, $goodsClassify);
            $cateArr = getParCate($goodsData['goods_classify_id'], $goodsClassify);
            $goodsData['cateTitle'] = implode(' / ', array_column($cateArr, 'title'));
            $goodsData['attr'] = self::getAttrType($request, $attrType, ['store_id' => $goodsData['store_id']])['data'];

            //查询商家分类
            $storeGoodsClassify = find_level(
                $StoreGoodsClassify
                    ->where(['status' => 1, 'store_id' => $goodsData['store_id']])
                    ->field('store_goods_classify_id,title,parent_id')
                    ->select(),
                'store_goods_classify_id'
            );
            //运费模板
            $freight = $freightExpressClassify
                ->where(['store_id' => $goodsData['store_id']])
                ->field('freight_express_classify_id,name')
                ->select();
        }
        // 单店
        if (config('user.one_more') != 1) {

            $storeGoodsClassify = find_level(
                $StoreGoodsClassify
                    ->where(['status' => 1, 'store_id' => config('user.one_store_id')])
                    ->field('store_goods_classify_id,title,parent_id')
                    ->select(),
                'store_goods_classify_id'
            );
            //运费模板
            $freight = $freightExpressClassify
                ->where(['store_id' => config('user.one_store_id')])
                ->field('freight_express_classify_id,name')
                ->select();
        }

        //查询用户设置过的商品分类缓存
        $cateCache = Cache::get('cateCreateCache_' . $request->loginId);
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distribution_status = Env::get('DISTRIBUTION_STATUS', 0);

        $goodsArr = isset($goodsData) ? $goodsData : [];
        if (!empty($goodsArr)) {
            if ($goodsArr['distribution_set']) {
                $goodsArr['distribution_set'] = unserialize($goodsArr['distribution_set']);
            }

            $goodsArr['multiple_file_extra_data'] = join(',', $goodsArr['multiple_file_extra']);
            $goodsArr['multiple_file_data'] = join(',', $goodsArr['multiple_file']);
        }

        Env::load(Env::get('app_path') . 'common/ini/.config');

        return $this->fetch('', [
            'cateCache' => $cateCache ?: [],
            'categoryOne' => $categoryOne,
            'brand' => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'rank_name' => $rank_name,
            'goodsData' => $goodsArr,
            'storeData' => isset($storeData) ? $storeData : [],
            'storeGoodsClassify' => isset($storeGoodsClassify) ? $storeGoodsClassify : [],
            'freight' => isset($freight) ? $freight : [],
            'distribution_status' => $distribution_status,
            'distribution_level' => $distributionLevel
                ->field('distribution_level_id,level_title,level_weight')
                ->order('level_weight', 'asc')
                ->select(),
            'single_store' => Config::get('user.one_more'),
            'is_shop' => Env::get('is_shop'),
            'is_city' => Env::get('is_city'),
            'one_more' => config('user.one_more'),
            'one_store_id' => config('user.one_store_id'),
            'pc_config' => config('user.pc.is_include'),
        ]);
    }

    /**
     * 批量审核
     */
    public function batchReview()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {

                // 商品   Goods
                // review_status    审核状态 0 未通过 1 已通过 2 待审核
                // review_content   审核内容

                // 限时抢购 Limit
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容

                // 拼团   GroupGoods
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容

                // 砍价   CutGoods
                // status   审核状态 0 未通过 1 已通过 2 待审核
                // reason   审核内容

                // 商品ID列表 eg:1,2,3,4,5
                $_idList = $this->request->get('id');

                // 审核状态 0 => 审核未通过 1=> 审核通过
                $_type = $this->request->post('type', 1);

                // 审核内容
                $_content = $this->request->post('content', '');

                // id列表总数
                $_idListCount = count(explode(',', $_idList));

                // 返回的信息
                $_resultMessage = config('message.')[0];

                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        'review_status' => 0,
                        // 默认下架
                        'is_putaway' => 0,
                        // 砍价
                        'is_bargain' => 0,
                        'cut_price' => 0,
                        // 拼团
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                        // 限时抢购
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];


                // 判断审核状态
                switch ($_type) {
                    case 0: // 审核未通过
                        if (empty($_content)) {
                            return ['code' => -100, 'message' => '请填写审核未通过原因'];
                        }

                        $_saveData['GOODS']['review_content'] = $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = $_content;

                        // 获取条件中 非审核未通过的商品
                        $_goodsList = GoodsModel::where([
                            ['goods_id', 'in', $_idList],
                            ['review_status', '<>', 1]  // 不是审核未通过的商品
                        ])->column('goods_id');

                        // 查询出来的 结果 的总数
                        $_idResultCount = count($_goodsList);

                        // 如果 都不符合
                        if ($_idResultCount <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }

                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_idResultCount;

                        // Id 结果 以 , 分隔
                        $_idResultJoin = join(',', $_goodsList);

                        // 修改 商品
                        GoodsModel::where([
                            ['goods_id', 'in', $_idResultJoin],
                        ])->update($_saveData['GOODS']);

                        // 修改 显示抢购
                        Limit::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['LIMIT']);

                        // 修改 拼团
                        GroupGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['GROUP_GOODS']);

                        // 修改 砍价
                        CutGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['CUT_GOODS']);

                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作" . count($_goodsList) . "个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        break;
                    case 1: // 审核通过
                        // 操作商品
                        $_result = GoodsModel::where([
                            ['goods_id', 'in', $_idList],
                            ['review_status', '<>', 1],
                        ])->update([
                            'review_status' => 1,
                        ]);

                        if ($_result <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }

                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_result;
                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作{$_result}个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        break;
                }

                Db::commit();
                return ['code' => 0, 'message' => $_resultMessage];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch();
    }

    /**
     * 筛选条件
     * 用途
     * 商品搜索
     * 审核全部
     * 上架全部
     * 下架全部
     * 删除全部
     */
    private function filterWhere()
    {
        // 店铺类型 1=>自营 2=>非自营
        $_shopType = (int)$this->request->get('shop_type', 1);

        // 商品状态 0=>全部 1=>上架商品 2=>下架商品 3=>待审核商品 4=>库存预警商品 5=>商品回收站 6=>审核未通过商品
        $_goodsStatus = (int)$this->request->get('status', 0);

        // 商品分类 0=>未选择
        $_goodsClassify = (int)$this->request->get('classify', 0);

        // 店铺名称
        $_storeName = (string)$this->request->get('store_name', '');

        // 商品名称
        $_goodsName = (string)$this->request->get('goods_name', '');

        // 商品品牌 0=>未选择
        $_goodsBrand = (int)$this->request->get('brand', 0);

        // 店铺类型 1=>个人 2=>公司 (仅出现在非自营店铺中)
        $_storeType = (int)$this->request->get('store_type', 0);

        // 推荐状态 0=>全部 1=>人气好物推荐 2=>特价优惠推荐 3=>特别推荐 4=>普通推荐 5=>轮播推荐
        $_goodsRecommend = (int)$this->request->get('recommend', 0);

        // 活动状态 0=>全部 1=>限时抢购 2=>拼团 3=>砍价
        $_goodsActivity = (int)$this->request->get('activity', 0);

        // 分销状态 0=>全部 1=>可分销商品 2=>购买成为分销商
        $_goodsDistribution = (int)$this->request->get('distribution', 0);

        // 是否需要关注收藏商品
        $_isCollect = $this->request->get('is_collect', 0);

//            $_where

//            0.    控制是否自营
//            1.    控制是否上下架
//            2.    控制是否过审
//            3.    控制库存预警

//            4.    控制商品分类
//            5.    控制店铺名称
//            6.    控制商品名称
//            7.    控制商品品牌
//            8.    控制推荐状态
//            9.    控制活动状态
//            10.   控制分销状态
//            11.   控制收藏
//            12.   永久删除

        $_where = [];
        $_pageConfig = [];

        // 初始化商品模型
        $_goodsList = new GoodsModel();


        if (config('user.one_more') != 1) {
            $_where[0] = [
                'Store.store_id',
                '=',
                config('user.one_store_id'),
            ];
        } else {
            // 商品状态
            switch ($_shopType) {
                case 1: // 自营
                    $_where[0] = [
                        'Store.shop',
                        '=',
                        0,
                    ];
                    break;
                case 2: // 非自营
                    $_where[0] = [
                        'Store.shop',
                        'in',
                        '1,2',
                    ];
                    break;
                default:
                    $this->error('参数错误');
            }
        }


        $_pageConfig['shop_type'] = $_shopType;

        // 商品状态
        if ($_goodsStatus != 0) {
            $_pageConfig['status'] = $_goodsStatus;
            switch ($_goodsStatus) {
                case 1: // 上架商品
                    // 上架的商品
                    $_where[1] = [
                        'Goods.is_putaway',
                        '=',
                        1,
                    ];
                    $_where[2] = [
                        'Goods.review_status',
                        '=',
                        1,
                    ];
                    break;
                case 2: // 下架商品
                    $_where[1] = [
                        'Goods.is_putaway',
                        '=',
                        0,
                    ];
                    break;
                case 3: // 待审核商品
                    // 待审核状态的商品
                    $_where[2] = [
                        'Goods.review_status',
                        '=',
                        2,
                    ];
                    break;
                case 4: // 库存预警商品
                    // 库存数量少于库存的商品
                    $_where[3] = [
                        'Goods.goods_number',
                        '<',
                        Db::raw('`Goods`.`warn_number`'),
                    ];
                    break;
                case 5: // 商品回收站
                    // 只查询被软删除的商品
                    $_goodsList = GoodsModel::onlyTrashed();
                    break;
                case 6: // 审核未通过商品
                    $_where[2] = [
                        'Goods.review_status',
                        '=',
                        0,
                    ];
                    break;
            }
        }


        // 商品分类
        if ($_goodsClassify != 0) {
            $_pageConfig['classify'] = $_goodsClassify;

            // 构建临时的下面 while 中 需要查询的 商品分类ID 列表
            $_goodsClassifyIdListTmp = [$_goodsClassify];

            // 后面需要查询的商品分类ID 列表
            $_goodsClassifyIdList = [$_goodsClassify];

            // 如果 查询出来的数组不为空 那就可以继续去执行下一次循环 使用本次结果当做父Id 去继续查询子分类
            while (!empty($_goodsClassifyIdListTmp = GoodsClassifyModel::where([['parent_id', 'in', join(',', $_goodsClassifyIdListTmp)], ['status', '=', 1]])->column('goods_classify_id'))) {
                $_goodsClassifyIdList = array_merge($_goodsClassifyIdList, $_goodsClassifyIdListTmp);
            }

            $_where[4] = [
                'Goods.goods_classify_id',
                'in',
                join(',', $_goodsClassifyIdList),
            ];
        }

        // 店铺名称
        if ($_storeName != '') {
            $_pageConfig['store_name'] = $_storeName;
            $_where[5] = [
                'Store.store_name',
                'LIKE',
                "%{$_storeName}%",
            ];
        }

        // 商品名称
        if ($_goodsName != '') {
            $_pageConfig['goods_name'] = $_goodsName;
            $_where[6] = [
                'Goods.goods_name',
                'LIKE',
                "%{$_goodsName}%",
            ];
        }

        // 商品品牌
        if ($_goodsBrand != 0) {
            $_pageConfig['brand'] = $_goodsBrand;
            $_where[7] = [
                'Goods.brand_id',
                '=',
                $_goodsBrand,
            ];
        }

        // 店铺类型
        if ($_storeType != 0) {
            $_pageConfig['store_type'] = $_storeType;
            $_where[0] = [
                'Store.shop',
                '=',
                $_storeType,
            ];
        }

        // 推荐状态
        if ($_goodsRecommend != 0) {
            $_pageConfig['recommend'] = $_goodsRecommend;
            switch ($_goodsRecommend) {
                case 1: // 人气好物推荐
                    $_where[8] = [
                        'Goods.is_popularity',
                        '=',
                        1,
                    ];
                    break;
                case 2: // 特价优惠推荐
                    $_where[8] = [
                        'Goods.is_preference',
                        '=',
                        1,
                    ];
                    break;
                case 3: // 特别推荐
                    $_where[8] = [
                        'Goods.store_particularly_recommend',
                        '=',
                        1,
                    ];
                    break;
                case 4: // 普通推荐
                    $_where[8] = [
                        'Goods.store_recommend',
                        '=',
                        1,
                    ];
                    break;
                case 5: // 轮播推荐
                    $_where[8] = [
                        'Goods.store_banner',
                        '=',
                        1,
                    ];
                    break;
            }
        }

        // 活动状态
        if ($_goodsActivity != 0) {
            $_pageConfig['activity'] = $_goodsActivity;
            switch ($_goodsActivity) {
                case 1: // 限时抢购
                    $_where[9] = [
                        'Goods.is_limit',
                        '=',
                        1,
                    ];
                    break;
                case 2: // 拼团
                    $_where[9] = [
                        'Goods.is_group',
                        '=',
                        1,
                    ];
                    break;
                case 3: // 砍价
                    $_where[9] = [
                        'Goods.is_bargain',
                        '=',
                        1,
                    ];
                    break;
            }
        }

        // 分销状态
        if ($_goodsDistribution != 0) {
            $_pageConfig['distribution'] = $_goodsDistribution;
            switch ($_goodsDistribution) {
                case 1: // 可分销商品
                    $_where[10] = [
                        'Goods.is_distribution',
                        '=',
                        1,
                    ];
                    $_where[11] = [
                        'Goods.is_group',
                        '=',
                        0,
                    ];
                    $_where[12] = [
                        'Goods.is_bargain',
                        '=',
                        0,
                    ];
                    $_where[13] = [
                        'Goods.is_limit',
                        '=',
                        0,
                    ];
                    break;
                case 2: // 购买成为分销商
                    $_where[10] = [
                        'Goods.is_distributor',
                        '=',
                        1,
                    ];
                    break;
            }
        }


        // 收藏
        if ($_isCollect != 0) {
            $_where[11] = [
                'Goods.collect_number',
                '>',
                0,
            ];
        }

        $_where[12] = ['Goods.forever_del_status', '=', '0'];

        return [
            'GOODS_LIST' => $_goodsList,
            'WHERE' => $_where,
            'PAGE_CONFIG' => $_pageConfig,
        ];

    }

    /**
     * 审核全部
     */
    public function reviewAll()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $_filterWhere = $this->filterWhere();

                $_goodsList = $_filterWhere['GOODS_LIST'];
                $_where = $_filterWhere['WHERE'];

                $_idListArr = $_goodsList
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_where)
                    ->field([
                        'Goods.goods_id' => 'goods_id',
                    ])->column('goods_id');

                // 审核类型
                $_type = $this->request->post('type');
                // 审核原因
                $_content = $this->request->post('content');

                // id列表总数
                $_idListCount = count($_idListArr);

                // 需要审核的Id 列表
                $_idList = join(',', $_idListArr);

                // 返回的信息
                $_resultMessage = config('message.')[0];

                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        'review_status' => 0,
                        // 砍价
                        'is_bargain' => 0,
                        'cut_price' => 0,
                        // 拼团
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                        // 限时抢购
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];

                // 判断审核状态
                switch ($_type) {
                    case 0: // 审核未通过
                        if (empty($_content)) {
                            return ['code' => -100, 'message' => '请填写审核未通过原因'];
                        }

                        $_saveData['GOODS']['review_content'] = $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = $_content;

                        // 获取条件中 非审核未通过的商品
                        $_goodsList = GoodsModel::where([
                            ['goods_id', 'in', $_idList],
                            ['review_status', '<>', '0']  // 不是审核未通过的商品
                        ])->column('goods_id');

                        // 查询出来的 结果 的总数
                        $_idResultCount = count($_goodsList);

                        // 如果 都不符合
                        if ($_idResultCount <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }

                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_idResultCount;

                        // Id 结果 以 , 分隔
                        $_idResultJoin = join(',', $_goodsList);

                        // 修改 商品
                        GoodsModel::where([
                            ['goods_id', 'in', $_idResultJoin],
                        ])->update($_saveData['GOODS']);

                        // 修改 显示抢购
                        Limit::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['LIMIT']);

                        // 修改 拼团
                        GroupGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['GROUP_GOODS']);

                        // 修改 砍价
                        CutGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['CUT_GOODS']);

                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作" . count($_goodsList) . "个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        break;
                    case 1: // 审核通过

                        // 操作商品
                        $_result = GoodsModel::where([
                            ['goods_id', 'in', $_idList],
                            ['review_status', '<>', 1],
                        ])->update([
                            'review_status' => 1,
                        ]);

                        if ($_result <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }

                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_result;
                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作{$_result}个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        break;
                }

                Db::commit();
                return ['code' => 0, 'message' => $_resultMessage];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch();
    }

    /**
     * 上架/下架全部
     *
     * @return array|mixed
     */
    public function putAwayAll()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $_filterWhere = $this->filterWhere();

                $_goodsList = $_filterWhere['GOODS_LIST'];
                $_where = $_filterWhere['WHERE'];

                // 查询出来的商品Id列表
                $_goodsIdList = $_goodsList
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_where)
                    ->field([
                        'Goods.goods_id' => 'goods_id',
                    ])->column('goods_id');


                // 商品   Goods
                // review_status    审核状态 0 未通过 1 已通过 2 待审核
                // review_content   审核内容

                // 限时抢购 Limit
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容

                // 拼团   GroupGoods
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容

                // 砍价   CutGoods
                // status   审核状态 0 未通过 1 已通过 2 待审核
                // reason   审核内容

                // 商品ID列表 eg:1,2,3,4,5
                $_idList = join(',', $_goodsIdList);

                // 上架下架状态 1 => 上架 2=> 下架 默认下架
                $_type = $this->request->post('type', 2);

                // id列表总数
                $_idListCount = count($_goodsIdList);

                // 返回的信息
                $_resultMessage = config('message.')[0];

                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        // 默认下架
                        'is_putaway' => 0,
                        // 砍价
                        'is_bargain' => 0,
                        'cut_price' => 0,
                        // 拼团
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                        // 限时抢购
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];


                // 判断审核状态
                switch ($_type) {
                    case 1: // 上架
                        // 操作商品
                        $_result = GoodsModel::where([
                            ['goods_id', 'in', $_idList],
                            ['review_status', '=', 1],    // 审核已通过
                            ['is_putaway', '<>', 1]     // 已下架
                        ])->update([
                            'is_putaway' => 1,
                        ]);

                        if ($_result <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }

                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_result;
                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作{$_result}个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        break;
                    case 2: // 下架
                        $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = '主商品被下架';

                        // 获取条件中 上架
                        $_goodsList = GoodsModel::where([
                            ['goods_id', 'in', $_idList],
                            ['is_putaway', '=', 1]  // 上架的商品
                        ])->column('goods_id');

                        // 查询出来的 结果 的总数
                        $_idResultCount = count($_goodsList);

                        // 如果 都不符合
                        if ($_idResultCount <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }

                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_idResultCount;

                        // Id 结果 以 , 分隔
                        $_idResultJoin = join(',', $_goodsList);

                        // 修改 商品
                        GoodsModel::where([
                            ['goods_id', 'in', $_idResultJoin],
                        ])->update($_saveData['GOODS']);

                        // 修改 显示抢购
                        Limit::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['LIMIT']);

                        // 修改 拼团
                        GroupGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['GROUP_GOODS']);

                        // 修改 砍价
                        CutGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['CUT_GOODS']);

                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作" . count($_goodsList) . "个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        break;
                }


                Db::commit();
                return ['code' => 0, 'message' => $_resultMessage];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch();
    }

    /**
     * 单个商品 上架/下架
     */
    public function putAway()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                // 商品ID
                $_goodsId = $this->request->post('goods_id', -1);
                // 上架下架状态 0 => 下架 1 => 上架 默认 下架
                $_type = $this->request->post('checked', 0);

                // 商品ID 没有穿送的时候
                if ($_goodsId === -1) {
                    return ['code' => -100, 'message' => '参数错误'];
                }

                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        // 默认下架
                        'is_putaway' => 0,
                        // 砍价
                        'is_bargain' => 0,
                        'cut_price' => 0,
                        // 拼团
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                        // 限时抢购
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];
                $_goods = GoodsModel::get($_goodsId);

                if (!$_goods) {
                    return ['code' => -100, 'message' => '商品不存在'];
                }

                // 判断上下架状态
                switch ($_type) {
                    case 0: // 下架
                        $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = '主商品被下架';

                        // 判断商品是否是下架状态
                        if ($_goods['is_putaway'] == 0) {
                            return ['code' => -100, 'message' => '商品已下架,请勿重复操作'];
                        }
                        // 下架商品
                        $_goods->save(
                            $_saveData['GOODS']
                        );

                        // 修改 显示抢购
                        Limit::where([
                            ['goods_id', '=', $_goodsId],
                            ['status', '<>', 0],
                        ])->update($_saveData['LIMIT']);

                        // 修改 拼团
                        GroupGoods::where([
                            ['goods_id', '=', $_goodsId],
                            ['status', '<>', 0],
                        ])->update($_saveData['GROUP_GOODS']);

                        // 修改 砍价
                        CutGoods::where([
                            ['goods_id', '=', $_goodsId],
                            ['status', '<>', 0],
                        ])->update($_saveData['CUT_GOODS']);
                        break;
                    default:// 上架
                        if ($_goods['is_putaway'] == 1) {
                            return ['code' => -100, 'message' => '商品已上架,请勿重复操作'];
                        }

                        if ($_goods['review_status'] != 1) {
                            return ['code' => -100, 'message' => '只能上架审核通过的商品'];
                        }

                        // 上架商品
                        $_goods->save([
                            'is_putaway' => 1,
                        ]);

                }
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
//                dump($e->getMessage());
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }
    }

    /**
     * 删除商品
     *
     * @return array
     */
    public function delete()
    {
        if ($this->request->isPost()) {

            $_goodsId = $this->request->post('goods_id', -1);

            if ($_goodsId === -1) {
                return ['code' => -100, 'message' => '商品不存在'];
            }

            try {

                $_goods = GoodsModel::get($_goodsId);

                if (!$_goods) {
                    return ['code' => -100, 'message' => '商品不存在'];
                }

                Db::startTrans();

                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        // 默认下架
                        'is_putaway' => 0,
                        // 砍价
                        'is_bargain' => 0,
                        'cut_price' => 0,
                        // 拼团
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                        // 限时抢购
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];

                // 修改 商品
                GoodsModel::where([
                    ['goods_id', '=', $_goodsId],
                ])->update($_saveData['GOODS']);

                // 修改 显示抢购
                Limit::where([
                    ['goods_id', '=', $_goodsId],
                    ['status', '<>', 0],
                ])->update($_saveData['LIMIT']);

                // 修改 拼团
                GroupGoods::where([
                    ['goods_id', '=', $_goodsId],
                    ['status', '<>', 0],
                ])->update($_saveData['GROUP_GOODS']);

                // 修改 砍价
                CutGoods::where([
                    ['goods_id', '=', $_goodsId],
                    ['status', '<>', 0],
                ])->update($_saveData['CUT_GOODS']);

                // 删除商品
                $_goods->delete();

                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }

    }

    /**
     * 删除全部
     *
     * @return array|mixed
     */
    public function deleteAll()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {

                $_filterWhere = $this->filterWhere();

                $_goodsList = $_filterWhere['GOODS_LIST'];
                $_where = $_filterWhere['WHERE'];

                $_goodsIdList = $_goodsList
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id AND isnull(Store.delete_time)')// 关联店铺
                    ->where($_where);

                // 删除类型
                $_type = $this->request->post('type');

                if ($_type == 1) {
                    // 删除
                    $_goodsIdList->update([
                        'Goods.delete_time' => date('Y-m-d H:i:s'),
                        // 砍价
                        'is_bargain' => 0,
                        'cut_price' => 0,
                        // 拼团
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                        // 限时抢购
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ]);
                } else {
                    // 恢复
                    $_goodsIdList->update([
                        'Goods.delete_time' => NULL,
                    ]);
                }

                // 更新商品信息
                $_saveDataList = [];
                foreach ($_goodsIdList as $key => $val) {
                    $_saveDataList[$key] = [
                        'goods_id' => $val,
                        'manage_id' => Session::get('manage_id'),
                        'nickname' => Session::get('manageName'),
                    ];
                    // $_type: 1删除 2恢复
                    // type:    5删除 6恢复
                    $_saveDataList[$key]['type'] = $_type == 1 ? 5 : 6;
                }

                (new GoodsOperation())->saveAll($_saveDataList);

                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch();
    }

    /**
     * 恢复删除的商品
     */
    public function restore()
    {

        if ($this->request->isPost()) {
            $_goodsId = $this->request->post('goods_id', -1);

            if ($_goodsId === -1) {
                return ['code' => -100, 'message' => '商品不存在'];
            }

            try {

                $_goods = GoodsModel::onlyTrashed()->find($_goodsId);

                if (!$_goods) {
                    return ['code' => -100, 'message' => '商品不存在'];
                }

                $_goods->restore();

                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')[-1]];
            }

        }

    }

    /**
     * 获取商品展示数据
     * @param $goods_id
     * @param GoodsModel $goods
     * @param GoodsClassify $goodsClassify
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function editGoodsShow($goods_id, GoodsModel $goods, GoodsClassify $goodsClassify)
    {
        $field = 'goods_id,g.store_id,default_express_type,g.goods_classify_id,attr_type_id,
                g.brand_id,goods_sn,freight_id,goods_name,goods_name_style,market_price,
                shop_price,cost_price,goods_number,warn_number,video,file,store_goods_classify_id,
                is_freight,freight_status,freight_price,freight_id,multiple_file,is_best,is_hot,recomme_file,
                is_popularity,is_preference,goods_weight,store_recommend,is_putaway,is_vip,keyword,store_banner,
                content,web_content,g.sort,store_particularly_recommend,is_distribution,is_distributor,rebates_type,
                distribution_set,express,express_one_city,express_self_lifting,Store.is_delivery,Store.is_city,Store.is_shop,
                g.is_group,g.is_bargain,g.is_limit';

        $goodsData = $goods
            ->alias('g')
            ->with(['parameter', 'spec' => function ($s) {
                $s->field('products_id,goods_id,goods_attr,attr_market_price,
                attr_shop_price,attr_cost_price,attr_goods_number,attr_warn_number,
                attr_file,attr_goods_sn');
            }])
            ->join('Store Store', 'Store.store_id = g.store_id')
            ->withAttr('is_delivery', function ($value, $data) {
                return $value;             // 快递邮寄 为否时 展示配送方式
            })
            ->view('brand b', 'brand_name', 'b.brand_id = g.brand_id', 'left')
            ->field($field)
            ->where(['goods_id' => $goods_id])
            ->find();

        // 分类
        $cateGroup = getParCate($goodsData['goods_classify_id'], $goodsClassify);
        $goodsData['cateTitle'] = implode(' / ', array_column($cateGroup, 'title'));
        $classifyId = array_column($cateGroup, 'goods_classify_id');
        $goodsData['firstCate'] = array_shift($classifyId);
        $goodsData['file_data'] = $goodsData->getData('file');
        $parent = array_column($cateGroup, 'parent_id');
        array_shift($parent);


        $classify = $goodsClassify
            ->where([['parent_id', 'in', implode(',', $parent)], ['status', '=', 1]])
            ->field('goods_classify_id,parent_id,title')
            ->select();
        $cateArr = [];

        // 原始视频数据
        $goodsData['video_data'] = $goodsData->getData('video');

        if ($classify) {
            foreach ($classify as $key => $value) {
                $value['checked'] = in_array($value['goods_classify_id'], $classifyId) ? 1 : 0;
                $cateArr[$value['parent_id']][] = $value;
            }
        }
        $goodsData['cateArr'] = array_values($cateArr);

        return $goodsData;
    }

    /**
     * 检测货号是否重复
     * @param Request $request
     * @param GoodsModel $goods
     * @return array
     */
    public function checkGoodsSn(Request $request, GoodsModel $goods)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                //检测货号是否唯一
                $ret = $goods->checkGoodsSnUnique($param);
                return ['code' => $ret ? -1 : 0, 'message' => config('message.')[$ret ? -6 : 0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 审核商品
     * @param Request $request
     * @param GoodsModel $goods
     * @return array
     */
    public function reviewStatus(Request $request, GoodsModel $goods, Store $store)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

                $goods->allowField(TRUE)->isUpdate(TRUE)->save($param);
                $store->countGoodsNum($param['goods_id']);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 获取商品规格类型
     * @param \think\Request $request
     * @param AttrType $attrType
     * @param array $ins
     * @return array
     */
    public function getAttrType(\think\Request $request, AttrType $attrType, $ins = [])
    {
        try {
            $param = $request->post();
            if ($ins) $param = $ins;
            //获取商品类型
            $attrWhere = [
                ['status', '=', 1],
                ['store_id', '=', $param['store_id']],
            ];
            $attr = $attrType
                ->where($attrWhere)
                ->field('attr_type_id,type_name')
                ->order(['update_time' => 'desc'])
                ->select();
            return ['code' => 0, 'message' => '查询成功', 'data' => $attr];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取店铺分类
     * @param \think\Request $request
     * @param StoreGoodsClassify $StoreGoodsClassify
     * @param array $ins
     * @return array
     */
    public function getStoreClassify(\think\Request $request, StoreGoodsClassify $StoreGoodsClassify, $ins = [])
    {
        try {
            $param = $request->post();
            if ($ins) $param = $ins;
            //获取商品类型
            $classWhere = [
                ['status', '=', 1],
                ['store_id', '=', $param['store_id']],
            ];
            $attr = $StoreGoodsClassify
                ->where($classWhere)
                ->field('store_goods_classify_id,title,parent_id')
                ->order(['update_time' => 'desc'])
                ->select();

            $_data = find_level($attr, 'store_goods_classify_id');

            return ['code' => 0, 'message' => '查询成功', 'data' => $_data];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取店铺运费模板
     * @param \think\Request $request
     * @param FreightExpressClassify $freightExpressClassify
     * @param array $ins
     * @return array
     */
    public function getFreight(\think\Request $request, FreightExpressClassify $freightExpressClassify, $ins = [])
    {
        try {
            $param = $request->post();
            if ($ins) $param = $ins;
            //获取商品类型
            $freightWhere = [
                ['store_id', '=', $param['store_id']],
            ];
            $attr = $freightExpressClassify
                ->where($freightWhere)
                ->field('freight_express_classify_id,name')
                ->order(['update_time' => 'desc'])
                ->select();
            return ['code' => 0, 'message' => '查询成功', 'data' => $attr];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 商品添加处理
     * @param Request $request
     * @param GoodsModel $goods
     * @param Store $store
     * @param GoodsAttr $goodsAttr
     * @param GoodsClassify $goodsClassify
     * @param GoodsParameter $goodsParameterModel
     * @param Cart $cart
     * @param CollectGoods $collectGoods
     * @return array
     */
    public function createAct(Request $request,
                              GoodsModel $goods,
                              Store $store,
                              GoodsAttr $goodsAttr,
                              GoodsClassify $goodsClassify,
                              GoodsParameter $goodsParameterModel,
                              Cart $cart,
                              CollectGoods $collectGoods)
    {
        $ratio = Env::get('RATIO_SET');
        if ($request::isPost()) {
            try {
                $param = $request::post();

                Db::startTrans();
                //验证数据
                $validRet = $goods->valid($param, 'create');

                if ((2 == $param['express']) && (2 == $param['express_one_city']) && (2 == $param['express_self_lifting'])) {
                    Db::rollback();
                    return ['code' => -100, 'message' => '请至少选择一项配送方式'];
                }


                if ($validRet['code']) return $validRet;
                //处理多图
                if (array_key_exists('picArr', $param) && $param['picArr'])
                    $param['multiple_file'] = implode(',', $param['picArr']);

                //商品名称转拼音
                $spell = app('app\\common\\service\\Spell');
                $param['spell_goods_name'] = str_replace(' ', '', $spell->convert($param['goods_name']));
                if (array_key_exists('spec', $param) && $param['spec']) {
                    $param['goods_number'] = 0;
                    foreach ($param['spec'] as $v) {
                        if (empty($v['attr_market_price'])) {
                            Db::rollback();
                            return ['code' => -100, 'message' => '请输入规格(' . $v['goods_attr'] . ')的市场价'];
                        }
                        if (empty($v['attr_shop_price'])) {
                            Db::rollback();
                            return ['code' => -100, 'message' => '请输入规格(' . $v['goods_attr'] . ')的售卖价'];
                        }
                        if (empty($v['attr_cost_price'])) {
                            Db::rollback();
                            return ['code' => -100, 'message' => '请输入规格(' . $v['goods_attr'] . ')的成本价'];
                        }
                        if (empty($v['attr_goods_number'])) {
                            Db::rollback();
                            return ['code' => -100, 'message' => '请输入规格(' . $v['goods_attr'] . ')的库存'];
                        }
                        if (empty($v['attr_warn_number'])) {
                            Db::rollback();
                            return ['code' => -100, 'message' => '请输入规格(' . $v['goods_attr'] . ')的库存预警值'];
                        }
                        $param['goods_number'] += $v['attr_goods_number']; //库存
                        $param['warn_number'] += $v['attr_warn_number'];  //库存预警值
                    }
                }
                //上传商品
                if (array_key_exists('goods_id', $param)) {
                    $status = TRUE;  //修改商品
                    self::arrivalWarn($goods, $cart, $collectGoods, $param);
                } else {
                    $status = FALSE;//添加商品

                    // 待审核
                    $param['review_status'] = 2;

                    // 已下架
                    $param['is_putaway'] = 0;
                }
                if (array_key_exists('spec', $param) && $param['spec']) {
                    // 统一库存
                    $param['goods_number'] = array_sum(array_column($param['spec'], 'attr_goods_number'));
                    $param['warn_number'] = array_sum(array_column($param['spec'], 'attr_warn_number'));
                }
                if (array_key_exists('distribution_set', $param) && $param['distribution_set']) {
                    // 检测比例设置是否低于系统设置值
                    foreach ($param['distribution_set'][1] as $k => $v) {
                        $_pri = 0;
                        foreach ($param['distribution_set'] as $_set) {
                            $_pri += $_set[$k];
                        }
                        if ($param['rebates_type'] == 1) {
                            // 比例
                            if ($_pri > $ratio) {
                                return ['code' => -1, 'message' => "您设置百分比的值不能大于等于$ratio%"];
                            }
                        } else {
                            if ($_pri >= $param['shop_price'] / 2) {
                                return ['code' => -1, 'message' => '您设置现金的值不能大于商品半价'];
                            }
                        }
                    }
                    $param['distribution_set'] = serialize($param['distribution_set']);
                }

                $goods->allowField(TRUE)->isUpdate($status)->save($param);
                if (!$status) {
                    $param['goods_id'] = $goods->goods_id;
                }
                if (empty($param['goods_sn'])) {
                    //设置商品货号
                    $goods->setGoodsSn($goods->goods_id);
                }

                $store->countGoodsNum($goods->goods_id);
                //设置商品参数
                if (array_key_exists('parameter_name', $param) && $param['parameter_name']) {
                    $goods->setParameter($param);
                } else {
                    // 删除商品参数
                    $goodsParameterModel->where([['goods_id','=',$goods->goods_id]])->delete(true);
                }
                //设置商品属性价格
                $goods->setSpec($param);
                //设置商品属性
                $goodsAttr->setGoodsAttr((array_key_exists('spec_attr', $param) && $param['spec_attr']) ? $param['spec_attr'] : [], $goods->goods_id);
                // 设置分类缓存
                $cateClassify = getParCate($param['goods_classify_id'], $goodsClassify);;
                $cateCache = [
                    'goods_classify_id' => array_column($cateClassify, 'goods_classify_id'),
                    'goods_classify_info' => $param['goods_classify_info'],
                ];
                if ($status) {
                    $goods->getReduction($param['goods_id'], $param['shop_price'], $param['file']);
                }
                self::setCateCache($cateCache);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods/index'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' =>  $e->getMessage()];
            }
        }
    }

    /**
     * 到货提醒
     * @param GoodsModel $goods
     * @param Cart $cart
     * @param CollectGoods $collectGoods
     * @param $args
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function arrivalWarn(GoodsModel $goods, Cart $cart, CollectGoods $collectGoods, $args)
    {
        // 查询商品原库存剩余量
        $arrivalData = $goods
            ->where([['goods_id', '=', $args['goods_id']]])
            ->field('goods_id,goods_number,warn_number')
            ->with(['arrivalSpec'])
            ->find();
        if ($arrivalData) {
            $where = [];
            // 增加了默认库存
            if ($arrivalData['goods_number'] == 0 && $args['goods_number'] > 0) {
                array_push($where, '');
            }
            if ($arrivalData->arrival_spec && array_key_exists('spec', $args) && $args['spec']) {
                foreach ($arrivalData->arrival_spec as $item) {
                    foreach ($args['spec'] as $_val) {
                        if ($item['attr_goods_number'] == 0 && $_val['attr_goods_number'] > 0) {
                            array_push($where, $_val['goods_attr']);
                        }
                    }
                }
            }
            // 查找购物车和商品收藏夹,查询哪些用户对此商品感兴趣
            if ($where) {
                $cartData = $cart
                    ->where([
                        ['goods_id', '=', $args['goods_id']],
                        ['goods_attr', 'in', implode(',', $where)],
                    ])
                    ->column('member_id');
                $collectGoodsData = $collectGoods
                    ->where([
                        ['goods_id', '=', $args['goods_id']],
                    ])
                    ->column('member_id');
                $memberData = array_unique(array_merge($cartData, $collectGoodsData));
                if ($memberData) {
                    $messageData = [];
                    foreach ($memberData as $_md) {
                        // 推送消息[到货提醒]
                        array_push($messageData, [
                            'member_id' => $_md,
                            'type' => 0,
                            'jump_state' => 'goods',
                            'attach_id' => $args['goods_id'],
                            'title' => '缺货商品到货提醒',
                            'describe' => sprintf(Config::get('inform.')['goods'][0], $args['goods_name']),
                            'file' => $args['file'],
                        ]);
                    }
                    $messageModel = new Message();
                    $messageModel->allowField(TRUE)->isUpdate(FALSE)->saveAll($messageData);
                }
            }
        }
    }

    /**
     * 单个修改商品的属性库存stock/价格price
     * @param Request $request
     * @param Products $products
     * @param GoodsModel $goods
     * @return bool
     */
    public function editAttr(Request $request, Products $products, GoodsModel $goods)
    {
        try {
            Db::startTrans();
            $param = $request::post();
            switch ($param['type']) {
                case 'stock': //库存
                    $field = 'attr_goods_number';
                    // 查询当前属性信息
                    $info = $products
                        ->where([['attr_goods_sn', '=', $param['goods_id']]])
                        ->field('products_id,goods_id,attr_goods_number')
                        ->find();
                    $diff = $param['attr_value'] - $info['attr_goods_number'];
                    $goods->allowField(TRUE)
                        ->isUpdate(TRUE)
                        ->save([
                            'goods_id' => $info['goods_id'],
                            'goods_number' => Db::raw('goods_number + ' . $diff),
                        ]);
                    break;
                case 'price': //价格
                    $field = 'attr_shop_price';
                    break;
                case 'warm': //库存预警
                    $field = 'attr_warn_number';
                    break;
                default: //编辑商品
                    $field = '';
                    break;
            }
            $products->where('attr_goods_sn', $param['goods_id'])->update(["$field" => $param['attr_value']]);
            Db::commit();
            return TRUE;
        } catch (\Exception $e) {
            Db::rollback();
            return FALSE;
        }
    }


    /**
     * 设置创建商品分类缓存
     * @param $cate
     * @return array|mixed
     */
    private function setCateCache($cate)
    {
        $cache = Cache::get('cateCreateCache_' . request()->loginId) ?: [];
        if ($cache) {
            foreach ($cache as $key => $value) {
                if ($value['goods_classify_id'] = $cate['goods_classify_id']) unset($cache[$key]);
            }
        }
        array_unshift($cache, $cate);
        $cache = array_slice($cache, 0, 20);
        Cache::set('cateCreateCache_' . request()->loginId, $cache);
        return $cache;
    }

    /**
     * 第一步获取子类分类
     * @param Request $request
     * @param GoodsClassify $goodsClassify
     * @return array
     */
    public function getSonCate(Request $request, GoodsClassify $goodsClassify)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $where[] = ['parent_id', '=', $param['id']];
                $where[] = ['status', '=', 1];
                //查询商品分类子类
                $sonCate = $goodsClassify
                    ->where($where)
                    ->field('goods_classify_id,parent_id,title')
                    ->order(['sort' => 'asc', 'update_time' => 'desc'])
                    ->select();
                $sonCateHtml = ($param['type'] == 1) ? '<li>请选择二级分类</li>' : '<li>请选择三级分类</li>';
                if ($sonCate->count() > 0) {
                    $flag = 1;
                    foreach ($sonCate as $key => $item) {
                        $inf = 'onclick="step.getSon(' . ($param['type'] + 1) . ',' . $item['goods_classify_id'] . ');"';
                        $sonCateHtml .= '<li ' . $inf . ' id="cate_' . $item['goods_classify_id'] . '"><a href="javascript:void(0);" ><i></i>' . $item['title'] . '</a></li>';
                    }
                } else {
                    $flag = 0;
                    $sonCateHtml .= '<li class="empty">暂无此级分类</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $sonCateHtml, 'flag' => $flag];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 获取个人分类历史输入
     * @param Request $request
     * @param GoodsClassify $goodsClassify
     * @return array
     */
    public function getCateHistory(Request $request, GoodsClassify $goodsClassify)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $data = $goodsClassify
                    ->where([['parent_id', 'in', implode(',', $param['id'])]])
                    ->field('goods_classify_id,parent_id,title,count')
                    ->select();
                $title = $goodsClassify
                    ->where([['goods_classify_id', 'in', implode(',', $param['id'])]])
                    ->column('title');
                $html = [];
                if ($data->count()) {
                    $text = ['<li>请选择二级分类</li>', '<li>请选择三级分类</li>'];
                    foreach ($data as $key => $val) {
                        $checked = in_array($val['goods_classify_id'], $param['id']) ? 'cur' : '';
                        $inf = 'onclick="step.getSon(' . ($val['count']) . ',' . $val['goods_classify_id'] . ');"';
                        if (!array_key_exists($val['parent_id'], $html)) $html[$val['parent_id']] = $text[$val['count'] - 2];
                        $html[$val['parent_id']] .= '<li ' . $inf . ' class="' . $checked . '" id="cate_' . $val['goods_classify_id'] . '"><a href="javascript:void(0);" ><i></i>' . $val['title'] . '</a></li>';
                    }
                }
                return ['code' => 0, 'message' => '查询成功', 'data' => array_values($html), 'title' => implode(' / ', $title)];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 第四步获取分类
     * @param Request $request
     * @param GoodsClassify $classify
     * @return array
     */
    public function getCate(Request $request, GoodsClassify $classify)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $where = [
                    ['goods_classify_id', '>', 0],
                    ['status', '=', 1],
                ];
                $head = '<li>请选择商品分类</li>';
                $headContent = [];
                if (array_key_exists('id', $param) && $param['id']) {
                    $where[] = ['parent_id', '=', $param['id']];
                    //查询所选项的父级
                    $total = getParCate($param['id'], $classify);
                    if ($total) {
                        $head = '<li><a href="javascript:step.getCate(0,0);">重选</a></li>';
                        foreach ($total as $item) {
                            $head .= '<li>&nbsp;> <a href="javascript:' . (($item['count'] == 3) ? 'void(0)' : 'step.getCate(' . $item['goods_classify_id'] . ',' . $item['count'] . ')') . ';">' . $item['title'] . '</a></li>';
                            $headContent[] = $item['title'];
                        }
                    }
                } else {
                    $where[] = ['parent_id', '=', 0];
                }
                if (array_key_exists('keyword', $param) && $param['keyword']) $where['title'] = ['like', '%' . $param['keyword'] . '%'];
                $data = $classify
                    ->where($where)
                    ->field('goods_classify_id,title,count')
                    ->order(['sort' => 'desc', 'update_time' => 'desc'])
                    ->select();
                $html = '';
                if ($data->count()) {
                    $count = [1 => 'Ⅰ', 2 => 'Ⅱ', 3 => 'Ⅲ'];
                    foreach ($data as $key => $item) {
                        $html .= '<li title="' . $item['title'] . '"  data-count="' . $item['count'] . '"  value="' . $item['goods_classify_id'] . '" ><em>' . $count[$item['count']] . '</em>' . $item['title'] . '</li>';
                    }
                } else {
                    $html .= '<li class="empty">暂无分类数据</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $html, 'head' => $head, 'headContent' => implode(' > ', $headContent)];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 批量商品上架/下架
     * @param Request $request
     * @param GoodsModel $goods
     * @return array
     */
    public function shelves(Request $request, GoodsModel $goods)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 商品   Goods
                // review_status    审核状态 0 未通过 1 已通过 2 待审核
                // review_content   审核内容

                // 限时抢购 Limit
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容

                // 拼团   GroupGoods
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容

                // 砍价   CutGoods
                // status   审核状态 0 未通过 1 已通过 2 待审核
                // reason   审核内容


                // 商品ID列表 eg:1,2,3,4,5
                $_idList = $this->request->post('id', '');

                // 上架/下架状态 0 => 下架 1=> 上架 默认下架
                $_type = $this->request->post('type', 0);

                // id列表总数
                $_idListCount = count(explode(',', $_idList));

                // 返回的信息
                $_resultMessage = config('message.')[0];

                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        // 默认下架
                        'is_putaway' => 0,
                        // 砍价
                        'is_bargain' => 0,
                        'cut_price' => 0,
                        // 拼团
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                        // 限时抢购
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];

                switch ($_type) {
                    case 0: // 下架
                        $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = '主商品被下架';

                        // 获取条件中 上架
                        $_goodsList = GoodsModel::where([
                            ['goods_id', 'in', $_idList],
                            ['is_putaway', '=', 1]  // 上架的商品
                        ])->column('goods_id');

                        // 查询出来的 结果 的总数
                        $_idResultCount = count($_goodsList);

                        // 如果 都不符合
                        if ($_idResultCount <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }

                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_idResultCount;

                        // Id 结果 以 , 分隔
                        $_idResultJoin = join(',', $_goodsList);

                        // 修改 商品
                        GoodsModel::where([
                            ['goods_id', 'in', $_idResultJoin],
                        ])->update($_saveData['GOODS']);

                        // 修改 显示抢购
                        Limit::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['LIMIT']);

                        // 修改 拼团
                        GroupGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['GROUP_GOODS']);

                        // 修改 砍价
                        CutGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['CUT_GOODS']);

                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作" . count($_goodsList) . "个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }

                        break;
                    default:    // 上架操作
                        // 操作商品
                        $_result = GoodsModel::where([
                            ['goods_id', 'in', $_idList],
                            ['review_status', '=', 1],    // 审核已通过
                            ['is_putaway', '<>', 1]     // 已下架
                        ])->update([
                            'is_putaway' => 1,
                        ]);

                        if ($_result <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }
                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_result;
                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作{$_result}个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                }
                Db::commit();
                return ['code' => 0, 'message' => $_resultMessage];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 活动搜索商品
     * @param Request $request
     * @param GoodsModel $goods
     * @return mixed
     * @throws Exception
     */
    public function searchGoods(Request $request, GoodsModel $goods, GoodsClassifyModel $goodsClassify, BrandModel $brand)
    {
        try {
            $param = $request::get();
            $where = [
                ['g.store_id', INI_CONFIG['SINGLE_STORE'] ? '=' : '>', INI_CONFIG['SINGLE_STORE'] ? INI_CONFIG['ONE_STORE_ID'] : 0],
                ['is_putaway', '=', 1],
                ['is_group', '=', 0],
                ['is_bargain', '=', 0],
                ['is_limit', '=', 0],
                ['g.review_status', '=', 1],
            ];

            if (array_key_exists('cateId', $param) && $param['cateId'])
                array_push($where, ['g.goods_classify_id', '=', $param['cateId']]);
            if (array_key_exists('brandId', $param) && $param['brandId'])
                array_push($where, ['g.brand_id', '=', $param['brandId']]);
            if (array_key_exists('keyword', $param) && $param['keyword'])
                array_push($where, ['g.goods_sn|g.goods_name|g.spell_keyword|g.describe', 'like', '%' . $param['keyword'] . '%']);

            $data = $goods
                ->alias('g')
                ->where($where)
                ->view('store s', 'store_name', 's.store_id= g.store_id')
                ->view('brand b', 'brand_name', 'b.brand_id = g.brand_id', 'left')
                ->join('Limit Limit','g.goods_id = Limit.goods_id','left') // 限时抢购
                ->join('CutGoods CutGoods','g.goods_id = CutGoods.goods_id','left') // 拼团
                ->join('GroupGoods GroupGoods','g.goods_id = GroupGoods.goods_id','left') // 砍价
                ->field('g.goods_id,g.goods_classify_id,file,goods_name,shop_price,goods_sn')
                ->order(['g.update_time' => 'desc', 'g.goods_id' => 'asc'])
                ->group(['g.goods_id'])
                ->paginate($goods->pageLimits, FALSE, ['query' => $param]);

            //查询商品一级分类
            $categoryOne = $goodsClassify
                ->where([
                    ['parent_id', '=', 0],
                    ['status', '=', 1],
                ])
                ->field('goods_classify_id,parent_id,title,count')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();

            //查询全部品牌
            $brandData = $brand->where([['brand_id', '>', 0]])->field('brand_id,brand_name,brand_first_char')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();
            //查询全部品牌首字母
            $brandFirstChr = $brand->where([['brand_id', '>', 0]])->distinct(TRUE)->order(['brand_first_char' => 'asc'])
                ->column('brand_first_char');
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this->fetch('', [
            'data' => $data,
            'type' => $param['type'],
            'categoryOne' => $categoryOne,
            'brand' => $brandData,
            'brandFirstChr' => $brandFirstChr,

        ]);
    }

    /**
     * 配送区域城市选择
     * @param Request $request
     * @param Area $area
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function city(Request $request, Area $area)
    {
        // 获取省级城市
        $where = [['deep', '=', 1], ['status', '=', 1]];
        $cityBase = $area->field('area_id,area_name,deep')->order(['area_id' => 'asc']);
        if ($request::isPost()) {
            try {
                $checked = $request::post('checked', '');
                $cityId = $request::post('cityId');
                $deep = $request::post('deep', 1);
                $classStr = $request::post('classStr', '');
                array_shift($where);
                array_unshift($where, ['parent_id', '=', $cityId], ['deep', '=', ++$deep]);
                $city = $cityBase->where($where)->select();
                $html = '<ul class="city-ul">';
                if ($city->count()) {
                    $classArr = explode(' ', $classStr);
                    if ($classArr) {
                        array_shift($classArr);
                        array_push($classArr, $cityId);
                        $classStr = implode(' ', $classArr);
                    }
                    foreach ($city as $key => $item) {
                        $getNext = ($deep == 4) ? ''
                            : '<em title="点击展示' . ($deep == 2 ? '区' : '街道') . '" onclick="getNextCity(' . $item->area_id . ',' . $item->deep . ',\'.city-list\');"> &gt; </em>';
                        $html .= <<<EXO
<li>
<input type="checkbox" $checked value="$item->area_id" id="$item->area_id" class="$deep $classStr" data-pid="$cityId" lay-filter="$deep" lay-skin="primary" title="$item->area_name">$getNext
</li>
EXO;
                    }
                } else {
                    $html .= '<li class="empty">未发现下级' . ['市', '区', '街道'][$deep - 2] . '</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $html . '</ul>'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $city = $cityBase->where($where)->select();
        return $this->fetch('', [
            'city' => $city,
        ]);
    }

    /**
     * 获取规格列表
     * @param Request $request
     * @param Attr $attr
     * @return array
     */
    public function attrList(Request $request, Attr $attr)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post('id');
                $data = $attr
                    ->where('attr_type_id', $param)
                    ->with('goodsAttr')
                    ->field('attr_type_id,attr_id,attr_name,attr_input_type,attr_value')
                    ->select();
                return ['code' => 0, 'data' => $data];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 获取商品属性规格
     * @param Request $request
     * @param Products $products
     * @return array
     */
    public function getProducts(Request $request, Products $products)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                // 平台待审核活动商品不可编辑属性
                $disabled = '';
                switch ($param['type']) {
                    case 1: //拼团
                        $field = 'products_id,goods_attr,attr_shop_price,attr_group_price,attr';
                        if ((new GroupGoods())->where('goods_id', $param['id'])->value('status') == 2 && Request::module() == 'master') {
                            $disabled = 'readonly';
                        }
                        break;
                    case 2: //砍价
                        $field = 'products_id,goods_attr,attr_shop_price,attr_cut_price,attr_single_cut_min,attr_single_cut_max,attr';
                        if ((new CutGoods())->where('goods_id', $param['id'])->value('status') == 2 && Request::module() == 'master') {
                            $disabled = 'readonly';
                        }
                        break;
                    case 3: //限时抢购
                        $field = 'products_id,goods_attr,attr_shop_price,attr_time_limit_price,attr_goods_number,attr_time_limit_number,attr';
                        if ((new Limit())->where('goods_id', $param['id'])->value('status') == 2 && Request::module() == 'master') {
                            $disabled = 'readonly';
                        }
                        break;
                    default: //编辑商品
                        $field = 'products_id,goods_id,goods_attr,attr_market_price,attr_shop_price,
                        attr_goods_weight,attr_cost_price,attr_goods_number,attr_warn_number,attr_file,attr_goods_sn,attr';
                        break;
                }
                $data = $products
                    ->where([['goods_id', '=', $param['id']]])
                    ->field($field)
                    ->order(['update_time' => 'desc', 'products_id' => 'desc'])
                    ->select();

                $goods_number = GoodsModel::where([['goods_id', '=', $param['id']]])->value('goods_number');
                $html = '';
                if ($data->count() > 0) {
                    foreach ($data as $key => $val) {
                        switch ($param['type']) {
                            case 1:
                                $html .= <<<EXO
<tr>
<td>$val->goods_attr<input type='hidden' name="products_id[]" value="$val->products_id" form="deniedform" /></td>
<td>$val->attr_shop_price</td>
<td><input type='text' maxlength="8" $disabled class="batch-2" name="attr_group_price[]" value="$val->attr_group_price" placeholder='0.00' form="deniedform" /></td>
</tr>
EXO;
                                break;
                            case 2:
                                $html .= <<<EXO
<tr>
<td>$val->goods_attr<input type='hidden' name="products_id[]" value="$val->products_id" form="deniedform" /></td>
<td>$val->attr_shop_price</td>
<td><input type='text' maxlength="8" $disabled class="batch-2" name="attr_cut_price[]" value="$val->attr_cut_price" placeholder='0.00' form="deniedform" /></td>
<td><input type='text' maxlength="8" $disabled class="batch-3" name="attr_single_cut_min[]" value="$val->attr_single_cut_min" placeholder='0.00' form="deniedform" /></td>
<td><input type='text' maxlength="8" $disabled class="batch-4" name="attr_single_cut_max[]" value="$val->attr_single_cut_max" placeholder='0.00' form="deniedform" /></td>
</tr>
EXO;
                                break;
                            case 3:
                                $html .= <<<EXO
<tr>
<td>$val->goods_attr<input type='hidden' name="products_id[]" value="$val->products_id" form="deniedform" /></td>
<td>$val->attr_shop_price</td>
<td><input type='text' maxlength="8" $disabled class="batch-2" name="attr_time_limit_price[]" value="$val->attr_time_limit_price" placeholder='0.00' form="deniedform" /></td>
<td>$val->attr_goods_number</td>
<td><input class="batch-4" maxlength="8" $disabled type='text' name="attr_time_limit_number[]"  onkeyup="check_arrt_number(this,$val->attr_goods_number)" number="$val->attr_goods_number" value="$val->attr_time_limit_number" placeholder='0' form="deniedform" /></td>
</tr>
EXO;
                                break;
                            case 4:
                                $gaClass = implode('#!@!#', explode(',', $val->goods_attr));
                                $faClass = implode('_', explode(',', $val->goods_attr)) . "_img";
                                $html .= <<<EXO
<tr>
<td>
<span class="$gaClass">$val->goods_attr</span>
<input type="hidden" name="spec[$val->goods_attr][goods_attr]" value="$val->goods_attr" form="deniedform" />
<input type="hidden" name="spec[$val->goods_attr][attr]" value="$val->attr" form="deniedform" />
<input type="hidden" name="spec[$val->goods_attr][products_id]" value="$val->products_id" form="deniedform" /> 
</td>
<td>
<input type="number" maxlength="8" $disabled class="attr-input batch-1" name="spec[$val->goods_attr][attr_market_price]" value="$val->attr_market_price" placeholder="0.00" form="deniedform" />
</td>
<td>
<input type="number" maxlength="8" $disabled class="attr-input batch-2" name="spec[$val->goods_attr][attr_shop_price]" value="$val->attr_shop_price" placeholder="0.00" form="deniedform" />
</td>
<td>
<input type="number" maxlength="8" $disabled class="attr-input batch-3" name="spec[$val->goods_attr][attr_cost_price]" value="$val->attr_cost_price" placeholder="0.00" form="deniedform" />
</td>
<td>
<input type="number" maxlength="8" $disabled class="attr-input batch-4" name="spec[$val->goods_attr][attr_goods_number]" value="$val->attr_goods_number" placeholder="0" form="deniedform" />
</td>
<td>
<input type="number" maxlength="8" $disabled class="attr-input batch-5" name="spec[$val->goods_attr][attr_warn_number]" value="$val->attr_warn_number" placeholder="0" form="deniedform" />
</td>
<td>
<input type="number" maxlength="8" $disabled class="attr-input batch-6" name="spec[$val->goods_attr][attr_goods_weight]" value="$val->attr_goods_weight" placeholder="不填用默认" form="deniedform" />
</td>
<td>
<input type="text" maxlength="8" $disabled class="attr-input-max" name="spec[$val->goods_attr][attr_goods_sn]" value="$val->attr_goods_sn" placeholder="不填自生成" form="deniedform" />
</td>
<td><i class="fa fa-plus chose" data-flag="$faClass"></i><input class="layui-upload-file" type="file" accept="image/*" name="attr_file"></td>
<td>
<img src="$val->attr_file_extra" onerror="this.src='/template/master/resource/image/common/imageError.png'" class="spec-img" title="$val->goods_attr" alt="$val->goods_attr" />
<input type="hidden" name="spec[$val->goods_attr][attr_file]" class="attr_file" value="$val->attr_file" form="deniedform" />
</td>
</tr>
EXO;
                                break;
                        }
                    }

                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $html, 'goods_number' => $goods_number];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 富文本编辑器展示页
     * @return mixed
     */
    public function uEditor()
    {
        return $this->fetch('goods/uEditor');
    }

    /**
     * 分销设置
     * @param Request $request
     * @param DistributionLevel $distributionLevel
     * @param GoodsModel $goodsModel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distribution(Request $request, DistributionLevel $distributionLevel, GoodsModel $goodsModel)
    {
        $ratio = Env::get('RATIO_SET');
        if ($request::isPost()) {
            $param = $request::post();
            if (!isset($param['is_distribution'])) {
                $param['is_distribution'] = 0;
            }
            if (!isset($param['is_distributor'])) {
                $param['is_distributor'] = 0;
            }
            // 分类总数
            $val = [];
            if ($param['distribution_set']) {
                // 价格
                $price = $goodsModel->where('goods_id', $param['goods_id'])->value('shop_price') / 2;
                if ($param['distribution_set']) {
                    foreach ($param['distribution_set'] as $_param) {
                        if (empty($val)) {
                            $val = $_param;
                        } else {
                            array_walk($val, function (&$v, $k) use ($_param) {
                                //强制转化成数字防止前台传递过来的数字不合法
                                $v = (float)$v;
                                $v += $_param[$k];
                            });
                        }
                    }
                    foreach ($val as $_val) {
                        // 如果是元 否则是百分比
                        if (empty($param['rebates_type'])) {
//                            if ($_val > $price) return ['code' => -1, 'message' => '您设置返利的值不能大于商品半价'];
                        } else {
                            if ($_val > $ratio) return ['code' => -1, 'message' => "您设置百分比的值不能大于$ratio%"];
                        }
                    }
                }
            }
            $param['distribution_set'] = array_filter($val) ? serialize($param['distribution_set']) : NULL;

            $goodsModel->allowField(TRUE)->isUpdate(TRUE)->save($param);
            return ['code' => 0, 'message' => config('message.')[0]];
        }
        $item = $goodsModel
            ->where('goods_id', $request::get('goods_id'))
            ->field('is_distribution,is_distributor,rebates_type,distribution_set,is_group,is_bargain,is_limit')
            ->find();
        $item['distribution_set'] = $item['distribution_set'] ? unserialize($item['distribution_set']) : '';
        return $this->fetch('', [
            'item' => $item,
            'distribution_level' => $distributionLevel
                ->field('distribution_level_id,level_title,level_weight')
                ->order('level_weight', 'asc')
                ->select(),
            'ratio' => $ratio,
            'is_buy' => Env::get('distribution_buy', 0),
        ]);
    }


    /**
     * 批量分销设置
     * @param Request $request
     * @param DistributionLevel $distributionLevel
     * @param GoodsModel $goodsModel
     * @return array|mixed
     * @throws \Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distributionAll(Request $request, DistributionLevel $distributionLevel, GoodsModel $goodsModel)
    {
        $ratio = Env::get('RATIO_SET');
        if ($request::isPost()) {
            $param = $request::post();
            if (!isset($param['is_distribution'])) {
                $param['is_distribution'] = $param['is_distributor'] = 0;
            }
            if (!isset($param['is_distributor'])) {
                $param['is_distributor'] = 0;
            }
            // 分类总数
            $val = [];
            if ($param['distribution_set']) {
                if ($param['distribution_set']) {
                    foreach ($param['distribution_set'] as $_param) {
                        if (empty($val)) {
                            $val = $_param;
                        } else {
                            array_walk($val, function (&$v, $k) use ($_param) {
                                $v += $_param[$k];
                            });
                        }
                    }
                    foreach ($val as $_val) {
                        if ($_val > $ratio) {
                            return ['code' => -1, 'message' => "您设置百分比的值不能大于$ratio%"];
                        }
                    }
                }
            }
            $param['distribution_set'] = array_filter($val) ? serialize($param['distribution_set']) : NULL;
            $arr = [];
            foreach (explode(',', rtrim($param['goods_id'])) as $key => $value) {
                $arr[$key]['goods_id'] = $value;
                $arr[$key]['is_distribution'] = $param['is_distribution'];
                $arr[$key]['is_distributor'] = $param['is_distributor'];
                $arr[$key]['rebates_type'] = $param['rebates_type'];
                $arr[$key]['distribution_set'] = $param['distribution_set'];
            }
            $goodsModel->allowField(TRUE)->isUpdate(TRUE)->saveAll($arr);
            return ['code' => 0, 'message' => config('message.')[0]];
        }
        return $this->fetch('', [
            'distribution_level' => $distributionLevel
                ->field('distribution_level_id,level_title,level_weight')
                ->order('level_weight', 'asc')
                ->select(),
            'ratio' => $ratio,
        ]);

    }

    /**
     * 店铺推荐
     * @param Request $request
     * @param GoodsModel $goodsModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recommend(Request $request, GoodsModel $goodsModel)
    {
        $param = $request::get();

        if ($request::isPost()) {
            $param = $request::post();
            try {
                $goodsModel->isUpdate(TRUE)->allowField(TRUE)->save($param);
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $data = $goodsModel
            ->alias('a')
            ->join('store store', 'store.store_id = a.store_id')
            ->where('a.goods_id', $param['goods_id'])
            ->field('shop,a.goods_id,recomme_file,pc_recomme_file,store_banner,store_particularly_recommend,a.file,store_recommend,is_popularity,is_preference,is_vip')
            ->find();
        $data['recomme_file_data'] = $data['recomme_file_extra'];
        $data['pc_recomme_file_data'] = $data['pc_recomme_file_extra'];

        return $this->fetch('', [
            'item' => $data,
            'pc_config' => config('user.pc')['is_include'],
        ]);
    }

    /**
     * 活动设置-限时抢购
     * @param Request $request
     * @param Limit $limit
     * @param LimitInterval $limitInterval
     * @param GoodsModel $goods
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_limit(Request $request, Limit $limit, LimitInterval $limitInterval, GoodsModel $goods)
    {
        $param = $request::get();

        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                if ($param['limit_id'] != '') {
                    $check = $limit->valid($param, 'edit');
                    if ($check['code']) return $check;
                    // 更新
                    $limit->allowField(TRUE)->isUpdate(TRUE)->save($param);
                } else {
                    $check = $limit->valid($param, 'create');
                    if ($check['code']) return $check;
                    // 写入
                    $limit->allowField(TRUE)->save($param);
                    $param['limit_id'] = $limit->limit_id;
                }

                if (array_key_exists('products_id', $param)) {
                    foreach ($param['products_id'] as $key => $value) {

                        $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                        $reg1 = '/(^[0-9]\d*$)/';
                        if (!preg_match($reg, $param['attr_time_limit_price'][$key])) return ['code' => -100, 'message' => '属性限时抢购价为正整数或保留两位小数'];
                        if (!preg_match($reg1, $param['attr_time_limit_number'][$key])) return ['code' => -100, 'message' => '属性限时抢购库存为正整数'];
                        if ($param['attr_time_limit_price'][$key] > $param['shop_price']) return ['code' => -100, 'message' => '属性限时抢购价小于商品原价'];

                        $attr_goods_number = Db::name('products')->where('products_id', $value)->value('attr_goods_number');

                        if ($param['attr_time_limit_number'][$key] > $attr_goods_number) return ['code' => -100, 'message' => '限时抢购属性库存小于属性库存'];

                    }
                    $limit->setSub($param);
                }
                $limit->setGoods($param);
                (new Beanstalk())->put(json_encode(['queue' => 'limitGoodsExpireChangeStatus',
                    'id' => $param['limit_id'], 'time' => date('Y-m-d H:i:s')]),
                    (strtotime($param['down_shelf_time']) - time()));

                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods/index'];

            } catch (\Exception $e) {

                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        // 各活动状态
        $activityStatus = $this->checkActivity($param['goods_id'], 'is_limit');

        $goods = $goods->where('goods_id', $param['goods_id'])->field('goods_name,shop_price')->find();

        $data = $limit->alias('a')
            ->join('goods goods', 'goods.goods_id = a.goods_id', 'left')
            ->where('a.goods_id', $param['goods_id'])
            ->field('a.*,time_limit_price')
            ->find();

        return $this->fetch('', [
            'classify_list' => $limitInterval::all(),
            'item' => $data,
            'goods_id' => $param['goods_id'],
            'goods' => $goods,
            'activity_status' => $activityStatus,
        ]);
    }

    /**
     * 活动设置-限时抢购拼团
     * @param Request $request
     * @param GoodsModel $goods
     * @param GroupGoods $groupGoods
     * @param GroupClassify $groupClassify
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_group(Request $request, GoodsModel $goods, GroupGoods $groupGoods, GroupClassify $groupClassify)
    {
        $param = $request::get();

        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                if ($param['group_goods_id'] != '') {
                    $check = $groupGoods->valid($param, 'edit');
                    if ($check['code']) return $check;
                    // 写入
                    $groupGoods->isUpdate(TRUE)->allowField(TRUE)->save($param);
                } else {
                    $check = $groupGoods->valid($param, 'create');
                    if ($check['code']) return $check;
                    // 写入
                    $groupGoods->allowField(TRUE)->save($param);
                    $param['group_goods_id'] = $groupGoods->group_goods_id;
                }

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
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods/index'];

            } catch (\Exception $e) {

                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        // 各活动状态
        $activityStatus = $this->checkActivity($param['goods_id'], 'is_group');

        $goods = $goods->where('goods_id', $param['goods_id'])->field('goods_name,shop_price')->find();

        $data = $groupGoods->alias('a')
            ->join('goods goods', 'goods.goods_id = a.goods_id', 'left')
            ->where('a.goods_id', $param['goods_id'])
            ->field('a.*,group_price')
            ->find();

        return $this->fetch('', [
            'classify_list' => find_level($groupClassify->field('group_classify_id,title,parent_id')->select()->toArray(), 'group_classify_id'),
            'item' => $data,
            'goods_id' => $param['goods_id'],
            'goods' => $goods,
            'activity_status' => $activityStatus,
        ]);
    }

    /**
     * 活动设置-砍价
     * @param Request $request
     * @param GoodsModel $goods
     * @param CutGoods $cutGoods
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_cut(Request $request, GoodsModel $goods, CutGoods $cutGoods)
    {
        $param = $request::get();
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');

                // 验证
                if ($param['cut_goods_id'] != '') {
                    $check = $cutGoods->valid($param, 'edit');
                    if ($check['code']) return $check;
                    // 写入
                    $cutGoods->isUpdate(TRUE)->allowField(TRUE)->save($param);
                } else {
                    $check = $cutGoods->valid($param, 'create');
                    if ($check['code']) return $check;
                    // 写入
                    $cutGoods->allowField(TRUE)->save($param);
                    $param['cut_goods_id'] = $cutGoods->cut_goods_id;
                }
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
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods/index'];

            } catch (\Exception $e) {

                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        // 各活动状态
        $activityStatus = $this->checkActivity($param['goods_id'], 'is_bargain');

        $goods = $goods->where('goods_id', $param['goods_id'])->field('goods_name,shop_price')->find();

        $data = $cutGoods->alias('a')
            ->join('goods goods', 'goods.goods_id = a.goods_id', 'left')
            ->where('a.goods_id', $param['goods_id'])
            ->field('a.*,cut_price')
            ->find();

        return $this->fetch('', [
            'item' => $data,
            'goods_id' => $param['goods_id'],
            'goods' => $goods,
            'activity_status' => $activityStatus,
        ]);
    }

    /**
     * 检验其他活动状态
     * @param $goods_id
     * @param $type
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkActivity($goods_id, $type)
    {
        $activity = Db::name('goods')
            ->where([
                ['goods_id', 'eq', $goods_id],
            ])
            ->field('goods_id,is_group,is_bargain,is_limit')
            ->find();

        if (!empty($activity) && $activity['is_group'] == 1) return ['status' => 1, 'message' => '商品正处于拼团活动中，活动结束才可进行新活动设置'];
        if (!empty($activity) && $activity['is_bargain'] == 1) return ['status' => 1, 'message' => '商品正处于砍价活动中，活动结束才可进行新活动设置'];
        if (!empty($activity) && $activity['is_limit'] == 1) return ['status' => 1, 'message' => '商品正处于限时抢购活动中，活动结束才可进行新活动设置'];
        return ['status' => 0, 'message' => ''];
    }

    /**
     * 活动设置-积分商城
     * @param Request $request
     * @param IntegralModel $integral
     * @param IntegralClassifyModel $integralClassify
     * @param GoodsModel $goods
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_integral(Request $request, IntegralModel $integral, IntegralClassifyModel $integralClassify, GoodsModel $goods)
    {
        $param = $request::get();

        if ($request::isPost()) {

            try {
                // 获取参数
                $param = $request::post();

                // 验证
                $check = $integral->valid($param, 'create');
                if ($check['code']) return $check;

                //处理多图
                if (array_key_exists('picArr', $param) && $param['picArr'])
                    $param['multiple_file'] = implode(',', $param['picArr']);
                // 写入
                $operation = $integral->allowField(TRUE)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $goods = $goods->where('goods_id', $param['goods_id'])->field('goods_name,shop_price,file')->find();
        $goods['file_data'] = $goods->getData('file');

        return $this->fetch('', [
            'classify_list' => $integralClassify->where('status', 1)->field('integral_classify_id,parent_id,title,sort')->select(),
            'goods_id' => $param['goods_id'],
            'goods' => $goods,
        ]);
    }

    /**
     * 查看评论
     * @param Request $request
     * @param GoodsEvaluate $goodsEvaluate
     * @return array|mixed
     */
    public function evaluate(Request $request, GoodsEvaluate $goodsEvaluate)
    {
        $param = $request::get();

        try {
            $condition = [];
            if (array_key_exists('star_num', $param) && $param['star_num'] != -1) $condition[] = ['star_num', 'eq', $param['star_num']];
            // 店铺评价星数 0=>未选择
            if (array_key_exists('store_star_num', $param) && $param['store_star_num'] != -1) $condition[] = ['store_star_num', 'eq', $param['store_star_num']];
            // 物流评价星数 0=>未选择
            if (array_key_exists('express_star_num', $param) && $param['express_star_num'] != -1) $condition[] = ['express_star_num', 'eq', $param['express_star_num']];

            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['a.create_time', 'between time', [$begin, $end]]);
            }

            $data = $goodsEvaluate->alias('a')
                ->join('member member', 'a.member_id = member.member_id')
                ->where($condition)
                ->where('goods_id', $param['goods_id'])
                ->field('a.*,nickname,avatar')
                ->paginate(15, FALSE, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
            'goods_id' => $param['goods_id'],
        ]);
    }

    /**
     * 查看收藏
     * @param Request $request
     * @param CollectGoods $collectGoods
     * @return array|mixed
     */
    public function collect(Request $request, CollectGoods $collectGoods)
    {
        try {
            $data = $collectGoods->alias('a')
                ->join('member member', 'a.member_id = member.member_id')
                ->where('goods_id', $request::get('goods_id'))
                ->field('a.*,nickname,avatar')
                ->paginate(15, FALSE);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 查看商品
     */
    public function view()
    {

        $_goodsId = $this->request->get('id', -1);

        if ($_goodsId == -1) {
            $this->error('商品不存在');
        }

        $_data = GoodsModel::withTrashed()
            ->alias('Goods')
            ->with([
                'parameter',
                'spec',
            ])
            ->join('Store Store', 'Goods.store_id = Store.store_id', 'left')// 关联店铺
            ->join('StoreGoodsClassify StoreGoodsClassify', 'Goods.store_goods_classify_id = StoreGoodsClassify.store_goods_classify_id', 'left')// 关联店铺分类
            ->join('Brand Brand', 'Goods.brand_id = Brand.Brand_id', 'left')
            ->join('FreightExpressClassify FreightExpressClassify', 'Goods.freight_id = FreightExpressClassify.freight_express_classify_id', 'left')
            ->where([
                ['goods_id', '=', $_goodsId],
            ])
            ->field([
                'Store.store_name' => 'store_name',
                'StoreGoodsClassify.title' => 'goods_classify_title',
                'Brand.brand_name' => 'brand_name',
                'FreightExpressClassify.name' => 'freight_express_classify',
                'Goods.store_id' => 'store_id',
                'Goods.goods_name' => 'goods_name',
                'Goods.goods_number' => 'goods_number',
                'Goods.warn_number' => 'warn_number',
                'Goods.keyword' => 'keyword',
                'Goods.describe' => 'describe',
                'Goods.goods_classify_id' => 'goods_classify_id',
                'Goods.store_goods_classify_id' => 'store_goods_classify_id',
                'Goods.goods_sn' => 'goods_sn',
                'Goods.goods_id' => 'goods_id',
                'Goods.file' => 'file',
                'Goods.multiple_file' => 'multiple_file',
                'Goods.video' => 'video',
                'Goods.content' => 'content',
                'Goods.web_content' => 'web_content',
                'Goods.default_express_type' => 'default_express_type',
                'Goods.express' => 'express',
                'Goods.express_one_city' => 'express_one_city',
                'Goods.express_self_lifting' => 'express_self_lifting',
                'Goods.freight_status' => 'freight_status',
                'Goods.goods_weight' => 'goods_weight',
                'Goods.freight_price' => 'freight_price',
                'Goods.freight_id' => 'freight_id',
                'Goods.shop_price' => 'shop_price',
                'Goods.market_price' => 'market_price',
                'Goods.cost_price' => 'cost_price',
            ])
            ->find();

        if (!$_data) {
            $this->error('商品不存在');
        }

        $this->assign('data', $_data);
        $this->assign('one_more', config('user.one_more'));

        return $this->fetch();
    }

    public function getFreightList()
    {
        if ($this->request->isPost()) {
            $_storeId = $this->request->post('store_id', -1);

            if ($_storeId === -1) {
                return ['code' => -100, 'message' => '店铺不存在'];
            }

            try {

                $_storeInfo = Store::withTrashed()->where([
                    ['store_id', '=', $_storeId],
                ])->field([
                    'is_delivery',  // 快递邮寄
                    'is_city',      // 同城
                    'is_shop',      // 到店自提
                ])->find();

                if (!$_storeInfo) {
                    return ['code' => -100, 'message' => '店铺不存在'];
                }

                return [
                    'code' => 0,
                    'data' => [
                        'express' => $_storeInfo['is_delivery'] == 1,           // 快递邮寄
                        'express_one_city' => $_storeInfo['is_city'] == 1,      // 同城速递
                        'express_self_lifting' => $_storeInfo['is_shop'] == 1,  // 到店自提
                    ],
                ];
            } catch (\Exception $e) {
                return [
                    'code' => -100,
                    'message' => config('message.')[-1],
                ];
            }
        }
    }

    public function selectActivity(CutGoods $cutGoods, Limit $limit, GroupGoods $groupGoods, Request $request)
    {
        $param = $request::post();

        // 1拼团 2砍价 3限时抢购
        if ($param['type'] == 1) {
            $groupActivity = $groupGoods->where('goods_id', $param['goods_id'])->find();
            if (!empty($groupActivity)) return ['code' => -100, 'message' => '该商品已参加过拼团活动，请修改原有活动'];
        } else if ($param['type'] == 2) {
            $cutActivity = $cutGoods->where('goods_id', $param['goods_id'])->find();
            if (!empty($cutActivity)) return ['code' => -100, 'message' => '该商品已参加过砍价活动，请修改原有活动'];
        } else {
            $limitActivity = $limit->where('goods_id', $param['goods_id'])->find();
            if (!empty($limitActivity)) return ['code' => -100, 'message' => '该商品已参加过砍价活动，请修改原有活动'];
        }

        return ['code' => 0];
    }
}