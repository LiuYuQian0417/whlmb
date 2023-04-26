<?php
declare(strict_types = 1);

namespace app\interfaces\controller\home;

use app\common\model\Adv;
use app\common\model\Article;
use app\common\model\Cart;
use app\common\model\Coupon;
use app\common\model\Goods;
use app\common\model\GoodsClassify;
use app\common\model\Icon;
use app\common\model\Limit;
use app\common\model\LimitInterval;
use app\common\model\Member;
use app\common\model\MemberCoupon;
use app\common\service\Inform;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Request;

/**
 * 首页 - Joy
 * Class Home
 * @package app\interfaces\controller\home
 */
class Index extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    /**
     * 首页
     * @param RSACrypt $crypt
     * @param Adv $adv
     * @param Article $article
     * @param LimitInterval $limitInterval
     * @param Limit $limit
     * @param Goods $goodsModel
     * @param GoodsClassify $goodsClassify
     * @param Cart $cart
     * @param Member $member
     * @param Coupon $coupon
     * @param Icon $icon
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Adv $adv,
                          Article $article,
                          LimitInterval $limitInterval,
                          Limit $limit,
                          Goods $goodsModel,
                          GoodsClassify $goodsClassify,
                          Cart $cart,
                          Member $member,
                          Coupon $coupon,
                          Icon $icon)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $function_status = Env::get();
        $result = [
            'banner' => self::getAd($adv, 12),                      // banner
            'adv_list' => self::getAd($adv, 14),                    // 主推广告
            'hot_list' => self::getHotList($article, 6),            // 热点新闻
            'popularity' => $function_status['IS_GOODS_RECOMMEND'] ? self::getPopularity($goodsModel, 6) : [],  // 好物推荐
            // 为你推荐 小随机 后期需要改成 用户习惯模式
            'recommend_list' => recommend_list($goodsModel, 10, $param['member_id']),
            'nav' => self::getNav($function_status, $icon),
            'parameter' => urlencode($crypt->singleEnc(['member_id' => $param['member_id']])),
            'info' => [
                'cart_number' => $cart->cartNum($param['member_id']),   // 购物车数量
                'discount' => discount($param['member_id']) ?: '100',            // 会员折扣率
            ],
            'set' => [
                'template' => $function_status['DEFAULT_TEMPLATE'],     // 默认模板 0 第一套 1 第二套
                'popup_adv_status' => $function_status['POPUP_ADV_STATUS'] &&
                $function_status['IS_COUPON'] && $coupon->hasValid() ?
                    $member->isGift($param['member_id']) : 0,   // 是否开启领取新人专享  是否开启优惠券功能
            ],
        ];
        $extra = [];
        if (array_key_exists('pattern', $param) && $param['pattern'] >= 1) {
            switch ($param['pattern']) {
                case 1:  // 单多新版1
                    if (self::$oneOrMore) {
                        // 多店
                        $lis = $limitInterval->getStage();
                        if (!empty($lis)) {
                            array_walk($lis, function (&$x) use ($limit) {
                                $x['count_down'] = strtotime(date('Y-m-d ' . $x['end_time'] . ':00:00')) - time();
                                $x['list'] = self::getLimit($limit, $x['limit_interval_id'], 3);
                            });
                        }
                        $extra = [
                            // 推荐商品分类包含商品
                            'class_list' => $function_status['IS_CLASSIFY_RECOMMEND'] ? self::getClassify($goodsClassify, 7) : [],
                            'new_list' => self::getNewList($goodsModel, 0, 2), // 新品上市[新上架的商品]
                            'big_list' => self::getNewList($goodsModel, 1, 2), // 大牌推荐[品牌甄选销量最高]
                            'word_list' => self::getNewList($goodsModel, 2, 2),// 今日爆款[优品推荐销量最高]
                            'limit' => $function_status['IS_LIMIT'] ? $lis : [],    // 限时抢购
                        ];
                    } else {
                        // 单店
                        $extra = [
                            // 推荐商品分类包含商品
                            'class_list' => $function_status['IS_CLASSIFY_RECOMMEND'] ? self::getClassify($goodsClassify, 3) : [],
                            'theme' => self::getAd($adv, 13, false),    // 主题广告
                            'limit' => [   // 限时抢购
                                'time' => $cur = $limitInterval->getCurrent(),      // 限时抢购当前时间段
                                'list' => $function_status['IS_LIMIT'] ? self::getLimit($limit, $cur['limit_interval_id'], 4) : [],
                            ]
                        ];
                    }
                    break;
                case 2:     //单多新版2
                    if (self::$oneOrMore) {
                        // 多店
                    } else {
                        // 单店
                        $extra = [
                            // 推荐商品分类包含商品
                            'class_list' => $function_status['IS_CLASSIFY_RECOMMEND'] ? self::getClassify($goodsClassify, 7) : [],
                            'new_list' => self::getNewList($goodsModel, 0), // 新品上市[新上架的商品]
                            'big_list' => self::getNewList($goodsModel, 1), // 大牌推荐[品牌甄选销量最高]
                            'word_list' => self::getNewList($goodsModel, 2),// 今日爆款[优品推荐销量最高]
                            'season_cate' => self::seasonCate(),    //应季分类
                            'limit' => [
                                'time' => $cur = $limitInterval->getCurrent(),      // 限时抢购当前时间段
                                'list' => $function_status['IS_LIMIT'] ? self::getLimit($limit, $cur['limit_interval_id'], 8) : [],
                            ]
                        ];
                    }
                    break;
            }
        } else {
            // 旧版多店/单店首页
            $extra = [
                // 推荐商品分类包含商品
                'class_list' => $function_status['IS_CLASSIFY_RECOMMEND'] ? self::getClassify($goodsClassify, 7) : [],
                'limit' => [
                    'time' => $cur = $limitInterval->getCurrent(),      // 限时抢购当前时间段
                    'list' => $function_status['IS_LIMIT'] ? self::getLimit($limit, $cur['limit_interval_id'], 8) : [],
                ]
            ];
        }
        if (!empty($extra)) {
            $result = array_merge($result, $extra);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $result,
        ], true);
    }
    
    /**
     * 关闭新人礼包
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function setGiftClose(RSACrypt $crypt)
    {
        $arr = Cache::store('file')->get('close_gift_list', []);
        $arr[] = request(0)->mid;
        $arr = array_unique($arr);
        Cache::store('file')->set('close_gift_list', $arr);
        return $crypt->response([
            'code' => 0,
            'message' => '操作成功',
        ], true);
    }
    
    /**
     * 增加广告浏览量统计
     * @param RSACrypt $crypt
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function adBrowseInc(RSACrypt $crypt,
                                Adv $adv)
    {
        $args = $crypt->request();
        $args['member_id'] = request()->mid ?? '';
        $bs = Request::ip();
        if ($args['member_id']) {
            $bs = $args['member_id'];
        }
        $flag = Cache::store('file')->get($bs . '_' . $args['adv_id'], null);
        if (array_key_exists('adv_id', $args) && $args['adv_id'] && is_null($flag)) {
            $ret = $adv
                ->allowField(true)
                ->isUpdate(true)
                ->save([
                    'adv_id' => $args['adv_id'],
                    'hits' => Db::raw('hits + 1'),
                ]);
            if ($ret) {
                Cache::store('file')->set($bs . '_' . $args['adv_id'], time());
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '统计成功',
        ], true);
    }
    
    /**
     * 当前限时抢购列表(局部刷新接口)
     * @param RSACrypt $crypt
     * @param LimitInterval $limitInterval
     * @param Limit $limit
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function curLimitList(RSACrypt $crypt,
                                 LimitInterval $limitInterval,
                                 Limit $limit)
    {
        $param = $crypt->request();
        $function_status = Env::get();
        if (!array_key_exists('type', $param)) {
            $param['type'] = 0;
        }
        switch ($param['type']) {
            case 1:
                // 多店铺新首页1
                $lis = $limitInterval->getStage();
                if (!empty($lis)) {
                    array_walk($lis, function (&$x) use ($limit) {
                        $x['count_down'] = strtotime(date('Y-m-d ' . $x['end_time'] . ':00:00')) - time();
                        $x['list'] = self::getLimit($limit, $x['limit_interval_id'], 3);
                    });
                }
                $data = $function_status['IS_LIMIT'] ? $lis : [];
                break;
            case 2:
                // 单店铺新首页1
                $data = [
                    'time' => $cur = $limitInterval->getCurrent(),          // 限时抢购时间段
                    'list' => $function_status['IS_LIMIT'] ? self::getLimit($limit, $cur['limit_interval_id'], 4) : [],
                ];
                break;
            default:
                // 单店铺新首页2
                $data = [
                    'time' => $cur = $limitInterval->getCurrent(),          // 限时抢购时间段
                    'list' => $function_status['IS_LIMIT'] ? self::getLimit($limit, $cur['limit_interval_id'], 8) : [],
                ];
                break;
        }
        
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $data,
        ], true);
        
    }
    
    /**
     * 推荐商品分类包含商品
     * @param GoodsClassify $goodsClassify
     * @param $num
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getClassify(GoodsClassify $goodsClassify,
                                   $num)
    {
        $data = $goodsClassify
            ->with('adv')
            ->where([
                ['is_recommend', '=', 1],
                ['status', '=', 1],
                ['parent_id', '=', 0],
            ])
            ->field('goods_classify_id,title,adv_id')
            ->order('sort', 'desc')
            ->select();
        $getData = [];
        if (!$data->isEmpty()) {
            foreach ($data as $_data) {
                $_data->limit = $num;
                $_data->append(['goods_list']);
                if (!empty($_data['goods_list']->toArray()) && count($getData) < 3 && !is_null($_data['adv'])) {
                    array_push($getData, $_data);
                }
            }
        }
        return $getData;
    }
    
    /**
     * 获取好物推荐数据
     * @param Goods $goods
     * @param $num
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getPopularity(Goods $goods, $num)
    {
        $notActivty = 'g.is_group = 0 and g.is_bargain = 0 and g.is_limit = 0 and ';        // 非活动条件
        $where = self::$goodsAuthSql . ' and g.is_popularity = 1';
        $data = $goods
            ->alias('g')
            ->join('store store', 'store.store_id = g.store_id', 'right')
            ->join('goods_classify gc', 'gc.goods_classify_id = g.goods_classify_id')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->whereRaw($where)
            ->field('g.store_id,goods_id,g.goods_name,g.file,g.sales_volume,gc.title,s.store_name,s.shop,
            g.is_limit,g.is_group,g.is_bargain,g.is_vip,g.group_price,g.cut_price,g.time_limit_price,g.shop_price')
            ->limit($num)
            ->orderRand()
            ->select();
        return $data;
    }
    
    /**
     * 获取当前时间段限购商品
     * @param Limit $limit
     * @param $limit_interval_id
     * @param $num
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getLimit(Limit $limit,
                                $limit_interval_id,
                                $num)
    {
        $where = [
            ['g.is_putaway', '=', 1],
            ['g.is_limit', '=', 1],
            ['l.status', '=', 1],
            ['l.up_shelf_time', '<=', date('Y-m-d')],
            ['l.down_shelf_time', '>=', date('Y-m-d')],
            ['l.exchange_num', '>', 0],
        ];
        $base = $limit
            ->alias('l')
            ->whereRaw('find_in_set(' . $limit_interval_id . ',l.interval_id)')
            ->where($where)
            ->join('goods g', 'l.goods_id = g.goods_id and ' . self::$goodsAuthSql);
        // 单店铺模式
        if (!self::$oneOrMore) {
            $base
                ->join('store s', 's.store_id = g.store_id and s.store_id = ' . self::$oneStoreId . ' and ' . self::$storeAuthSql);
        } else {
            $base->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql);
        }
        $data = $base
            ->field('g.goods_id,goods_name,file,shop_price,time_limit_price,
            available_sale,exchange_num')
            ->limit($num)
            ->order(['l.create_time' => 'desc'])
            ->select();
        return $data->isEmpty() ? [] : $data->toArray();
    }
    
    /**
     * 获取新品上市
     * @param Goods $goods
     * @param $type 0 新品上市 1大牌推荐 2今日爆款
     * @param $num int 商品数量
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getNewList(Goods $goods, $type, $num = 1)
    {
        $where = [
            ['review_status', '=', 1],
            ['is_putaway', '=', 1],
            ['goods_number', '>', 0],
        ];
        switch ($type) {
            case 1:
                // 品牌
                $brandData = $goods
                    ->alias('g')
                    ->where($where)
                    ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
                    ->field('count(g.goods_id) as bc,g.brand_id')
                    ->having('bc >= 2')
                    ->group('g.brand_id')
                    ->orderRand()
                    ->find();
                if (!is_null($brandData)) {
                    array_push($where, ['brand_id', '=', $brandData['brand_id']]);
                }
                break;
            case 2:
                // 分类
                $classData = $goods
                    ->where($where)
                    ->alias('g')
                    ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
                    ->field('count(goods_id) as cc,g.goods_classify_id')
                    ->having('cc >= 2')
                    ->group('g.goods_classify_id')
                    ->orderRand()
                    ->find();
                if (!is_null($classData)) {
                    array_push($where, ['goods_classify_id', '=', $classData['goods_classify_id']]);
                }
                break;
        }
        $data = $goods
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->where($where)
            ->field('g.goods_id,g.file')
            ->order([['g.create_time', 'g.sales_volume', 'g.sales_volume'][$type] => 'desc', 'g.sort' => 'desc']);
        $res = $num > 1 ? $data->limit($num)->select() : ($data->find() ?: json([]));
        return $res;
    }
    
    /**
     * 查询应季分类
     * @param $num int 限制数量
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function seasonCate($num = 8)
    {
        // 查询一级分类
        $data = (new GoodsClassify())
            ->where([
                ['status', '=', 1],
                ['parent_id', '=', 0],
                ['count', '=', 1],
            ])
            ->field('goods_classify_id,title,web_file')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
            ->limit($num)
            ->select();
        return $data;
    }
    
    /**
     * 热点文章
     * @param Article $article
     * @param int $num
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getHotList(Article $article, $num = 6)
    {
        $where = [
            ['status', '=', 1],
            ['article_classify_id', '=', 2]
        ];
        // 单店铺模式
        if (!self::$oneOrMore) {
            array_push($where, ['store_id', ['=', self::$oneStoreId], ['=', 0], 'or']);
        }
        $data = $article
            ->where($where)
            ->field('article_id,title')
            ->order(['create_time' => 'desc'])
            ->limit($num)
            ->select();
        return $data;
    }
    
    /**
     * 获取banner和theme
     * @param Adv $adv
     * @param int $type
     * @param bool $all
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getAd(Adv $adv, $type, $all = true)
    {
        $where = [
            ['adv_position_id', '=', $type],
            ['status', '=', 1],
            ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
            ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
        ];
        $Base = $adv
            ->where($where)
            ->field('adv_id,title,type,content,file')
            ->order(['sort' => 'desc']);
        $data = $all ? $Base->select() : ($Base->find()) ?: json([]);
        return $data;
    }
    
    /**
     * 获取首页导航
     * @param $funcStatus
     * @param Icon $icon
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getNav($funcStatus, Icon $icon)
    {
        $where = [
            ['is_show', '=', 1],
        ];
        $notShow = [];
        if (!empty($funcStatus)) {
            foreach ($funcStatus as $_k => $_f) {
                if (stripos($_k, 'is_') === 0 && !$_f) {
                    $name = substr(strtolower($_k), 3);
                    array_push($notShow, $name);
                    if ($name == 'red_packet') {
                        // 红包不显示,则邀请有礼也不显示
                        array_push($notShow, 'invit');
                    }
                }
            }
        }
        if (!empty($notShow)) {
            array_push($where, ['name', 'not in', implode(',', $notShow)]);
        }
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        array_push($where, ['name', '<>', Env::get('distribution_status') ? 'vip' : 'distribution']);
        if (!self::$oneOrMore) {
            // 单店铺下禁用商家入驻
            array_push($where, ['name', '<>', 'merchant'], ['name', '<>', 'brand']);
        }
        // 获取首页图标
        $nav = $icon
            ->where($where)
            ->field('type,img,name,title')
            ->order(['type' => 'asc', 'sort' => 'asc'])
            ->limit(15)
            ->select();
        return $nav;
    }
    
    /**
     * 赠送优惠券
     * @param RSACrypt $crypt
     * @param Coupon $coupon
     * @param Article $article
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function coupon_list(RSACrypt $crypt,
                                Coupon $coupon,
                                Article $article)
    {
        $result = $coupon
            ->where([
                ['is_gift', '=', 1],
                ['status', '=', 1],
                ['receive_start_time', '<=', date('Y-m-d')],
                ['receive_end_time', '>=', date('Y-m-d')],
                ['exchange_num', '>', 0],
            ])
            ->field('actual_price,full_subtraction_price')
            ->order('actual_price', 'asc')
            ->select();
        $content = $article
            ->where([
                ['article_id', '=', 18],
            ])
            ->value('web_content');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'content' => str_replace('class="tools"', 'class="tools" hidden', $content),
        ], true);
    }
    
    /**
     * 领取优惠券
     * @param RSACrypt $crypt
     * @param Coupon $coupon
     * @param Member $member
     * @param MemberCoupon $memberCoupon
     * @param Inform $inform
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function get_coupon(RSACrypt $crypt,
                               Coupon $coupon,
                               Member $member,
                               MemberCoupon $memberCoupon,
                               Inform $inform)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $info = $member
            ->where('member_id', $param['member_id'])
            ->field('member_id,is_gift,web_open_id,subscribe_time,micro_open_id,phone')
            ->find();
        if ($info['is_gift'] == 1) {
            // 您已经成功领取,请勿重复领取
            return $crypt->response([
                'code' => -1,
                'message' => '您已经成功领取,请勿重复领取',
            ]);
        }
        // 关闭优惠券功能
        if (Env::get('is_coupon', 1) == 0) {
            // 优惠券功能关闭，暂时无法领取/兑换
            return $crypt->response([
                'code' => -2,
                'message' => '优惠券功能关闭，暂时无法领取/兑换',
            ], true);
        }
        $result = $coupon
            ->where([
                ['is_gift', '=', 1],
                ['status', '=', 1],
                ['receive_start_time', '<=', date('Y-m-d')],
                ['receive_end_time', '>=', date('Y-m-d')],
                ['exchange_num', '>', 0],
            ])
            ->field('delete_time', true)
            ->order(['actual_price' => 'asc'])
            ->select();
        if (!$result->isEmpty()) {
            $result = $result->toArray();
            foreach ($result as $key => &$value) {
                $value['member_id'] = $param['member_id'];
                $value['store_id'] = $value['classify_str'];
                unset($result[$key]['status']);
            }
//             dump($result);
            Db::startTrans();
            // 批量插入
            $mc = $memberCoupon
                ->allowField(true)
                ->isUpdate(false)
                ->saveAll($result);
            $inform->coupon_inform(0, '15', $info->toArray(), 0, implode(',', $mc->column('member_coupon_id')));
            // 改变状态
            $member
                ->allowField(true)
                ->isUpdate(true)
                ->save([
                    'member_id' => $param['member_id'],
                    'is_gift' => 1,
                ]);
            Db::commit();
        }
        return $crypt->response([
            'code' => 0,
            'message' => '领取成功',
        ], true);
    }
}