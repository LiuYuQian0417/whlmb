<?php
declare(strict_types = 1);

namespace app\computer\controller\home;

use app\computer\model\Adv;
use app\computer\model\Article;
use app\computer\model\Coupon;
use app\computer\model\GroupClassify;
use app\computer\model\GroupGoods;
use app\computer\model\Goods;
use app\computer\model\GoodsClassify;
use app\computer\model\Limit;
use app\computer\model\LimitInterval;
use app\computer\model\Member;
use app\computer\model\MemberCoupon;
use app\computer\model\Store;
use app\common\service\Inform;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;

/**
 * 首页
 * Class Home
 * @package app\computer\controller\home
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
     * @param GroupClassify $groupClassify
     * @param GroupGoods $groupGoods
     * @param Coupon $coupon
     *  * * * * * * * * * * * @param Store $store
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
                          GroupClassify $groupClassify,
                          GroupGoods $groupGoods,
                          Coupon $coupon,
                          Store $store)
    {
        $param = $crypt->request();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? '';
        $function_status = self::$functionStatus;
        
        $result = [
            // banner
            'banner' => self::getAd($adv, Config::get('pc_config.index_banner_id', '')),
            // 主推广告
            'theme' => self::getAd($adv, Config::get('pc_config.index_theme_id', ''), false),
            // 热点新闻
            'hot_list' => self::getHotList($article, 5),
            'limit' => $function_status['is_limit'] == 1 ? [
                'time' => $cur = $limitInterval->getCurrent(),          // 限时抢购时间段
                'list' => $function_status['is_limit'] ? self::getLimit($limit, $cur['limit_interval_id'], 3) : [],
            ] : [],
            //排行榜
            'ranking_list' => $function_status['is_ranking'] == 1 ? self::getRankingList(
                $goodsClassify,
                $goodsModel
            ) : [],
            //拼团
            'group_goods' => $function_status['is_group'] == 1 ? self::getGroupGoods(
                $groupClassify,
                $groupGoods
            ) : [],
            //优惠券
            'coupon' => $function_status['is_coupon'] == 1 ? self::getCoupon(
                $goodsClassify,
                $coupon
            ) : [],
            // 好物推荐
            'popularity' => $function_status['is_goods_recommend'] ? self::getPopularity(
                $goodsModel,
                $function_status,
                7
            ) : [],
            'class_list' => $function_status['is_classify_recommend'] ? self::getClassify($goodsClassify) : [],
            // 为你推荐 小随机 后期需要改成 用户习惯模式
            'recommend_list' => recommend_list($goodsModel, 15, $param['member_id']),
            'info' => [
                'in_state' => $store->inStore($param['member_id']),     // 店铺入驻状态
            ],
            //商品分类
            'goods_classify' => $goodsClassify->getTree(),
        ];
        
        //是否显示新人礼包 1不显示 2 显示  礼包开关  未登录 或是
        $_is_gift_bag = 1;
        if (env('POPUP_ADV_STATUS') == 1 and (empty($param['member_id']) or (false === array_search(
                        $param['member_id'],
                        Cache::store('file')->get('close_gift_list') ?: []
                    )))) {
            $_is_gift_bag = 2;
        }
        
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $config = Env::get();
        $navigation_status = $config['NAVIGATION_STATUS'];
        return $this->fetch(
            '',
            [
                'result' => $result,
                'is_gift_bag' => $_is_gift_bag,
                'navigation_status' => $navigation_status,
            ]
        );
    }
    
    /**
     * 当前限时抢购列表(局部刷新接口)
     * @param RSACrypt $crypt
     * @param LimitInterval $limitInterval
     * @param Limit $limit
     * @return mixed
     */
    public function curLimitList(RSACrypt $crypt,
                                 LimitInterval $limitInterval,
                                 Limit $limit)
    {
        try {
            $data = [
                'time' => $cur = $limitInterval->getCurrent(),          // 限时抢购时间段
                'list' => self::$functionStatus['is_limit'] == 0 ? [] : self::getLimit(
                    $limit,
                    $cur['limit_interval_id'],
                    3
                ),
            ];
            if (isset($data['time']['end_time'])) {
                $data['time']['end_time'] = date('Y-m-d ' . $data['time']['end_time'] . ':00:00');
            }
            return $crypt->response(
                [
                    'code' => 0,
                    'message' => '查询成功',
                    'result' => $data,
                ],
                true
            );
        } catch (\Exception $e) {
            return $crypt->response(
                [
                    'code' => -100,
                    'message' => self::$errMsg ?: $e->getMessage(),
                ],
                true
            );
        }
    }
    
    /**
     * 推荐商品分类包含商品
     * @param GoodsClassify $goodsClassify
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getClassify(GoodsClassify $goodsClassify)
    {
        $data = $goodsClassify
            ->with('PcAdv')
            ->where(
                [
                    ['pc_is_recommend', '=', 1],
                    ['status', '=', 1],
                    ['parent_id', '=', 0],
                ]
            )
            ->field('goods_classify_id,pc_adv_id')
            ->append(['goods_list'])
            ->order('sort', 'desc')
            ->select();
        return $data;
    }
    
    /**
     * 获得排行榜数据
     * @param $goodsClassify
     * @param $goodsModel
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getRankingList(GoodsClassify $goodsClassify, goods $goodsModel)
    {
        $ranking_list_classify = $goodsClassify
            ->where(['parent_id' => 0, 'status' => 1])
            ->field('classify_adv_id,goods_classify_id,title')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
            ->limit(6)
            ->select();
        $where = [
            ['goods_number', '>', 0],
            ['review_status', '=', 1],
            ['is_putaway', '=', 1],
            [
                'goods_classify_id',
                'in',
                implode(
                    ',',
                    array_column(
                        getParCate(
                            $ranking_list_classify[0]['goods_classify_id'] ?? 0,
                            $goodsClassify,
                            0
                        ),
                        'goods_classify_id'
                    )
                ) ?: $ranking_list_classify[0]['goods_classify_id'] ?? 0,
            ],
        ];
        //判断当前功能开关状态从新生成查询条件
        $where = self::goods_where($where, 'goods');
        return [
            'classify' => $ranking_list_classify,
            'goods' => $goodsModel->alias('goods')
                ->join('store store', 'store.store_id = goods.store_id and ' . self::store_auth_sql('store'))
                ->field(
                    'goods.goods_id,goods_name,shop_price,goods.sales_volume,freight_status,shop,goods.file,freight_status,shop,is_group,is_bargain,freight_status,shop,group_price,cut_price,group_num,is_vip,attr_type_id,file as cart_file,goods_number,goods.store_id,is_limit,time_limit_price'
                )
                ->where($where)
                ->append(['attribute_list', 'limit_state'])
                ->order(['goods.sales_volume' => 'desc'])->limit(4)->select(),
        ];
    }
    
    
    /**
     * 接口刷新获得排行榜数据
     * @param Request $request
     * @param GoodsClassify $goodsClassify
     * @param Goods $goodsModel
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function AjaxRankingList(Request $request, GoodsClassify $goodsClassify, goods $goodsModel)
    {
        if (self::$functionStatus['is_ranking'] == 0) {
            $data = [];
        } else {
            $condition = [
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1],
                [
                    'goods_classify_id',
                    'in',
                    implode(
                        ',',
                        array_column(
                            getParCate($request::post('goods_classify_id', 0), $goodsClassify, 0),
                            'goods_classify_id'
                        )
                    ) ?: $request::post('goods_classify_id', 0),
                ],
            ];
            //判断当前功能开关状态从新生成查询条件
            $condition = self::goods_where($condition, 'goods');
            $data = $goodsModel->alias('goods')
                ->join('store store', 'store.store_id = goods.store_id and ' . self::store_auth_sql('store'))
                ->field(
                    'goods.goods_id,goods_name,shop_price,freight_status,shop,goods.file,freight_status,shop,is_group,is_bargain,freight_status,shop,group_price,cut_price,group_num,is_vip,is_city,attr_type_id,file,is_limit,time_limit_price'
                )
                ->where($condition)
                ->append(['goods_price', 'limit_state'])
                ->order(['goods.sales_volume' => 'desc'])
                ->limit(4)
                ->select();
        }
        // 数据
        return [
            'code' => 0,
            'data' => $data,
        ];
    }
    
    /**
     * 获得拼团商品数据
     * @param GroupClassify $groupClassify
     * @param GroupGoods $groupGoods
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getGroupGoods(GroupClassify $groupClassify, GroupGoods $groupGoods)
    {
        $group_goods_classify = $groupClassify
            ->where('parent_id', '=', 0)
            ->field('group_classify_id,title')
            ->order('create_time', 'asc')
            ->limit(6)
            ->select();
        if (empty($group_goods_classify)) {
            $result = [];
        } else {
            $condition = [
                ['gg.up_shelf_time', '<=', date('Y-m-d H:i:s')],
                ['gg.down_shelf_time', '>=', date('Y-m-d H:i:s')],
                ['gg.status', '=', 1],
                ['g.is_putaway', '=', 1],
                ['g.review_status', '=', 1],
            ];
            $parent_id = $groupClassify
                ->where('parent_id', $group_goods_classify[0]['group_classify_id'] ?? 0)
                ->column('group_classify_id');
            $condition[] =
                [
                    'gg.group_classify_id',
                    'in',
                    $parent_id ? implode(
                            ',',
                            $parent_id
                        ) . ',' : '' . $group_goods_classify[0]['group_classify_id'] ?? 0,
                ];
            //判断当前功能开关状态从新生成查询条件
            $condition = self::goods_where($condition, 'g');
            $result = $groupGoods
                ->alias('gg')
                ->join('goods g', 'g.goods_id = gg.goods_id')
                ->join('store store', 'store.store_id = g.store_id and ' . self::store_auth_sql('store'))
                ->where($condition)
                ->field('g.goods_id,gg.group_num,g.goods_name,g.group_price,g.shop_price,g.file')
                ->order('gg.create_time', 'desc')
                ->limit(4)
                ->select();
        }
        return [
            'classify' => $group_goods_classify,
            'goods' => $result,
        ];
    }
    
    
    /**
     * 接口刷新获得拼团商品数据
     * @param GroupClassify $groupClassify
     * @param Request $request
     * @param GroupGoods $groupGoods
     * @return array
     * @throws \OSS\Core\OssException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function AjaxGetGroupGoods(GroupClassify $groupClassify, Request $request, GroupGoods $groupGoods)
    {
        $parent_id = $groupClassify
            ->where('parent_id', $request::post('group_classify_id'))
            ->column('group_classify_id');
        if (self::$functionStatus['is_group'] == 0 || empty($parent_id)) {
            $result = [];
        } else {
            $condition = [
                ['gg.up_shelf_time', '<=', date('Y-m-d H:i:s')],
                ['gg.down_shelf_time', '>=', date('Y-m-d H:i:s')],
                ['gg.status', '=', 1],
                ['g.is_putaway', '=', 1],
                ['g.review_status', '=', 1],
            ];
            //判断当前功能开关状态从新生成查询条件
            $condition = self::goods_where($condition, 'g');
            
            $condition[] =
                [
                    'gg.group_classify_id',
                    'in',
                    $parent_id ? implode(
                            ',',
                            $parent_id
                        ) . ',' : '' . $request::post('group_classify_id'),
                ];
            $result = $groupGoods
                ->alias('gg')
                ->join('goods g', 'g.goods_id = gg.goods_id')
                ->join('store store', 'store.store_id = g.store_id and ' . self::store_auth_sql('store'))
                ->where($condition)
                ->field('g.goods_id,gg.group_num,g.goods_name,g.group_price,g.shop_price,g.file')
                ->order('gg.create_time', 'desc')
                ->limit(4)
                ->select();
        }
        return [
            'code' => 0,
            'data' => $result,
        ];
    }
    
    
    /**
     * 获取优惠券
     * @param GoodsClassify $goodsClassify
     * @param Coupon $coupon
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getCoupon(GoodsClassify $goodsClassify, Coupon $coupon)
    {
        $goods_classify = $goodsClassify
            ->where(['parent_id' => 0, 'status' => 1])
            ->field('classify_adv_id,goods_classify_id,title,web_file')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
            ->limit(6)
            ->select();
        $condition = [
            ['status', '=', 1],
            ['type', '=', 0], //店铺优惠券
            ['is_gift', '=', 0],
            ['modality', '=', 0],
            ['receive_end_time', '>=', date('Y-m-d')],
            ['receive_start_time', '<=', date('Y-m-d')],
            ['exchange_num', '>', 0],   //优惠券剩余数量
            ['is_integral_exchage', '=', 0],            // 是否参与积分兑换
        ];
        //        后台隐藏了推荐 只展示店铺优惠券
        //        if (!empty($goods_classify[0]['goods_classify_id']))
        //        {
        //            $condition[] = ['classify_str', 'in', $goods_classify[0]['goods_classify_id']];
        //        } else
        //        {
        //            $condition[] = ['is_recommend', '=', 1];
        //        }
        //如果会员登录  优惠券去除用户不能领的
        if (Session::get('member_info')['member_id'] ?? null) {
            $MemberCouponCount = (new MemberCoupon())
                ->alias('m_c')
                ->where(
                    [
                        ['m_c.member_id', '=', Session::get('member_info')['member_id']],
                        ['m_c.coupon_id', '=', 'c.coupon_id'],
                    ]
                )
                ->fetchSql()->count();
            $in_coupon_id = $coupon
                ->alias('c')
                ->field("c.coupon_id,limit_num,({$MemberCouponCount}) as count")
                ->where($condition)
                ->having('limit_num =0 or (limit_num > 0 and limit_num > count)')
                ->select()
                ->toArray();
            $condition[] = ['coupon_id', 'in', array_column($in_coupon_id, 'coupon_id')];
        }
        $result = $coupon
            ->where($condition)
            ->field(
                'classify_str,coupon_id,file,title,actual_price,full_subtraction_price,classify_str,type'
            )
            ->group('coupon_id')
            ->append(['member_state'])
            ->order(['create_time' => 'desc'])
            ->limit(4)
            ->select();
        return [
            'classify' => $goods_classify,
            'coupon' => $result,
        ];
    }
    
    /**
     * 接口刷新获取优惠券
     * @param Request $request
     * @param Coupon $coupon
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function AjaxGetCoupon(Request $request, Coupon $coupon)
    {
        if (self::$functionStatus['is_coupon']) {
            $data = [];
        } else {
            $condition = [
                ['status', '=', 1],
                ['is_gift', '=', 0],
                ['modality', '=', 0],
                ['receive_end_time', '>=', date('Y-m-d')],
                ['end_time', '>=', date('Y-m-d')],
                ['start_time', '<=', date('Y-m-d', strtotime("+1 day"))],
                ['receive_start_time', '<=', date('Y-m-d', strtotime("+1 day"))],
                ['is_integral_exchage', '=', 0],            // 是否参与积分兑换
            ];
            if ($request::post('goods_classify_id', null)) {
                $condition[] = ['classify_str', 'in', $request::post('goods_classify_id')];
            } else {
                $condition[] = ['is_recommend', '=', 1];
            }
            //如果会员登录  优惠券去除用户不能领的
            if (Session::get('member_info')['member_id'] ?? null) {
                $MemberCouponCount = (new MemberCoupon())
                    ->alias('m_c')
                    ->where(
                        'm_c.member_id = ' . Session::get(
                            'member_info'
                        )['member_id'] . ' and m_c.coupon_id = c.coupon_id'
                    )
                    ->fetchSql()->count();
                $in_coupon_id = $coupon->alias('c')->field(
                    "c.coupon_id,limit_num,({$MemberCouponCount}) as count"
                )->having(
                    'limit_num=0 or (limit_num > 0 and limit_num > count)'
                )->select()->toArray();
                $condition[] = ['coupon_id', 'in', array_column($in_coupon_id, 'coupon_id')];
            }
            $data = $coupon
                ->where($condition)
                ->field(
                    'classify_str,coupon_id,file,title,actual_price,full_subtraction_price,classify_str,type'
                )
                ->group('coupon_id')
                ->order(['create_time' => 'desc'])
                ->limit(4)
                ->select();
        }
        return [
            'code' => 0,
            'data' => $data,
        ];
    }
    
    
    /**
     * 获取好物推荐数据
     * @param Goods $goods
     * @param $function_status
     * @param $num
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getPopularity(Goods $goods, $function_status, $num)
    {
        //判断当前功能开关状态从新生成查询条件
        $function_where = self::goods_where([], 'a');
        $data ['goods'] = $goods
            ->alias('a')
            ->join('store store', 'store.store_id = a.store_id and ' . self::store_auth_sql('store'))
            ->join('goods_classify gc', 'gc.goods_classify_id = a.goods_classify_id')
            ->with('store')
            ->where(
                [
                    ['is_popularity', '=', 1],
                    ['is_putaway', '=', 1],
                ]
            )
            ->where('store.delete_time', 'null')
            ->where($function_where)
            ->field('a.shop_price,a.store_id,market_price,goods_id,a.goods_name,a.file,a.sales_volume,gc.title')
            ->limit($num)
            ->select();
        $data['adv_file'] = Adv::get(Config::get('index_good_thing_id', ''))->file ?? '';
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
    protected function getLimit(Limit $limit, $limit_interval_id, $num)
    {
        $condition = [
            //            ['interval_id', '=', $limit_interval_id],
            ['is_putaway', '=', 1],
            ['l.status', '=', 1],
            ['up_shelf_time', '<=', date('Y-m-d')],
            ['down_shelf_time', '>=', date('Y-m-d')],
            ['l.exchange_num', '>', 0],
        ];
        //判断当前功能开关状态从新生成查询条件
        $condition = self::goods_where($condition, 'g');
        $data = $limit->alias('l')
            ->join('goods g', 'l.goods_id = g.goods_id and g.is_limit = 1')
            ->join('store store', 'store.store_id = g.store_id and ' . self::store_auth_sql('store'))
            ->whereRaw('find_in_set(' . $limit_interval_id . ',l.interval_id)')
            ->where($condition)
            ->field(
                'g.goods_id,goods_name,file,shop_price,time_limit_price,
            available_sale,exchange_num'
            )
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
        $data = $article
            ->where(
                [
                    ['status', '=', 1],
                    ['article_classify_id', '=', 2],
                ]
            )
            ->field('article_id,title')
            ->order('create_time', 'desc')
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
        $Base = $adv
            ->where(
                [
                    ['adv_position_id', '=', $type],
                    ['status', '=', 1],
                    ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ['client', '=', 1],
                ]
            )
            ->field('adv_id,title,type,content,file')
            ->order('sort', 'desc');
        $data = $all ? $Base->select() : $Base->find();
        return $data;
    }
    
    /**
     * 赠送优惠券
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Coupon $coupon
     * @param Article $article
     * @return mixed
     */
    public function coupon_list(Request $request, RSACrypt $crypt, Coupon $coupon, Article $article)
    {
        if ($request::isPost()) {
            try {
                
                $result = $coupon
                    ->where(
                        [
                            ['is_gift', '=', 1],
                            ['status', '=', 1],
                            ['receive_start_time', '<=', date('Y-m-d')],
                            ['receive_end_time', '>=', date('Y-m-d')],
                            ['exchange_num', '>', 0],
                        ]
                    )
                    ->field('actual_price,full_subtraction_price')
                    ->order('actual_price', 'asc')
                    ->select();
                
                $content = $article->where('article_id', 18)->value('web_content');
                
                return $crypt->response(
                    [
                        'code' => 0,
                        'result' => $result,
                        'content' => str_replace('class="tools"', 'class="tools" hidden', $content),
                    ]
                );
                
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
    
    /**
     * 领取优惠券
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Coupon $coupon
     * @param Member $member
     * @param MemberCoupon $memberCoupon
     * @param Inform $inform
     * @return mixed
     */
    public function get_coupon(Request $request,
                               RSACrypt $crypt,
                               Coupon $coupon,
                               Member $member,
                               MemberCoupon $memberCoupon,
                               Inform $inform)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                $is_gift = $member
                    ->where('member_id', $param['member_id'])
                    ->value('is_gift');
                if ($is_gift == 1) {
                    // 您已经成功领取,请勿重复领取
                    return $crypt->response(['code' => -100, 'message' => config('message.')[-13][-1]]);
                }
                // 关闭优惠券功能
                if (Env::get('is_coupon') == 0) {
                    // 优惠券功能关闭，暂时无法领取/兑换
                    return $crypt->response(['code' => -100, 'message' => config('message.')[-13][-2]]);
                }
                $result = $coupon
                    ->where(
                        [
                            ['is_gift', '=', 1],
                            ['status', '=', 1],
                            ['receive_start_time', '<=', date('Y-m-d')],
                            ['receive_end_time', '>=', date('Y-m-d')],
                            ['exchange_num', '>', 0],
                        ]
                    )
                    ->field('coupon_id,actual_price,full_subtraction_price,title,type,end_time,classify_str')
                    ->order(['actual_price' => 'asc'])
                    ->select()
                    ->toArray();
                $array = [];
                Db::startTrans();
                foreach ($result as $key => $value) {
                    $array[$key] = $value;
                    $array[$key]['member_id'] = $param['member_id'];
                    $array[$key]['goods_classify_id'] = $value['classify_str'];
                    $array[$key]['start_time'] = date('Y-m-d');
                    // 推送
                    $inform->coupon_inform(0, 'coupon', $param['member_id'], 0, $value['coupon_id']);
                }
                // 批量插入
                $memberCoupon->allowField(true)->saveAll($array);
                // 改变状态
                $member
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save(
                        [
                            'member_id' => $param['member_id'],
                            'is_gift' => 1,
                        ]
                    );
                Db::commit();
                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);
            } catch (\Exception $e) {
                Db::rollback();
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
    
}