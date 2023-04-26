<?php
/**
 * Created by PhpStorm.
 * User: hhd
 * Date: 2019/2/22 0022
 * Time: 16:23
 */

namespace app\computer\controller\goods;

use app\computer\controller\BaseController;
use app\computer\model\Cart;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use app\computer\model\Store;
use app\computer\model\GoodsReductionNotic;
use app\computer\model\Goods as GoodsModel;
use app\computer\model\
{Article,
    Coupon,
    GoodsClassify,
    GoodsEvaluate,
    GoodsAttr,
    GoodsParameter,
    CollectGoods,
    CollectStore,
    RecordGoods,
    Limit,
    OrderGoods,
    LimitInterval,
    Distribution,
    StoreGoodsClassify,
    Adv,
    Products,
    Take,
    MemberAddress,
    Area,
    MemberCoupon};
use think\facade\Session;

class Goods extends BaseController
{
    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => 'view,index,choiceness_list'],
    ];

    /**
     * 列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, RSACrypt $crypt, GoodsModel $goodsModel, GoodsClassify $goodsClassify)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request()->mid ?? '';

                // 条件
                // $condition[] = ['goods_number', '>', 0];
                $condition[] = ['review_status', '=', 1];
                $condition[] = ['is_putaway', '=', 1];


                // 物流状态
                if (!empty($param['freight_status']))
                {
                    $condition[] = ['freight_status', 'in', $param['freight_status']];
                }
                // 是否有货
                if (!empty($param['goods_number']))
                {
                    $condition[] = ['goods_number', '>', $param['goods_number']];
                }
                // 个人、企业类型
                if (!empty($param['shop']))
                {
                    $condition[] = ['shop', 'in', $param['shop']];
                }
                if (!empty($param['keyword']))
                {
                    $condition[] = ['goods_name|keyword', 'like', '%' . $param['keyword'] . '%'];
                    searchSet($param['keyword']);
                }
                // 关键词查询

                // 是否免运费
                if (!empty($param['is_freight']))
                {
                    $condition[] = ['freight_status', '=', $param['is_freight']];
                }
                // 品牌
                if (!empty($param['brand_id']))
                {
                    $condition[] = ['brand_id', '=', $param['brand_id']];
                }
                // 分类
                if (!empty($param['goods_classify_id']))
                {
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
                // 最低价查询
                if (!empty($param['minimum_price']))
                {
                    $condition[] = ['shop_price', '>=', $param['minimum_price']];
                }
                // 最高价间查询
                if (!empty($param['top_price']))
                {
                    $condition[] = ['shop_price', '<=', $param['top_price']];
                }
                // 价格区间查询
                if ((!empty($param['minimum_price'])) && (!empty($param['top_price'])))
                {
                    $condition[] = ['shop_price', 'between', [$param['minimum_price'], $param['top_price']]];
                }
                // 是否为购买即成为分销商商品
                if (array_key_exists('is_distributor', $param) && $param['is_distributor'])
                {
                    $condition[] = ['is_distributor', '=', $param['is_distributor'] - 1];
                }
                // 排序
                $parameter = !empty($param['parameter']) ? $param['parameter'] : 'goods.sort';
                $rank = !empty($param['rank']) ? $param['rank'] : 'asc';
                // 数据
                $result = $goodsModel
                    ->alias('goods')
                    ->join('store store', 'store.store_id = goods.store_id and ' . self::store_auth_sql('store'))
                    ->where(self::goods_where($condition, 'goods'))
                    ->field(
                        'goods.goods_id,goods.store_id,goods_name, shop_price,shop_price as goods_price,market_price,
                                    goods.sales_volume,freight_status,shop,store_name,goods.file,is_group,
                                    is_bargain,freight_status,shop,group_price,cut_price,group_num,is_vip,is_shop,is_city,
                                    attr_type_id,file as cart_file,goods_number,is_limit,time_limit_price,
                                    is_distributor,is_distribution,' . $goodsModel->getGoodsRealPriceSql('goods')
                    )
                    ->order([$parameter => $rank, 'goods.sort' => 'desc'])
                    ->append(['attribute_list', 'limit_state'])
                    ->paginate(10, FALSE, $param);
                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }

        // 获取参数
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? 0;

        // dump($param);
        $goods_classify = $goodsClassify->getTree();
        // halt($goods_classify);
        if (empty($param['goods_classify_id']))
        {
            $param['goods_classify_id'] = $goods_classify[0]['tree_relevance'][0]['tree_relevance'][0]['goods_classify_id']??$goods_classify[0]['tree_relevance'][0]['goods_classify_id']??$goods_classify[0]['goods_classify_id'];
        }

        $classify_title = $goodsClassify
            ->alias('three')
            ->join('goods_classify two', 'three.parent_id = two.goods_classify_id','left')
            ->join('goods_classify one', 'two.parent_id = one.goods_classify_id','left')
            ->where(['three.goods_classify_id' => $param['goods_classify_id']])
            ->field('one.title as one_title,two.title as two_title,three.title as three_title')
            ->find();
        $discount = discount($param['member_id']);

        return $this->fetch(
            '',
            [
                'code'           => 0,
                'goods_classify' => $goods_classify,
                'discount'       => $discount,
                'classify_title' => $classify_title,
            ]
        );
    }


    //查询商品状态
    public function goods_status(Request $request, GoodsModel $goods)
    {
        $goods_id = $request::post('goods_id', 0);
        $is_putaway = $goods->where([['goods_id', '=', $goods_id], ['review_status', '=', 1]])->value('is_putaway', 0);
        return ['code' => $is_putaway, 'message' => $is_putaway ? '' : '该商品已下架'];
    }


    /**
     * 详情
     * @param Request $request
     * @param GoodsModel $goodsModel
     * @param Coupon $coupon
     * @param GoodsClassify $goodsClassify
     * @param GoodsEvaluate $goodsEvaluate
     * @param GoodsAttr $goodsAttr
     * @param GoodsParameter $goodsParameter
     * @param CollectGoods $collectGoods
     * @param CollectStore $collectStore
     * @param RecordGoods $recordGoods
     * @param Limit $limit
     * @param OrderGoods $orderGoods
     * @param Distribution $distribution
     * @param Store $store
     * @param StoreGoodsClassify $storeGoodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function view(Request $request,
                         GoodsModel $goodsModel,
                         Coupon $coupon,
                         GoodsClassify $goodsClassify,
                         GoodsEvaluate $goodsEvaluate,
                         GoodsAttr $goodsAttr,
                         GoodsParameter $goodsParameter,
                         CollectGoods $collectGoods,
                         CollectStore $collectStore,
                         RecordGoods $recordGoods,
                         Limit $limit,
                         OrderGoods $orderGoods,
                         Distribution $distribution,
                         Store $store,
                         StoreGoodsClassify $storeGoodsClassify)
    {

        // 获取参数
        $goods_id = $request::get('goods_id', NULL) ?? exception('商品不存在');
        $member_id = Session::get('member_info')['member_id'] ?? '';
        //是否关注店铺子查询
        $_collect_store_sql = $collectStore->alias('c_s')
            ->where('c_s.member_id = ' . (int)$member_id . ' and c_s.store_id = goods.store_id')
            ->fetchSql()
            ->count();

        $result = $goodsModel
            ->alias('goods')
            ->with(
                [
                    'parameter' => function ($query)
                    {
                        $query->field('goods_parameter_id,parameter_name,parameter_val,goods_id');
                    },
                ]
            )
            ->join('store store', 'store.store_id = goods.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where(
                    [['goods_id', '=', $goods_id], ['goods.review_status', '=', 1], ['goods.is_putaway', '=', 1]],
                    'goods'
                ))
            ->field(
                'goods_classify_id,goods_id,goods.store_id,goods.cost_price,goods_name,market_price,express_self_lifting,express,express_one_city,
                    goods.shop_price,group_price,cut_price,group_num,file,multiple_file,video,is_putaway,
                    goods.sales_volume,province,city,is_city,is_delivery,is_express,comments_number,
                    store.goods_num,store.is_shop,logo,store_name,goods.store_id,content,is_group,is_bargain,
                    is_limit,group_price,time_limit_price,attr_type_id,active_begin_time,active_end_time,
                    group_success_num,cut_success_num,goods_number,file as cart_file,is_vip,goods_weight,
                    is_distribution,is_distributor,rebates_type,distribution_set,
                    (' . $_collect_store_sql . ') collect_store'
            )
            ->append(['continue_time,video_snapshot'])
            ->find();

        $classify_title = $goodsClassify
            ->alias('three')
            ->join('goods_classify two', 'three.parent_id = two.goods_classify_id')
            ->join('goods_classify one', 'two.parent_id = one.goods_classify_id')
            ->where(['three.goods_classify_id' => $result['goods_classify_id']])
            ->field('one.title as one_title,two.title as two_title,three.title as three_title')
            ->find();


        if (is_null($result))
        {
            exception(config('message.')[-5][-3]);
        }
        /**************************************************************评价信息*********************************************************/
        //商品评价数量信息
        $result['evaluation_number'] = $goodsEvaluate->evaluationNumber($result['goods_id']);
        //店铺评价信息
        $store_evaluate = $goodsEvaluate->storeEvaluate($result['store_id']);
        $result['store_evaluate'] = $store_evaluate['self_score'];
        $result['evaluate_trend'] = $store_evaluate['trend'];
        /**************************************************************评价信息*********************************************************/
        /**************************************************************分销*********************************************************/
        // 分销商设置初始化
        $result['distribution'] = [];
        $result['distribution_accumulative'] = $result['distribution_accumulative_price'] = '';
        // 读取平台分销商设置
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distributionSet = Env::get();
        $result['distribution_is_open'] = $distributionSet['DISTRIBUTION_STATUS'];
        // 商品开启分销&&平台开启分销
        if ($result['is_distribution'] && $distributionSet['DISTRIBUTION_STATUS'] == 1)
        {
            $result['distribution_accumulative'] = $distributionSet['DISTRIBUTION_ACCUMULATIVE'];
            $result['distribution_accumulative_price'] = $distributionSet['DISTRIBUTION_ACCUMULATIVE_PRICE'];
            // 查询当前用户分销商信息[分销商等级分佣比例]
            $distributionInfo = $distribution
                ->alias('d')
                ->where([['d.member_id', '=', $member_id]])
                ->join('distribution_level dl', 'dl.distribution_level_id = dl.distribution_level_id')
                ->field('distribution_id,level_weight,one_ratio,d.distribution_level_id')
                ->find();
            // 平台分销比例
            $scale = $distributionSet['DISTRIBUTION_ONE'] / 100;
            // 平台是否开启按照商品利润分佣[默认按照交易价分佣]
            $goods_profit = $distributionSet['DISTRIBUTION_GOODS_PROFIT'];
            // 当前用户为分销商&&含有一级分销比例
            if ($distributionInfo && $distributionInfo['one_ratio'])
            {
                // 分销商等级分销比例
                $scale = $distributionInfo['one_ratio'] / 100;
            }
            // 现金
            $cash = 0;
            // 商品单品分销比例[含一级分销对应等级比例]
            $result['distribution_set'] = $result['distribution_set'] ? unserialize(
                $result['distribution_set']
            ) : [];
            if (!empty($result['distribution_set'])
                && isset($result['distribution_set'][1][$distributionInfo['distribution_level_id']]))
            {
                if ($single_product = $result['distribution_set'][1][$distributionInfo['distribution_level_id']])
                {
                    $scale = $single_product / 100;
                    if (!$result['rebates_type'])
                    {
                        // 单品现金
                        $cash = (float)$single_product;
                    }
                }
            }
            $result['distribution'] = [
                'distribution_id'    => $distributionInfo['distribution_id'] ?: '',
                'shop_max_brokerage' => number_format(
                    $cash ?: ($scale * ($goods_profit ? (($result['cost_price'] - $result['shop_price']) >= 0 ?
                            ($result['cost_price'] - $result['shop_price']) : 0) : $result['shop_price'])),
                    2,
                    '.',
                    ''
                ),
            ];
        }
        $result->hidden(['distribution_set']);
        /**************************************************************分销*********************************************************/
        //商品介绍文本内容修改
        $result['content'] = str_replace('class="tools"', 'class="tools" hidden', $result['content']);
        /**************************************************************拼团*********************************************************/

        /**************************************************************限时抢购*********************************************************/
        if ($result['is_limit'] == 1)
        {
            // 查询数据
            $limit_find = $limit
                ->where(
                    [
                        ['goods_id', '=', $goods_id],
                        ['status', '=', 1],
                        ['up_shelf_time', '<=', date('Y-m-d')],
                        ['down_shelf_time', '>=', date('Y-m-d')],
                    ]
                )
                ->field('limit_purchase,available_sale,exchange_num,interval_id,up_shelf_time')
                ->find();
            //从新设置商品可买库存
            $result['goods_number']=$limit_find['exchange_num'];
            //如果有每人限购数量  则从新赋值每人限领数量
            if (!empty($limit_find['limit_purchase']))
            {
                //查询当前人已抢购数量
                $_quantity = $orderGoods->where(
                    [
                        ['member_id', '=', $member_id],
                        ['goods_id', '=', $goods_id],
                        ['create_time', '>=', $limit_find['up_shelf_time']],
                        ['status', 'not in', '6.1'],
                        ['is_limit', '=', 1],
                    ]
                )->sum('quantity');
                //商品限购数量
                $result['purchase_goods_number'] = $limit_find['limit_purchase'] - $_quantity > 0 ? $limit_find['limit_purchase'] - $_quantity : 0;
            }
        }
        /**************************************************************限时抢购*********************************************************/
        // 是否收藏
        $result['collect'] = $collectGoods
            ->where(
                [
                    ['member_id', '=', $member_id],
                    ['goods_id', '=', $goods_id],
                ]
            )->value('collect_goods_id');
        // 商品规格
        $attrCollect = [];
        $attrArr = $goodsAttr
            ->alias('ga')
            ->where([['ga.goods_id', '=', $result['goods_id']]])
            ->join('attr a', 'a.attr_id = ga.attr_id')
            ->field('ga.goods_attr_id,ga.attr_value,a.attr_id,a.attr_name')
            ->order(['ga.attr_id' => 'asc'])
            ->select();
        if ($attrArr)
        {
            foreach ($attrArr as $item)
            {
                if (!array_key_exists($item['attr_id'], $attrCollect))
                {
                    $attrCollect[$item['attr_id']] = [
                        'attr_id'    => $item['attr_id'],
                        'attr_name'  => $item['attr_name'],
                        'goods_attr' => [],
                    ];
                }
                array_push(
                    $attrCollect[$item['attr_id']]['goods_attr'],
                    [
                        'attr_id'       => $item['attr_id'],
                        'attr_value'    => $item['attr_value'],
                        'goods_attr_id' => $item['goods_attr_id'],
                    ]
                );
            }
        }
        $result['attr'] = $attrCollect ? array_values($attrCollect) : [];
        // 商品参数
        $result['parameter'] = $goodsParameter
            ->where('goods_id', $goods_id)
            ->field('parameter_name,parameter_val')
            ->order('goods_parameter_id', 'asc')
            ->select();
        /**************************************************************优惠券*********************************************************/
        if (self::$functionStatus['is_coupon'] == 1)
        {
            // 获取父级ID
            $classify = getParCate($result['goods_classify_id'], $goodsClassify);
            $result['first_goods_classify_id'] = 0;
            if ($classify)
            {
                $result['first_goods_classify_id'] = reset($classify)['goods_classify_id'];
            }
            // 优惠券  差条件
            $result['coupon'] = $coupon
                ->where(
                    [
                        ['exchange_num', '>', 0],
                        ['modality', '=', 0],
                        ['start_time', '<=', date('Y-m-d H:i:s')],
                        ['end_time', '>=', date('Y-m-d H:i:s')],
                        ['classify_str', 'in', $classify[0]['goods_classify_id']],
                    ]
                )
                ->field('coupon_id,full_subtraction_price,actual_price,limit_num')
                ->order('actual_price', 'desc')
                ->limit(3)
                ->append(['member_state'])
                ->select();
        } else
        {
            $result['coupon'] = [];
        }
        /**************************************************************优惠券*********************************************************/
        //记录商品浏览
        if ($member_id)
        {
            // 记录ID
            $record_goods_id = $recordGoods
                ->where(
                    [
                        ['member_id', '=', $member_id],
                        ['goods_id', '=', $goods_id],
                        ['create_time', '=', date('Y-m-d')],
                    ]
                )
                ->value('record_goods_id');

            if ($record_goods_id)
            {
                $recordGoods->allowField(TRUE)->save(
                    ['goods_id' => $goods_id, 'member_id' => $member_id],
                    ['record_goods_id' => $record_goods_id]
                );
            } else
            {
                $recordGoods->allowField(TRUE)->isUpdate(FALSE)->save(
                    [
                        'goods_id'          => $goods_id,
                        'member_id'         => $member_id,
                        'goods_classify_id' => $result['goods_classify_id'],
                    ]
                );
            }
        }
        //看了又看
        $result['lock_goods_list'] = $goodsModel->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(
                self::goods_where(
                    [
                        ['goods.goods_number', '>', 0],
                        ['goods.review_status', '=', 1],
                        ['goods.is_putaway', '=', 1],
                        ['goods.store_id', '=', $result['store_id']],
                    ],
                    'goods'
                )

            )
            ->field(
                'goods.goods_id,goods.goods_name,goods.shop_price,goods.is_group,goods.is_bargain,goods.sales_volume,goods.freight_status,goods.group_price,goods.cut_price,goods.file,goods.group_num,goods.is_vip,goods.attr_type_id,goods.file as cart_file,goods.goods_number,goods.store_id,goods.is_limit,goods.time_limit_price'
            )
            ->append(['attribute_list', 'cart_number', 'cart_id', 'limit_state'])
            ->order(['goods.sort' => 'desc'])
            ->paginate(9);
        // 店铺推荐
        $result['recommend'] = $goodsModel
            ->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(
                self::goods_where(
                    [
                        ['goods.goods_id', '<>', $goods_id],
                        ['goods.store_id', '=', $result['store_id']],
                        ['goods.store_recommend', '=', 1],      //普通推荐
                        ['goods.is_putaway', '=', 1],           //是否上架
                        ['goods.goods_number', '>', 0],
                        ['goods.review_status', '=', 1],        //审核通过
                    ],
                    'goods'
                )

            )
            ->field(
                'goods.goods_id,goods.file,goods.goods_name,goods.is_group,goods.is_bargain,goods.is_limit,goods.is_vip,goods.group_price,goods.cut_price,goods.time_limit_price,goods.shop_price'
            )
            ->order('goods.sort', 'desc')
            ->limit(18)
            ->select();
        //店铺商品分类
        $result['store_classify'] = $storeGoodsClassify->relation('subset')
            ->where(
                [
                    ['store_id', '=', $result['store_id']],
                    ['status', '=', 1],
                    ['is_hot', '=', 1],
                ]
            )
            ->field('store_goods_classify_id,title')
            ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
            ->select();
        // 排行榜
        $result['ranking'] = $goodsModel->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(
                self::goods_where(
                    [
                        ['goods.store_id', '=', $result['store_id']],
                        ['goods.is_putaway', '=', 1],     //是否上架
                        ['goods.goods_number', '>', 0],
                        ['goods.review_status', '=', 1]   //审核通过
                    ],
                    'goods'
                )

            )
            ->field(
                'goods.goods_id,goods.file,goods.goods_name,goods.is_group,goods.is_bargain,goods.is_limit,goods.is_vip,goods.group_price,goods.cut_price,goods.time_limit_price,goods.shop_price'
            )
            ->order('goods.sales_volume', 'desc')
            ->limit(5)
            ->select();
        //猜你喜欢
        $recommend_list = recommend_list($goodsModel, 8, $member_id, 1);

        /**************************************************************店铺分类*********************************************************/
        // 店铺分类
        $result['classify'] = $storeGoodsClassify
            ->with('subset')
            ->where(
                [
                    ['store_id', '=', $result['store_id']],
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
                    ['store_id', '=', $result['store_id']],
                    ['status', '=', 1],
                    //                        ['parent_id', '=', 0],
                    ['is_hot', '=', 1],
                ]
            )
            ->field('store_goods_classify_id,title,is_hot')
            ->order(['sort' => 'desc', 'store_goods_classify_id' => 'desc'])
            ->select();
        /**************************************************************店铺详情*********************************************************/
        // 店铺信息
        $result['store'] = $store
            ->where('store_id', $result['store_id'])
            ->append(['store_percent'])
            ->field('store_id,logo,member_id,store_name,shop,collect,status,back_image,goods_style,pc_head_back_image')
            ->find();
        return $this->fetch(
            '',
            [
                'result'         => $result,
                'recommend_list' => $recommend_list,
                'classify_title' => $classify_title,
            ]
        );
    }


    /**
     * 商品属性获取价格和图片
     * @param Request $request
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param Products $products
     * @param Limit $limit
     * @return mixed
     */
    public function attr_find(Request $request, RSACrypt $crypt, GoodsModel $goodsModel, Products $products, Limit $limit)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request()->mid ?? '';
                // 验证
                $check = $goodsModel->valid($param, 'attr_find');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }
                // 读取价格和图片
                $result = $products
                    ->where(
                        [
                            ['goods_attr', '=', $param['goods_attr']],
                            ['goods_id', '=', $param['goods_id']],
                        ]
                    )
                    ->field(
                        'goods_id,products_id,attr_shop_price,attr_group_price,attr_market_price,attr_cut_price,attr_time_limit_price,attr_goods_number,attr_file'
                    )
                    ->append(['attr_file_img', 'is_vip'])
                    ->find();
                //商品限购数量  默认等于商品库存
                $result['purchase_goods_number'] = $result['attr_goods_number'];
                // 查找活动库存
                if (!empty($result))
                {
                    switch ((string)$param['type'])
                    {
                        case '100':
                            // 拼团
                            break;
                        case '010':
                            // 砍价
                            break;
                        case '001':
                            // 限购
                            $time_num = $limit
                                ->alias('l')
                                ->where(
                                    [
                                        ['l.goods_id', '=', $param['goods_id']],
                                        ['l.status', '=', 1],
                                    ]
                                )
                                ->join('goods g', 'g.goods_id = l.goods_id')
                                ->join(
                                    'products p',
                                    'p.goods_id = g.goods_id and p.goods_attr = \'' . $param['goods_attr'] . "'",
                                    'left'
                                )
                                ->field('l.exchange_num,p.attr_time_limit_number,limit_purchase,l.up_shelf_time')
                                ->find();
                            $result['attr_goods_number'] = $result['limit_purchase'] = $time_num['attr_time_limit_number'] ?: $time_num['exchange_num'];
                            //如果有每人限购数量 并且登录  则从新赋值每人限领数量
                            if (!empty($time_num['limit_purchase']) and !empty(Session::get('member_info')['member_id']))
                            {
                                //查询当前人已抢购数量
                                $_quantity = OrderGoods::where(
                                    [
                                        ['member_id', '=', Session::get('member_info')['member_id']],
                                        ['goods_id', '=', $param['goods_id']],
                                        ['create_time', '>=', $time_num['up_shelf_time']],
                                        ['status', 'not in', '6.1'],
                                        ['is_limit', '=', 1],
                                    ]
                                )->sum('quantity');
                                //商品限购数量
                                $result['purchase_goods_number'] = $result['limit_purchase'] - $_quantity > 0 ? $result['limit_purchase'] - $_quantity : 0;
                            }
                            break;
                        default:
                            // 默认
                            break;
                    }
                }
                // 折扣
                $discount = discount($param['member_id']);

                return $crypt->response(['code' => 0, 'result' => $result, 'discount' => $discount]);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 商品评价
     * @param Request $request
     * @param RSACrypt $crypt
     * @param GoodsEvaluate $goodsEvaluate
     * @return mixed
     */
    public function evaluate_list(Request $request, RSACrypt $crypt, GoodsEvaluate $goodsEvaluate)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $goodsEvaluate->valid($param, 'evaluate_list');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                // 条件
                $condition[] = ['goods_id', '=', $param['goods_id']];
                $condition[] = ['evaluate.status', '=', 1];
                // 有图
                if (isset($param['file']) && $param['file'] == 1)
                {
                    $condition[] = ['multiple_file', '<>', ''];
                }
                // 视频
                if (isset($param['video']))
                {
                    $condition[] = ['video', '<>', ''];
                }

                // 好评
                switch ($param['star_level'] ?? '')
                {
                    case 'good':
                        $condition[] = ['star_num', ['<', 6], ['>', 3], 'and'];
                        break;
                    case 'medium':
                        $condition[] = ['star_num', '=', 3];
                        break;
                    case 'negative':
                        $condition[] = ['star_num', ['<', 3], ['>', 0], 'and'];
                        break;
                }

                // 评价
                $result = $goodsEvaluate->alias('evaluate')
                    ->join('member member', 'member.member_id = evaluate.member_id')
                    ->where($condition)
                    ->field(
                        'star_num,is_anonymous,content,reply,create_time,attr,avatar,nickname,multiple_file,video,is_anonymous'
                    )
                    ->order(['create_time' => 'desc'])
                    ->append(['video_snapshot'])
                    ->paginate(10, FALSE, $param);

                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 收藏商品
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Store $store
     * @param GoodsModel $goodsModel
     * @param CollectGoods $collectGoods
     * @return mixed
     */
    public function collect_goods(Request $request, RSACrypt $crypt, Store $store, GoodsModel $goodsModel, CollectGoods $collectGoods)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 验证
                $check = $goodsModel->valid($param, 'collect_goods');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                $store_id = $store->where('member_id', $param['member_id'])->value('store_id');

                if ($param['store_id'] == $store_id)
                {
                    return $crypt->response(['code' => -1, 'message' => config('message.')[-5][-2]]);
                }

                $collect_id = $collectGoods
                    ->where(
                        [
                            ['member_id', '=', $param['member_id']],
                            ['goods_id', '=', $param['goods_id']],
                        ]
                    )
                    ->value('collect_goods_id');

                if ($collect_id)
                {
                    return $crypt->response(['code' => -1, 'message' => config('message.')[-5][-1]]);
                }

                // 新增
                $collectGoods->allowField(TRUE)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 收藏商品列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param CollectGoods $collectGoods
     * @return mixed
     */
    public function collect_goods_list(Request $request, RSACrypt $crypt, GoodsModel $goodsModel, CollectGoods $collectGoods)
    {
        try
        {
            // 获取参数
            $param = $request::instance()->param();
            $param['member_id'] = Session::get('member_info')['member_id'];

            $condition = [];

            if (!empty($param['keyword']))
            {
                $condition[] = ['goods_name', 'like', '%' . $param['keyword'] . '%'];
            }

            $result = $collectGoods
                ->alias('collect_goods')
                ->join('goods goods', 'goods.goods_id = collect_goods.goods_id')
                ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
                ->where('collect_goods.member_id', $param['member_id'])
                ->where(self::goods_where($condition, 'goods'))
                ->field(
                    'collect_goods_id,goods.goods_id,goods.store_id,goods_name,shop_price,is_group,is_bargain,is_limit,price,group_price,cut_price,time_limit_price,group_num,file,goods_number,is_putaway,is_vip,attr_type_id,file as cart_file'
                )
                ->append(['attribute_list', 'is_limit'])
                ->order('collect_goods.create_time', 'desc')
                ->paginate(12, FALSE, ['query' => $param]);


            $recommend_list = recommend_list($goodsModel, 8, $param['member_id'], 1);

            // 折扣
            $discount = discount($param['member_id']);

        } catch (\Exception $e)
        {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
        }
        return $this->fetch(
            '',
            [
                'code'           => 0,
                'result'         => $result,
                'recommend_list' => $recommend_list,
                'discount'       => $discount,
            ]
        );

    }

    /**
     * 收藏商品删除
     * @param Request $request
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param CollectGoods $collectGoods
     * @return mixed
     */
    public function collect_goods_delete(Request $request, RSACrypt $crypt, GoodsModel $goodsModel, CollectGoods $collectGoods)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 验证
                $check = $goodsModel->valid($param, 'collect_goods_delete');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                $res = $collectGoods::get($param['collect_goods_id']);
                if ($res)
                {
                    // 删除
                    $state = $collectGoods::destroy($param['collect_goods_id'], TRUE);

                    if ($state)
                    {
                        $redisInstance = Cache::handler();
                        $prefix = Config::get('cache.default')['prefix'];
                        $redisInstance->zIncrBy(
                            $prefix . 'collect_goods',
                            -count(explode(',', $param['collect_goods_id'])),
                            $param['member_id']
                        );
                        $goodsModel->where('goods_id', 'in', $param['goods_id'])->setDec('collect_number');
                    }
                }


                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 降价通知
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Store $store
     * @param GoodsModel $goodsModel
     * @param CollectGoods $collectGoods
     * @param GoodsReductionNotic $goodsReductionNotic
     * @return mixed
     */
    public function depreciate_goods(Request $request, RSACrypt $crypt, Store $store, GoodsModel $goodsModel, CollectGoods $collectGoods, GoodsReductionNotic $goodsReductionNotic)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 验证
                $check = $goodsModel->valid($param, 'depreciate_goods');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                $store_id = $store->where('member_id', $param['member_id'])->value('store_id');

                if ($param['store_id'] == $store_id)
                {
                    return $crypt->response(['code' => -1, 'message' => config('message.')[-5][-2]]);
                }

                $collect_id = $collectGoods
                    ->where(
                        [
                            ['member_id', '=', $param['member_id']],
                            ['goods_id', '=', $param['goods_id']],
                        ]
                    )
                    ->value('collect_goods_id');

                if ($collect_id)
                {
                    $collectGoods->allowField(TRUE)->save(
                        ['price' => $param['price']],
                        ['collect_goods_id' => $collect_id]
                    );
                } else
                {
                    $collectGoods->allowField(TRUE)->save($param);
                }

                $data = [
                    'member_id'      => $param['member_id'],
                    'goods_id'       => $param['goods_id'],
                    'price'          => $param['goods_price'],
                    'current_price'  => $param['goods_price'],
                    'expected_price' => $param['price'],
                ];

                // 降价通知表
                $goodsReductionNotic->allowField(TRUE)->save($data);


                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch
            (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 好物推荐 - 精选
     * @param Request $request
     * @param GoodsModel $goodsModel
     * @param Adv $adv
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function choiceness_list(Request $request, GoodsModel $goodsModel, Adv $adv, GoodsClassify $goodsClassify)
    {
        // 获取参数
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'];

        $result['banner'] = $adv
            ->where(
                [
                    ['adv_position_id', '=', config('pc_config.goods_recommend_id')],
                    ['status', '=', 1],
                    ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                ]
            )
            ->field('adv_id,title,type,file,content')
            ->order('sort', 'desc')
            ->find();

        // 人气
        $result['popularity'] = $goodsModel
            ->alias('goods')
//            ->join('store store','goods.store_id = store.store_id and '.self::store_auth_sql('store'))
            ->where(
                [
                    ['goods.goods_number', '>', 0],
                    ['goods.is_putaway', '=', 1],
                    ['goods.review_status', '=', 1],
                    ['goods.is_popularity', '=', 1],
                ]

            )
            ->field(
                'goods.goods_id,goods.goods_name,goods.file,goods.shop_price,goods.shop_price as goods_price,goods.is_vip,goods.is_group,goods.is_bargain,goods.is_limit,goods.time_limit_price,goods.group_price,goods.cut_price'
            )->limit(11)
            ->select()->toArray();


        // 特惠
        $result['preference'] = $goodsModel->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(
                self::goods_where(
                    [
                        ['goods.goods_number', '>', 0],
                        ['goods.is_putaway', '=', 1],
                        ['goods.review_status', '=', 1],
                        ['goods.is_preference', '=', 1],
                    ],
                    'goods'
                )

            )
            ->field(
                'goods.goods_id,goods.goods_name,goods.file,goods.shop_price,goods.shop_price as goods_price,goods.is_vip,goods.is_group,goods.is_bargain,goods.is_limit,goods.time_limit_price,goods.group_price,goods.cut_price'
            )->limit(11)
            ->select()->toArray();

        $result['goods_classify'] = $goodsClassify
            ->where(['parent_id' => 0, 'status' => 1])
            ->field('classify_adv_id,goods_classify_id,title,web_file')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
            ->select();

        if (empty($param['goods_classify_id']))
        {
            $param['goods_classify_id'] = $result['goods_classify'][0]['goods_classify_id'];
        }


        // 好物推荐
        $result['goods_list'] = $goodsModel
            ->alias('goods')
            ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(
                self::goods_where(
                    [
                        ['goods.goods_number', '>', 0],
                        ['goods.is_putaway', '=', 1],
                        ['goods.review_status', '=', 1],
                        ['goods.is_group', '=', 0],
                        ['goods.is_bargain', '=', 0],
                        ['goods.is_limit', '=', 0],
                        [
                            'goods.goods_classify_id',
                            'in',
                            implode(
                                ',',
                                array_column(
                                    getParCate($param['goods_classify_id'], $goodsClassify, 0),
                                    'goods_classify_id'
                                )
                            ),
                        ],
                    ],
                    'goods'
                )

            )
            ->field(
                'goods.goods_id,goods.goods_name,goods.file,goods.shop_price,goods.shop_price as goods_price,goods.is_group,goods.is_bargain,goods.freight_status,goods.group_price,goods.cut_price,goods.group_num,goods.is_vip,goods.attr_type_id,goods.file as cart_file,goods.goods_number,goods.store_id,goods.is_limit,goods.time_limit_price'
            )
            ->append(['attribute_list', 'limit_state'])
            ->paginate(20, FALSE, $param);
        // 折扣
        $discount = discount($param['member_id']);

       // halt($result['goods_classify'][0]['goods_classify_id']);
        // dump($result);
        return $this->fetch(
            '',
            [
                'result'            => $result,
                'discount'          => $discount,
                'goods_classify_id' => $result['goods_classify'][0]['goods_classify_id'],
                'header_title'      => '好物推荐',
            ]
        );

    }

    /**
     * 好物推荐 - 分类
     * @param Request $request
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param GoodsClassify $goodsClassify
     * @return mixed
     */
    public function good_recommend_list(Request $request, RSACrypt $crypt, GoodsModel $goodsModel, GoodsClassify $goodsClassify)
    {
        if ($request::isPost())
        {
            try
            {

                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request()->mid ?? 0;
//                // 验证
//                $check = $goodsModel->valid($param, 'good_recommend_list');
//                if ($check['code'])
//                {
//                    return $crypt->response($check);
//                }


                $param['category'] = empty($param['category']) ?? 0;

                $store_classify_id = $goodsClassify
                                         ->field('goods_classify_id')
                                         ->where('status', 1)
                                         ->order(['sort' => 'desc', 'create_time' => 'desc'])
                                         ->limit($param['category'], 1)
                                         ->select()[0]['goods_classify_id'] ?? 0;
                if (empty($param['goods_classify_id']))
                {
                    $param['goods_classify_id'] = $store_classify_id;
                }

                // 好物推荐
                $result = $goodsModel
                    ->alias('goods')
                    ->join('store store', 'goods.store_id = store.store_id and ' . self::store_auth_sql('store'))
                    ->where(
                        self::goods_where(
                            [
                                ['goods.goods_number', '>', 0],
                                ['goods.is_putaway', '=', 1],
                                ['goods.review_status', '=', 1],
                                ['goods.is_group', '=', 0],
                                ['goods.is_bargain', '=', 0],
                                ['goods.is_limit', '=', 0],
                                [
                                    'goods_classify_id',
                                    'in',
                                    implode(
                                        ',',
                                        array_column(
                                            getParCate($param['goods_classify_id'], $goodsClassify, 0),
                                            'goods_classify_id'
                                        )
                                    ),
                                ],
                            ],
                            'goods'
                        )

                    )
                    ->field(
                        'goods.goods_id,goods.goods_name,goods.file,goods.shop_price,goods.shop_price as goods_price,goods.is_group,goods.is_bargain,goods.freight_status,goods.group_price,goods.cut_price,goods.group_num,goods.is_vip,goods.attr_type_id,goods.file as cart_file,goods.goods_number,goods.store_id,goods.is_limit,goods.time_limit_price'
                    )
                    ->append(['attribute_list', 'limit_state'])
                    ->paginate(20, FALSE, $param);

                // 折扣
                $discount = discount($param['member_id']);

                return $crypt->response(['code' => 0, 'result' => $result, 'discount' => $discount]);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /******废弃****/
//    /**
//     * 商品详情看了又看
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param GoodsModel $goods
//     * @return mixed
//     */
//    public function lock_goods_list(Request $request, RSACrypt $crypt, GoodsModel $goods)
//    {
//
//        if ($request::isPost())
//        {
//            try
//            {
//                // 获取参数
//                $param = $crypt->request();
//                $param['member_id'] = request()->mid ?? '';
//                // 条件
//                $condition[] = ['goods_number', '>', 0];
//                $condition[] = ['review_status', '=', 1];
//                $condition[] = ['is_putaway', '=', 1];
//                $condition[] = ['store_id', '=', $param['store_id']];
//                // 店铺信息
//                $result = $goods->alias('goods')
//                    ->join('store store', 'goods.store_id and store.store_id and ' . self::store_auth_sql('store'))
//                    ->where(self::goods_where($condition, 'goods'))
//                    ->field(
//                        'goods.goods_id,goods.goods_name,goods.shop_price,goods.is_group,goods.is_bargain,goods.sales_volume,goods.freight_status,goods.group_price,goods.cut_price,goods.file,goods.group_num,goods.is_vip,goods.attr_type_id,goods.file as cart_file,goods.goods_number,goods.store_id,goods.is_limit,goods.time_limit_price'
//                    )
//                    ->append(['attribute_list', 'cart_number', 'cart_id', 'limit_state'])
//                    ->order(['goods.sort' => 'desc'])
//                    ->paginate(20);
//                // 折扣
//                $discount = discount($param['member_id']);
//                return $crypt->response(['code' => 0, 'result' => $result, 'discount' => $discount]);
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
//     * 门店自提
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param Take $take
//     * @param Store $store
//     * @return mixed
//     */
//    public function take_list(Request $request, RSACrypt $crypt, Take $take, Store $store)
//    {
//        if ($request::isPost())
//        {
//            try
//            {
//                // 获取参数
//                $param = $crypt->request();
//
//                // 验证
//                $check = $take->valid($param, 'take_list');
//                if ($check['code'])
//                {
//                    return $crypt->response($check);
//                }
//
//                // 默认条件
//                $condition[] = ['store_id', '=', $param['store_id']];
//                $condition[] = ['status', '=', 1];
//
//                // 自提点名称|所在地
//                if (!empty($param['keyword']))
//                {
//                    $condition[] = ['take_name|address', 'like', '%' . $param['keyword'] . '%'];
//                }
//
//                // 地区
//                if (!empty($param['area']))
//                {
//                    $condition[] = ['area', '=', $param['area']];
//                }
//
//                // 门店自提
//                $result = $take
//                    ->where($condition)
//                    ->field(
//                        'take_id,take_name,contacts_phone,start_hours,end_hours,address,lat,lng,round(st_distance(point(lng,lat),point(' . $param['lng'] . ',' . $param['lat'] . '))*111.195,3) AS distance'
//                    )
//                    ->order('distance', 'asc')
//                    ->select();
//
//                return $crypt->response(
//                    [
//                        'code'     => 0,
//                        'result'   => $result,
//                        'province' => $store->where('store_id', $param['store_id'])->field('city')->append(
//                            ['city_id']
//                        )->find(),
//                    ]
//                );
//
//            } catch (\Exception $e)
//            {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
//            }
//        }
//    }
//
//    /**
//     * 配送说明
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param Store $store
//     * @param MemberAddress $memberAddress
//     * @param GoodsModel $goodsModel
//     * @param Area $area
//     * @return mixed
//     */
//    public function shipping_instructions(Request $request, RSACrypt $crypt, Store $store, MemberAddress $memberAddress, GoodsModel $goodsModel, Area $area)
//    {
//        if ($request::isPost())
//        {
//            try
//            {
//                // 获取参数
//                $param = $crypt->request();
//                $param['member_id'] = request()->mid ?? '';
//                // 店铺
//                $result = $store
//                    ->where('store_id', $param['store_id'])
//                    ->field('is_shop,is_express,is_city,is_delivery,city_explain')
//                    ->find();
//
//                // 我的地址
//                $address = $memberAddress
//                    ->where('member_id', $param['member_id'])
//                    ->field('lat,lng,province,city,area,street')
//                    ->order('is_default', 'desc')
//                    ->append(['street_id'])
//                    ->find();
//
//                if (!$address)
//                {
//
//                    $address['street_id'] = $area->where('area_name', $param['area'])->value('area_id');
//                    $address['province'] = $param['province'];
//                    $address['city'] = $param['city'];
//                    $address['area'] = $param['area'];
//                    $address['lat'] = $param['lat'];
//                    $address['lng'] = $param['lng'];
//
//                }
//
//                $goods_info = $goodsModel
//                    ->where('goods_id', $param['goods_id'])
//                    ->field('goods_id,store_id,freight_id,is_freight,goods_weight')
//                    ->find();
//                if (empty($goods_info))
//                {
//                    return $crypt->response(['code' => -11, 'message' => config('message.')[-17][-1]]);
//                }
//                $freightService = app(
//                    'app\\common\\service\\Freight',
//                    [
//                        'args'    => [
//                            [
//                                'goods_id'     => $goods_info['goods_id'],
//                                'store_id'     => $goods_info['store_id'],
//                                'goods_attr'   => '',
//                                'freight_id'   => $goods_info['freight_id'],
//                                'quantity'     => $param['goods_number'],
//                                'single_price' => $param['goods_price'],
//                                'goods_weight' => $goods_info['goods_weight'],
//                                'is_freight'   => $goods_info['is_freight'],
//                            ],
//                        ],
//                        'address' => [
//                            'street_id' => $address['street_id'],
//                            'city_name' => $address['city'],
//                            'lat'       => $address['lat'],
//                            'lng'       => $address['lng'],
//                        ],
//                    ]
//                );
//
//                return $crypt->response(
//                    [
//                        'code'           => 0,
//                        'result'         => $result,
//                        'address'        => $address,
//                        'freightService' => $freightService->calculation(),
//                    ]
//                );
//
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
//     * 商品详情收藏商品删除
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param GoodsModel $goodsModel
//     * @param CollectGoods $collectGoods
//     * @return mixed
//     */
//    public function view_collect_goods_delete(Request $request, RSACrypt $crypt, GoodsModel $goodsModel, CollectGoods $collectGoods)
//    {
//        if ($request::isPost())
//        {
//            try
//            {
//                // 获取参数
//                $param = $crypt->request();
//                $param['member_id'] = request(0)->mid;
//                // 验证
//                $check = $goodsModel->valid($param, 'view_collect_goods_delete');
//                if ($check['code'])
//                {
//                    return $crypt->response($check);
//                }
//
//                // 删除
//                $state = $collectGoods->where(
//                    [
//                        ['member_id', '=', $param['member_id']],
//                        ['goods_id', '=', $param['goods_id']],
//                    ]
//                )->field('collect_goods_id')->find()->delete(TRUE);
//
//                if ($state)
//                {
//                    $redisInstance = Cache::handler();
//                    $prefix = Config::get('cache.default')['prefix'];
//                    $redisInstance->zIncrBy($prefix . 'collect_goods', -1, $param['member_id']);
//                    $goodsModel->where('goods_id', $param['goods_id'])->setDec('collect_number');
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
//     * 商品优惠券列表
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param GoodsModel $goodsModel
//     * @param Coupon $coupon
//     * @param GoodsClassify $goodsClassify
//     * @param MemberCoupon $memberCoupon
//     * @return mixed
//     */
//    public function coupon_list(Request $request,
//                                RSACrypt $crypt,
//                                GoodsModel $goodsModel,
//                                Coupon $coupon,
//                                GoodsClassify $goodsClassify,
//                                MemberCoupon $memberCoupon)
//    {
//        if ($request::isPost())
//        {
//            try
//            {
//                // 获取参数
//                $param = $crypt->request();
//                $param['member_id'] = request()->mid ?? '';
//
//                // 验证
//                $check = $goodsModel->valid($param, 'coupon_list');
//                if ($check['code'])
//                {
//                    return $crypt->response($check);
//                }
//
//                // 获取父级ID
//                $classify = getParCate($param['goods_classify_id'], $goodsClassify);
//
//                // 优惠券  差条件
//                $result = $coupon
//                    ->where(
//                        [
//                            ['exchange_num', '>', 0],
//                            ['modality', '=', 0],
//                            ['start_time', '<=', date('Y-m-d')],
//                            ['end_time', '>=', date('Y-m-d')],
//                            ['classify_str', 'in', $param['store_id'] . ',' . $classify[0]['goods_classify_id']],
//                        ]
//                    )
//                    ->field(
//                        'coupon_id,type,classify_str,full_subtraction_price,actual_price,total_num,exchange_num,is_vip,is_group,is_bargain,is_limit,time_limit_price,group_price,cut_price,
//                    limit_num,start_time,end_time'
//                    )
//                    ->order('actual_price', 'desc')
//                    ->append(['member_state'])
//                    ->select();
//                if (!$result->isEmpty())
//                {
//                    $idArr = array_column($result->toArray(), 'coupon_id');
//                    $get = [];
//                    if ($idArr)
//                    {
//                        $get = $memberCoupon
//                            ->where(
//                                [
//                                    ['member_id', '=', $param['member_id']],
//                                    ['coupon_id', 'in', implode(',', $idArr)],
//                                ]
//                            )
//                            ->field('coupon_id,count(member_coupon_id) as count')
//                            ->select();
//                    }
//                    foreach ($result as $item)
//                    {
//                        $item['get_count'] = 0;
//                        if ($get)
//                        {
//                            foreach ($get as $_get)
//                            {
//                                if ($_get['coupon_id'] == $item['coupon_id'])
//                                {
//                                    $item['get_count'] = $_get['count'];
//                                }
//                            }
//                        }
//                    }
//                }
//                return $crypt->response(['code' => 0, 'result' => $result]);
//
//            } catch (\Exception $e)
//            {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
//            }
//        }
//    }
}