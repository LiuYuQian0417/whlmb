<?php
declare(strict_types = 1);

namespace app\interfaces\controller\store;

use app\common\model\Article;
use app\common\model\ArticleAttach;
use app\common\model\StoreAuth;
use app\common\model\StoreClassify;
use app\common\service\QrCode;
use app\common\model\Coupon;
use app\common\model\CollectStore;
use app\common\model\Goods;
use app\common\model\Store;
use app\common\model\StoreGoodsClassify;
use app\interfaces\controller\applet\My;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;

/**
 * 店铺 - Joy
 * Class Index
 * @package app\interfaces\controller\goods
 */
class Index extends BaseController
{
    
    /**
     * 头部
     * @param RSACrypt $crypt
     * @param Store $store
     * @param CollectStore $collectStore
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function head(RSACrypt $crypt,
                         Store $store,
                         CollectStore $collectStore)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $store->valid($param, 'head');
        // 店铺信息
        $result = $store
            ->where([
                ['store_id', '=', $param['store_id']],
                ['end_time', ['exp', Db::raw('is null')], ['>', date('Y-m-d')], 'or'],
                ['status', '=', 4],
            ])
            ->field('logo,store_name,type,shop,collect,status,back_image,goods_style,phone as store_phone,
            member_id as rong_id')
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -1,
                'message' => '当前店铺不存在或已过期',
            ], true);
        }
        // 关注状态 null未关注 有值已关注
        $result['state'] = $collectStore
            ->where([
                ['member_id', '=', $param['member_id']],
                ['store_id', '=', $param['store_id']],
            ])
            ->value('collect_store_id') ?: 0;
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 首页
     * @param RSACrypt $crypt
     * @param Store $store
     * @param Coupon $coupon
     * @param Goods $goods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Store $store,
                          Coupon $coupon,
                          Goods $goods)
    {
        $param = $crypt->request();
        $param['member_id'] = request(true)->mid ?? '';
        $store->valid($param, 'index');
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $result['coupon'] = [];
        // 优惠券是否显示
        if (Env::get('is_coupon', 1)) {
            // 优惠券
            $result['coupon'] = $coupon
                ->where([
                    ['type', '=', 0],
                    ['modality', '=', 0],
                    ['classify_str', '=', $param['store_id']],
                    ['exchange_num', '>', 0],
                    ['start_time', '<=', date('Y-m-d')],
                    ['end_time', '>=', date('Y-m-d')],
                    ['status', '=', 1],
                    ['is_gift', '=', 0],
                    ['is_integral_exchage', '=', 0],
                ])
                ->field('coupon_id,actual_price,full_subtraction_price')
                ->order('actual_price', 'asc')
                ->limit(4)
                ->select();
        }
        // banner推荐[按销量倒序]
        $result['banner'] = $goods
            ->where([
                ['store_id', '=', $param['store_id']],
                ['store_banner', '=', 1],           //是否为店铺banner推荐
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1],
            ])
            ->field('goods_id,recomme_file')
            ->order(['sales_volume' => 'desc'])
            ->find() ?: json([]);
        // 特级推荐[按销量倒序]
        $result['particularly_recommend'] = $goods
            ->where([
                ['store_id', '=', $param['store_id']],
                ['store_particularly_recommend', '=', 1],       //是否为店铺特别推荐
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1],
            ])
            ->field('goods_id,recomme_file')
            ->order(['sales_volume' => 'desc'])
            ->limit(2)
            ->select();
        // 普通推荐[按销量倒序]
        $result['recommend'] = $goods
            ->where([
                ['store_id', '=', $param['store_id']],
                ['store_recommend', '=', 1],            //是否为店铺普通推荐
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1],
            ])
            ->field('goods_id,file')
            ->order(['sales_volume' => 'desc', 'goods_id' => 'desc'])
            ->paginate(4, false);
        // 折扣
        $discount = discount($param['member_id']);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'discount' => $discount,
        ], true);
        
    }
    
    /**
     * 分类列表
     * @param RSACrypt $crypt
     * @param StoreGoodsClassify $storeGoodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function classify_list(RSACrypt $crypt,
                                  StoreGoodsClassify $storeGoodsClassify)
    {
        $param = $crypt->request();
        $storeGoodsClassify->valid($param, 'classify_list');
        // 店铺信息
        $result = $storeGoodsClassify
            ->with('subset')
            ->where([
                ['store_id', '=', $param['store_id']],
                ['status', '=', 1],         //是否显示
                ['parent_id', '=', 0],
            ])
            ->field('store_goods_classify_id,title')
            ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
        
    }
    
    /**
     * 热门分类列表
     * @param RSACrypt $crypt
     * @param StoreGoodsClassify $storeGoodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function hot_classify_list(RSACrypt $crypt,
                                      StoreGoodsClassify $storeGoodsClassify)
    {
        $param = $crypt->request();
        $storeGoodsClassify->valid($param, 'hot_classify_list');
        $result = $storeGoodsClassify
            ->where([
                ['store_id', '=', $param['store_id']],
                ['status', '=', 1],     // 是否显示
                ['is_hot', '=', 1],     // 是否为热门分类
            ])
            ->field('store_goods_classify_id,title')
            ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 店铺详情
     * @param RSACrypt $crypt
     * @param Store $store
     * @param QrCode $qrCode
     * @param StoreAuth $storeAuth
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function info(RSACrypt $crypt,
                         Store $store,
                         QrCode $qrCode,
                         StoreAuth $storeAuth)
    {
        $param = $crypt->request();
        $store->valid($param, 'info');
        // 店铺信息
        $result = $store
            ->where([
                ['store_id', '=', $param['store_id']],
                ['end_time', ['exp', Db::raw('is null')], ['>', date('Y-m-d')], 'or'],
            ])
            ->field('logo,store_name,collect,address,create_time,
            qr_code,describe,phone,lat,lng')
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -1,
                'message' => '店铺不存在或已过期',
            ], true);
        }
        
        $result['applet_code'] = '';
        if (config('user.is_show_two_code') && config('user.applet.is_include')) {
            // 小程序二维码
            $file = 'static/img/interfaces/qr_code/store/applet/store_' . $param['store_id'] . '.png';
            My::aplCode([
                'file_path' => $file,
                'scene' => 'store,' . $param['store_id'],
            ], [
                'width' => 600,
                'page' => 'nearby_shops/shop_detail/shop_detail',
            ]);
            $result['applet_code'] = Request::domain() . '/' . $file;
        }
        
        // app二维码
        $result['qr_code'] = $qrCode->store_qrCode($param['store_id']);
        // 营业执照和行政许可证
        $path = $storeAuth
            ->where([
                ['store_id', '=', $param['store_id']],
            ])
            ->field('file1,licence_file')
            ->find();
        $result['business_file'] = $path && $path['file1'] ? $path['file1'] : '';
        $result['licence_file'] = $path && $path['licence_file'] ? $path['licence_file'] : '';
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 全部商品
     * @param RSACrypt $crypt
     * @param Goods $goods
     * @param StoreGoodsClassify $storeGoodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_list(RSACrypt $crypt,
                               Goods $goods,
                               StoreGoodsClassify $storeGoodsClassify)
    {
        // 能调用这个接口,说明店铺状态正常,无需判断店铺状态
        $param = $crypt->request();
        $param['member_id'] = request(true)->mid ?? '';
        $goods->valid($param, 'store_goods_list');
        $condition = [
            ['goods_number', '>', 0],
            ['review_status', '=', 1],
            ['is_putaway', '=', 1],
            ['store_id', '=', $param['store_id']],
        ];
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        // 是否推荐[店铺普通推荐,店铺特别推荐]
        if ($param['recommend']) {
            $condition[] = ['store_recommend|store_particularly_recommend', '=', $param['recommend']];
        }
        // 查询分类
        $classify_id = $param['classify_id'];
        if ($total = getStoreParCate($param['classify_id'], $storeGoodsClassify, 0)) {
            $classify_id .= ',' . implode(',', array_column($total, 'store_goods_classify_id'));
        }
        // 分类
        if (!empty($param['classify_id'])) {
            $condition[] = ['store_goods_classify_id', 'in', $classify_id];
        }
        // 关键字
        if (!empty($param['keyword'])) {
            $condition[] = ['goods_name', 'like', '%' . $param['keyword'] . '%'];
        }
        // 排序
        $rank = !empty($param['rank']) ? $param['rank'] : 'desc';
        if (!empty($param['parameter'])) {
            $order[$param['parameter'] == 'shop_price' ? 'shop_price_with_disc' : $param['parameter']] = $rank;
        }
        $order['sort'] = 'desc';
        // 店铺信息
        $discount = discount($param['member_id']);
        $result = $goods
            ->where($condition)
            ->field('goods_id,goods_name,shop_price,is_group,is_bargain,sales_volume,
            freight_status,group_price,cut_price,file,group_num,is_vip,attr_type_id,
            file as cart_file,goods_number,store_id,is_limit,time_limit_price,market_price,
            if(is_vip=1,shop_price*' . $discount / 100 . ',shop_price) as shop_price_with_disc')
            ->append(['attribute_list', 'cart_number', 'cart_id', 'limit_state'])
            ->hidden(['shop_price_with_disc'])
            ->order($order)
            ->paginate($goods->pageLimits, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'discount' => $discount,
        ], true);
        
    }
    
    /**
     * 新品
     * @param RSACrypt $crypt
     * @param Goods $goods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function new_product_list(RSACrypt $crypt,
                                     Goods $goods)
    {
        $param = $crypt->request();
        $param['member_id'] = request(true)->mid ?? '';
        $goods->valid($param, 'new_product_list');
        $condition = [
            ['goods_number', '>', 0],
            ['review_status', '=', 1],
            ['is_putaway', '=', 1],
            ['store_id', '=', $param['store_id']],
        ];
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $result = $goods
            ->where($condition)
            ->field('goods_id,goods_name,shop_price,sales_volume,is_group,
            is_bargain,freight_status,group_price,cut_price,file,group_num,
            is_vip,DATE_FORMAT(create_time,"%Y-%m-%d") as date_time,attr_type_id,
            file as cart_file,goods_number,store_id,is_limit,time_limit_price,
            market_price')
            ->order('create_time', 'desc')
            ->append(['attribute_list'])
            ->paginate(20, false)
            ->toArray();
        $result['data'] = arrayGrouping($result['data'], 'date_time', 'date', 'list');
        // 折扣
        $discount = discount($param['member_id']);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'discount' => $discount,
        ], true);
        
    }
    
    /**
     * 收藏店铺
     * @param RSACrypt $crypt
     * @param Store $store
     * @param CollectStore $collectStore
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect_store(RSACrypt $crypt,
                                  Store $store,
                                  CollectStore $collectStore)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $store->valid($param, 'collect_store');
        $store_id = $store
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->value('store_id');
        if ($param['store_id'] == $store_id) {
            return $crypt->response([
                'code' => -1,
                'message' => '不能关注自己的店铺',
            ], true);
        }
        $collect_id = $collectStore
            ->where([
                ['member_id', '=', $param['member_id']],
                ['store_id', '=', $param['store_id']],
            ])
            ->value('collect_store_id');
        if ($collect_id) return $crypt->response([
            'code' => -2,
            'message' => '您已经关注过该店铺,请勿重复关注',
        ], true);
        // 新增
        $collectStore
            ->allowField(true)
            ->isupdate(false)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '关注成功',
        ], true);
    }
    
    /**
     * 收藏店铺列表
     * @param RSACrypt $crypt
     * @param CollectStore $collectStore
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect_store_list(RSACrypt $crypt,
                                       CollectStore $collectStore)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $result = $collectStore
            ->alias('cs')
            ->join('store s', 's.store_id = cs.store_id')
            ->where([
                ['cs.member_id', '=', $param['member_id']],
            ])
            ->field('collect_store_id,s.store_id,logo,store_name,collect,status,end_time')
            ->append(['is_invalid'])
            ->hidden(['status', 'end_time'])
            ->order('cs.create_time', 'desc')
            ->paginate(10, false);
        // 矫正计数
        if ($result->total() >= 0) {
            $prefix = Config::get('cache.default')['prefix'];
            Cache::handler()->zAdd($prefix . 'collect_store', $result->total(), $param['member_id']);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 收藏店铺删除
     * @param RSACrypt $crypt
     * @param Store $store
     * @param CollectStore $collectStore
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect_store_delete(RSACrypt $crypt,
                                         Store $store,
                                         CollectStore $collectStore)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $store->valid($param, 'collect_store_delete');
        $state = $collectStore::destroy($param['collect_store_id'], true);
        if ($state) {
            $redisInstance = Cache::handler();
            $prefix = Config::get('cache.default')['prefix'];
            $score = $redisInstance->zScore($prefix . 'collect_store', $param['member_id']);
            $count = count(explode(',', $param['collect_store_id']));
            if ($score > $count) {
                $redisInstance->zIncrBy($prefix . 'collect_store', -$count, $param['member_id']);
            } else {
                $redisInstance->zAdd($prefix . 'collect_store', 0, $param['member_id']);
            }
            $store->where([
                ['store_id', 'in', $param['store_id']],
            ])->setDec('collect');
        }
        return $crypt->response([
            'code' => 0,
            'message' => '删除成功',
        ], true);
    }
    
    /**
     * 店铺首页收藏商品删除
     * @param RSACrypt $crypt
     * @param Store $store
     * @param CollectStore $collectStore
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function view_collect_store_delete(RSACrypt $crypt,
                                              Store $store,
                                              CollectStore $collectStore)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $store->valid($param, 'view_collect_store_delete');
        $state = $collectStore
            ->where([
                ['member_id', '=', $param['member_id']],
                ['store_id', '=', $param['store_id']],
            ])
            ->field('collect_store_id')
            ->find();
        if (is_null($state)) {
            return $crypt->response([
                'code' => -1,
                'message' => '记录不存在',
            ], true);
        }
        $state->delete(true);
        $redisInstance = Cache::handler();
        $prefix = Config::get('cache.default')['prefix'];
        $redisInstance->zIncrBy($prefix . 'collect_store', -1, $param['member_id']);
        $store
            ->where('store_id', $param['store_id'])
            ->setDec('collect');
        return $crypt->response([
            'code' => 0,
            'message' => '取消关注成功',
        ], true);
        
    }
    
    /**
     * 店铺动态
     * @param RSACrypt $crypt
     * @param Article $article
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function article_list(RSACrypt $crypt,
                                 Article $article)
    {
        $param = $crypt->request();
        $article->valid($param, 'article_list');
        $result = $article
            ->where([
                ['store_id', '=', $param['store_id']],
                ['article_classify_id', '=', 3],
                ['status', '=', 1],
            ])
            ->field('article_id,title,file,multiple_file,DATE_FORMAT(create_time,"%m-%d") as date_time')
            ->order(['create_time' => 'desc'])
            ->paginate(10, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
        
        
    }
    
    /**
     * 店铺动态详情
     * @param RSACrypt $crypt
     * @param Store $store
     * @param CollectStore $collectStore
     * @param Article $article
     * @param ArticleAttach $articleAttach
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_view(RSACrypt $crypt,
                                 Store $store,
                                 CollectStore $collectStore,
                                 Article $article,
                                 ArticleAttach $articleAttach)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $article->valid($param, 'article_view');
        $article
            ->where([
                ['article_id', '=', $param['article_id']],
            ])
            ->setInc('hits');
        $result = $article
            ->where([
                ['article_id', '=', $param['article_id']],
            ])
            ->field('title,web_content,hits')
            ->order('create_time', 'desc')
            ->find();
        // 店铺信息
        $result['shop'] = $store
            ->where([
                ['store_id', '=', $param['store_id']],
                ['end_time', ['exp', Db::raw('is null')], ['>', date('Y-m-d')], 'or'],
            ])
            ->field('logo,store_name')
            ->find();
        // 关注状态
        $result['shop']['state'] = $collectStore
            ->where([
                ['member_id', '=', $param['member_id']],
                ['store_id', '=', $param['store_id']],
            ])
            ->value('collect_store_id') ?: 0;
        $result['goods'] = $articleAttach
            ->alias('aa')
            ->join('goods g', 'g.goods_id = aa.goods_id and ' . self::$goodsAuthSql)
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->where([
                ['article_id', '=', $param['article_id']],
            ])
            ->field('g.goods_id,goods_name,file,shop_price,g.is_limit,g.is_group,g.market_price,
            g.is_bargain,g.is_vip,g.group_price,g.cut_price,g.time_limit_price,g.shop_price')
            ->select();
        $result['web_content'] = str_ireplace('<div class="tools"><i class="fa fa-arrow-up move-up"></i><i class="fa fa-arrow-down move-down"></i><em class="move-remove"><i class="fa fa-times" aria-hidden="true"></i> 移除</em><div class="cover"></div></div>', '', $result['web_content']);
        $result['web_content'] = str_ireplace('<div class="tools"><i class="fa fa-arrow-up move-up"></i><i class="fa fa-arrow-down move-down"></i><em class="move-remove" hidden ><i class="fa fa-times" aria-hidden="true"></i> 移除</em><div class="cover"></div></div>', '', $result['web_content']);
        $result['web_content'] = str_ireplace('<div class="tools" style="display: none;"><i class="fa fa-arrow-up move-up disabled"></i><i class="fa fa-arrow-down move-down"></i><em class="move-remove" hidden  hidden=""><i class="fa fa-times" aria-hidden="true"></i> 移除</em><div class="cover"></div></div>', '', $result['web_content']);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'goods_num' => count($result['goods']),
        ], true);
    }
    
    /**
     * 店铺动态详情 - web页面
     * @param Request $request
     * @param Article $article
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function web_article_view(Request $request, Article $article)
    {
        $info = $article
            ->where('article_id', $request::get('article_id'))
            ->field('title,web_content')
            ->find();
        if (!$info) {
            return "<div style='text-align: center;padding: 30px 0;'>文章不存在</div>";
        }
        return web_page($info['title'], $info['web_content']);
    }
    
    /**
     * 附近店铺
     * @param RSACrypt $crypt
     * @param Store $store
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function nearby_list(RSACrypt $crypt,
                                Store $store)
    {
        $param = $crypt->request();
        $result = $store
            ->with('posterGoods')
            ->where([
                ['is_good', '=', 1],        // 是否为发现好店
                ['status', '=', 4],
                ['end_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d')], 'or'],
            ])
            ->field('store_id,good_image,type,logo,store_name')
            ->order('sort', 'desc')
            ->limit(5)
            ->select();
        $condition = [
            ['status', '=', 4],
            ['end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or'],
        ];
        $rank = ['is_recommend' => 'desc', 'sort' => 'desc'];
        // 销量排序
        if (!empty($param['sales_volume'])) {
            $rank = ['sales_volume' => 'desc'];
        }
        // 门店自提[支持多店]
        if (!empty($param['is_shop']) && self::$oneOrMore) {
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
        if ($param['shop'] <> null) {
            $condition[] = ['shop', 'in', $param['shop']];
        }
        // 距离排序
        if (!empty($param['distance'])) {
            $rank = ['distance' => 'asc'];
        }
        $param['lng'] = empty($param['lng']) ? 0 : $param['lng'];
        $param['lat'] = empty($param['lat']) ? 0 : $param['lat'];
        $store_list = $store
            ->where($condition)
            ->field('store_id,logo,store_name,address,collect,lat,lng,type,
            round(6378.138*2*asin(sqrt(pow(sin( (lat*pi()/180-' . $param['lat'] . '*pi()/180)/2),2)+cos(lat*pi()/180)*cos(' . $param['lat'] . '*pi()/180)* pow(sin( (lng*pi()/180-' . $param['lng'] . '*pi()/180)/2),2))),3) AS distance')
            ->append(['shop_goods'])
            ->order($rank)
            ->paginate(10, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'store_list' => $store_list,
        ], true);
    }
    
    /**
     * 发现好店列表
     * @param RSACrypt $crypt
     * @param Store $store
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function good_list(RSACrypt $crypt,
                              Store $store)
    {
        $param = $crypt->request();
        $condition_array = [
            ['status', '=', 4],
            ['is_good', '=', 1],          // 是否为好店
            ['end_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d')], 'or'],
        ];
        $condition = '';
        if (!empty($param['category'])) {
            $condition = 'FIND_IN_SET(' . $param['category'] . ',category)';
        }
        $result = $store
            ->where($condition)
            ->where($condition_array)
            ->where('')
            ->field('store_id,logo,store_name')
            ->append(['shop_goods'])
            ->order(['is_recommend' => 'desc', 'sort' => 'desc'])
            ->paginate(20, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 搜索店铺
     * @param RSACrypt $crypt
     * @param Store $store
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function search_list(RSACrypt $crypt,
                                Store $store)
    {
        $param = $crypt->request();
        $condition = [
            ['status', '=', 4],
        ];
        // 默认排序
        $rank = ['sort' => 'desc'];
        // 同城配送
        if (!empty($param['keyword'])) {
            $condition[] = ['store_name|keywords', 'like', '%' . $param['keyword'] . '%'];
        }
        // 销量排序
        if (!empty($param['sales_volume'])) {
            $rank = ['sales_volume' => 'desc'];
        }
        // 门店自提
        if (!empty($param['is_shop']) && self::$oneOrMore) {
            $condition[] = ['is_shop', '=', 1];
        }
        // 同城配送
        if (!empty($param['is_city'])) {
            $condition[] = ['is_city', '=', 1];
        }
        // 个人、企业类型
        if ($param['shop'] <> null) {
            $condition[] = ['shop', 'in', $param['shop']];
        }
        // 距离排序
        if (!empty($param['distance'])) {
            $rank = ['distance' => 'asc'];
        }
        $result = $store
            ->where($condition)
            ->field('store_id,logo,store_name,address,collect,lat,lng,type,
            round(st_distance(point(lng,lat),point(' . $param['lng'] . ',' . $param['lat'] . '))*111.195,3) AS distance')
            ->append(['shop_goods'])
            ->order($rank)
            ->paginate(10, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 店铺分类
     * @param RSACrypt $crypt
     * @param StoreClassify $storeClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function platform_classify(RSACrypt $crypt,
                                      StoreClassify $storeClassify)
    {
        $result = $storeClassify
            ->field('store_classify_id,title')
            ->where([
                ['status', '=', 1],
            ])
            ->order(['sort' => 'desc', 'create_time' => 'desc'])
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 读取两级
     * @param $data
     * @param $list
     * @param $val
     * @param $val2
     * @param $val3
     * @param $val4
     * @param $val5
     * @return array
     */
    protected function cartGrouping($data, $list, $val, $val2, $val3 = '', $val4 = '', $val5 = '')
    {
        
        if (empty($data)) {
            return [];
        }
        $result = array_values(array_reduce($data, function ($value, $key) use ($val, $val2, $val3, $val4, $val5, $list) {
            if ($val) $value[$key[$val]][$val] = $key[$val];
            if ($val2) $value[$key[$val]][$val2] = $key[$val2];
            if ($val3) $value[$key[$val]][$val3] = $key[$val3];
            if ($val4) $value[$key[$val]][$val4] = $key[$val4];
            if ($val5) $value[$key[$val]][$val5] = $key[$val5];
            $value[$key[$val]][$list][] = $key;
            return $value;
        }));
        return $result;
    }
}