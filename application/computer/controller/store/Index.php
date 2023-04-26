<?php
declare(strict_types = 1);

namespace app\computer\controller\store;

use app\common\model\StoreAuth;
use app\common\service\QrCode;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;
use app\computer\model\
{GoodsEvaluate, StoreClassify, Goods, CollectStore, Store, Coupon, StoreGoodsClassify, Search
};

/**
 * 店铺
 */
class index extends BaseController
{
    
    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['only' => 'collect_store_list'],
    ];
    
    /**
     * 收藏店铺
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Store $store
     * @param CollectStore $collectStore
     * @return mixed
     */
    public function collect_store(Request $request, RSACrypt $crypt, Store $store, CollectStore $collectStore)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 验证
                $check = $store->valid($param, 'collect_store');
                if ($check['code']) {
                    return $crypt->response($check);
                }
                
                $store_id = $store->where('member_id', $param['member_id'])->value('store_id');
                
                if ($param['store_id'] == $store_id) {
                    return $crypt->response(['code' => -1, 'message' => config('message.')[-7][-2]]);
                }
                
                $collect_id = $collectStore
                    ->where(
                        [
                            ['member_id', '=', $param['member_id']],
                            ['store_id', '=', $param['store_id']],
                        ]
                    )
                    ->value('collect_store_id');
                
                if (!$collect_id) {
                    // 新增
                    $collectStore->allowField(true)->save($param);
                }
                //店铺关注数量
                $collect_count = $collectStore->where([['store_id', '=', $param['store_id']]])->count();
                return $crypt->response(
                    [
                        'code' => 0,
                        'collect_count' => $collect_count,
                        'message' => config('message.')[0][0],
                        'collect_store_id' => $collectStore->collect_store_id,
                    ]
                );
                
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
    
    /**
     * 收藏店铺列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Store $store
     * @param CollectStore $collectStore
     * @param GoodsEvaluate $evaluate
     * @return mixed
     */
    public function collect_store_list(Request $request, RSACrypt $crypt, CollectStore $collectStore, GoodsEvaluate $evaluate)
    {
        try {
            // 获取参数
            $param = $request::instance()->param();
            $param['member_id'] = Session::get('member_info')['member_id'];
            
            $condition = [];
            
            if (!empty($param['keyword'])) {
                $condition[] = ['store_name', 'like', '%' . $param['keyword'] . '%'];
            }
            
            //当前店铺平均评分
            $self_score = $evaluate->alias('b')->where('b.store_id=collect_store.store_id')->field(
                'IFNULL(0+cast(round(SUM(store_star_num) / (count(*) * max(store_star_num)) * 5,2) as char),0)'
            )->fetchSql()->find();
            $result = $collectStore
                ->relation('shop_goods')
                ->alias('collect_store')
                ->join('store store', 'store.store_id = collect_store.store_id and store.delete_time is null and store.status = 4 and if(store.end_time,unix_timestamp(store.end_time) > unix_timestamp(),1)')
                ->where('collect_store.member_id', $param['member_id'])
                ->where($condition)
                ->field(
                    'collect_store_id,store.store_id,store.grade,logo,store_name,collect,(' . $self_score . ') self_score'
                )
                ->order('collect_store.create_time', 'desc')
                ->paginate(6);
            
        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
        }
        
        return $this->fetch('', ['code' => 0, 'result' => $result]);
        
    }
    
    /**
     * 收藏店铺删除
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Store $store
     * @param CollectStore $collectStore
     * @return mixed
     */
    public function collect_store_delete(Request $request, RSACrypt $crypt, Store $store, CollectStore $collectStore)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::instance()->param();
                $param['member_id'] = Session::get('member_info')['member_id'];
                // 验证
                $check = $store->valid($param, 'collect_store_delete');
                if ($check['code']) {
                    return $crypt->response($check);
                }
                
                $res = $collectStore::get($param['collect_store_id']);
                
                if ($res) {
                    // 删除
                    $state = $collectStore::destroy($param['collect_store_id'], true);
                    
                    if ($state) {
                        $redisInstance = Cache::handler();
                        $prefix = Config::get('cache.default')['prefix'];
                        $redisInstance->zIncrBy(
                            $prefix . 'collect_store',
                            -count(explode(',', $param['collect_store_id'])),
                            $param['member_id']
                        );
                        $store->where('store_id', 'in', $param['store_id'])->setDec('collect');
                    }
                }
                //店铺关注数量
                $collect_count = $collectStore->where([['store_id', '=', $param['store_id']]])->count();
                return $crypt->response(['code' => 0, 'collect_count' => $collect_count, 'message' => config('message.')[0][0]]);
                
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
    
    
    /**
     * 附近商家
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Store $store
     * @param StoreClassify $storeClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function nearby_list(Request $request, RSACrypt $crypt, Store $store, StoreClassify $storeClassify)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                
                $member_id = Session::get('member_info')['member_id'] ?? 0;
                
                // 默认条件
                $condition[] = ['status', '=', 4];
                $condition[] = ['end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or'];
                
                // 默认排序
                $rank = ['is_recommend' => 'desc', 'sort' => 'desc'];
                
                // 销量排序
                if (!empty($param['sales_volume'])) {
                    $rank = ['sales_volume' => 'desc'];
                }
                // 门店自提
                if (!empty($param['is_shop'])) {
                    $condition[] = ['is_shop', '=', 1];
                }
                // 同城配送
                if (!empty($param['is_city'])) {
                    $condition[] = ['is_city', '=', 1];
                }
                // 城市
                if (!empty($param['city'])) {
                    $condition[] = ['city', '=', $param['city']];
                }
                // 商品分类
                if (!empty($param['category'])) {
                    $condition[] = ['category', 'in', $param['category']];
                }
                // 个人、企业类型
                if (!empty($param['shop'])) {
                    $condition[] = ['shop', 'in', $param['shop']];
                }
                // 距离排序
                if (!empty($param['distance'])) {
                    $rank = ['distance' => 'asc'];
                }
                
                $store_list = $store
                    ->alias('store')
                    ->join(
                        'collect_store collect_store',
                        'store.store_id = collect_store.store_id and collect_store.member_id=' . $member_id,
                        'left'
                    )
                    ->append(['shop_goods', 'store_percent'])
                    ->field(
                        'collect_store.collect_store_id,store.store_id,store.logo,store.store_name,store.address,store.collect,store.lat,store.lng,round(st_distance(point(lng,lat),point(' . $param['lng'] . ',' . $param['lat'] . '))*111.195,3) AS distance'
                    )
                    ->where($condition)
                    ->order($rank)
                    ->paginate(10, false, $param)->toArray();
                
                
                return $crypt->response(['code' => 0, 'store_list' => $store_list]);
                
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
        
        $classify = $storeClassify
            ->field('store_classify_id,title,file')
            ->where('status', 1)
            ->order(['sort' => 'desc', 'create_time' => 'desc'])
            ->select();
        
        return $this->fetch('', [
            'code' => 0,
            'header_title' => '附近商家',
            'classify' => $classify,
        ]);
    }
    
    
    /**
     * 发现好店
     * @param Store $store
     * @param StoreClassify $storeClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function find_store(Store $store, StoreClassify $storeClassify)
    {
        $top_store = $store
            ->with('posterGoods')
            ->where(
                [
                    ['is_good', '=', 1],
                    ['status', '=', 4],
                    ['end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or'],
                ]
            )
            ->field('store_id,good_image,logo,store_name')
            ->order('sort', 'desc')
            ->limit(5)
            ->select();
        
        $classify = $storeClassify
            ->field('store_classify_id,title,file')
            ->where('status', 1)
            ->order(['sort' => 'desc', 'create_time' => 'desc'])
            ->select();
        
        
        // 获取参数
        $param = Request::instance()->param();
        // 条件
        $condition_array[] = ['status', '=', 4];
        $condition_array[] = ['is_good', '=', 1];
        $condition_array[] = ['end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or'];
        
        // 特殊
        $condition = '';
        
        if (empty($param['category'])) {
            $param['category'] = $classify[0]['store_classify_id'];
        }
        
        if (!empty($param['category'])) {
            $condition = 'FIND_IN_SET(' . $param['category'] . ',category)';
        }
        
        $result = $store
            ->where($condition)
            ->where($condition_array)
            ->append(['store_goods'])
            ->field('store_id,logo,store_name,shop')
            ->order(['is_recommend' => 'desc', 'sort' => 'desc'])
            ->paginate(20, false, ['query' => $param]);
        
        $num = array_search($param['category'], array_column($classify->toArray(), 'store_classify_id'));
        return $this->fetch(
            '',
            [
                'topData' => $top_store,
                'classify' => $classify,
                'category' => $classify[0]['store_classify_id'],
                'result' => $result,
                'num' => $num,
                'header_title' => '发现好店',
            ]
        );
    }
    
    
    /**
     * 搜索店铺
     * @param Request $request
     * @param RSACrypt $crypt
     * @param StoreClassify $storeClassify
     * @param Store $store
     * @param Goods $goods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function search_list(Request $request, RSACrypt $crypt, StoreClassify $storeClassify, Store $store, Goods $goods)
    {
        if ($request::isPost()) {
            try {
                $param = $crypt->request();
                $member_id = Session::get('member_info')['member_id'] ?? 0;
                // 默认条件
                $condition = [
                    ['status', '=', 4],
                ];
                // 默认排序
                $rank = ['sort' => 'desc'];
                // 同城配送
                if (!empty($param['keyword'])) $condition[] = ['store_name', 'like', '%' . $param['keyword'] . '%'];
                // 销量排序
                if (!empty($param['sales_volume'])) $rank = ['sales_volume' => 'desc'];
                // 门店自提
                if (!empty($param['is_shop'])) $condition[] = ['is_shop', '=', 1];
                // 同城配送
                if (!empty($param['is_city'])) $condition[] = ['is_city', '=', 1];
                // 自营
                if (isset($param['shop'])) $condition[] = ['shop', '=', 0];
                // 距离排序
                if (!empty($param['distance'])) $rank = ['distance' => 'asc'];
                // 分类
                if (!empty($param['category'])) $condition[] = ['category', '=', $param['category']];
                
                
                $result = $store
                    ->alias('store')
                    ->join(
                        'collect_store collect_store',
                        'store.store_id = collect_store.store_id and collect_store.member_id=' . $member_id,
                        'left'
                    )
                    ->where($condition)
                    ->field(
                        'collect_store.collect_store_id,store.store_id,store.logo,store.shop,store.store_name,store.address,store.collect,store.lat,store.lng,round(st_distance(point(lng,lat),point(' . $param['lng'] . ',' . $param['lat'] . '))*111.195,3) AS distance'
                    )
                    ->append(['shop_goods', 'store_percent'])
                    ->order($rank)
                    ->paginate(10, false, $param);
                
                return $crypt->response(['code' => 0, 'store_list' => $result]);
                
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
        
        $member_id = Session::get('member_info')['member_id'] ?? 0;
        $classify = $storeClassify
            ->field('store_classify_id,title')
            ->where('status', 1)
            ->order(['sort' => 'desc', 'create_time' => 'desc'])
            ->select();
        
        //猜你喜欢
        $recommend_list = recommend_list($goods, 8, $member_id, 1);
        return $this->fetch(
            '',
            [
                'code' => 0, /*'result' => $result,*/
                'recommend_list' => $recommend_list,
                'classify' => $classify,
            ]
        );
        
    }
    
    
    /**
     * 首页
     * @param Request $request
     * @param Store $store
     * @param CollectStore $collectStore
     * @param Coupon $coupon
     * @param Goods $goods
     * @param StoreGoodsClassify $storeGoodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, Store $store, CollectStore $collectStore, Coupon $coupon, Goods $goods, StoreGoodsClassify $storeGoodsClassify)
    {
        // 获取参数
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? '';
        
        // 店铺信息
        $result = $store->where('store_id', $param['store_id'])->append(['store_percent'])->field(
            'store_id,logo,store_name,shop,collect,status,back_image,goods_style,pc_head_back_image'
        )->find();
        $result['store_auth'] = StoreAuth::where([['store_id', '=', $param['store_id']]])->field('type')->find();
        // 关注状态
        $result['state'] = $collectStore->where(
            [
                ['member_id', '=', $param['member_id']],
                ['store_id', '=', $param['store_id']],
            ]
        )->value('collect_store_id');
        
        // 店铺分类
        $result['classify'] = $storeGoodsClassify->with('subset')->where(
            [
                ['store_id', '=', $param['store_id']],
                ['status', '=', 1],
                ['parent_id', '=', 0],
            ]
        )->field('store_goods_classify_id,title')->order(
            ['sort' => 'desc', 'store_goods_classify_id' => 'desc']
        )->select();
        
        //热推
        $result['top_classify'] = $storeGoodsClassify->where(
            [
                ['store_id', '=', $param['store_id']],
                ['status', '=', 1],
                //                        ['parent_id', '=', 0],
                ['is_hot', '=', 1],
            ]
        )->field('store_goods_classify_id,title,is_hot')->order(
            ['sort' => 'desc', 'store_goods_classify_id' => 'desc']
        )->select();
        
        
        // 优惠券
        $result['coupon'] = $coupon
            ->where(
                [
                    ['type', '=', 0],
                    ['modality', '=', 0],
                    ['is_integral_exchage', '=', 0],
                    ['is_gift', '=', 0],
                    ['classify_str', '=', $param['store_id']],
                    ['exchange_num', '>', 0],
                    ['start_time', '<=', date('Y-m-d')],
                    ['end_time', '>=', date('Y-m-d')],
                    ['status', '=', 1],
                ]
            )->field('coupon_id,actual_price,full_subtraction_price')->order('actual_price', 'asc')->limit(4)->select();
        
        
        // banner推荐
        $result['banner'] = $goods->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where([
                ['goods.store_id', '=', $param['store_id']],
                ['goods.store_banner', '=', 1],
                ['goods.goods_number', '>', 0],
                ['goods.review_status', '=', 1],
                ['goods.is_putaway', '=', 1],
                ['goods.pc_recomme_file', '<>', ''],
            ], 'goods')
            )
            ->field('goods.goods_id,goods.recomme_file,goods.pc_recomme_file')
            ->select();
        
        
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $function_status = [];
        // 拼团关闭
        if (Env::get('is_group') == 0) {
            $function_status[] = ['is_group', 'eq', '0'];
        }
        // 砍价关闭
        if (Env::get('is_cut') == 0) {
            $function_status[] = ['is_bargain', 'eq', '0'];
        }
        // 限时抢购关闭
        if (Env::get('is_limit') == 0) {
            $function_status[] = ['is_limit', 'eq', '0'];
        }
        
        // 特级推荐
        $result['particularly_recommend'] = $goods
            ->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where([
                ['goods.store_id', '=', $param['store_id']],
                ['goods.store_particularly_recommend', '=', 1],
                ['goods.goods_number', '>', 0],
                ['goods.review_status', '=', 1],
                ['goods.is_putaway', '=', 1],
            ], 'goods')
            )
            ->where($function_status)
            ->field(
                'goods.goods_id,goods.file,goods.shop_price,goods.shop_price as goods_price,goods.is_group,goods.is_bargain,goods.is_limit,goods.is_vip,goods.group_price,goods.cut_price,goods.time_limit_price'
            )
            ->limit(2)
            ->select();
        
        
        // 分类推荐
        $result['recommend'] = $storeGoodsClassify
            ->with('goods')
            ->where(
                [
                    ['store_id', '=', $param['store_id']],
                    ['is_hot', '=', 1],
                    ['status', '>', 0],
                ]
            )
            ->field('title,store_goods_classify_id')
            ->select();
        
        // 折扣
        $discount = discount($param['member_id']);
        //        halt($result);
        
        return $this->fetch('', ['code' => 0, 'result' => $result, 'discount' => $discount]);
    }
    
    /**
     * 全部商品
     * @param Request $request
     * @param Store $store
     * @param CollectStore $collectStore
     * @param Goods $goods
     * @param StoreGoodsClassify $storeGoodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_list(Request $request, Store $store, CollectStore $collectStore, Goods $goods, StoreGoodsClassify $storeGoodsClassify)
    {
        
        // 获取参数
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? '';
        
        // 店铺信息
        $result = $store
            ->where('store_id', $param['store_id'])
            ->append(['store_percent'])
            ->field('store_id,logo,store_name,shop,collect,status,back_image,goods_style,pc_head_back_image')
            ->find();
        
        
        // 关注状态
        $result['state'] = $collectStore
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['store_id', '=', $param['store_id']],
                ]
            )->value('collect_store_id');
        
        // 店铺分类
        $result['classify'] = $storeGoodsClassify
            ->with('subset')
            ->where(
                [
                    ['store_id', '=', $param['store_id']],
                    ['status', '=', 1],
                    ['parent_id', '=', 0],
                ]
            )
            ->field('store_goods_classify_id,title')
            ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
            ->select();
        
        //热推
        $result['top_classify'] = $storeGoodsClassify
            ->where(
                [
                    ['store_id', '=', $param['store_id']],
                    ['status', '=', 1],
                    //                        ['parent_id', '=', 0],
                    ['is_hot', '=', 1],
                ]
            )
            ->field('store_goods_classify_id,title,is_hot')
            ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
            ->select();
        
        
        // 排行榜
        $result['ranking'] = $goods
            ->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where([
                ['goods.store_id', '=', $param['store_id']],
                ['goods.is_putaway', '=', 1],
                ['goods.goods_number', '>', 0],
                ['goods.review_status', '=', 1],
            ], 'goods')
            )
            ->field('goods.goods_id,goods.file,goods.goods_name,goods.shop_price')
            ->order('goods.sales_volume', 'desc')
            ->limit($param['ranking_limit'] ?? 10)
            ->select();
        
        
        // 条件
        $condition[] = ['goods.goods_number', '>', 0];
        $condition[] = ['goods.review_status', '=', 1];
        $condition[] = ['goods.is_putaway', '=', 1];
        $condition[] = ['goods.store_id', '=', $param['store_id']];
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $function_status = [];
        // 拼团关闭
        if (Env::get('is_group') == 0) {
            $function_status[] = ['is_group', 'eq', '0'];
        }
        // 砍价关闭
        if (Env::get('is_cut') == 0) {
            $function_status[] = ['is_bargain', 'eq', '0'];
        }
        // 限时抢购关闭
        if (Env::get('is_limit') == 0) {
            $function_status[] = ['is_limit', 'eq', '0'];
        }
        
        // 是否推荐
        if ($param['recommend'] ?? 0) {
            $condition[] = ['store_recommend|store_particularly_recommend', '=', $param['recommend']];
        }
        // 查询分类
        $classify_id = $param['classify_id'] ?? 0;
        if ($classify_id && $total = getStoreParCate($classify_id, $storeGoodsClassify, 0)) {
            $classify_id .= ',' . implode(',', array_column($total, 'store_goods_classify_id'));
        }
        // 分类
        if ($classify_id) {
            $condition[] = ['store_goods_classify_id', 'in', $classify_id];
        }
        // 关键字
        if ($param['keyword1'] ?? 0) {
            $condition[] = ['goods_name', 'like', '%' . $param['keyword1'] . '%'];
        }
        //起始价格
        if ($param['start_price'] ?? 0) {
            $condition[] = ['shop_price', '>=', $param['start_price']];
        }
        //结束价格
        if ($param['end_price'] ?? 0) {
            $condition[] = ['shop_price', '<=', $param['end_price']];
        }
        // 排序
        //(CASE WHEN is_group = 1 THEN group_price WHEN is_limit = 1 THEN time_limit_price ELSE shop_price END)
        $parameter = !empty($param['parameter']) ? 'goods.' . $param['parameter'] : 'goods.sort';
        
        $rank = $param['rank'] ?? 'desc';
        $goods_filed = 'goods.goods_id,goods.goods_name,goods.shop_price,goods.is_group,goods.multiple_file,goods.is_bargain,goods.sales_volume,goods.freight_status,goods.group_price,goods.cut_price,goods.file,goods.group_num,goods.is_vip,goods.attr_type_id,goods.file as cart_file,goods.goods_number,goods.store_id,goods.is_limit,goods.time_limit_price';
        if ((int)($param['show_type'] ?? 1) == 2) {
            $goods_filed .= ',multiple_file';
        }
        // 店铺商品信息
        $result['goods'] = $goods
            ->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where($condition, 'goods'))
            ->where($function_status)
            ->field($goods_filed)
            ->order([$parameter => $rank, 'goods.sort' => 'desc'])
            ->paginate(20, false, ['query' => $param]);
        
        
        // 折扣
        $discount = discount($param['member_id']);
        
        //        halt($result);
        return $this->fetch(
            '',
            ['code' => 0, 'result' => $result, 'discount' => $discount]
        );
        
    }
    
    public function view(Request $request,
                         QrCode $qrCode,
                         Store $storeModel)
    {
        $query = $request::get();
        $info = $storeModel
            ->alias('s')
            ->where([
                ['s.store_id', '=', $query['store_id']],
            ])
            ->join('store_auth sa', 'sa.store_id = s.store_id')
            ->field('s.shop,s.store_id,s.store_name,s.pc_head_back_image,s.lng,s.lat,s.province,
            s.city,s.area,s.address,s.qr_code,s.collect,s.describe,s.phone,s.create_time,
            sa.licence_file,sa.file1,s.pc_back_image,sa.type')
            ->find();
        $info['qr_code'] = $qrCode->store_qrCode($info['store_id']);
        return $this->fetch('', [
            'info' => $info,
        ]);
    }
    
    
    
    /*********废弃****/
    
    
    //    /**
    //     * 发现好店 废弃
    //     * @param Request $request
    //     * @param RSACrypt $crypt
    //     * @param Store $store
    //     * @return mixed
    //     */
    //    public function good_list(Request $request, RSACrypt $crypt, Store $store)
    //    {
    //        if ($request::isPost())
    //        {
    //            try
    //            {
    //                // 获取参数
    //                $param = $crypt->request();
    //                // 条件
    //                $condition_array[] = ['status', '=', 1];
    //                $condition_array[] = ['is_good', '=', 1];
    //                $condition_array[] = ['end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or'];
    //
    //                // 特殊
    //                $condition = '';
    //
    //                if (!empty($param['category']))
    //                {
    //                    $condition = 'FIND_IN_SET(' . $param['category'] . ',category)';
    //                }
    //
    //                $result = $store
    //                    ->relation('shop_goods')
    //                    ->where($condition)
    //                    ->where($condition_array)
    //                    ->where('')
    //                    ->field('store_id,logo,store_name')
    //                    ->order(['is_recommend' => 'desc', 'sort' => 'desc'])
    //                    ->paginate(20, FALSE, $param);
    //
    //                return $crypt->response(['code' => 0, 'result' => $result]);
    //
    //            } catch (\Exception $e)
    //            {
    //                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
    //            }
    //        }
    //    }
    //
    //    /**
    //     * 店铺分类 废弃
    //     * @param Request $request
    //     * @param RSACrypt $crypt
    //     * @param StoreClassify $storeClassify
    //     * @return mixed
    //     */
    //    public function platform_classify(Request $request, RSACrypt $crypt, StoreClassify $storeClassify)
    //    {
    //        if ($request::isPost())
    //        {
    //            try
    //            {
    //
    //                $result = $storeClassify
    //                    ->field('store_classify_id,title')
    //                    ->where('status', 1)
    //                    ->order(['sort' => 'desc', 'create_time' => 'desc'])
    //                    ->select();
    //
    //                return $crypt->response(['code' => 0, 'result' => $result]);
    //
    //            } catch (\Exception $e)
    //            {
    //                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
    //            }
    //        }
    //    }
    //
    //
    //    /**
    //     * 分类列表 废弃
    //     * @param Request $request
    //     * @param RSACrypt $crypt
    //     * @param StoreGoodsClassify $storeGoodsClassify
    //     * @param Goods $goods
    //     * @return mixed
    //     */
    //    public function classify_list(Request $request, RSACrypt $crypt, StoreGoodsClassify $storeGoodsClassify, Goods $goods)
    //    {
    //        if ($request::isPost())
    //        {
    //            try
    //            {
    //                // 获取参数
    //                $param = $crypt->request();
    //
    //                // 验证
    //                $check = $storeGoodsClassify->valid($param, 'classify_list');
    //                if ($check['code'])
    //                {
    //                    return $crypt->response($check);
    //                }
    //                // 店铺信息
    //                $result = $storeGoodsClassify
    //                    ->with('subset')
    //                    ->where(
    //                        [
    //                            ['store_id', '=', $param['store_id']],
    //                            ['status', '=', 1],
    //                            ['parent_id', '=', 0],
    //                        ]
    //                    )
    //                    ->field('store_goods_classify_id,title')
    //                    ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
    //                    ->select();
    //
    //                return $crypt->response(['code' => 0, 'result' => $result]);
    //
    //            } catch (\Exception $e)
    //            {
    //                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
    //            }
    //        }
    //    }
    //
    //
    //    /**
    //     * 店铺分类列表
    //     * @param Request $request
    //     * @param RSACrypt $crypt
    //     * @param Store $store
    //     * @param GoodsEvaluate $evaluate
    //     * @param CollectStore $collectStore
    //     * @return mixed
    //     */
    //    public function nearby_list_classify(Request $request, RSACrypt $crypt, Store $store, GoodsEvaluate $evaluate, CollectStore $collectStore)
    //    {
    //        if ($request::isPost())
    //        {
    //            try
    //            {
    //                // 获取参数
    //                $param = $crypt->request();
    //                $param['member_id'] = request()->mid ?? '';
    //                // 默认条件
    //                $condition[] = ['status', '=', 1];
    //                $condition[] = ['end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or'];
    //                // 默认排序
    //                $rank = ['is_recommend' => 'desc', 'sort' => 'desc'];
    //                // 销量排序
    //                if (!empty($param['sales_volume']))
    //                {
    //                    $rank = ['sales_volume' => 'desc'];
    //                }
    //                // 门店自提
    //                if (!empty($param['is_shop']))
    //                {
    //                    $condition[] = ['is_shop', '=', 1];
    //                }
    //                // 同城配送
    //                if (!empty($param['is_city']))
    //                {
    //                    $condition[] = ['is_city', '=', 1];
    //                }
    //                // 城市
    //                if (!empty($param['city']))
    //                {
    //                    $condition[] = ['city', '=', $param['city']];
    //                }
    //                // 商品分类
    //                if (!empty($param['category']))
    //                {
    //                    $condition[] = ['category', 'in', $param['category']];
    //                }
    //                // 个人、企业类型
    //                if ($param['shop'] ?? 0)
    //                {
    //                    $condition[] = ['shop', 'in', $param['shop']];
    //                }
    //                // 距离排序
    //                if (!empty($param['distance']))
    //                {
    //                    $rank = ['distance' => 'asc'];
    //                }
    //
    //                //当前店铺平均评分
    //                $self_score = $evaluate->alias('b')->where('b.store_id=a.store_id')->field(
    //                    'IFNULL(0+cast(round(SUM(store_star_num) / (count(*) * max(store_star_num)) * 5,2) as char),0) store_percent'
    //                )->fetchSql()->find();
    //                //其他店铺平均分
    //                $sum_score = $evaluate->alias('c')->where('c.store_id <> a.store_id')
    //                    ->field(
    //                        'IFNULL(0+cast(round(SUM(store_star_num) / (count(*) * max(store_star_num)) * 5,2) as char),0) store_percent'
    //                    )->fetchSql()->find();
    //                //关注数量
    //                $collect_number = $collectStore->alias('d')->where('d.store_id=a.store_id')->fetchSql()->count();
    //                //是否关注
    //                $is_collect = $collectStore->alias('e')->where(
    //                    'e.store_id=a.store_id and e.member_id = ' . (int)$param['member_id']
    //                )->fetchSql()->count();
    //                //趋势   -1下降 0相等 1上升
    //                $result = $store->alias('a')
    //                    ->relation(
    //                        [
    //                            'shop_goods' => function ($query) use ($param)
    //                            {
    //                                $query->limit($param['shop_goods_limit'] ?? 4);
    //                            },
    //                        ]
    //                    )
    //                    ->where($condition)
    //                    ->field(
    //                        'store_id,logo,store_name,address,collect,lat,lng,a.shop,
    //                    round(st_distance(point(lng,lat),point(' . $param['lng'] . ',' . $param['lat'] . '))*111.195,3) AS distance,
    //                    (' . $self_score . ') self_score,
    //                    (' . $sum_score . ') sum_score,
    //                    (' . $collect_number . ') collect_number,
    //                    (' . $is_collect . ') is_collect'
    //                    )
    //                    ->order($rank)
    //                    ->paginate(10, FALSE, $param);
    //                return $crypt->response(['code' => 0, 'result' => $result]);
    //
    //            } catch (\Exception $e)
    //            {
    //                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
    //            }
    //        }
    //    }
    //
    //
    //    /**
    //     * 店铺头部 带banner 废弃
    //     * @param Request $request
    //     * @param RSACrypt $crypt
    //     * @param StoreGoodsClassify $storeGoodsClassify
    //     * @param CollectStore $collectStore
    //     * @param Store $store
    //     * @param GoodsEvaluate $evaluate
    //     * @return mixed
    //     */
    //    public function head(Request $request, RSACrypt $crypt, StoreGoodsClassify $storeGoodsClassify, CollectStore $collectStore, Store $store, GoodsEvaluate $evaluate)
    //    {
    //        if ($request::isPost())
    //        {
    //            try
    //            {
    //                // 获取参数
    //                $param = $crypt->request();
    //                $param['member_id'] = request()->mid ?? '';
    //                // 验证
    //                $check = $store->valid($param, 'head');
    //                if ($check['code'])
    //                {
    //                    return $crypt->response($check);
    //                }
    //
    //                // 店铺信息
    //                $result = $store
    //                    ->where('store_id', $param['store_id'])
    //                    ->field('store_name,shop,status,pc_head_back_image,goods_style')
    //                    ->find();
    //                // 关注状态
    //                $result['state'] = $collectStore
    //                    ->where(
    //                        [
    //                            ['member_id', '=', $param['member_id']],
    //                            ['store_id', '=', $param['store_id']],
    //                        ]
    //                    )->count('collect_store_id');
    //                //评分状态
    //                $result['store_info'] = $evaluate->storeEvaluate($param['store_id']);
    //                // 店铺推荐分类
    //                $result['classify_list'] = $storeGoodsClassify
    //                    ->where(
    //                        [
    //                            ['store_id', '=', $param['store_id']],
    //                            ['status', '=', 1],
    //                            ['is_hot', '=', 1],
    //                            ['parent_id', '=', 0],
    //                        ]
    //                    )
    //                    ->field('store_goods_classify_id,title')
    //                    ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
    //                    ->limit($check['limit'] ?? 10)
    //                    ->select();
    //                $result['all_classify_list'] = $storeGoodsClassify
    //                    ->with('subset')
    //                    ->where(
    //                        [
    //                            ['store_id', '=', $param['store_id']],
    //                            ['status', '=', 1],
    //                        ]
    //                    )
    //                    ->field('store_goods_classify_id,title')
    //                    ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
    //                    ->select();
    //                //是否关注店铺
    //                return $crypt->response(['code' => 0, 'result' => $result]);
    //
    //            } catch (\Exception $e)
    //            {
    //                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
    //            }
    //        }
    //    }
    //
    //    /**
    //     * 店铺首页收藏商品删除
    //     * @param Request $request
    //     * @param RSACrypt $crypt
    //     * @param Store $store
    //     * @param CollectStore $collectStore
    //     * @return mixed
    //     */
    //    public function view_collect_store_delete(Request $request, RSACrypt $crypt, Store $store, CollectStore $collectStore)
    //    {
    //        if ($request::isPost())
    //        {
    //            try
    //            {
    //                // 获取参数
    //                $param = $crypt->request();
    //                $param['member_id'] = request(0)->mid;
    //                // 验证
    //                $check = $store->valid($param, 'view_collect_store_delete');
    //                if ($check['code'])
    //                {
    //                    return $crypt->response($check);
    //                }
    //
    //                // 删除
    //                $state = $collectStore->where(
    //                    [
    //                        ['member_id', '=', $param['member_id']],
    //                        ['store_id', '=', $param['store_id']],
    //                    ]
    //                )->field('collect_store_id')->find()->delete(TRUE);
    //
    //                if ($state)
    //                {
    //                    $redisInstance = Cache::handler();
    //                    $prefix = Config::get('cache.default')['prefix'];
    //                    $redisInstance->zIncrBy($prefix . 'collect_store', -1, $param['member_id']);
    //                    $store->where('store_id', $param['store_id'])->setDec('collect');
    //                }
    //
    //                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);
    //
    //            } catch (\Exception $e)
    //            {
    //                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
    //            }
    //        }
    //    }
    
}