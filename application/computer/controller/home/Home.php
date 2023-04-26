<?php
declare(strict_types = 1);

namespace app\computer\controller\home;

use app\computer\model\Article;
use app\computer\model\ArticleAttach;
use app\computer\model\BrandClassify;
use app\computer\model\CollectArticle;
use app\computer\model\CollectStore;
use app\computer\model\Goods;
use app\computer\model\GoodsClassify;
use app\computer\model\IntegralRecord;
use app\computer\model\Member;
use app\computer\model\MemberGrowthRecord;
use app\computer\model\Store;
use app\computer\model\Adv;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\exception\Handle;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;
use app\computer\model\StoreClassify;

/**
 * 首页附属
 * Class Home
 * @package app\computer\controller\goods
 */
class Home extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['only' => 'article_list'],
    ];
    
    /**
     * 热点文章列表
     * @param Article $article
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function hot_list(Article $article)
    {
        $result = $article
            ->where(
                [
                    ['status', '=', 1],
                    ['article_classify_id', '=', 2],
                ]
            )
            ->field('article_id,title,hits,file,describe')
            ->order(['state' => 'desc', 'create_time' => 'desc'])
            ->append(['goods_number'])
            ->paginate(9);
        
        return $this->fetch(
            '',
            [
                'data' => $result,
            ]
        );
    }
    
    /**
     * 热点文章详情
     * @param Request $request
     * @param Article $article
     * @param ArticleAttach $articleAttach
     * @param CollectArticle $collectArticle
     * @param IntegralRecord $integralRecord
     * @param MemberGrowthRecord $memberGrowthRecord
     * @param Member $member
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function hot_view(Request $request, Article $article, ArticleAttach $articleAttach, CollectArticle $collectArticle, IntegralRecord $integralRecord, MemberGrowthRecord $memberGrowthRecord, Member $member)
    {
        // 获取参数
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? 0;
        
        // 验证
        $article->valid($param, 'hot_view');
        
        $result = $article
            ->where('article_id', $param['article_id'])
            ->field('article_id,title,content,file,create_time,hits')
            ->find();
        
        // 关注状态
        $result['attention_state'] = $collectArticle
            ->where(
                [
                    ['article_id', '=', $param['article_id']],
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->value('collect_article_id');
        
        // 商品状态
        $result['goods'] = $articleAttach->alias('article_attach')
            ->join('goods goods', 'goods.goods_id = article_attach.goods_id')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where([['article_id', '=', $param['article_id']]], 'goods'))
            ->field('goods.goods_id,goods_name,file,shop_price')
            ->select();
        
        $article->where('article_id', $param['article_id'])->setInc('hits');
        
        // 积分广告记录查询
        $integral_record_id = $integralRecord
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['type', '=', 0],
                    ['origin_point', '=', 8],
                    ['parameter', '=', $param['article_id']],
                ]
            )
            ->value('integral_record_id');
        
        if (empty($integral_record_id) && $param['member_id']) {
            
            // 插入积分记录
            $integralRecord->save(
                [
                    'member_id' => $param['member_id'],
                    'type' => 0,
                    'origin_point' => 8,
                    'parameter' => $param['article_id'],
                    'integral' => Env::get('integral_adv'),
                    'describe' => '浏览了' . $result['title'] . '广告',
                    'create_time' => date('Y-m-d H:i:s'),
                ]
            );
            
            // 增加积分
            $member->where('member_id', $param['member_id'])->setInc('pay_points', (int)Env::get('integral_adv'));
        }
        
        // 成长值广告记录查询
        $member_growth_record_id = $memberGrowthRecord
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['type', '=', 0],
                    ['describe', '=', '浏览了' . $result['title'] . '_' . $param['article_id'] . '广告'],
                ]
            )
            ->value('member_growth_record_id');
        
        if (empty($member_growth_record_id) && $param['member_id']) {
            
            // 插入成长值记录
            $memberGrowthRecord->save(
                [
                    'type' => 0,
                    'member_id' => $param['member_id'],
                    'growth_value' => Env::get('growth_adv'),
                    'describe' => '浏览了' . $result['title'] . '_' . $param['article_id'] . '广告',
                    'create_time' => date('Y-m-d H:i:s'),
                ]
            );
            
        }
        
        return $this->fetch(
            '',
            [
                'item' => $result,
            ]
        );
        
    }
    
    /**
     * 收藏热点文章
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CollectArticle $collectArticle
     * @return mixed
     */
    public function collect_article(Request $request, RSACrypt $crypt, CollectArticle $collectArticle)
    {
        if ($request::isPost()) {
            try {
                
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                
                // 验证
                $check = $collectArticle->valid($param, 'collect_article');
                if ($check['code']) {
                    return $crypt->response($check);
                }
                
                $collect_id = $collectArticle
                    ->where(
                        [
                            ['member_id', '=', $param['member_id']],
                            ['article_id', '=', $param['article_id']],
                        ]
                    )
                    ->value('collect_article_id');
                
                if ($collect_id) {
                    return $crypt->response(['code' => -1, 'message' => config('message.')[-12][-1]]);
                }
                
                // 新增
                $collectArticle->allowField(true)->save($param);
                
                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);
                
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
    
    /**
     * 收藏文章删除
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CollectArticle $collectArticle
     * @param Article $article
     * @return mixed
     */
    public function collect_article_delete(Request $request, RSACrypt $crypt, CollectArticle $collectArticle, Article $article)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                
                // 验证
                $check = $collectArticle->valid($param, 'collect_article_delete');
                if ($check['code']) {
                    return $crypt->response($check);
                }
                
                $res = $collectArticle::get($param['collect_article_id']);
                if ($res) {
                    // 删除
                    $state = $collectArticle::destroy($param['collect_article_id'], true);
                    
                    if ($state) {
                        $redisInstance = Cache::handler();
                        $prefix = Config::get('cache.default')['prefix'];
                        $redisInstance->zIncrBy(
                            $prefix . 'collect_article',
                            -count(explode(',', $param['collect_article_id'])),
                            $param['member_id']
                        );
                        $article->where('article_id', 'in', $param['article_id'])->setDec('collect');
                    }
                }
                
                
                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);
                
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
    
    /**
     * 收藏文章列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CollectArticle $collectArticle
     * @return mixed
     */
    public function article_list(Request $request, RSACrypt $crypt, CollectArticle $collectArticle)
    {
        try {
            // 获取参数
            $param = $request::instance()->param();
            $param['member_id'] = Session::get('member_info')['member_id'];
            
            $result = $collectArticle->alias('collect_article')
                ->join('article article', 'article.article_id = collect_article.article_id and article.delete_time is null')
                ->where('collect_article.member_id', $param['member_id'])
                ->field(
                    'collect_article_id,article.article_id,file,title,collect,DATE_FORMAT(collect_article.create_time,"%Y-%m-%d") as date_time'
                )
                ->append(['goods_file'])
                ->paginate(12);
            
        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
        }
        
        return $this->fetch('', ['code' => 0, 'result' => $result]);
    }
    
    
    /**
     * 品牌甄选
     * @param Request $request
     * @param Store $store
     * @param BrandClassify $brandClassify
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function brand_list(Request $request, Store $store, BrandClassify $brandClassify, Adv $adv)
    {
        
        //如果没有开启品牌甄选跳转到商城首页
        if (self::$functionStatus['is_brand'] == 0) {
            header("Location: /pc2.0/index/index");
            die;
        }
        
        $Classify = $brandClassify
            ->where('status', '1')
            ->field('brand_classify_id,brand_classify_name')
            ->order('sort', 'desc')
            ->select()->toArray();
        
        $banner = $adv
            ->where(
                [
                    ['adv_position_id', '=', config('pc_config.brand_class_id')],
                    ['status', '=', 1],
                    ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                ]
            )
            ->field('adv_id,title,type,file,content')
            ->order('sort', 'desc')
            ->select();
        
        // 获取参数
        $param = $request::instance()->param();
        
        // 条件
        if (empty($param['brand_classify_id'])) {
            $param['brand_classify_id'] = $Classify[0]['brand_classify_id'];
        }
        
        $condition = 'FIND_IN_SET(' . $param['brand_classify_id'] . ',brand_classify_id)';
        
        $result = $store
            //            ->relation('shop_goods')
            ->where($condition)
            ->where('status', 4)
            ->where('end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or')
            ->append(['shop_goods'])
            ->field('store_id,brand_image,store_name,logo')
            ->order('sort', 'desc')
            ->paginate(10, false, ['query' => $param]);
        
        $num = array_search($param['brand_classify_id'], array_column($Classify, 'brand_classify_id'));
        
        return $this->fetch(
            '',
            [
                'code' => 0,
                'classify' => $Classify,
                'banner' => $banner,
                'result' => $result,
                'array' => json($Classify),
                'brand_classify_id' => $Classify[0]['brand_classify_id'],
                'num' => $num,
                'header_title' => '品牌甄选',
            ]
        );
    }
    
    
    /**
     * 排行榜
     * @param Request $request
     * @param Goods $goodsModel
     * @param GoodsClassify $goodsClassify
     * @param Store $store
     * @param StoreClassify $storeClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ranking(Request $request, Goods $goodsModel, GoodsClassify $goodsClassify, Store $store, StoreClassify $storeClassify)
    {
        // 获取参数
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? '';
        
        // 条件
        $condition[] = ['goods_number', '>', 0];
        $condition[] = ['review_status', '=', 1];
        $condition[] = ['is_putaway', '=', 1];
        
        
        $goods_classify = $goodsClassify
            ->with('subset')
            ->where(['parent_id' => 0, 'status' => 1])
            ->field('classify_adv_id,goods_classify_id,title,web_file')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
            ->select();
        
        // dump($goods_classify);
        $param['goods_classify_id'] = $goods_classify[0]['subset'][0]['goods_classify_id'];
        
        $goodsData = $goodsClassify
            ->with(
                [
                    'ThreeGoods' => function ($e) use ($condition) {
                        $e->alias('goods')
                            ->join('store store', 'store.store_id = goods.store_id and ' . self::store_auth_sql('store'))
                            ->field(
                                'goods.goods_id,goods.goods_classify_id,goods_name,shop_price,shop_price as goods_price,goods.sales_volume,freight_status,shop,goods.file,freight_status,shop,is_group,is_bargain,freight_status,shop,group_price,cut_price,group_num,is_vip,is_city,attr_type_id,file as cart_file,goods_number,goods.store_id,is_limit,time_limit_price'
                            )
                            ->where(self::goods_where($condition, 'goods'))
                            ->order(['goods.sales_volume' => 'desc'])
                            ->limit(8);
                    },
                ]
            )
            ->where(['parent_id' => $param['goods_classify_id']])
            ->field('goods_classify_id,title')
            ->select()->toArray();
        foreach ($goodsData as $key => $val) {
            if (empty($val['three_goods'])) {
                unset($goodsData[$key]);
            }
        }
        
        
        $store_classify = $storeClassify
            ->field('store_classify_id,title,file')
            ->where('status', 1)
            ->order(['sort' => 'desc', 'create_time' => 'desc'])
            ->select();
        
        $condition = 'FIND_IN_SET(' . $store_classify[0]['store_classify_id'] . ',category)';
        $storeData = $store
            ->where('end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or')
            ->where('status', 4)
            ->where($condition)
            ->field('store_id,logo,shop,is_city,store_name,collect,sales_volume')
            ->append(['store_goods'])
            ->order('sales_volume', 'desc')
            ->select();
        if (!$storeData->isEmpty()) {
            $storeData = array_filter($storeData->toArray(), function ($v) {
                return !($v['store_goods']->isEmpty());
            });
        }
        // 折扣
        $discount = discount($param['member_id']);
        return $this->fetch(
            '',
            [
                'code' => 0,
                'goods_classify' => $goods_classify,
                'first_goods_classify_name' => $goods_classify[0]['subset'][0]['title'],
                'goods_data' => $goodsData,
                'discount' => $discount,
                'store_classify' => $store_classify,
                'store_data' => $storeData,
                'header_title' => '排行榜',
            ]
        );
    }
    
    public function ajax_goods_ranking(Request $request, RSACrypt $crypt, GoodsClassify $goodsClassify)
    {
        if ($request::isPost()) {
            try {
                $param = $crypt->request();
                $goodsData = $goodsClassify
                    ->with(
                        [
                            'ThreeGoods' => function ($e) {
                                $e->alias('goods')
                                    ->join('store store', 'store.store_id = goods.store_id and ' . self::store_auth_sql('store'))
                                    ->where(self::goods_where([], 'goods'))
                                    ->field(
                                        'goods.goods_id,goods.goods_classify_id,is_vip,goods_name,shop_price,shop_price as goods_price,goods.sales_volume,freight_status,shop,goods.file,freight_status,shop,is_group,is_bargain,freight_status,shop,group_price,cut_price,group_num,is_vip,is_city,attr_type_id,file as cart_file,goods_number,goods.store_id,is_limit,time_limit_price'
                                    )
                                    ->order(['goods.sales_volume' => 'desc'])
                                    ->limit(8);
                            },
                        ]
                    )
                    ->where(['parent_id|goods_classify_id' => $param['goods_classify_id']])
                    ->field('goods_classify_id,title')
                    ->select()->toArray();
                foreach ($goodsData as $key => $val) {
                    if (empty($val['three_goods'])) {
                        unset($goodsData[$key]);
                    }
                }
                return $crypt->response(
                    [
                        'code' => 0,
                        'result' => $goodsData,
                    ]
                );
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
    
    public function ajax_shop_ranking(Request $request, RSACrypt $crypt, Store $store)
    {
        if ($request::isPost()) {
            try {
                $param = $crypt->request();
                
                $condition = 'FIND_IN_SET(' . $param['store_classify_id'] . ',category)';
                
                $storeData = $store
                    ->where('end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or')
                    ->where('status', 4)
                    ->where($condition)
                    ->field('store_id,logo,shop,is_city,store_name,collect,sales_volume')
                    ->append(['store_goods'])
                    ->order('sales_volume', 'desc')
                    ->limit(12)->select();
                
                
                return $crypt->response(
                    [
                        'code' => 0,
                        'result' => $storeData,
                    ]
                );
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
    
    
    /**
     * 商品排行榜
     * @param Request $request
     * @param Goods $goodsModel
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_ranking(Request $request, Goods $goodsModel, GoodsClassify $goodsClassify)
    {
        // 获取参数
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? '';
        
        // 条件
        $condition[] = ['goods_number', '>', 0];
        $condition[] = ['review_status', '=', 1];
        $condition[] = ['is_putaway', '=', 1];
        
        // 分类
        if ($param['goods_classify_id']) {
            $condition[] = [
                'goods_classify_id',
                'in',
                implode(
                    ',',
                    array_column(
                        getParCate($param['goods_classify_id'], $goodsClassify, 0),
                        'goods_classify_id'
                    )
                ) ?: $param['goods_classify_id'],
            ];
        }
        
        $classify = $goodsClassify->where(['goods_classify_id' => $param['goods_classify_id']])->value('title');
        
        // 数据
        $result = $goodsModel->alias('goods')
            ->join('store store', 'store.store_id = goods.store_id and ' . self::store_auth_sql('store'))
            ->field(
                'goods.goods_id,goods_name,shop_price,goods.sales_volume,freight_status,shop,goods.file,freight_status,shop,is_group,is_bargain,freight_status,shop,group_price,cut_price,group_num,is_vip,is_city,attr_type_id,file as cart_file,goods_number,goods.store_id,is_limit,time_limit_price'
            )
            ->where(self::goods_where($condition, 'goods'))
            ->append(['attribute_list', 'limit_state'])
            ->order(['goods.sales_volume' => 'desc'])
            ->limit(6)
            ->select();
        
        // 折扣
        $discount = discount($param['member_id']);
        
        return $this->fetch('', ['code' => 0, 'result' => $result, 'classify' => $classify, 'discount' => $discount]);
        
    }
    
    /**
     * 店铺排行榜
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Store $store
     * @param StoreClassify $storeClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function store_ranking(Request $request, RSACrypt $crypt, Store $store, StoreClassify $storeClassify)
    {
        
        // 获取参数
        $param = $request::instance()->param();
        
        $param['goods_classify_id'] = empty($param['goods_classify_id']) ? '322' : $param['goods_classify_id'];
        
        
        // 验证
        $check = $store->valid($param, 'store_ranking');
        if ($check['code']) {
            return $crypt->response($check);
        }
        
        // 条件
        $condition = '';
        
        if (!empty($param['goods_classify_id'])) {
            $condition = 'FIND_IN_SET(' . $param['goods_classify_id'] . ',category)';
        }
        
        
        $storeData = $store
            ->where('end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or')
            ->where('status', 4)
            ->where($condition)
            ->field('store_id,logo,shop,is_city,store_name,collect,sales_volume')
            ->append(['store_goods'])
            ->order('sales_volume', 'desc')
            ->select();
        
        $classify = $storeClassify->where(['store_classify_id' => $param['goods_classify_id']])->value('title');
        
        return $this->fetch(
            '',
            ['code' => 0, 'result' => $storeData, 'classify' => $classify, 'header_title' => '排行榜']
        );
    }
    
    
    
    /*********废弃*****/
    
    //    /**
    //     * 文章详情 - 收藏文章删除
    //     * @param Request $request
    //     * @param RSACrypt $crypt
    //     * @param Article $article
    //     * @param CollectArticle $collectArticle
    //     * @return mixed
    //     */
    //    public function view_collect_article_delete(Request $request, RSACrypt $crypt, Article $article, CollectArticle $collectArticle)
    //    {
    //        if ($request::isPost())
    //        {
    //            try
    //            {
    //                // 获取参数
    //                $param = $crypt->request();
    //                $param['member_id'] = request(0)->mid;
    //
    //                // 删除
    //                $state = $collectArticle->where(
    //                    [
    //                        ['member_id', '=', $param['member_id']],
    //                        ['article_id', '=', $param['article_id']],
    //                    ]
    //                )->field('collect_article_id')->find()->delete(TRUE);
    //
    //                if ($state)
    //                {
    //                    $redisInstance = Cache::handler();
    //                    $prefix = Config::get('cache.default')['prefix'];
    //                    $redisInstance->zIncrBy($prefix . 'collect_article', -1, $param['member_id']);
    //                    $article->where('article_id', $param['article_id'])->setDec('collect');
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
    //
    //
    //    /**
    //     * 品牌甄选分类
    //     * @param Request $request
    //     * @param RSACrypt $crypt
    //     * @param BrandClassify $brandClassify
    //     * @param Adv $adv
    //     * @return mixed
    //     */
    //    public function brand_class_list(Request $request, RSACrypt $crypt, BrandClassify $brandClassify, Adv $adv)
    //    {
    //        if ($request::isPost())
    //        {
    //            try
    //            {
    //
    //                $result = $brandClassify
    //                    ->where('status', '1')
    //                    ->field('brand_classify_id,brand_classify_name')
    //                    ->order('sort', 'desc')
    //                    ->select();
    //
    //                $banner = $adv
    //                    ->where(
    //                        [
    //                            ['adv_position_id', '=', config('pc_config.brand_class_id')],
    //                            ['status', '=', 1],
    //                            ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
    //                            ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
    //                        ]
    //                    )
    //                    ->field('adv_id,title,type,file')
    //                    ->order('sort', 'desc')
    //                    ->select();
    //
    //                return $crypt->response(['code' => 0, 'result' => $result, 'banner' => $banner]);
    //
    //            } catch (\Exception $e)
    //            {
    //                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
    //            }
    //        }
    //    }
    
}