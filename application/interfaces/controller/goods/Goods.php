<?php
declare(strict_types = 1);

namespace app\interfaces\controller\goods;

use app\common\model\Distribution;
use app\common\model\GoodsAttr;
use app\common\model\GoodsReductionNotic;
use app\common\model\Limit;
use app\common\model\Order;
use app\common\model\OrderGoods;
use app\common\service\QrCode;
use EasyWeChat\Factory;
use app\common\model\MemberAddress;
use app\common\model\RecordGoods;
use app\common\model\Coupon;
use app\common\model\Products;
use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsClassify;
use app\common\model\GoodsEvaluate;
use app\common\model\GoodsParameter;
use app\common\model\CollectGoods;
use app\common\model\GroupGoods;
use app\common\model\Take;
use app\common\model\Store;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;

/**
 * 商品 - Joy
 * Class Goods
 * @package app\interfaces\controller\goods
 */
class Goods extends BaseController
{
    
    /**
     * 列表
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          GoodsModel $goodsModel,
                          GoodsClassify $goodsClassify)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $condition = [
            ['review_status', '=', 1],
            ['is_putaway', '=', 1],
        ];
        // $condition[] = ['goods_number', '>', 0];
        // 物流状态
        if (array_key_exists('freight_status', $param) && $param['freight_status'] != '') {
            if ($param['freight_status'] == 3) {
                // 同城配送
                $condition[] = ['s.is_city', '=', 1];
                $condition[] = ['g.express_one_city', '=', 1];
            } elseif ($param['freight_status'] == 2) {
                // 门店自提
                $condition[] = ['s.is_shop', '=', 1];
                $condition[] = ['g.express_self_lifting', '=', 1];
            }
        }
        // 是否有货
        if (array_key_exists('goods_number', $param) && $param['goods_number'] != '') {
            $condition[] = ['g.goods_number', '>=', $param['goods_number']];
        }
        // 个人、企业类型
        if (array_key_exists('shop', $param) && $param['shop'] != '') {
            $condition[] = ['s.shop', 'in', $param['shop']];
        }
        // 关键词查询
        if (array_key_exists('keyword', $param) && $param['keyword'] != '') {
            $condition[] = ['g.goods_name|g.keyword|b.brand_name|gc.title', 'like', '%' . $param['keyword'] . '%'];
            searchSet($param['keyword']);
        }
        // 是否免运费
        if (array_key_exists('is_freight', $param) && $param['is_freight']) {
            $condition[] = ['g.freight_status', '=', $param['is_freight']];
        }
        // 品牌
        if (array_key_exists('brand_id', $param) && $param['brand_id']) {
            $condition[] = ['g.brand_id', '=', $param['brand_id']];
        }
        // 分类
        if (array_key_exists('goods_classify_id', $param) && $param['goods_classify_id']) {
            $condition[] = ['g.goods_classify_id', 'in', implode(',', array_column(getParCate($param['goods_classify_id'], $goodsClassify, 0), 'goods_classify_id')) ?: $param['goods_classify_id']];
        }
        // 最低价查询
        if (array_key_exists('minimum_price', $param) && $param['minimum_price']) {
            $condition[] = ['g.shop_price', '>=', $param['minimum_price']];
        }
        // 最高价间查询
        if (array_key_exists('top_price', $param) && $param['top_price']) {
            $condition[] = ['g.shop_price', '<=', $param['top_price']];
        }
        // 价格区间查询
        if (array_key_exists('top_price', $param) && array_key_exists('minimum_price', $param)
            && $param['minimum_price'] && $param['top_price']) {
            $condition[] = ['g.shop_price', 'between', [$param['minimum_price'], $param['top_price']]];
        }
        // 是否为购买即成为分销商商品
        $avt = [
            ['g.is_group', '=', 0],
            ['g.is_bargain', '=', 0],
            ['g.is_limit', '=', 0],
        ];
        if (array_key_exists('is_distributor', $param) && $param['is_distributor']) {
            $condition[] = ['is_distributor', '=', $param['is_distributor'] - 1];
            if ($param['is_distributor'] == 2) {
                $condition = array_merge($condition, $avt);
            }
        }
        // 是否为可分销商品
        if (array_key_exists('is_distribution', $param) && $param['is_distribution']) {
            $condition[] = ['g.is_distribution', '=', $param['is_distribution'] - 1];
            if ($param['is_distribution'] == 2) {
                $condition = array_merge($condition, $avt);
            }
        }
        // 排序
        $parameter = !empty($param['parameter']) ? 'g.' . $param['parameter'] : 'g.sort';
        $rank = !empty($param['rank']) ? $param['rank'] : 'asc';
        // 数据
        $result = $goodsModel
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->join('brand b', 'b.brand_id = g.brand_id', 'left')
            ->join('goods_classify gc', 'gc.goods_classify_id = g.goods_classify_id')
            ->field('g.goods_id,g.store_id,goods_name,shop_price,
                    g.sales_volume,freight_status,shop,store_name,g.file,is_group,
                    is_bargain,freight_status,shop,group_price,cut_price,group_num,is_vip,
                    attr_type_id,g.file as cart_file,goods_number,is_limit,time_limit_price,
                    is_distributor,is_distribution,g.market_price')
            ->where($condition)
            ->order([$parameter => $rank, 'is_vip' => $rank == 'desc' ? 'asc' : 'desc', 'g.sort' => 'desc', 'g.goods_id' => 'desc'])
            ->append(['attribute_list', 'limit_state'])
            ->paginate(20, false);
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
     * 详情
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param Coupon $coupon
     * @param GoodsClassify $goodsClassify
     * @param GoodsEvaluate $goodsEvaluate
     * @param GoodsAttr $goodsAttr
     * @param GoodsParameter $goodsParameter
     * @param GroupGoods $groupGoods
     * @param CollectGoods $collectGoods
     * @param RecordGoods $recordGoods
     * @param Limit $limit
     * @param Order $order
     * @param QrCode $qrCode
     * @param OrderGoods $orderGoods
     * @param Distribution $distribution
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function view(RSACrypt $crypt,
                         GoodsModel $goodsModel,
                         Coupon $coupon,
                         GoodsClassify $goodsClassify,
                         GoodsEvaluate $goodsEvaluate,
                         GoodsAttr $goodsAttr,
                         GoodsParameter $goodsParameter,
                         GroupGoods $groupGoods,
                         CollectGoods $collectGoods,
                         RecordGoods $recordGoods,
                         Limit $limit,
                         Order $order,
                         QrCode $qrCode,
                         OrderGoods $orderGoods,
                         Distribution $distribution)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $goodsModel->valid($param, 'view');
        $result = $goodsModel
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['is_putaway', '=', 1],
                ['review_status', '=', 1],
            ])
            ->field('goods_classify_id,goods_id,g.store_id,g.cost_price,goods_name,market_price,
                    shop_price,group_price,cut_price,group_num,file,multiple_file,video,is_putaway,
                    g.sales_volume,province,city,is_city,is_delivery,is_shop,is_express,comments_number,
                    s.goods_num,logo,store_name,g.store_id,web_content,is_group,is_bargain,default_express_type,
                    is_limit,group_price,time_limit_price,attr_type_id,active_begin_time,active_end_time,
                    group_success_num,cut_success_num,goods_number,file as cart_file,is_vip,goods_weight,
                    is_distribution,is_distributor,rebates_type,distribution_set,express,express_one_city,
                    express_self_lifting,s.member_id,s.shop,s.phone as store_phone,s.describe as store_describe,
                    s.member_id as rong_id')
            ->append(['continue_time', 'video_snapshot'])
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -3,
                'message' => '该商品不存在或已下架',
            ], true);
        }
        if (!self::$oneOrMore) {
            Env::load(Env::get('app_path') . 'common/ini/.config');
            $result['province'] = Env::get('address', '');
            $result['city'] = '';
        }
        // 查询店铺商品数量
        $result['goods_num'] = $goodsModel
            ->where([
                ['store_id', '=', $result['store_id']],
                ['is_putaway', '=', 1],
                ['review_status', '=', 1],
            ])
            ->count();
        $result['is_own_shop'] = $result['member_id'] == $param['member_id'] ? 1 : 0;
        $result->hidden(['member_id']);
        // 分销商设置初始化
        $result['distribution'] = [];
        $result['distribution_accumulative'] = $result['distribution_accumulative_price'] = '';
        // 读取平台分销商设置
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distributionSet = Env::get();
        $result['distribution_is_open'] = $distributionSet['DISTRIBUTION_STATUS'];
        if ($result['is_group'] || $result['is_bargain'] || $result['is_limit']) {
            // 若商品参加活动则取消分销
            $result['is_distribution'] = 0;
        }
        // 商品开启分销&&平台开启分销&&非活动商品
        if ($result['is_distribution'] && $distributionSet['DISTRIBUTION_STATUS'] == 1) {
            $result['distribution_accumulative'] = $distributionSet['DISTRIBUTION_ACCUMULATIVE'];
            $result['distribution_accumulative_price'] = $distributionSet['DISTRIBUTION_ACCUMULATIVE_PRICE'];
            // 查询当前用户分销商信息[分销商等级分佣比例]
            $distributionInfo = $distribution
                ->alias('d')
                ->where([['d.member_id', '=', $param['member_id']]])
                ->join('distribution_level dl', 'dl.distribution_level_id = dl.distribution_level_id')
                ->field('distribution_id,level_weight,one_ratio,d.distribution_level_id')
                ->find();
            // 平台分销比例
            $scale = $distributionSet['DISTRIBUTION_ONE'] / 100;
            // 平台是否开启按照商品利润分佣[默认按照交易价分佣]
            $goods_profit = $distributionSet['DISTRIBUTION_GOODS_PROFIT'];
            // 当前用户为分销商&&含有一级分销比例
            if (!is_null($distributionInfo) && $distributionInfo['one_ratio'] > 0) {
                // 分销商等级分销比例
                $scale = $distributionInfo['one_ratio'] / 100;
            }
            // 现金
            $cash = 0;
            // 商品单品分销比例[含一级分销对应等级比例]
            $result['distribution_set'] = $result['distribution_set'] ? unserialize($result['distribution_set']) : [];
            if (!empty($result['distribution_set'])
                && isset($result['distribution_set'][1][$distributionInfo['distribution_level_id']])) {
                if ($single_product = $result['distribution_set'][1][$distributionInfo['distribution_level_id']]) {
                    $scale = $single_product / 100;
                    if (!$result['rebates_type']) {
                        // 单品现金
                        $cash = (float)$single_product;
                    }
                }
            }
            $result['distribution'] = [
                'distribution_id' => $distributionInfo['distribution_id'] ?: '',
                'shop_max_brokerage' => number_format(
                    $cash ?: ($scale * ($goods_profit ? (($result['shop_price'] - $result['cost_price']) >= 0 ?
                            ($result['shop_price'] - $result['cost_price']) : 0) : $result['shop_price'])),
                    2, '.', ''),
            ];
        }
        $result->hidden(['distribution_set']);
        // 是否开启快递
        $result['web_content'] = str_replace('class="tools"', 'class="tools" hidden', $result['web_content']);
        // todo 拼团
        $result['get_group_goods_num'] = $result['buy_cum_limit'] = 0;
        if ($result['is_group']) {
            $groupGoodsInfo = $groupGoods
                ->where([
                    ['goods_id', '=', $result['goods_id']],
                    ['up_shelf_time', '<=', date('Y-m-d')],
                    ['down_shelf_time', '>=', date('Y-m-d')],
                ])
                ->field('buy_cum_limit,up_shelf_time')
                ->order(['group_goods_id' => 'desc'])
                ->find();
            // 累积购买上限
            if (!is_null($groupGoodsInfo)) {
                $result['buy_cum_limit'] = $groupGoodsInfo['buy_cum_limit'] ?: 0;
            }
            // 查询用户已购买此拼团商品数量
            $get = $order
                ->alias('o')
                ->where([
                    ['o.member_id', '=', $param['member_id']],
                    ['o.order_type', '=', 2],
                    ['og.goods_id', '=', $param['goods_id']],
                    ['o.create_time', '>=', $groupGoodsInfo['up_shelf_time']],
                    ['og.status', 'not in', '6.1'],
                ])
                ->join('order_goods og', 'og.order_id = o.order_id')
                ->sum('og.quantity');
            $result['get_group_goods_num'] = $get;
        }
        // 是否收藏
        $result['collect'] = $collectGoods
            ->where([
                ['member_id', '=', $param['member_id']],
                ['goods_id', '=', $param['goods_id']],
            ])->value('collect_goods_id');
        // 拼团列表
        $result['group_list'] = $groupGoods
            ->alias('gg')
            ->join('group_activity ga', 'ga.group_goods_id = gg.group_goods_id')
            ->join('group_activity_attach gaa', 'gaa.group_activity_id = ga.group_activity_id and gaa.member_id = ga.owner')
            ->join('member m', 'm.member_id = ga.owner')
            ->where([
                ['gg.goods_id', '=', $param['goods_id']],
                ['ga.status', '=', 1],
                ['ga.owner', '<>', $param['member_id']],
            ])
            ->where('unix_timestamp(ga.end_time)-unix_timestamp(now()) > 0')
            ->field('owner,ga.group_activity_id,group_activity_attach_id,surplus_num,avatar,nickname,end_time')
            ->limit(5)
            ->append(['continue_time'])
            ->select();
        // 商品规格
        $attrCollect = [];
        $attrArr = $goodsAttr
            ->alias('ga')
            ->where([['ga.goods_id', '=', $result['goods_id']]])
            ->join('attr a', 'a.attr_id = ga.attr_id')
            ->field('ga.goods_attr_id,ga.attr_value,a.attr_id,a.attr_name')
            ->order(['ga.attr_id' => 'asc'])
            ->select();
        if ($attrArr) {
            foreach ($attrArr as $item) {
                if (!array_key_exists($item['attr_id'], $attrCollect)) {
                    $attrCollect[$item['attr_id']] = [
                        'attr_id' => $item['attr_id'],
                        'attr_name' => $item['attr_name'],
                        'goods_attr' => [],
                    ];
                }
                array_push($attrCollect[$item['attr_id']]['goods_attr'], [
                    'attr_id' => $item['attr_id'],
                    'attr_value' => $item['attr_value'],
                    'goods_attr_id' => $item['goods_attr_id'],
                ]);
            }
        }
        $result['attr'] = $attrCollect ? array_values($attrCollect) : [];
        // 商品参数
        $result['parameter'] = $goodsParameter
            ->where([
                ['goods_id', '=', $param['goods_id']],
            ])
            ->field('parameter_name,parameter_val')
            ->order('goods_parameter_id', 'asc')
            ->select();
        // 获取父级ID
        $classify = getParCate($result['goods_classify_id'], $goodsClassify);
        $result['first_goods_classify_id'] = 0;
        // 优惠券
        $result['coupon'] = [];
        if ($classify && $result['store_id']) {
            $result['first_goods_classify_id'] = reset($classify)['goods_classify_id'];
            // 优惠券
            $couponWhere = [
                ['modality', '=', 0],           // 非大转盘
                ['status', '=', 1],
                ['receive_end_time', '>=', date('Y-m-d')],
                ['receive_start_time', '<=', date('Y-m-d')],
                ['exchange_num', '>', 0],
                ['is_integral_exchage', '=', 0],
                ['is_gift', '=', 0],
            ];
            $couponRawWhere = [
                '(type = 0 and classify_str = ' . $result['store_id'] . ')',
                '(type = 1 and find_in_set(\'' . $result['first_goods_classify_id'] . '\',classify_str))',
            ];
            $result['coupon'] = $coupon
                ->where($couponWhere)
                ->whereRaw(implode('or', $couponRawWhere))
                ->field('coupon_id,full_subtraction_price,actual_price,limit_num')
                ->order('actual_price', 'desc')
                ->limit(2)
                ->append(['member_state'])
                ->select();
        }
        // 促销 满减
        $result['promotion'] = [];
        // 评价
        $result['evaluate'] = $goodsEvaluate
            ->alias('evaluate')
            ->join('member member', 'member.member_id = evaluate.member_id')
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['evaluate.status', '=', 1],
            ])
            ->field('star_num,content,create_time,attr,avatar,nickname,is_anonymous')
            ->order('create_time', 'desc')
            ->find() ?: json([]);
        // 店铺推荐
        $result['recommend'] = $goodsModel
            ->where([
                ['goods_id', '<>', $param['goods_id']],
                ['store_id', '=', $result['store_id']],
                ['store_recommend', '=', 1],
                ['is_putaway', '=', 1],
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
            ])
            ->field('goods_id,file,goods_name,shop_price,market_price,
            is_limit,is_bargain,is_group,is_vip,group_price,cut_price,time_limit_price')
            ->order('sort', 'desc')
            ->limit(3)
            ->select();
        // 排行榜
        $result['ranking'] = $goodsModel
            ->where([
                ['store_id', '=', $result['store_id']],
                ['is_putaway', '=', 1],
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
            ])
            ->field('goods_id,file,goods_name,shop_price,is_limit,market_price,
            is_bargain,is_group,is_vip,group_price,cut_price,time_limit_price')
            ->order('sales_volume', 'desc')
            ->limit(3)
            ->select();
        // todo 限时抢购
        $result['limit_purchase_used'] = $result['limit_number'] = $result['limit_purchase'] = $result['limit_sales_volume'] = 0;
        if ($result['is_limit'] == 1) {
            // 查询数据
            $limit_find = $limit
                ->where([
                    ['goods_id', '=', $param['goods_id']],
                    ['status', '=', 1],
                    ['up_shelf_time', '<=', date('Y-m-d')],
                    ['down_shelf_time', '>=', date('Y-m-d')],
                ])
                ->field('limit_purchase,available_sale,exchange_num,interval_id,up_shelf_time')
                ->order(['limit_id' => 'desc'])
                ->find();
            // 查询限时抢购订单此人购买多少
            if (!is_null($limit_find)) {
                $result['limit_purchase_used'] = $orderGoods
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['goods_id', '=', $param['goods_id']],
                        ['create_time', '>=', $limit_find['up_shelf_time']],
                        ['status', 'not in', '6.1'],
                        ['is_limit', '=', 1],
                    ])->sum('quantity');
                $result['limit_number'] = $limit_find['exchange_num'];
                $result['limit_purchase'] = $limit_find['limit_purchase'];
                $result['limit_sales_volume'] = $limit_find['available_sale'] - $limit_find['exchange_num'];
            }
        }
        if ($param['member_id']) {
            // 记录ID
            $record_goods_id = $recordGoods
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['goods_id', '=', $param['goods_id']],
                    ['create_time', '=', date('Y-m-d')],
                ])
                ->value('record_goods_id');
            if ($record_goods_id) {
                $recordGoods
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save([
                        'record_goods_id' => $record_goods_id,
                        'update_time' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                // 获取分类
                $param['goods_classify_id'] = $result['goods_classify_id'];
                $recordGoods->allowField(true)->isUpdate(false)->save($param);
            }
        }
        // 折扣
        $discount = discount($param['member_id']) ?: '100';
        $applet_goods_code_file = 'qr_code/goods/goods_' . $param['goods_id'] . '.png';
        $applet_distribution_code_file = $app_distribution_code_file = $mobile_goods_code_file = '';
        if (config('user.is_show_two_code')) {
            if (!empty($result['distribution']) && $result['distribution']['distribution_id']) {
                $applet_distribution_code_file = 'qr_code/distribution/dis_' . $param['goods_id'] . '_' . $result['distribution']['distribution_id'] . '.png';
                if (!is_file(Env::get('root_path') . 'public/' . $applet_distribution_code_file)) {
                    $app = Factory::miniProgram(config('wechat.')['applet']);
                    $response = $app->app_code->getUnlimit('goods,' . $param['goods_id'] . '-sup_id,' . $result['distribution']['distribution_id'], [
                        'width' => 600,
                        'page' => 'nearby_shops/good_detail/good_detail',
                    ]);
                    if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
                        $response->saveAs('./qr_code/distribution', 'dis_' . $param['goods_id'] . '_' . $result['distribution']['distribution_id'] . '.png');
                    }
                }
                $app_distribution_code_file = $qrCode->goods_distribution_qrCode($param['goods_id'], $result['distribution']['distribution_id']);
            }
            if (!is_file(Env::get('root_path') . 'public/' . $applet_goods_code_file)) {
                $app = Factory::miniProgram(config('wechat.')['applet']);
                $response = $app->app_code->getUnlimit('goods,' . $param['goods_id'], [
                    'width' => 600,
                    'page' => 'nearby_shops/good_detail/good_detail',
                ]);
                if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
                    $response->saveAs('./qr_code/goods', 'goods_' . $param['goods_id'] . '.png');
                }
            }
            if (array_key_exists('mobile_domain', $param) && $param['mobile_domain']) {
                $mobile_goods_code_file = $qrCode->goods_qrCode($param['goods_id'], $param['mobile_domain']);
            }
        }
        
        
        $_storePhone = $param['goods_id'];
        
        return $crypt->response([
            'code' => 0,
            'result' => $result,
            'discount' => $discount,
            'applet_goods_code_file' => Request::instance()->domain() . '/' . $applet_goods_code_file,
            'applet_distribution_code_file' => Request::instance()->domain() . '/' . $applet_distribution_code_file,
            'app_goods_code_file' => $qrCode->goods_qrCode($param['goods_id']),
            'app_distribution_code_file' => $app_distribution_code_file,
            'mobile_goods_code_file' => $mobile_goods_code_file,
            'mobile_distribution_code_file' => '',
        ], true);
    }
    
    /**
     * 配送说明
     * @param RSACrypt $crypt
     * @param Store $store
     * @param MemberAddress $memberAddress
     * @param GoodsModel $goodsModel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function shipping_instructions(RSACrypt $crypt,
                                          Store $store,
                                          MemberAddress $memberAddress,
                                          GoodsModel $goodsModel)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        // 店铺
        $result = $store
            ->where([
                ['store_id', '=', $param['store_id']],
            ])
            ->field('is_shop,is_express,is_city,is_delivery,city_explain')
            ->find();
        // 我的地址
        $address = $memberAddress
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->field('lat,lng,province,city,area,street')
            ->order('is_default', 'desc')
            ->append(['address_info'])
            ->find();
        if (is_null($address)) {
            $address = $param;
            $addressInfo = $memberAddress->optAddress($param);
            $addressInfo = array_filter($addressInfo);
            $address['street_id'] = end($addressInfo);
        } else {
            $address = array_merge($address->toArray(), $address['address_info']);
        }
        $goods_info = $goodsModel
            ->where('goods_id', $param['goods_id'])
            ->field('goods_id,store_id,freight_id,is_freight,goods_weight')
            ->find();
        if (empty($goods_info)) {
            return $crypt->response([
                'code' => -11,
                'message' => '商品不存在或已下架',
            ]);
        }
        $discount = discount($param['member_id']) ?: '100';
        $freightService = app('app\\common\\service\\Freight',
            [
                'args' => [
                    [
                        'goods_id' => $goods_info['goods_id'],
                        'store_id' => $goods_info['store_id'],
                        'goods_attr' => '',
                        'freight_id' => $goods_info['freight_id'],
                        'quantity' => $param['goods_number'],
                        'sub_price' => fmtPrice($param['goods_price'] * $discount / 100 * $param['goods_number']),
                        'goods_weight' => $goods_info['goods_weight'],
                        'is_freight' => $goods_info['is_freight'],
                    ],
                ],
                'address' => [
                    'street_id' => $address['street_id'] ?: $address['area_id'],
                    'city_name' => $address['city'],
                    'lat' => $address['lat'],
                    'lng' => $address['lng'],
                ],
            ]);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'address' => $address,
            'freightService' => $freightService->calculation(),
        ], true);
    }
    
    /**
     * 门店自提
     * @param RSACrypt $crypt
     * @param Take $take
     * @param Store $store
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function take_list(RSACrypt $crypt,
                              Take $take,
                              Store $store)
    {
        $param = $crypt->request();
        $take->valid($param, 'take_list');
        $condition = [
            ['store_id', '=', $param['store_id']],
            ['status', '=', 1],
        ];
        // 自提点名称|所在地
        if (!empty($param['keyword'])) {
            $condition[] = ['take_name|address', 'like', '%' . $param['keyword'] . '%'];
        }
        // 地区
        if (!empty($param['area'])) {
            $condition[] = ['area', '=', $param['area']];
        }
        // 门店自提
        $result = $take
            ->where($condition)
            ->field('take_id,take_name,contacts_phone,start_hours,end_hours,address,
            lat,lng,round(st_distance(point(lng,lat),
            point(' . $param['lng'] . ',' . $param['lat'] . '))*111.195,3) AS distance')
            ->order('distance', 'asc')
            ->select();
        $prov = $store
            ->where([
                ['store_id', '=', $param['store_id']],
            ])
            ->field('city')
            ->append(['city_id'])
            ->find();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'province' => $prov,
        ], true);
    }
    
    /**
     * 商品评价
     * @param RSACrypt $crypt
     * @param GoodsEvaluate $goodsEvaluate
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function evaluate_list(RSACrypt $crypt,
                                  GoodsEvaluate $goodsEvaluate)
    {
        $param = $crypt->request();
        $goodsEvaluate->valid($param, 'evaluate_list');
        $condition = [
            ['goods_id', '=', $param['goods_id']],
            ['e.status', '=', 1],
        ];
        $order = ['create_time' => 'desc'];
        if ($param['newest']) {
            $order = ['create_time' => 'desc'];
        }
        // 有图
        if ($param['file']) {
            $condition[] = ['multiple_file', '<>', ''];
        }
        // 视频
        if ($param['video']) {
            $condition[] = ['video', '<>', ''];
        }
        // 好评
        switch ($param['star_level']) {
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
        $result = $goodsEvaluate
            ->alias('e')
            ->join('member m', 'm.member_id = e.member_id')
            ->where($condition)
            ->field('star_num,goods_id,is_anonymous,content,reply,create_time,attr,avatar,
            nickname,multiple_file,video,is_anonymous')
            ->order($order)
            ->append(['video_snapshot'])
            ->paginate(5, false, $param);
        
        // 全部
        $statistics['all'] = $goodsEvaluate
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['status', '=', 1],
            ])
            ->count();
        
        // 好评
        $statistics['good'] = $goodsEvaluate
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['status', '=', 1],
                ['star_num', ['<', 6], ['>', 3], 'and'],
            ])
            ->count();
        
        // 中评
        $statistics['medium'] = $goodsEvaluate
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['status', '=', 1],
                ['star_num', '=', 3],
            ])
            ->count();
        
        // 差评
        $statistics['negative'] = $goodsEvaluate
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['status', '=', 1],
                ['star_num', ['<', 3], ['>', 0], 'and'],
            ])
            ->count();
        
        // 有图
        $statistics['file'] = $goodsEvaluate
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['status', '=', 1],
                ['multiple_file', '<>', ''],
            ])
            ->count();
        
        // 视频
        $statistics['video'] = $goodsEvaluate
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['status', '=', 1],
                ['video', '<>', ''],
            ])
            ->count();
        
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'statistics' => $statistics,
        ], true);
    }
    
    /**
     * 商品优惠券列表
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param Coupon $coupon
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function coupon_list(RSACrypt $crypt,
                                GoodsModel $goodsModel,
                                Coupon $coupon,
                                GoodsClassify $goodsClassify)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $goodsModel->valid($param, 'coupon_list');
        $where = [
            ['exchange_num', '>', 0],
            ['modality', '=', 0],       //优惠券形式： 0 普通 1 大转盘活动
            ['receive_end_time', '>=', date('Y-m-d')],
            ['receive_start_time', '<=', date('Y-m-d')],
            ['is_integral_exchage', '=', 0],        //是否参与积分兑换：0否 1是
            ['status', '=', 1],
            ['is_gift', '=', 0],
        ];
        $whereClassify = [];
        // 筛选分类
        if ($param['store_id']) {
            $whereClassify[] = '(type = 0 and classify_str = ' . $param['store_id'] . ' )';
        }
        if ($param['goods_classify_id']) {
            foreach (explode(',', (string)$param['goods_classify_id']) as $_cla) {
                $getPar = getParCate($_cla, $goodsClassify);
                if (!empty($getPar)) {
                    $whereClassify[] = '(type = 1 and find_in_set(\'' . $getPar[0]['goods_classify_id'] . '\',classify_str))';
                }
            }
        }
        // 优惠券
        $result = $coupon
            ->where($where)
            ->where(implode(' or ', $whereClassify))
            ->field('coupon_id,type,classify_str,full_subtraction_price,actual_price,
                    total_num,exchange_num,limit_num,start_time,end_time')
            ->withCount(['memberCoupon' => function ($e) use ($param) {
                if ($param['member_id']) {
                    $e->where([['member_id', '=', $param['member_id']]]);
                }
            }])
            ->order(['actual_price' => 'desc'])
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    
    /**
     * 商品属性获取价格和图片
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param Products $products
     * @param Limit $limit
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function attr_find(RSACrypt $crypt,
                              GoodsModel $goodsModel,
                              Products $products,
                              Limit $limit)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $goodsModel->valid($param, 'attr_find');
        // 读取价格和图片
        $result = $products
            ->where([
                ['goods_attr', '=', $param['goods_attr']],
                ['goods_id', '=', $param['goods_id']],
            ])
            ->field('goods_id,products_id,attr_shop_price,attr_group_price,attr_cut_price,
            attr_time_limit_price,attr_goods_number,attr_file')
            ->append(['attr_file_img', 'is_vip'])
            ->find();
        // 查找活动库存
        if (!is_null($result)) {
            switch ($param['type']) {
                case 2:
                    // 拼团
                    break;
                case 3:
                    // 砍价
                    break;
                case 4:
                    // 限购
                    $time_num = $limit
                        ->alias('l')
                        ->where([
                            ['l.goods_id', '=', $param['goods_id']],
                            ['l.status', '=', 1],
                        ])
                        ->join('goods g', 'g.goods_id = l.goods_id')
                        ->join('products p', 'p.goods_id = g.goods_id and p.goods_attr = \'' . $param['goods_attr'] . "'", 'left')
                        ->field('l.exchange_num,p.attr_time_limit_number')
                        ->find();
                    $result['attr_goods_number'] = $time_num['attr_time_limit_number'] ?: $time_num['exchange_num'];
                    break;
                default:
                    // 默认
                    break;
            }
        }
        $discount = discount($param['member_id']) ?: '100';
        return $crypt->response([
            'code' => 0,
            'result' => $result,
            'discount' => $discount,
        ], true);
    }
    
    /**
     * 收藏商品
     * @param RSACrypt $crypt
     * @param Store $store
     * @param GoodsModel $goodsModel
     * @param CollectGoods $collectGoods
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect_goods(RSACrypt $crypt,
                                  Store $store,
                                  GoodsModel $goodsModel,
                                  CollectGoods $collectGoods)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $goodsModel->valid($param, 'collect_goods');
        $store_id = $store
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->value('store_id');
        if ($param['store_id'] == $store_id) {
            return $crypt->response([
                'code' => -1,
                'message' => '自己不能关注自己的商品',
            ]);
        }
        $collect_id = $collectGoods
            ->where([
                ['member_id', '=', $param['member_id']],
                ['goods_id', '=', $param['goods_id']],
            ])
            ->value('collect_goods_id');
        if ($collect_id) {
            return $crypt->response([
                'code' => -1,
                'message' => '您已经关注过该商品,请勿重复关注',
            ], true);
        }
        Db::startTrans();
        $collectGoods
            ->allowField(true)
            ->save($param);
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '收藏成功',
        ], true);
    }
    
    /**
     * 降价通知
     * @param RSACrypt $crypt
     * @param Store $store
     * @param GoodsModel $goodsModel
     * @param CollectGoods $collectGoods
     * @param GoodsReductionNotic $goodsReductionNotic
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function depreciate_goods(RSACrypt $crypt,
                                     Store $store,
                                     GoodsModel $goodsModel,
                                     CollectGoods $collectGoods,
                                     GoodsReductionNotic $goodsReductionNotic)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $goodsModel->valid($param, 'depreciate_goods');
        $store_id = $store
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->value('store_id');
        if ($param['store_id'] == $store_id) {
            return $crypt->response([
                'code' => -1,
                'message' => '自己不能关注自己的商品',
            ], true);
        }
        $data = [
            'member_id' => $param['member_id'],
            'goods_id' => $param['goods_id'],
            'price' => $param['goods_price'],
            'current_price' => $param['goods_price'],
            'expected_price' => $param['price'],
        ];
        $collect_id = $collectGoods
            ->where([
                ['member_id', '=', $param['member_id']],
                ['goods_id', '=', $param['goods_id']],
            ])
            ->value('collect_goods_id');
        Db::startTrans();
        if ($collect_id) {
            $collectGoods
                ->allowField(true)
                ->save([
                    'price' => $param['price'],
                ], [
                    'collect_goods_id' => $collect_id,
                ]);
        } else {
            $collectGoods
                ->allowField(true)
                ->save($param);
        }
        // 降价通知表
        $goodsReductionNotic
            ->allowField(true)
            ->save($data);
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '发送成功',
        ], true);
    }
    
    /**
     * 收藏商品列表
     * @param RSACrypt $crypt
     * @param CollectGoods $collectGoods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect_goods_list(RSACrypt $crypt,
                                       CollectGoods $collectGoods)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $where = [
            ['cg.member_id', '=', $param['member_id']],
        ];
        if (!self::$oneOrMore) {
            array_push($where, ['s.store_id', '=', self::$oneStoreId]);
        }
        $result = $collectGoods
            ->alias('cg')
            ->join('goods g', 'g.goods_id = cg.goods_id')
            ->join('store s', 's.store_id = g.store_id')
            ->where($where)
            ->field('collect_goods_id,g.goods_id,g.store_id,goods_name,shop_price,g.is_group,
            g.is_bargain,g.is_limit,price,group_price,cut_price,time_limit_price,group_num,file,
            goods_number,is_vip,attr_type_id,file as cart_file,g.market_price,g.delete_time as goods_delete_time,
            g.is_putaway,g.review_status,s.status,s.delete_time as store_delete_time,s.end_time')
            ->append(['attribute_list', 'is_invalid'])
            ->hidden(['is_putaway', 'review_status', 'status', 'goods_delete_time', 'store_delete_time', 'end_time'])
            ->order('cg.create_time', 'desc')
            ->paginate($collectGoods->pageLimits, false);
        // 矫正计数
        if ($result->total() >= 0) {
            $prefix = Config::get('cache.default')['prefix'];
            Cache::handler()->zAdd($prefix . 'collect_goods', $result->total(), $param['member_id']);
        }
        $discount = discount($param['member_id']);
        return $crypt->response([
            'code' => 0,
            'result' => $result,
            'discount' => $discount,
        ], true);
    }
    
    /**
     * 收藏商品删除
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param CollectGoods $collectGoods
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect_goods_delete(RSACrypt $crypt,
                                         GoodsModel $goodsModel,
                                         CollectGoods $collectGoods)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $goodsModel->valid($param, 'collect_goods_delete');
        $state = $collectGoods::destroy($param['collect_goods_id'], true);
        if ($state) {
            $redisInstance = Cache::handler();
            $prefix = Config::get('cache.default')['prefix'];
            $score = $redisInstance->zScore($prefix . 'collect_goods', $param['member_id']);
            $count = count(explode(',', $param['collect_goods_id']));
            if ($score > $count) {
                $redisInstance->zIncrBy($prefix . 'collect_goods', -$count, $param['member_id']);
            } else {
                $redisInstance->zAdd($prefix . 'collect_goods', 0, $param['member_id']);
            }
            $goodsModel->where([
                ['goods_id', 'in', $param['goods_id']],
            ])->setDec('collect_number');
        }
        return $crypt->response([
            'code' => 0,
            'message' => '删除成功',
        ], true);
    }
    
    /**
     * 商品详情收藏商品删除
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param CollectGoods $collectGoods
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function view_collect_goods_delete(RSACrypt $crypt,
                                              GoodsModel $goodsModel,
                                              CollectGoods $collectGoods)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $goodsModel->valid($param, 'view_collect_goods_delete');
        $state = $collectGoods
            ->where([
                ['member_id', '=', $param['member_id']],
                ['goods_id', '=', $param['goods_id']],
            ])
            ->field('collect_goods_id')
            ->find();
        Db::startTrans();
        $state->delete();
        $goodsModel->where([
            ['goods_id', '=', $param['goods_id']],
        ])->setDec('collect_number');
        
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '取消收藏成功',
        ], true);
    }
    
    /**
     * 好物推荐 - 精选
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function choiceness_list(RSACrypt $crypt,
                                    GoodsModel $goodsModel)
    {
        // 获取参数
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        // 人气
        $result['popularity'] = $goodsModel
            ->where([
                ['goods_number', '>', 0],
                ['is_putaway', '=', 1],
                ['review_status', '=', 1],
                ['is_popularity', '=', 1],
                ['is_group', '=', 0],
                ['is_bargain', '=', 0],
                ['is_limit', '=', 0],
            ])
            ->field('goods_id,goods_name,file,shop_price,is_vip,is_limit,is_group,is_bargain,
            group_price,market_price,cut_price,time_limit_price')
            ->order(['sales_volume' => 'desc', 'goods_id' => 'desc'])
            ->select();
        // 特惠
        $result['preference'] = $goodsModel
            ->where([
                ['goods_number', '>', 0],
                ['is_putaway', '=', 1],
                ['review_status', '=', 1],
                ['is_preference', '=', 1],
                ['is_group', '=', 0],
                ['is_bargain', '=', 0],
                ['is_limit', '=', 0],
            ])
            ->field('goods_id,goods_name,file,shop_price,is_vip,is_limit,is_group,is_bargain,
            group_price,market_price,cut_price,time_limit_price')
            ->order(['sales_volume' => 'desc', 'goods_id' => 'desc'])
            ->select();
        $discount = discount($param['member_id']);
        return $crypt->response([
            'code' => 0,
            'result' => $result,
            'discount' => $discount,
        ], true);
    }
    
    /**
     * 好物推荐 - 分类
     * @param RSACrypt $crypt
     * @param GoodsModel $goodsModel
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function good_recommend_list(RSACrypt $crypt,
                                        GoodsModel $goodsModel,
                                        GoodsClassify $goodsClassify)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $goodsModel->valid($param, 'good_recommend_list');
        // 好物推荐
        $result = $goodsModel
            ->where([
                ['goods_number', '>', 0],
                ['is_putaway', '=', 1],
                ['review_status', '=', 1],
                ['is_group', '=', 0],
                ['is_bargain', '=', 0],
                ['is_limit', '=', 0],
                ['is_popularity', '=', 1],
                ['goods_classify_id', 'in', implode(',', array_column(getParCate($param['goods_classify_id'], $goodsClassify, 0), 'goods_classify_id'))],
            ])
            ->field('goods_id,goods_name,file,shop_price,is_group,is_bargain,
            freight_status,group_price,cut_price,group_num,is_vip,attr_type_id,
            file as cart_file,goods_number,store_id,is_limit,time_limit_price')
            ->append(['attribute_list', 'limit_state'])
            ->paginate(20, false);
        $discount = discount($param['member_id']);
        return $crypt->response([
            'code' => 0,
            'result' => $result,
            'discount' => $discount,
        ], true);
    }
}