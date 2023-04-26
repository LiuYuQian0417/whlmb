<?php
declare(strict_types = 1);

namespace app\interfaces\controller\home;

use app\common\model\Article;
use app\common\model\ArticleAttach;
use app\common\model\BrandClassify;
use app\common\model\CollectArticle;
use app\common\model\Goods;
use app\common\model\GoodsClassify;
use app\common\model\IntegralRecord;
use app\common\model\Member;
use app\common\model\MemberGrowthRecord;
use app\common\model\Store;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;

/**
 * 首页附属 - Joy
 * Class Home
 * @package app\interfaces\controller\goods
 */
class Home extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    /**
     * 商品排行榜
     * @param RSACrypt $crypt
     * @param Goods $goodsModel
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_ranking(RSACrypt $crypt,
                                  Goods $goodsModel,
                                  GoodsClassify $goodsClassify)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $condition = [
            ['goods_number', '>', 0],
            ['review_status', '=', 1],
            ['is_putaway', '=', 1],
        ];
        // 分类
        if ($param['goods_classify_id']) {
            $condition[] = ['goods_classify_id', 'in', implode(',',
                array_column(getParCate($param['goods_classify_id'], $goodsClassify, 0), 'goods_classify_id')) ?:
                $param['goods_classify_id']];
        }
        // 数据
        $result = $goodsModel
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->field('g.goods_id,goods_name,shop_price,g.sales_volume,freight_status,shop,
            g.file,freight_status,shop,is_group,is_bargain,freight_status,shop,
            group_price,cut_price,group_num,is_vip,attr_type_id,file as cart_file,
            goods_number,g.store_id,is_limit,time_limit_price,g.market_price')
            ->where($condition)
            ->append(['attribute_list', 'limit_state'])
            ->order(['g.sales_volume' => 'desc'])
            ->paginate(20, false);
        // 折扣
        $discount = discount($param['member_id']) ?: '100';
        return $crypt->response([
            'code' => 0,
            'result' => $result,
            'discount' => $discount,
        ], true);
    }
    
    /**
     * 店铺排行榜
     * @param RSACrypt $crypt
     * @param Store $store
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function store_ranking(RSACrypt $crypt,
                                  Store $store)
    {
        $param = $crypt->request();
        $store->valid($param, 'store_ranking');
        $condition = '';
        if (!empty($param['goods_classify_id'])) {
            $condition = 'FIND_IN_SET(' . $param['goods_classify_id'] . ',category)';
        }
        $result = $store
            ->where($condition)
            ->where([
                ['status', '=', 4],
                ['end_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d')], 'or'],
            ])
            ->field('store_id,logo,store_name,type,collect')
            ->append(['shop_goods'])
            ->order('sales_volume', 'desc')
            ->paginate(10, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 品牌甄选分类
     * @param RSACrypt $crypt
     * @param BrandClassify $brandClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function brand_class_list(RSACrypt $crypt,
                                     BrandClassify $brandClassify)
    {
        if (!self::$oneOrMore) {
            return $crypt->response([
                'code' => -1,
                'message' => '当前模式不支持品牌优选'
            ], true);
        }
        $result = $brandClassify
            ->where([
                ['status', '=', '1'],
            ])
            ->field('brand_classify_id,brand_classify_name')
            ->order('sort', 'desc')
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
        
    }
    
    /**
     * 品牌甄选
     * @param RSACrypt $crypt
     * @param Store $store
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function brand_list(RSACrypt $crypt,
                               Store $store)
    {
        $param = $crypt->request();
        $store->valid($param, 'brand_list');
        $condition = '';
        if (!empty($param['brand_classify_id'])) {
            $condition = 'FIND_IN_SET(' . $param['brand_classify_id'] . ',brand_classify_id)';
        }
        $result = $store
            ->where($condition)
            ->where([
                ['status', '=', 4],
                ['end_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d')], 'or'],
            ])
            ->field('store_id,brand_image,store_name')
            ->append(['shop_goods'])
            ->order('sort', 'desc')
            ->paginate(10, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 热点文章列表
     * @param RSACrypt $crypt
     * @param Article $article
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function hot_list(RSACrypt $crypt,
                             Article $article)
    {
        $result = $article
            ->where([
                ['status', '=', 1],
                ['article_classify_id', '=', 2],
            ])
            ->field('article_id,title,hits,file')
            ->order(['state' => 'desc', 'create_time' => 'desc'])
            ->append(['goods_number'])
            ->paginate(20, false);
        return $crypt->response([
            'code' => 0,
            'result' => $result,
        ], true);
        
    }
    
    /**
     * 热点文章详情
     * @param RSACrypt $crypt
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
    public function hot_view(RSACrypt $crypt,
                             Article $article,
                             ArticleAttach $articleAttach,
                             CollectArticle $collectArticle,
                             IntegralRecord $integralRecord,
                             MemberGrowthRecord $memberGrowthRecord,
                             Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $article->valid($param, 'hot_view');
        $result = $article
            ->where([
                ['article_id', '=', $param['article_id']],
            ])
            ->field('article_id,title,web_content,file,link_url')
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -1,
                'message' => '文章不存在',
            ], true);
        }
        // 关注状态
        $result['attention_state'] = $collectArticle
            ->where([
                ['article_id', '=', $param['article_id']],
                ['member_id', '=', $param['member_id']],
            ])
            ->value('collect_article_id') ?: '';
        // 商品状态
        $result['goods'] = $articleAttach
            ->alias('aa')
            ->join('goods g', 'g.goods_id = aa.goods_id')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->where([
                ['article_id', '=', $param['article_id']],
                ['g.is_putaway', '=', 1],
                ['g.review_status', '=', 1],
                ['g.goods_number', '>', 0],
            ])
            ->field('g.goods_id,g.goods_name,file,shop_price,market_price,
            g.is_limit,g.is_bargain,g.is_group,g.shop_price,g.is_vip,
            g.group_price,g.cut_price,g.time_limit_price')
            ->select();
        $article
            ->where([
                ['article_id', '=', $param['article_id']],
            ])
            ->setInc('hits');
        // 积分广告记录查询
        $integral_record_id = $integralRecord
            ->where([
                ['member_id', '=', $param['member_id']],
                ['type', '=', 0],
                ['origin_point', '=', 8],
                ['parameter', '=', $param['article_id']],
            ])
            ->value('integral_record_id');
        if (empty($integral_record_id) && $param['member_id']) {
            // 插入积分记录
            $integralRecord->save([
                'member_id' => $param['member_id'],
                'type' => 0,
                'origin_point' => 8,
                'parameter' => $param['article_id'],
                'integral' => Env::get('integral_adv'),
                'describe' => '浏览了' . $result['title'] . '广告',
                'create_time' => date('Y-m-d H:i:s')
            ]);
            // 增加积分
            $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->setInc('pay_points', (int)Env::get('integral_adv', 0));
        }
        // 成长值广告记录查询
        $member_growth_record_id = $memberGrowthRecord
            ->where([
                ['member_id', '=', $param['member_id']],
                ['type', '=', 0],
                ['describe', '=', '浏览了' . $result['title'] . '_' . $param['article_id'] . '广告']
            ])
            ->value('member_growth_record_id');
        if (empty($member_growth_record_id) && $param['member_id']) {
            // 插入成长值记录
            $memberGrowthRecord->save([
                'type' => 0,
                'member_id' => $param['member_id'],
                'growth_value' => Env::get('growth_adv'),
                'describe' => '浏览了' . $result['title'] . '_' . $param['article_id'] . '广告',
                'create_time' => date('Y-m-d H:i:s')
            ]);
            // 检测会员成长值若升级则推送信息
            app('app\\interfaces\\behavior\\Growth')->checkCurGrowth([
                'member_id' => $param['member_id'],
                'web_open_id' => Request::param('web_open_id'),
                'subscribe_time' => Request::param('subscribe_time'),
                'phone' => Request::param('phone'),
            ]);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
        
    }
    
    /**
     * 收藏热点文章
     * @param RSACrypt $crypt
     * @param CollectArticle $collectArticle
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect_article(RSACrypt $crypt,
                                    CollectArticle $collectArticle)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $collectArticle->valid($param, 'collect_article');
        $collect_id = $collectArticle
            ->where([
                ['member_id', '=', $param['member_id']],
                ['article_id', '=', $param['article_id']],
            ])
            ->value('collect_article_id');
        if ($collect_id) {
            return $crypt->response([
                'code' => -1,
                'message' => '您已经关注过该文章,请勿重复关注',
            ], true);
        }
        // 新增
        $collectArticle
            ->allowField(true)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '收藏成功',
        ], true);
        
    }
    
    /**
     * 收藏文章删除
     * @param RSACrypt $crypt
     * @param CollectArticle $collectArticle
     * @param Article $article
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect_article_delete(RSACrypt $crypt,
                                           CollectArticle $collectArticle,
                                           Article $article)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $collectArticle->valid($param, 'collect_article_delete');
        $state = $collectArticle::destroy($param['collect_article_id'], true);
        if ($state) {
            $redisInstance = Cache::handler();
            $prefix = Config::get('cache.default')['prefix'];
            $count = count(explode(',', $param['collect_article_id']));
            $score = $redisInstance->zScore($prefix . 'collect_article', $param['member_id']);
            if ($score > $count) {
                $redisInstance->zIncrBy($prefix . 'collect_article', -$count, $param['member_id']);
            } else {
                $redisInstance->zAdd($prefix . 'collect_article', 0, $param['member_id']);
            }
            $article
                ->where([
                    ['article_id', 'in', $param['article_id']],
                ])
                ->setDec('collect');
        }
        return $crypt->response([
            'code' => 0,
            'message' => '取消收藏成功',
        ], true);
    }
    
    /**
     * 收藏文章列表
     * @param RSACrypt $crypt
     * @param CollectArticle $collectArticle
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_list(RSACrypt $crypt,
                                 CollectArticle $collectArticle)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $result = $collectArticle
            ->alias('ca')
            ->join('article a', 'a.article_id = ca.article_id')
            ->where('ca.member_id', $param['member_id'])
            ->field('collect_article_id,a.article_id,file,title,collect,
            DATE_FORMAT(ca.create_time,"%Y-%m-%d") as date_time,a.status,a.delete_time')
            ->append(['goods_file', 'is_invalid'])
            ->hidden(['status', 'delete_time'])
            ->order(['ca.create_time' => 'desc'])
            ->paginate(10, false);
        // 矫正计数
        if ($result->total() >= 0) {
            $prefix = Config::get('cache.default')['prefix'];
            Cache::handler()->zAdd($prefix . 'collect_article', $result->total(), $param['member_id']);
        }
        return $crypt->response([
            'code' => 0,
            'result' => $result,
        ], true);
    }
    
    /**
     * 文章详情 - 收藏文章删除
     * @param RSACrypt $crypt
     * @param Article $article
     * @param CollectArticle $collectArticle
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function view_collect_article_delete(RSACrypt $crypt,
                                                Article $article,
                                                CollectArticle $collectArticle)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $state = $collectArticle
            ->where([
                ['member_id', '=', $param['member_id']],
                ['article_id', '=', $param['article_id']],
            ])
            ->field('collect_article_id')
            ->find();
        if ($state) {
            $state->delete();
            $redisInstance = Cache::handler();
            $prefix = Config::get('cache.default')['prefix'];
            $score = $redisInstance->zScore($prefix . 'collect_article', $param['member_id']);
            if ($score > 0) {
                $redisInstance->zIncrBy($prefix . 'collect_article', -1, $param['member_id']);
            }
            $article
                ->where([
                    ['article_id', '=', $param['article_id']],
                ])
                ->setDec('collect');
        }
        return $crypt->response([
            'code' => 0,
            'message' => '取消收藏成功',
        ], true);
    }
}