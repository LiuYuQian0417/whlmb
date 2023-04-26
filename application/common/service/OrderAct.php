<?php
declare(strict_types = 1);

namespace app\common\service;

use app\common\model\Cart;
use app\common\model\Consumption;
use app\common\model\CutActivity;
use app\common\model\CutGoods;
use app\common\model\Goods;
use app\common\model\GroupActivity;
use app\common\model\GroupGoods;
use app\common\model\Limit;
use app\common\model\Member;
use app\common\model\MemberAddress;
use app\common\model\MemberCoupon;
use app\common\model\MemberPacket;
use app\common\model\Order;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\Products;
use app\common\model\Store;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Hook;
use GuzzleHttp\Client;

/**
 * 订单处理
 * Class OrderAct
 * @package app\common\service
 */
class OrderAct
{
    /**
     * 活动类型 1普通 2拼团 3砍价 4限时抢购
     * @var int
     */
    private $type = 1;
    /**
     * 平台优惠券金额(每单仅限一张)
     * @var int
     */
    private $platformCouponPrice = 0;
    /**
     * 数据集
     * @var array
     */
    private $args = [];
    
    /**
     * 下单数据
     * @var array
     */
    private $set = [];
    /**
     * 返回主表数据集合
     * @var array
     */
    private $retData = [];
    /**
     * 返回子表数据集合
     * @var array
     */
    private $retAttachData = [];
    /**
     * 返回订单商品数据集合
     * @var array
     */
    private $retGoodsData = [];
    /**
     * 实例集合
     * @var array
     */
    private $instanceArr = [];
    /**
     * 锁集合
     * @var array
     */
    private $lockKey = [];
    /**
     * 用户地址数据集合
     * @var array
     */
    private $address = [];
    /**
     * 商品订单默认状态(未支付)
     * @var float
     */
    private $retGoodsStatus = 0.1;
    /**
     * 店铺总金额被减金额数据
     * @var array
     */
    private $subtotal_price = [];
    /**
     * 购物返利积分比例
     * @var int
     */
    private $scale = 0;
    /**
     * 缓存前缀
     * @var
     */
    private $prefix;
    /**
     * distribution_goods 升级分销商指定商品
     * goods 分销可分佣商品
     * @var
     */
    private $distArr = ['member_id' => '', 'distribution_goods' => [], 'goods' => [],];
    
    /**
     * 构造
     * OrderAct constructor.
     */
    public function __construct()
    {
        $this->instanceArr = [
            'memberCouponModel' => app('app\\common\\model\\MemberCoupon'),
            'groupActivityModel' => app('app\\common\\model\\GroupActivity'),
            'groupActivityAttachModel' => app('app\\common\\model\\GroupActivityAttach'),
            'lock' => app('app\\common\\service\\Lock'),
            'redisService' => Cache::handler(),
        ];
        // 切换库
        $this->instanceArr['redisService']->select(1);
        $this->prefix = Config::get('cache.default')['prefix'];
        Env::load(Env::get('app_path') . 'common/ini/.config');
    }
    
    /**
     * 输出订单数据
     * @param $args
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function outData($args)
    {
        $this->type = $args['order_type'];
        $this->args = $args;
        if (!is_array($args['store_set'])) {
            $args['store_set'] = json_decode($args['store_set'], true);
        }
        $this->distArr['member_id'] = $args['member_id'];
        // 检测是否需要收货地址
        $disTypeArr = array_column($args['store_set'], 'distribution_type');
        if ((in_array(1, $disTypeArr) || in_array(3, $disTypeArr)) && !$this->args['member_address_id']) {
            // 同城速递, 快递邮寄若存在并收货地址为空,则异常
            return [
                'code' => -1,
                'message' => '收货地址不可为空',
            ];
        }
        // 下单渠道 1立即购买 2购物车
        $this->set = ($args['pay_channel'] == 1) ? reset($args['store_set']) : $args['store_set'];
        // 订单主表初始化
        $this->retData = [
            'member_id' => $args['member_id'],
            'total_price' => 0,             //结算总价(支付价格)
            'total_cod_price' => 0,         //货到付款总金额
            'total_cut_amount' => 0,        //砍价成功的砍价总额(结算总价不包含此项)
            'total_coupon_price' => 0,      //结算总优惠券金额
            'total_promotion_price' => 0,   //结算总促销金额
            'total_fullSub_price' => 0,     //结算总满减金额
            'total_packet_price' => 0,      //结算红包金额
            'total_freight' => 0,           //结算总运费金额
            'total_used_integral' => 0,     //结算使用积分
            'total_back_integral' => 0,     //结算赠送总积分
            'order_type' => $this->type,    //订单类型 1普通 2拼团 3砍价 4限时抢购
            'origin_type' => isset($this->args['origin_type']) ? $this->args['origin_type'] : 2,    //端订单来源 1app 2小程序 3pc 4手机站wap 5线下支付
            'member_address_id' => $this->args['member_address_id'] ?: null,    //关联用户配送地址id
            'used_platform_member_coupon_id' => null,               //已使用的平台优惠券
        ];
        // 若含有用户收货地址则合并收货人信息到主表
        if ($this->args['member_address_id']) {
            self::getAddress();
        }
        // *pay_channel* 1 立即购买流程 *2 购物车流程
        $ret = ($this->args['pay_channel'] == 1) ? self::immediate() : self::cart();
        if ($ret) {
            return $ret;
        }
        // 总返利积分
        $this->retData['total_back_integral'] = floor($this->scale / 100 * $this->retData['total_price']);
        // 更改总金额格式
        $this->retData['total_price'] = fmtPrice($this->retData['total_price']);
        return [
            'code' => 0,
            'data' => $this->retData,               //总订单数据
            'attachData' => $this->retAttachData,   //店铺订单数据
            'goodsData' => $this->retGoodsData,     //商品订单数据
            'distribution' => $this->distArr,       //分销数据(货到付款用)
            'group_activity_attach_id' => $this->groupActivityAttachId,     //拼团活动附表id
        ];
    }
    
    /**
     * 查询会员发货地址
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getAddress()
    {
        // 获取用户地址
        $addressInfo = (new MemberAddress())
            ->where([
                ['member_address_id', '=', $this->args['member_address_id']],
            ])
            ->field('name as consignee_name,phone as consignee_phone,province,city,area,street,
            province as address_province,city as address_city,area as address_area,street,province,
            street as address_street,address as address_details,lng as address_lng,lat as address_lat')
            ->append(['address_info'])
            ->find();
        if ($addressInfo) {
            $this->retData = array_merge($this->retData, $addressInfo->toArray());
            $this->address = [
                'street_id' => $addressInfo['address_info']['street_id'] ?: $addressInfo['address_info']['area_id'],
                'lng' => $addressInfo['address_lng'],
                'lat' => $addressInfo['address_lat'],
            ];
        }
    }
    
    
    /**
     * 立即购买
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    private function immediate()
    {
        $productModel = new Products();
        $goodsModel = new Goods();
        if ($this->set['products_id']) {
            // 含规格
            $data = $productModel
                ->alias('p')
                ->where([
                    ['p.products_id', '=', $this->set['products_id']],
                    // 已上架商品
                    ['g.is_putaway', '=', 1],
                ])
                ->join('goods g', 'g.goods_id = p.goods_id')
                ->join('limit l', 'l.goods_id = g.goods_id and l.delete_time is null and l.status = 1 and unix_timestamp(l.down_shelf_time) >= unix_timestamp(now())', 'left')
                ->field('p.attr_shop_price as shop_price,p.attr_group_price as group_price,p.attr_goods_weight as goods_weight,
                p.attr_cut_price as cut_price,p.attr_time_limit_price as time_limit_price,p.attr,g.is_distribution,g.is_distributor,
                p.attr_goods_number as goods_number,p.attr_time_limit_number,l.exchange_num,p.goods_attr,g.distribution_set,
                g.is_vip,g.is_sub,g.is_freight,g.freight_status,g.freight_price,g.freight_id,g.goods_id,g.store_id,
                g.goods_name,g.goods_name_style,g.spell_goods_name,g.goods_sn,p.attr_file as file,g.describe,g.rebates_type,
                l.limit_id,l.limit_purchase,l.create_time as limit_create_time,p.attr_cost_price as cost_price,g.goods_unit,g.is_group,
                g.is_bargain,g.is_limit')
                ->find();
            if (!$data) {
                return [
                    'code' => -1,
                    'message' => '商品已下架或不存在',
                ];
            }
            // 检测规格实时性
            if ($data['goods_attr'] != $this->set['goods_attr']) {
                return [
                    'code' => -5,
                    'message' => '商品规格不存在,请更改',
                ];
            }
            array_push($this->lockKey, 'products_id_' . $this->set['products_id']);
        } else {
            // 不含规格
            $data = $goodsModel
                ->alias('g')
                ->where([
                    ['g.goods_id', '=', $this->args['id_set']],
                    ['is_putaway', '=', 1],     // 已上架商品
                ])
                ->join('limit l', 'l.goods_id = g.goods_id and l.delete_time is null and l.status = 1 and unix_timestamp(l.down_shelf_time) >= unix_timestamp(now())', 'left')
                ->field('shop_price,time_limit_price,group_price,cut_price,g.goods_id,store_id,g.rebates_type,
                goods_number,is_vip,is_sub,is_freight,goods_name,goods_name_style,l.exchange_num,g.goods_weight,
                l.limit_id,l.limit_purchase,l.create_time as limit_create_time,freight_status,freight_price,
                freight_id,spell_goods_name,file,goods_sn,`describe`,cost_price,g.goods_unit,g.distribution_set,
                g.is_distribution,g.is_distributor,g.is_group,g.is_bargain,g.is_limit')
                ->find();
        }
        if (empty($data)) {
            return [
                'code' => -1,
                'message' => '商品已下架或不存在',
            ];
        }
        if (!array_key_exists('distribution_type', $this->set) || !$this->set['distribution_type']) {
            return [
                'code' => -1,
                'message' => '收货地址不在商家配送范围内,请更换收货地址或商家',
            ];
        }
        // 检测拼团限购上限
        if ($this->type == 2) {
            $groupGoodsRet = self::checkGroupLimit($data['goods_id'], $this->args['member_id'], $this->set['quantity']);
            if ($groupGoodsRet['code']) {
                return $groupGoodsRet;
            }
        }
        array_push($this->lockKey, 'goods_id_' . $this->args['id_set']);
        // 检测库存[是否为限时抢购]
        $inventory = ($this->type == 4) ? $data['exchange_num'] : $data['goods_number'];
        $timeCheck = $this->type == 4 && $this->set['products_id'] ? $data['attr_time_limit_number'] < $this->set['quantity'] : false;
        // 库存不足甩异常
        if ($inventory < $this->set['quantity'] || $timeCheck) {
            return [
                'code' => -1,
                'message' => '库存不足',
            ];
        }
        if ($this->type == 4) {
            array_push($this->lockKey, 'limit_id_' . $data['limit_id']);
            // 查询用户此限购商品购买次数
            $limitBuyTimes = self::getLimitTimes($this->args['member_id'], $data['limit_create_time'], $data['goods_id']);
            if (!empty($limitBuyTimes) && $limitBuyTimes['count'] >= $data['limit_purchase'] && $data['limit_purchase'] > 0) {
                return [
                    'code' => -1,
                    'message' => '商品已达限购次数',
                ];
            }
        }
        $lockData = $this->instanceArr['lock']->lock($this->lockKey, 10000);
        if ($lockData === false) {
            return [
                'code' => -1,
                'message' => '网络繁忙,请重试',
            ];
        }
        // 库存充足扣除库存(主商品[从规格])
        $goodsModel
            ->where([
                ['goods_id', '=', $this->args['id_set']],
            ])
            ->setDec('goods_number', $this->set['quantity']);
        // 更新限时抢购剩余数量
        if ($this->type == 4) {
            (new Limit())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                    ['status', '=', 1],
                ])
                ->setDec('exchange_num', $this->set['quantity']);
        }
        // 含规格扣除规格库存[是否为限时抢购]
        if ($this->set['products_id'] !== '') {
            $productModel
                ->where([
                    ['products_id', '=', $this->set['products_id']],
                ])
                ->setDec(($this->type == 4) ? 'attr_time_limit_number' : 'attr_goods_number', $this->set['quantity']);
        }
        $this->instanceArr['lock']->unlock($lockData);
        // 定价[店铺默认价,拼团默认价(拼团规格价),砍价默认价(砍价规格价),抢购默认价(抢购规格价),线下(店铺默认价)]
        $typeText = ['shop_price', 'group_price', 'cut_price', 'time_limit_price', 'shop_price'];
        // 确定使用普通价格还是活动价
        $finalPrice = $data[$typeText[$this->type - 1]];
        // 砍价商品特殊处理
        if ($this->type == 3 && $this->args['cut_activity_id']) {
            $cutActivityData = (new CutActivity())
                ->where([
                    ['cut_activity_id', '=', $this->args['cut_activity_id']],
                ])
                ->field('original_price,cut_goods_id,present_price,cut_price,status,order_attach_id')
                ->find();
            if (is_null($cutActivityData)) {
                return [
                    'code' => -1,
                    'message' => '砍价活动不存在',
                ];
            }
            if ($cutActivityData['order_attach_id']) {
                return [
                    'code' => -2,
                    'message' => '砍价活动已关联订单,不可再次关联',
                ];
            }
            // 状态改为成功
            $cutActivityData->status = 2;
            $cutActivityData->save();
            (new CutGoods())
                ->allowField(true)
                ->isUpdate(true)
                ->save([
                    'cut_goods_id' => $cutActivityData['cut_goods_id'],
                    'sales_volume' => Db::raw('sales_volume + ' . $this->set['quantity']),
                ]);
            // 砍价成功的砍价总额(结算总价不包含此项)
            $this->retData['total_cut_amount'] = $cutActivityData['original_price'] - $cutActivityData['present_price'];
            // 支付时的砍价现价替换最终单价
            $finalPrice = $cutActivityData['present_price'];
        }
        // 考虑会员折扣[活动订单除外]
        $discount = 1;
        if (in_array($this->type, [1]) && $data['is_vip'] == 1) {
            $finalPrice *= $discount = (discount($this->retData['member_id']) / 100);
        }
        // 订单附表设值和初始化
        $this->subtotal_price[$data['store_id']] = 0;
        $this->retAttachData[$data['store_id']] = [
            'member_id' => $this->retData['member_id'],
            'store_id' => $data['store_id'],
            'pay_type' => $this->set['pay_type'],                       //支付方式 1在线支付 2货到付款
            'group_activity_id' => array_key_exists('group_activity_id', $this->args)
                ? $this->args['group_activity_id'] : null,              //关联拼团活动主表id(记录拼团跟随者的主活动)
            'cut_activity_id' => array_key_exists('cut_activity_id', $this->args)
                ? $this->args['cut_activity_id'] : null,                //关联砍价活动主表id
            'limit_goods_id' => $this->type == 4 ? $data['limit_id'] : null,//关联限时抢购商品id
            'subtotal_price' => 0,                                      //结算店铺小计(支付价格)
            'subtotal_coupon_price' => 0,                               //结算店铺优惠券(店铺优惠券)小计金额
            'subtotal_promotion_price' => 0,                            //结算店铺促销小计金额
            'subtotal_fullSub_price' => 0,                              //结算店铺满减小计金额
            'subtotal_freight_price' => 0,                              //结算店铺运费小计金额
            'subtotal_share_platform_coupon_price' => 0,                //按比例分摊平台优惠券金额(店铺小计)
            'subtotal_share_platform_packet_price' => 0,                //按比例分摊平台红包金额(店铺小计)
            'subtotal_back_integral' => 0,                              //返积分(店铺计)数
            'used_shop_member_coupon_id' => null,                       //已使用的店铺会员优惠券
            'number' => $this->set['quantity'],                         //小计总商品数
            'distribution_type' => $this->set['distribution_type'],     //配送方式 1同城速递 2预约自提 3快递邮寄
            'message' => $this->set['message'],                         //买家的店铺留言
            'after_sale_times' => Env::get('after_sale', 7),    // 售后后的售后期限
            'status' => ($this->set['pay_type'] == 2) ? 1 : 0,               //支付方式 1 在线支付[0待付款] 2货到付款[1待配送]
            'is_invoice' => array_key_exists('invoice_set', $this->set) && !empty($this->set['invoice_set']) ? 1 : 0,       // 是否开票 0否 1是
            'invoice_data' => array_key_exists('invoice_set', $this->set) ? $this->set['invoice_set'] : [],                 // 发票数据(存表时关联订单数据)
        ];
        // 预约自提
        if ($this->set['distribution_type'] == 2) {
            $this->retAttachData[$data['store_id']]['take_id'] = $this->set['take_id'];
            // 自提时间段
            //            $this->retAttachData[$data['store_id']]['take_time'] = $this->set['take_time'];
            // 生成自提码(适用条形码,二维码)
            $this->retAttachData[$data['store_id']]['take_code'] = get_order_sn();
        }
        // 设置主订单总金额和店铺小计金额(浮点型)
        $this->retData['total_price'] = $this->retAttachData[$data['store_id']]['subtotal_price'] = $finalPrice * $this->set['quantity'];
        // 店铺利润[不包含优惠]
        $profit = ($finalPrice - $data['cost_price']) * $this->set['quantity'];
        //支付方式 1 在线支付[0.1未支付] 2货到付款[普通,拼团成团,砍价,限时抢购1.1已支付拼团(未成团)1.2]
        if ($this->set['pay_type'] == 2) {
            $this->retGoodsStatus = 1.1;
            if ($data['is_distributor']) {
                // 升级分销商指定商品
                $this->distArr['distribution_goods'][] = $data['goods_id'];
            }
            if ($data['is_distribution']) {
                // 分销可分佣商品
                $this->distArr['goods'][] = [
                    'goods_id' => $data['goods_id'],
                    'store_id' => $data['store_id'],                            //店铺id
                    'profit' => $profit,                                        //交易利润
                    'actual' => $finalPrice * $this->set['quantity'],           //实收金额
                    'distribution_set' => $data['distribution_set'],            //商品分销设置
                    'rebates_type' => $data['rebates_type'],                    // 返利类型 0 现金（元） 1 百分比
                ];
            }
            if ($this->type == 2) {
                self::changeGroup(
                    $data['goods_id'],
                    $this->args['group_activity_id'],
                    $this->args['member_id'],
                    $this->set['distribution_type'],
                    $this->set['quantity'],
                    $data['goods_name'],
                    $data['store_id']
                );
            }
        }
        // 订单商品设值和初始化
        $this->retGoodsData[$data['goods_id']] = [
            'member_id' => $this->args['member_id'],
            'goods_id' => $data['goods_id'],
            'store_id' => $data['store_id'],
            'products_id' => $this->set['products_id'],
            'is_limit' => $this->type == 4 ? 1 : 0,                                     //是否是限时抢购商品 1是 0否
            'goods_attr' => isset($data['goods_attr']) ? $data['goods_attr'] : '',      //商品规格 红色,S （程序）
            'attr' => isset($data['attr']) ? $data['attr'] : '',                        //商品规格详细 颜色：红色 尺码：S（展示）
            'quantity' => $this->set['quantity'],                                       //购买数量
            'subtotal_price' => $finalPrice * $this->set['quantity'],                   //实收金额[第一步未计算优惠]
            'single_price' => $finalPrice,                                              //单品价格(此时为默认[活动]价,去会员折扣)
            'original_price' => $data['shop_price'],                                    //店铺商品默认[规格]价
            'sub_share_shop_coupon_price' => 0,                                         //商品按比例分摊店铺优惠券金额
            'sub_share_platform_coupon_price' => 0,                                     //商品按比例分摊平台优惠券金额
            'subtotal_share_platform_packet_price' => 0,                                //商品按比例分摊平台红包金额
            'sub_freight_price' => 0,                                                   //商品运费金额
            'sub_fullSub_price' => 0,                                                   //商品满减金额
            'goods_name' => $data['goods_name'],                                        //记录商品名称快照
            'goods_name_style' => $data['goods_name_style'],                            //记录商品名称样式快照
            'spell_goods_name' => $data['spell_goods_name'],                            //记录商品名称拼音版
            'goods_sn' => $data['goods_sn'],                                            //商品货号
            'file' => $data->getData('file'),                                     //记录商品缩略图
            'describe' => $data['describe'],                                            //记录商品描述
            'discount' => $discount,                                                    //会员折扣率
            'status' => $this->retGoodsStatus,                                          //商品订单状态
            'profit' => $profit,                                                        //[利润]原价(已计算折扣率)-成本价[计算第一步]
            'goods_unit' => $data['goods_unit'],                                        //商品单位
            'discount_price' => $data['is_vip'] && $this->type == 1 ? $data[$typeText[$this->type - 1]] * (1 - $discount) : 0,      //折扣差价
        ];
        // 对商品总价和子表小计价格进行二次处理
        if ($data) {
            $priceRet = self::priceHandle($data->toArray());
            if ($priceRet['code']) {
                return $priceRet;
            }
        }
    }
    
    /**
     * 检测拼团商品上限
     * @param $goods_id
     * @param $member_id
     * @param $quantity
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function checkGroupLimit($goods_id, $member_id, $quantity)
    {
        $groupGoodsData = (new GroupGoods())
            ->alias('gg')
            ->where([
                ['gg.goods_id', '=', $goods_id],
                ['gg.status', '=', 1],                           //已审核
                ['gg.down_shelf_time', '>', date('Y-m-d')],      //为下架
            ])
            ->join('goods g', 'g.goods_id = gg.goods_id')
            ->join('store s', 'g.store_id = s.store_id')
            ->field('gg.group_goods_id,gg.goods_id,gg.is_auto,gg.group_num,gg.continue_time,gg.up_shelf_time,
            gg.buy_cum_limit,gg.delete_time,s.store_name,g.file')
            ->find();
        // 拼团商品未找到
        if (!$groupGoodsData) return [
            'code' => -1,
            'message' => '拼团商品不存在或已下架',
        ];
        // 查询用户此活动之后购买此商品的数量
        $getQuantity = (new Order())
            ->alias('o')
            ->where([
                ['o.member_id', '=', $member_id],
                ['o.order_type', '=', 2],
                ['o.create_time', '>=', $groupGoodsData['up_shelf_time']],
                ['og.goods_id', '=', $groupGoodsData['goods_id']],
                ['og.status', 'not in', '6.1,0.1'],
            ])
            ->join('order_goods og', 'og.order_id = o.order_id')
            ->sum('og.quantity');
        if ($groupGoodsData['buy_cum_limit'] > 0 && ($lastQuantity = $groupGoodsData['buy_cum_limit'] - $getQuantity) < $quantity) {
            return [
                'code' => -1,
                'message' => (($lastQuantity > 0) ?
                    sprintf('此商品已购买%d件,您还可购买%d件', $getQuantity, $lastQuantity) : '商品已达购买上限'),
            ];
        }
        return [
            'code' => 0,
            'data' => $groupGoodsData,
        ];
    }
    
    /**
     * 购物车
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    private function cart()
    {
        $cartModel = new Cart();
        $data = $cartModel
            ->alias('c')
            ->join('goods g', 'g.goods_id = c.goods_id')
            ->join('limit l', 'l.goods_id = g.goods_id and l.delete_time is null and l.status = 1 and unix_timestamp(l.down_shelf_time) >= unix_timestamp(now())', 'left')
            ->join('products p', 'p.products_id = c.products_id', 'left')
            ->where([
                ['c.cart_id', 'in', $this->args['id_set']],
                // 已上架商品
                ['g.is_putaway', '=', 1],
            ])
            ->field('c.cart_id,c.member_id,ifnull(p.attr_shop_price,g.shop_price) as price,c.number,c.goods_attr,c.attr,c.products_id,
                c.goods_id,c.store_id,g.freight_status,g.freight_price,g.freight_id,g.goods_name,p.attr_file,
                g.is_freight,g.shop_price,g.goods_name,g.goods_name_style,g.spell_goods_name,g.file,g.goods_sn,
                g.goods_number,g.describe,p.attr_goods_number,l.limit_id,l.exchange_num,l.limit_purchase,
                l.create_time as limit_create_time,p.attr_time_limit_number,p.attr_cost_price,g.cost_price,
                c.goods_weight,c.discount,g.goods_unit,g.is_distribution,g.is_distributor,g.distribution_set,
                g.rebates_type,c.discount_price,g.is_group,g.is_bargain,g.is_limit')
            ->orderRaw('field(c.cart_id,' . $this->args['id_set'] . ')')
            ->select();
        if ($data->isEmpty()) {
            return [
                'code' => -2,
                'message' => '商品已下架或不存在',
            ];
        }
        $clearCartId = [];
        // 初始化商品和规格库存更新集
        $invent = ['goods_id' => [], 'products_id' => [], 'limit' => []];
        foreach ($data as $key => $value) {
            array_push($clearCartId, $value['cart_id']);
            // 检测库存
            $flag = $value['products_id'] ?
                ($this->type == 4 ? 'attr_time_limit_number' : 'attr_goods_number') :
                ($this->type == 4 ? 'exchange_num' : 'goods_number');
            if ($value[$flag] < $value['number']) {
                $msg = '商品：' . $value['goods_name'] . '，';
                if ($value['products_id']) {
                    $msg .= '规格：' . $value['goods_attr'] . '，';
                }
                return [
                    'code' => -2,
                    'message' => $msg . '库存不足',
                ];
            }
            // 更新商品库存[正常/限时抢购]数据
            array_push($invent['goods_id'], [
                'goods_id' => $value['goods_id'],
                'goods_number' => Db::raw('goods_number - ' . $value['number']),
            ]);
            // 增加商品行锁集合
            array_push($this->lockKey, 'goods_id_' . $value['goods_id']);
            // 限时抢购更新库存
            if ($this->type == 4) {
                array_push($invent['limit'], [
                    'limit_id' => $value['limit_id'],
                    'exchange_num' => Db::raw('exchange_num - ' . $value['number']),
                ]);
                array_push($this->lockKey, 'limit_id_' . $value['limit_id']);
                // 查询用户此限购商品购买次数
                $limitBuyTimes = self::getLimitTimes($this->args['member_id'], $value['limit_create_time'], $value['goods_id']);
                if (!empty($limitBuyTimes) && $limitBuyTimes['count'] >= $data['limit_purchase']) {
                    return [
                        'code' => -2,
                        'message' => '商品已达限购次数',
                    ];
                }
            }
            // 增加规格商品库存[正常/限时抢购]数据
            if ($value['products_id']) {
                $updateKey = $this->type == 4 ? 'attr_time_limit_number' : 'attr_goods_number';
                array_push($invent['products_id'], [
                    'products_id' => $value['products_id'],
                    $updateKey => Db::raw($updateKey . ' - ' . $value['number']),
                ]);
                // 增加商品规格行锁集合
                array_push($this->lockKey, 'products_id_' . $value['products_id']);
            }
            foreach ($this->set as $_key => $_value) {
                // 订单子表(按店铺)数据整理
                if ($_value['store_id'] == $value['store_id']) {
                    if (!array_key_exists($_value['store_id'], $this->retAttachData)) {
                        if (!array_key_exists('distribution_type', $_value) || !$_value['distribution_type']) {
                            return [
                                'code' => -2,
                                'message' => '收货地址不在商家配送范围内,请更换收货地址或商家',
                            ];
                        }
                        $this->subtotal_price[$_value['store_id']] = 0;
                        $this->retAttachData[$_value['store_id']] = [
                            'member_id' => $value['member_id'],
                            'store_id' => $_value['store_id'],
                            'pay_type' => $_value['pay_type'],              //支付方式 1在线支付 2货到付款
                            'group_activity_id' => array_key_exists('group_activity_id', $this->args)
                                ? $this->args['group_activity_id'] : null,  //关联拼团活动主表id(记录拼团跟随者的主活动)
                            'cut_activity_id' => array_key_exists('cut_activity_id', $this->args)
                                ? $this->args['cut_activity_id'] : null,    //关联砍价活动主表id
                            'limit_goods_id' => $this->type == 4
                                ? $value['limit_id'] : null,                //关联限时抢购商品id
                            'subtotal_price' => 0,                          //结算店铺小计(支付价格)
                            'subtotal_coupon_price' => 0,                   //结算店铺优惠券(店铺优惠券)小计金额
                            'subtotal_promotion_price' => 0,                //结算店铺促销小计金额
                            'subtotal_fullSub_price' => 0,                  //结算店铺满减小计金额
                            'subtotal_freight_price' => 0,                  //结算店铺运费小计金额
                            'subtotal_share_platform_coupon_price' => 0,    //按比例分摊平台优惠券金额(店铺小计)
                            'subtotal_share_platform_packet_price' => 0,    //按比例分摊平台红包金额(店铺小计)
                            'subtotal_back_integral' => 0,                  //返积分(店铺计)数
                            'used_shop_member_coupon_id' => null,           //已使用的店铺会员优惠券
                            'number' => 0,                                  //小计总商品数
                            'distribution_type' => $_value['distribution_type'],            //配送方式 1同城速递 2预约自提 3快递邮寄
                            'message' => $_value['message'],                //买家留言
                            'after_sale_times' => Env::get('after_sale', 7),    // 售后后的售后期限
                            'status' => ($_value['pay_type'] == 2) ? 1 : 0,             //支付方式 1 在线支付[0待付款] 2货到付款[1待配送]
                            'is_invoice' => array_key_exists('invoice_set', $_value) && !empty($_value['invoice_set']) ? 1 : 0,     // 是否开票 0否 1是
                            'invoice_data' => array_key_exists('invoice_set', $_value) ? $_value['invoice_set'] : [],                // 发票数据(存表时关联订单数据)
                        ];
                    }
                    // 预约自提
                    if ($_value['distribution_type'] == 2) {
                        $this->retAttachData[$_value['store_id']]['take_id'] = $_value['take_id'];
                        // 自提时间段
                        //                            $this->retAttachData[$_value['store_id']]['take_time'] = $_value['take_time'];
                        // 生成自提码(适用条形码,二维码)
                        $this->retAttachData[$_value['store_id']]['take_code'] = get_order_sn();
                    }
                    // 货到付款
                    if ($_value['pay_type'] == 2) {
                        if ($value['is_distributor']) {
                            // 升级分销商指定商品
                            $this->distArr['distribution_goods'][] = $value['goods_id'];
                        }
                        if ($value['is_distribution']) {
                            // 分销可分佣商品
                            $this->distArr['goods'][] = [
                                'goods_id' => $value['goods_id'],
                                'store_id' => $value['store_id'],                               //店铺id
                                'profit' => ($value['price'] - $value['discount_price'] - ($value['products_id'] ?         //[利润]原价(已计算折扣率)-成本价[计算第一步]
                                            $value['attr_cost_price'] : $value['cost_price'])) * $value['number'],                                        //交易利润
                                'actual' => $value['price'] * $value['number'],            //实收金额
                                'distribution_set' => $value['distribution_set'],            //商品分销设置
                                'rebates_type' => $value['rebates_type'],                    // 返利类型 0 现金（元） 1 百分比
                            ];
                        }
                    }
                }
            }
            if (!array_key_exists($value['store_id'], $this->retAttachData)) {
                return [
                    'code' => -1,
                    'message' => '店铺数据未找到,请重试',
                ];
            }
            if ($this->retAttachData[$value['store_id']]['pay_type'] == 2) {
                $this->retGoodsStatus = 1.1;
            }
            $aPrice = ($value['price'] - $value['discount_price']) * $value['number'];
            // 主订单累加金额,货到付款
            $this->retData['total_price'] += $aPrice;
            // 合计店铺商品数量
            $this->retAttachData[$value['store_id']]['number'] += $value['number'];
            // 合计店铺小计价格
            $this->retAttachData[$value['store_id']]['subtotal_price'] += $aPrice;
            // 订单商品数据整理
            $single_price = $value['price'] - $value['discount_price'];
            $this->retGoodsData[$value['cart_id']] = [
                'member_id' => $this->args['member_id'],
                'goods_id' => $value['goods_id'],
                'store_id' => $value['store_id'],
                'products_id' => $value['products_id'],
                'is_limit' => 0,                                                //是否是限时抢购商品 1是 0否
                'goods_attr' => $value['goods_attr'],                           //商品规格 红色,S （程序）
                'attr' => $value['attr'],                                       //商品规格详细 颜色：红色 尺码：S（展示）
                'subtotal_price' => $single_price * $value['number'],           //实收金额[X折扣]
                'single_price' => $single_price,                                //单品价格(此时为默认[活动]价,去会员折扣)
                'original_price' => $value['price'],                            //商品原价(默认价或规格价)
                'sub_share_shop_coupon_price' => 0,                             //商品按比例分摊店铺优惠券金额
                'sub_share_platform_coupon_price' => 0,                         //商品按比例分摊平台优惠券金额
                'subtotal_share_platform_packet_price' => 0,                    //商品按比例分摊平台红包金额
                'quantity' => $value['number'],                                 //购买数量
                'sub_freight_price' => 0,                                       //商品运费金额
                'sub_fullSub_price' => 0,                                       //商品满减金额
                'goods_name' => $value['goods_name'],                           //记录商品名称快照
                'goods_name_style' => $value['goods_name_style'],               //记录商品名称样式快照
                'spell_goods_name' => $value['spell_goods_name'],               //记录商品名称拼音版
                'goods_sn' => $value['goods_sn'],                               //商品货号
                // 记录商品缩略图
                'file' => $value['products_id'] ? $value->getData('attr_file') : $value->getData('file'),
                'describe' => $value['describe'],                               //记录商品描述
                'discount' => $value['discount'] / 100,                         //会员折扣率
                'status' => $this->retGoodsStatus,                              //商品订单状态
                'profit' => ($value['price'] - $value['discount_price'] - ($value['products_id'] ?         //[利润]原价(已计算折扣率)-成本价[计算第一步]
                            $value['attr_cost_price'] : $value['cost_price'])) * $value['number'],
                'goods_unit' => $value['goods_unit'],                           //商品单位
                'discount_price' => $value['discount_price'],
            ];
        }
        $lockData = $this->instanceArr['lock']->lock($this->lockKey, 10000);
        if ($lockData === false) {
            return [
                'code' => -2,
                'message' => '网络繁忙,请重试',
            ];
        }
        // 库存充足扣除库存[商品,规格,限购]
        if ($invent['goods_id']) {
            (new Goods())->allowField(true)->isUpdate(true)->saveAll($invent['goods_id']);
        }
        if ($invent['products_id']) {
            (new Products())->allowField(true)->isUpdate(true)->saveAll($invent['products_id']);
        }
        if ($invent['limit']) {
            (new Limit())->allowField(true)->isUpdate(true)->saveAll($invent['limit']);
        }
        $this->instanceArr['lock']->unlock($lockData);
        // 对商品总价和子表小计价格进行二次处理
        if ($data) {
            $priceRet = self::priceHandle($data->toArray());
            if ($priceRet['code']) {
                return $priceRet;
            }
        }
        // 清空用户购物车
        $cartModel
            ->where([
                ['cart_id', 'in', implode(',', $clearCartId)],
            ])->delete();
    }
    
    /**
     * 会员限购商品次数
     * @param $member_id
     * @param $time
     * @param $goods_id
     * @return array|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getLimitTimes($member_id, $time, $goods_id)
    {
        $limitBuyTimes = (new OrderGoods())
            ->where([
                ['member_id', '=', $member_id],
                ['goods_id', '=', $goods_id],
                ['create_time', '>=', $time],
                ['status', 'not in', '6.1'],
                ['is_limit', '=', 1],
            ])
            ->field('goods_id,sum(quantity) as count')
            ->find();
        return ($limitBuyTimes && $limitBuyTimes['count'] > 0) ? $limitBuyTimes : [];
    }
    
    /**
     * 下单商品总价和子表(按店铺拆单)小计价格
     * 二次处理
     * @param $data
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function priceHandle($data)
    {
        // 平台总金额被减数累加,优惠券计数初始化
        $total_price = $coupon_statistics = $packet_statistics = 0;
        $raw_total_price = $this->retData['total_price'];
        // 使用优惠券抵扣
        if ($couponData = self::getCouponPrice()) {
            // 含有平台优惠券进行分摊到每个商品上
            if ($this->args['member_platform_coupon_id'] && $this->platformCouponPrice) {
                //已使用的平台优惠券(每单限一张)
                $this->retData['used_platform_member_coupon_id'] = $this->args['member_platform_coupon_id'];
                // 计数器,此时key代表购物车id或商品id
                $counter_one = 0;
                foreach ($this->retGoodsData as $key => &$item) {
                    if (++$counter_one == count($this->retGoodsData)) {
                        // 最后商品剩余分摊平台优惠券金额
                        $this->subtotal_price[$item['store_id']] -=
                        $item['sub_share_platform_coupon_price'] = fmtPrice($this->platformCouponPrice - $coupon_statistics);
                        $this->retAttachData[$item['store_id']]['subtotal_share_platform_coupon_price'] += $item['sub_share_platform_coupon_price'];
                        break;
                    }
                    // 单个单件商品分摊平台优惠券金额
                    $_share_platform_coupon_price = floor(($item['single_price'] / $this->retData['total_price']) * $this->platformCouponPrice * 100) / 100;
                    $coupon_statistics += $item['sub_share_platform_coupon_price'] = $_share_platform_coupon_price * $item['quantity'];
                    // 店铺小计金额累减
                    $this->subtotal_price[$item['store_id']] -= $item['sub_share_platform_coupon_price'];
                    // 按比例分摊平台优惠券金额(店铺小计)
                    $this->retAttachData[$item['store_id']]['subtotal_share_platform_coupon_price'] += $item['sub_share_platform_coupon_price'];
                }
            }
            $storeIdCounts = array_count_values(array_column($this->retGoodsData, 'store_id'));
            foreach ($couponData as $key => $value) {
                // 主订单金额累减店铺优惠券[循环中包含平台优惠券]
                $realCoupon = ($value['actual_price'] >= $this->retAttachData[$value['store_id']]['subtotal_price']) ?
                    $this->retAttachData[$value['store_id']]['subtotal_price'] : $value['actual_price'];
                $total_price += $realCoupon;
                $raw_total_price -= $realCoupon;
                // 主订单优惠券金额累加店铺优惠券和平台优惠券
                $this->retData['total_coupon_price'] += $realCoupon;
                $shop_coupon_statistics = 0;
                foreach ($this->retGoodsData as $_ke => &$_va) {
                    if ($_va['store_id'] == $value['store_id']) {
                        if (!--$storeIdCounts[$_va['store_id']]) {
                            // 最后商品剩余分摊店铺优惠券金额
                            $_va['sub_share_shop_coupon_price'] = $realCoupon - $shop_coupon_statistics;
                            // 各店铺各自优惠券累加,各店铺各自金额小计累减
                            if (array_key_exists($value['store_id'], $this->retAttachData)) {
                                //已使用的店铺会员优惠券id(各店铺限一张)
                                $this->retAttachData[$value['store_id']]['used_shop_member_coupon_id'] = $value['member_coupon_id'];
                                $this->retAttachData[$value['store_id']]['subtotal_coupon_price'] += $realCoupon;
                                $this->subtotal_price[$_va['store_id']] -= $realCoupon;
                            }
                        } else {
                            // 单个单件商品分摊店铺优惠券金额
                            $_sub_share_shop_coupon_price = fmtPrice(($_va['single_price'] / $this->retAttachData[$_va['store_id']]['subtotal_price']) * $realCoupon);
                            // 将每次均摊价格统计
                            $shop_coupon_statistics += $_va['sub_share_shop_coupon_price'] = $_sub_share_shop_coupon_price * $_va['quantity'];
                        }
                    }
                }
            }
        }
        // 使用平台红包抵扣
        if ($this->retData['total_packet_price'] = $packetPrice = self::getPacketPrice()) {
            $total_price += $packetPrice;
            $raw_total_price -= $packetPrice;
            $counter_two = 0;
            foreach ($this->retGoodsData as $key => &$_v_) {
                if (++$counter_two == count($this->retGoodsData)) {
                    // 最后商品用剩余分摊红包金额
                    $this->subtotal_price[$_v_['store_id']] -=
                    $_v_['subtotal_share_platform_packet_price'] = fmtPrice($packetPrice - $packet_statistics);
                    $this->retAttachData[$_v_['store_id']]['subtotal_share_platform_packet_price'] += $_v_['subtotal_share_platform_packet_price'];
                    break;
                }
                // 单个单件商品分摊平台红包金额
                $_share_platform_packet_price = floor(($_v_['single_price'] / $this->retData['total_price']) * $packetPrice * 100) / 100;
                $packet_statistics += $_v_['subtotal_share_platform_packet_price'] = $_share_platform_packet_price * $_v_['quantity'];
                // 店铺小计金额累减
                $this->subtotal_price[$_v_['store_id']] -= $_v_['subtotal_share_platform_packet_price'];
                $this->retAttachData[$_v_['store_id']]['subtotal_share_platform_packet_price'] += $_v_['subtotal_share_platform_packet_price'];
            }
        }
        // 使用积分抵扣
        if ($this->args['used_integral'] != 0 && $this->type == 1
            && $raw_total_price > ($this->retData['total_used_integral'] = round($this->args['used_integral'] / 100, 2))) {
            $total_price += $this->retData['total_used_integral'];
            $raw_total_price -= $this->retData['total_used_integral'];
            //TODO 扣除用户积分
        }
        // 运费[不免运费或者除了预约自提外需计算运费]
        $args = [];
        if (count($data) == count($data, COUNT_RECURSIVE)) {
            /** 立即购买 */
            $args = [[
                'flag_id' => $data['goods_id'],
                'goods_id' => $data['goods_id'],
                'goods_attr' => isset($data['goods_attr']) ? $data['goods_attr'] : '',
                'store_id' => $data['store_id'],
                'freight_id' => $data['freight_id'],
                'is_freight' => $data['is_freight'],
                'quantity' => $this->set['quantity'],
                'sub_price' => $this->retGoodsData[$data['goods_id']]['single_price'] * $this->set['quantity'],             // 采用原价
                'goods_weight' => $data['goods_weight'],
                'distribution_type' => $this->retAttachData[$data['store_id']]['distribution_type'],
            ]];
        } else {
            /** 购物车 */
            foreach ($data as $key => $value) {
                // 导入每个商品运费数据
                array_push($args, [
                    'flag_id' => $value['cart_id'],
                    'goods_id' => $value['goods_id'],
                    'goods_attr' => $value['goods_attr'],
                    'store_id' => $value['store_id'],
                    'freight_id' => $value['freight_id'],
                    'is_freight' => $value['is_freight'],
                    'quantity' => $value['number'],
                    'sub_price' => $this->retGoodsData[$value['cart_id']]['single_price'] * $value['number'],
                    'goods_weight' => $value['goods_weight'],
                    'distribution_type' => $this->retAttachData[$value['store_id']]['distribution_type'],
                ]);
            }
        }
        // 运费模板集合整理
        if ($args) {
            $freightService = app('app\\common\\service\\Freight', ['args' => $args, 'address' => $this->address]);
            if ($freightData = $freightService->calculation()) {
                foreach ($freightData as $key => $value) {
                    // 选择并支持快递邮寄
                    if ($value['distribution_type'] == 3) {
                        if ($value['express_freight_sup']) {
                            $this->retData['total_freight'] += $value['express_freight_price'];
                            $this->subtotal_price[$value['store_id']] += $value['express_freight_price'];
                            $this->retAttachData[$value['store_id']]['subtotal_freight_price'] += $value['express_freight_price'];
                            $this->retGoodsData[$value['flag_id']]['sub_freight_price'] = $value['express_freight_price'];
                        } else {
                            // todo 细化返回信息
                            return [
                                'code' => -3,
                                'message' => '部分商品不支持配送',
                            ];
                        }
                    }
                    // 选择并支持预约自提
                    if ($value['distribution_type'] == 2 && !$value['take_freight_sup']) {
                        return [
                            'code' => -3,
                            'message' => '部分商品不支持配送',
                        ];
                    }
                    // 选择并支持同城速递
                    if ($value['distribution_type'] == 1) {
                        if ($value['city_freight_sup']) {
                            $this->retData['total_freight'] += $value['city_freight_price'];
                            $this->subtotal_price[$value['store_id']] += $value['city_freight_price'];
                            $this->retAttachData[$value['store_id']]['subtotal_freight_price'] += $value['city_freight_price'];
                            $this->retGoodsData[$value['flag_id']]['sub_freight_price'] = $value['city_freight_price'];
                        } else {
                            return [
                                'code' => -3,
                                'message' => $value['city_freight_msg'] ?: '部分商品不支持配送',
                            ];
                        }
                    }
                }
            }
        }
        $freightState = $this->retData['total_freight'] > 0;
        if ($raw_total_price > 0) {
            $this->retData['total_price'] -= $total_price;
            if ($freightState) {
                $this->retData['total_price'] += $this->retData['total_freight'];
            }
        } else {
            // 最低1毛钱支付
            $this->retData['total_price'] = $freightState ? $this->retData['total_freight'] + 0.1 : 0.1;
            // 抵扣掉交易金额,直接发货
            //            if ($this->retData['total_price'] == 0) {
            //                foreach ($this->retGoodsData as &$_rgd) {
            //                    $_rgd['status'] = 1.1;
            //                }
            //            }
        }
        // 店铺订单总金额和状态最后定值
        //        $changeStore = [];
        $n = 0;
        foreach ($this->retAttachData as $_retAttachData_key => &$_retAttachData) {
            if ($raw_total_price > 0) {
                // 计算返利积分
                // 商品金额换算积分比例
                $this->scale = Env::get('integral_conversion', 0);
                foreach ($this->subtotal_price as $_subtotal_price_key => $_subtotal_price) {
                    if ($_retAttachData_key == $_subtotal_price_key && $_subtotal_price !== 0) {
                        $_retAttachData['subtotal_price'] += $_subtotal_price;
                    }
                }
                if ($this->scale) {
                    $_retAttachData['subtotal_back_integral'] =
                        floor($this->scale / 100 * ($_retAttachData['subtotal_price'] - $_retAttachData['subtotal_freight_price']));
                }
            } else {
                $_retAttachData['subtotal_price'] = $_retAttachData['subtotal_freight_price'];
                if (++$n == count($this->retAttachData)) {
                    $_retAttachData['subtotal_price'] += 0.1;
                }
                //                if ($_retAttachData['subtotal_freight_price'] == 0) {
                //                    $_retAttachData['subtotal_price'] = 0;
                //                    $_retAttachData['status'] = 1;
                //                    $_retAttachData['pay_channel'] = 3;     // 默认余额
                //                    array_push($changeStore, $_retAttachData_key);
            }
            //            }
        }
        $n = 0;
        // 商品订单状态最后定值
        foreach ($this->retGoodsData as $_retGoodsData_key => &$_retGoodsData) {
            //            if (!empty($changeStore) && in_array($_retGoodsData['store_id'], $changeStore)) {
            //                $_retGoodsData['status'] = 1.1;
            //            }
            $discount = ($_retGoodsData['sub_share_shop_coupon_price'] +
                $_retGoodsData['sub_share_platform_coupon_price'] +
                $_retGoodsData['subtotal_share_platform_packet_price'] +
                $_retGoodsData['sub_fullSub_price']);
            // 商品利润第二步
            $_retGoodsData['profit'] -= $discount;
            if ($raw_total_price <= 0) {
                $_retGoodsData['subtotal_price'] = (++$n == count($this->retGoodsData) ? 0.1 + $_retGoodsData['sub_freight_price'] : 0);
            } else {
                // 商品订单实收价格第二步
                // 减去优惠
                $_retGoodsData['subtotal_price'] -= $discount;
                // 加上运费
                $_retGoodsData['subtotal_price'] += $_retGoodsData['sub_freight_price'];
            }
        }
        // 货到付款总金额
        foreach ($this->retAttachData as $rat) {
            if ($rat['pay_type'] == 2) {
                //货到付款总金额
                $this->retData['total_cod_price'] += $rat['subtotal_price'];
            }
        }
        return ['code' => 0];
    }
    
    /**
     * 获取使用的会员优惠券总值
     * @return array   返回已使用的优惠券信息集
     */
    private function getCouponPrice()
    {
        // 取出用户店铺优惠券id
        $couponIdArr = count($this->set) == count($this->set, COUNT_RECURSIVE) ?
            [$this->set['member_shop_coupon_id']] :
            array_column($this->set, 'member_shop_coupon_id');
        // 插入用户平台优惠券id
        if ($this->args['member_platform_coupon_id']) {
            array_push($couponIdArr, $this->args['member_platform_coupon_id']);
        }
        // 确保优惠券是会员本人的
        $data = $update = [];
        if ($couponIdArr) {
            $data = $this->instanceArr['memberCouponModel']
                ->where([
                    ['member_coupon_id', 'in', implode(',', $couponIdArr)],
                    ['member_id', '=', $this->retData['member_id']],
                    ['status', '=', 0]  //未使用的
                ])
                ->field('member_coupon_id,store_id,full_subtraction_price,type,actual_price,store_id')
                ->select();
            //更改会员优惠券状态->已使用并记录使用时间
            foreach ($data as $item) {
                // 摘出平台优惠券
                if ($item['type'] == 1) {
                    $this->platformCouponPrice = $item['actual_price'];
                }
                array_push($update, [
                    'member_coupon_id' => $item['member_coupon_id'],
                    'use_time' => date('Y-m-d H:i:s'),
                    'status' => 1,
                ]);
            }
            if ($update) {
                $this->instanceArr['memberCouponModel']
                    ->allowField(true)
                    ->isUpdate(true)
                    ->saveAll($update);
            }
        }
        return $data;
    }
    
    /**
     * 获取使用的会员平台红包值
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getPacketPrice()
    {
        $packetPrice = 0;
        if ($this->args['member_packet_id']) {
            // 确保红包是会员本人的
            $data = (new MemberPacket())
                ->where([
                    ['member_packet_id', '=', $this->args['member_packet_id']],
                    ['member_id', '=', $this->retData['member_id']],
                ])
                ->field('member_packet_id,use_time,actual_price')
                ->find();
            $packetPrice = $data['actual_price'];
            //更改红包状态为已使用并更改使用时间
            $data->status = 1;
            $data->use_time = date('Y-m-d H:i:s');
            $data->save();
        }
        return $packetPrice;
    }
    
    /**
     * 店铺订单更新数据
     * @var array
     */
    private $order_attach_update = [];
    /**
     * 商品订单更新数据
     * @var array
     */
    private $order_goods_update = [];
    /**
     * 商品更新数据
     * @var array
     */
    private $goods_update = [];
    /**
     * 店铺更新数据
     * @var array
     */
    private $store_update = [];
    /**
     * 拼团附表id
     * @var null
     */
    private $groupActivityAttachId = '';
    /**
     * 砍价主表id
     * @var string
     */
    private $cutActivityId = '';
    /**
     * 店铺资金记录数据
     * @var array
     */
    private $store_capital = [];
    
    /**
     * 支付订单更新
     * @param $args
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function execPayOrder($args)
    {
        // 查询订单信息
        $where = [
            // 待付款或已取消
            ['oa.status', 'in', '0,6'],
            ['oa.order_number|oa.order_attach_number', '=', $args['out_trade_no']],
        ];
        $orderAttachModel = new OrderAttach();
        $memberModel = new Member();
        // 列表付款(单)或直接付款(多)
        $orderAttachData = $orderAttachModel
            ->alias('oa')
            ->where($where)
            ->join('store s', 's.store_id = oa.store_id')
            ->field('oa.order_attach_id,oa.prepay_id,oa.group_activity_id,oa.subtotal_price,oa.pay_type,
            oa.status,oa.number,oa.member_id,oa.store_id,oa.distribution_type,oa.order_id,oa.cut_activity_id,
            oa.pay_channel,oa.subtotal_freight_price,oa.used_shop_member_coupon_id,oa.limit_goods_id,s.store_name,
            oa.express_value,oa.express_number')
            ->with(['orderPay', 'groupActivityPay', 'orderGoodsPay', 'memberPay'])
            ->select();
        if ($orderAttachData->isEmpty()) {
            return [
                'code' => -3,
                'message' => '订单不存在或商家变动订单,请确认好订单状态再进行付款',
            ];
        }
        $args['member_id'] = $orderAttachData[0]['member_id'];
        $total_price = $total_freight = 0;
        foreach ($orderAttachData as $_oad) {
            if ($_oad['pay_type'] == 1) {
                // 货到付款不计入
                $total_price += $_oad['subtotal_price'];
                $total_freight += $_oad['subtotal_freight_price'];
            }
        }
        // 购买者会员&分销信息
        $distribution_info = $memberModel
            ->where([
                ['member_id', '=', $args['member_id']],
            ])
            ->with(['distributionRecord'])
            ->field('member_id,nickname,phone,sex,distribution_superior,cumulative_order_sum,
            web_open_id,subscribe_time,micro_open_id')
            ->find();
        // 会员累积订单消费金额(支付既累计,满X元升级分销商,退款累减)[刨除运费]
        $distribution_info->cumulative_order_sum += ($total_price - $total_freight);
        $distribution_info->save();
        switch ($args['pay_channel']) {
            // 余额支付
            case 3:
                //如果支付密码为空
                if (empty($orderAttachData[0]['member_pay']['pay_password'])) {
                    return ['code' => -3, 'message' => '请设置支付密码',];
                }
                // 比较支付密码
                if (!self::comparePwd($orderAttachData[0]['member_pay']['pay_password'], $args['pay_password'])) {
                    return [
                        'code' => -3,
                        'message' => '支付密码错误',
                    ];
                }
                // 比较用户余额
                if ($orderAttachData[0]['member_pay']['usable_money'] < $total_price) {
                    return [
                        'code' => -3,
                        'message' => '余额不足',
                    ];
                }
                // 扣除余额
                self::deductionBalance($args['member_id'], $total_price);
                // 记录消费明细
                self::recordConsumption(
                    $args['member_id'],
                    $orderAttachData[0]['member_pay']['usable_money'],
                    $total_price,
                    $args['out_trade_no'],
                    $orderAttachData[0]['order_pay']['order_number'] == $args['out_trade_no']
                );
                break;
            default:
                // 对比金额
                //                if ($total_price != $args['total_fee']) {
                //                    return [
                //                        'code' => -3,
                //                        'message' => '金额不匹配',
                //                    ];
                //                }
                break;
        }
        $msg = [
            'base' => [
                'title' => '您有新订单啦,请点击查看',
                'silent' => true,
                'sound' => request()->domain() . '/template/client/resource/voice/orderSuccess.mp3',
                'tag' => 'orderSuccess',
                'requireInteraction' => true,
            ],
            'data' => [],
        ];
        $distributionArr = [
            'member_id' => '',
            'cumulative_order_sum' => $distribution_info->cumulative_order_sum,
            'distributor_goods' => [],
            'goods' => [],
        ];
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        if (!is_null($distribution_info->distribution_record) &&
            $disId = $distribution_info->distribution_record->distribution_id) {
            if ($flag = Cache::store('file')->get($disId)) {
                Cache::store('file')->rm($disId);
            };
        }
        $changeCoupon = [];
        // 初始化商品和规格库存更新集
        $invent = ['goods_id' => [], 'products_id' => [], 'limit' => []];
        foreach ($orderAttachData as $_k => $_val) {
            $this->store_update[$_val['store_id']] = [
                'store_id' => $_val['store_id'],
                'sales_volume' => Db::raw('sales_volume + ' . $_val['number']),
            ];
            if ($distributionArr['member_id'] === '') {
                $distributionArr['member_id'] = $_val['member_id'];
            }
            if ($_val['pay_type'] != 2) {
                $this->store_capital[$_val['store_id']] = [
                    'store_id' => $_val['store_id'],
                    'type' => 3,
                    'status' => 2,  // 交易中
                    'order_attach_id' => $_val['order_attach_id'],
                    'price' => $_val['subtotal_price'],
                ];
            }
            $this->order_attach_update[$_val['order_attach_id']] = [
                'order_attach_id' => $_val['order_attach_id'],
                'pay_time' => date('Y-m-d H:i:s'),
                // 自提更改为配送中,其他为待配送(待发货) [拼团未成团属于特殊待配送]
                'status' => ($_val['distribution_type'] == 2) ? 2 : 1,
                // 更改支付方式
                'pay_channel' => $args['pay_channel'],
                'case_pay_type' => $args['case_pay_type'],
                'trade_no' => $args['trade_no'],
            ];
            // 订单类型 1普通 [2拼团 3砍价 4限时抢购]
            $this->type = $_val->order_pay->order_type;
            // 每个店铺订单下的商品订单
            foreach ($_val->order_goods_pay as $key => $item) {
                if (!isset($msg['data'][$_val['order_attach_id']])) {
                    $msg['data'][$_val['order_attach_id']] = [
                        'dist' => $_val['store_id'],
                        'icon' => $item['file'],
                        'body' => '',
                        'data' => [
                            'order_attach_id' => $_val['order_attach_id'],
                        ],
                    ];
                }
                $msg['data'][$_val['order_attach_id']]['body'] .= $item['goods_name'];
                if (!isset($this->goods_update[$item['goods_id']])) {
                    $this->goods_update[$item['goods_id']] = [
                        'goods_id' => $item['goods_id'],
                        // 'sales_volume' => Db::raw('sales_volume + ' . $item['quantity']),
                        'sales_volume' => $item['quantity'],
                    ];
                } else {
                    $this->goods_update[$item['goods_id']]['sales_volume'] += $item['quantity'];
                }
                
                $this->order_goods_update[$item['order_goods_id']] = [
                    'order_goods_id' => $item['order_goods_id'],
                    //自提更改为已发货,其他为已支付(待发货)
                    'status' => ($_val['distribution_type'] == 2) ? 2.1 : 1.1,
                ];
                // 拼团处理
                if ($this->type == 2) {
                    $this->set['products_id'] = $item['products_id'];
                    $groupRet = self::changeGroup(
                        $item['goods_id'],
                        $_val['group_activity_id'],
                        $_val['member_id'],
                        $_val['distribution_type'],
                        $item['quantity'],
                        $item['goods_name']
                    );
                    if ($groupRet['code']) {
                        return $groupRet;
                    }
                }
                // 分销数据搜集[排除活动]
                if ($item['goods_distribution'] && $this->type == 1) {
                    // 买完此商品即成为分销商
                    if ($item['goods_distribution']['is_distributor'] && Env::get('distribution_buy', 0)) {
                        if (is_null($distribution_info->distribution_record) ||
                            $distribution_info->distribution_record->audit_status != 1) {
                            // 当前用户没有分销记录或者非已审核状态
                            $this->order_goods_update[$item['order_goods_id']]['is_distributor'] = 1;
                        }
                        // 存入指定商品的订单id(供待定分销商到稳定分销商检测)
                        $distributionArr['distributor_goods'][] = $item['order_goods_id'];
                    }
                    //商品参加分销
                    if ($item['goods_distribution']['is_distribution']) {
                        array_push($distributionArr['goods'], [
                            'order_goods_id' => $item['order_goods_id'],    //商品订单id
                            'order_attach_id' => $_val['order_attach_id'],  //店铺订单id
                            'store_id' => $_val['store_id'],                //店铺id
                            'profit' => $item['profit'],                    //交易利润
                            'actual' => $item['subtotal_price'] - $item['sub_freight_price'],            //实收金额(除运费)
                            'quantity' => $item['quantity'],
                            'distribution_set' => $item['goods_distribution']['distribution_set'],      //商品分销设置
                            'rebates_type' => $item['goods_distribution']['rebates_type'],    // 返利类型 0 现金（元） 1 百分比
                        ]);
                    }
                }
                // 已取消订单继续支付
                if ($item['status'] == 6.1) {
                    // 更新商品库存[正常/限时抢购]数据
                    array_push($invent['goods_id'], [
                        'goods_id' => $item['goods_id'],
                        'goods_number' => Db::raw('goods_number - ' . $item['quantity']),
                    ]);
                    // 增加商品行锁集合
                    array_push($this->lockKey, 'goods_id_' . $item['goods_id']);
                    // 限时抢购更新库存
                    if ($this->type == 4) {
                        array_push($invent['limit'], [
                            'limit_id' => $_val['limit_goods_id'],
                            'exchange_num' => Db::raw('exchange_num - ' . $item['quantity']),
                        ]);
                        array_push($this->lockKey, 'limit_id_' . $_val['limit_goods_id']);
                    }
                    if ($this->type == 3) {
                        // 增加砍价商品的销量
                        $cut_goods_id = (new CutActivity())
                            ->where([
                                ['cut_activity_id', '=', $_val['cut_activity_id']],
                            ])
                            ->value('cut_goods_id');
                        if (!is_null($cut_goods_id)) {
                            (new CutGoods())
                                ->allowField(true)
                                ->isUpdate(true)
                                ->save([
                                    'cut_goods_id' => $cut_goods_id,
                                    'sales_volume' => Db::raw('sales_volume + ' . $_val['number']),
                                ]);
                        }
                    }
                    // 增加规格商品库存[正常/限时抢购]数据
                    if ($item['products_id']) {
                        $updateKey = $this->type == 4 ? 'attr_time_limit_number' : 'attr_goods_number';
                        array_push($invent['products_id'], [
                            'products_id' => $item['products_id'],
                            $updateKey => Db::raw($updateKey . ' - ' . $item['quantity']),
                        ]);
                        // 增加商品规格行锁集合
                        array_push($this->lockKey, 'products_id_' . $item['products_id']);
                    }
                }
            }
            // 砍价处理
            if ($this->type == 3) {
                // 商品砍价成单量+1
                array_walk($this->goods_update, function (&$v, $k, $p) {
                    $v = array_merge($v, $p);
                }, ['cut_success_num' => Db::raw('cut_success_num + 1')]);
                $this->cutActivityId = $_val->cut_activity_id;
            }
            // 已取消订单继续支付
            if ($_val['status'] == 6) {
                if ($_val['used_shop_member_coupon_id']) {
                    $changeCoupon[] = [
                        'member_coupon_id' => $_val['used_shop_member_coupon_id'],
                        'status' => 1,
                        'use_time' => date('Y-m-d H:i:s'),
                    ];
                }
            }
        }
        // 更改店铺优惠券为已使用
        if (!empty($changeCoupon)) {
            (new MemberCoupon())
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($changeCoupon);
        }
        if (!empty($this->lockKey)) {
            $lockData = $this->instanceArr['lock']->lock($this->lockKey, 10000);
            if ($lockData === false) {
                return [
                    'code' => -1,
                    'message' => '网络繁忙,请重试',
                ];
            }
            // 库存充足扣除库存[商品,规格,限购]
            if ($invent['goods_id']) {
                (new Goods())->allowField(true)->isUpdate(true)->saveAll($invent['goods_id']);
            }
            if ($invent['products_id']) {
                (new Products())->allowField(true)->isUpdate(true)->saveAll($invent['products_id']);
            }
            if ($invent['limit']) {
                (new Limit())->allowField(true)->isUpdate(true)->saveAll($invent['limit']);
            }
            $this->instanceArr['lock']->unlock($lockData);
        }
        // 分销数据处理[处理分销订单或检测分销商]
        $distributionRet = (new Distribution())->opera($distributionArr, $distribution_info);
        if (!empty($distributionRet['distributionGoodsArr'])) {
            // 当前会员为稳定型分销商并分销了此次支付商品
            foreach ($distributionRet['distributionGoodsArr'] as $_dga) {
                $this->order_goods_update[$_dga]['is_distribution'] = 1;
            }
        }
        self::saveData();
        // 存储店铺资金记录
        if ($this->store_capital) {
            Hook::exec(['app\\interfaces\\behavior\\StoreCapital', 'record'], $this->store_capital);
        }
        $_httpConfig = Config::pull('daemon')['notify'];
        
        //TODO::向平台发送订单成功通知
        try {
            (new Client())->post("127.0.0.1:{$_httpConfig['port']}/NOTIFY", [
                'form_params' => $msg,
            ]);
        } catch (\Exception $e) {
            
        }
        return [
            'code' => 0,
            'total_fee' => $total_price,
            'member_id' => $args['member_id'],
            'groupActivityAttachId' => $this->groupActivityAttachId,
            'cutActivityId' => $this->cutActivityId,
        ];
    }
    
    /**
     * 比较余额支付密码
     * @param $old string 正确密码
     * @param $new string 待校验密码
     * @return bool
     */
    public function comparePwd($old, $new)
    {
        return passEnc($new) == $old;
    }
    
    /**
     * 扣除用户余额
     * @param $member_id
     * @param $price
     */
    public function deductionBalance($member_id, $price)
    {
        app('app\\common\\model\\Member', true)
            ->allowField(true)
            ->isUpdate(true)
            ->save([
                'member_id' => $member_id,
                'usable_money' => Db::raw('usable_money - ' . $price),
            ]);
    }
    
    /**
     * 记录消费明细
     * @param $member_id string 用户id
     * @param $usable_money string 用户余额
     * @param $price string 用户消费金额
     * @param $number string 订单号
     * @param $switch string 区分是否为总订单号
     */
    public function recordConsumption($member_id, $usable_money, $price, $number, $switch)
    {
        $args = [
            'member_id' => $member_id,
            // 消费类型 0充值 1提现 2消费
            'type' => 2,
            // 资金方式：1支付宝2微信3银行卡4余额5线下
            'way' => 4,
            'price' => $price,
            'balance' => $usable_money - $price,
            'order_number' => $switch ? $number : null,
            'order_attach_number' => $switch ? null : $number,
        ];
        (new Consumption())->allowField(true)->isUpdate(false)->save($args);
    }
    
    /**
     * 支付拼团处理
     * @param $goods_id string 商品id
     * @param $group_activity_id    string 拼团活动主表id(判断是否为参团)
     * @param $member_id    string  会员id
     * @param $distribution_type    integer  配送方式 1同城速递 2预约自提 3快递邮寄
     * @param $quantity    integer  购买数量
     * @param $goods_name
     * @param string $store_id string 立即购买货到付款店铺标识
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function changeGroup($goods_id, $group_activity_id, $member_id, $distribution_type, $quantity, $goods_name, $store_id = '')
    {
        $groupActivityModel = new GroupActivity();
        $memberModel = new Member();
        $groupGoodsRet = self::checkGroupLimit($goods_id, $member_id, $quantity);
        if ($groupGoodsRet['code']) {
            return $groupGoodsRet;
        }
        $groupGoodsData = $groupGoodsRet['data'];
        // 开团信息初始化
        $ins = [
            'group_goods_id' => $groupGoodsData['group_goods_id'],
            'owner' => $member_id,                                  //开团会员id
            'products_id' => $this->set['products_id'],
            'surplus_num' => $groupGoodsData['group_num'] - 1,
            'status' => 1,                                          //团购状态 1进行中 2成功 3失败
            'is_auto' => $groupGoodsData['is_auto'],                //是否自动成团
            'end_time' => date('Y-m-d H:i:s', intval(time() + $groupGoodsData['continue_time'] * 3600)),
        ];
        // 查询用户昵称
        $memberInfo = $memberModel
            ->where([
                ['member_id', '=', $member_id],
            ])
            ->field('nickname,web_open_id,member_id,subscribe_time,micro_open_id,phone')
            ->find();
        // 收集所有团员昵称信息[其中至少包含团长]
        $grouper = [];
        $group_msg_flag = 0;
        $this->instanceArr['redisService']->select(1);
        // 去参团
        if ($group_activity_id) {
            // 检测是否成团(人数足够)
            $groupActivityData = $groupActivityModel
                ->where([
                    ['group_activity_id', '=', $group_activity_id],
                    ['status', '=', 1],     //进行中
                    ['end_time', '>', date('Y-m-d')],  //未结束
                    ['surplus_num', '>', 0],    //人数未满
                ])
                ->with(['groupActivityAttachMsg'])
                ->field('group_activity_id,surplus_num,status')
                ->find();
            // 查看用户是否已加入过
            $has_own = false;
            if ($groupActivityData) {
                if ($groupActivityData->group_activity_attach_msg) {
                    foreach ($groupActivityData->group_activity_attach_msg as $v) {
                        if ($v->member) {
                            array_push($grouper, [
                                'group_activity_attach_id' => $v->group_activity_attach_id,
                                'nickname' => $v->member->nickname,
                                'web_open_id' => $v->member->web_open_id,
                                'subscribe_time' => $v->member->subscribe_time,
                                'micro_open_id' => $v->member->micro_open_id,
                                'member_id' => $v->member->member_id,
                                'phone' => $v->member->phone,
                            ]);
                        }
                        if ($member_id == $v['member_id']) {
                            $has_own = true;
                        }
                    }
                }
                if (!$has_own) {
                    // 更新拼团主表信息
                    $ins = [];
                    if (($groupActivityData->surplus_num -= 1) === 0) {
                        // 参团成功,主表状态为成功
                        $groupActivityData->status = 2;
                        // 商品拼团成单量+1
                        array_walk($this->goods_update, function (&$v, $k, $p) {
                            $v = array_merge($v, $p);
                        }, ['group_success_num' => Db::raw('group_success_num + 1')]);
                        // 更改所有拼团全团团员商品订单状态为待发货
                        $otherOrderGoodsData = (new OrderGoods())
                            ->where([
                                ['oa.group_activity_id', '=', $group_activity_id],
                            ])
                            ->alias('og')
                            ->join('order_attach oa', 'oa.order_attach_id = og.order_attach_id')
                            ->field('og.order_goods_id,oa.distribution_type')
                            ->select();
                        if (!$otherOrderGoodsData->isEmpty()) {
                            foreach ($otherOrderGoodsData as $item) {
                                $this->order_goods_update[$item['order_goods_id']] = [
                                    'order_goods_id' => $item['order_goods_id'],
                                    // 自提状态为配送中(已发货)
                                    'status' => ($item['distribution_type'] == 2) ? 2.1 : 1.1,
                                ];
                            }
                        }
                        // 拼团成功
                        $group_msg_flag = 2;
                    } elseif ($groupActivityData->surplus_num > 0) {
                        // 拼团人数不足,继续进行中(状态为拼团进行中,自提为2.2等待,其他为1.2等待)
                        // 拼团进行中(拼团成功->待发货)
                        array_walk($this->order_goods_update, function (&$v, $k, $p) {
                            $v = array_merge($v, $p);
                        }, ['status' => $this->retGoodsStatus = (($distribution_type == 2) ? 2.2 : 1.2)]);
                        // 拼团信息(用户已参团)
                        $this->instanceArr['redisService']->zAdd($this->prefix . 'goods_' . $goods_id, time() . '1', $group_activity_id . '@_@' . $memberInfo['nickname']);
                        // 已参团
                        $group_msg_flag = 1;
                        $grouper = [];
                    } else {
                        // 处理异常
                        return [
                            'code' => -4,
                            'message' => '拼团处理异常',
                        ];
                    }
                    $lockData = $this->instanceArr['lock']->lock(['group_activity_' . $group_activity_id], 10000);
                    $groupActivityData->save();
                    $this->instanceArr['lock']->unlock($lockData);
                }
            }
            if ($has_own || !$groupActivityData) {
                // 开新团,进行中
                // 拼团进行中(拼团成功->待发货)
                array_walk($this->order_goods_update, function (&$v, $k, $p) {
                    $v = array_merge($v, $p);
                }, ['status' => $this->retGoodsStatus = (($distribution_type == 2) ? 2.2 : 1.2)]);
            }
        }
        if ($ins) {
            // 新增拼团活动主表信息
            $this->instanceArr['groupActivityModel']
                ->allowField(true)
                ->isUpdate(false)
                ->save($ins);
            $group_activity_id = $this->instanceArr['groupActivityModel']->group_activity_id;
            //拼团进行中(拼团成功->待发货)
            array_walk($this->order_goods_update, function (&$v, $k, $p) {
                $v = array_merge($v, $p);
            }, ['status' => $this->retGoodsStatus = (($distribution_type == 2) ? 2.2 : 1.2)]);
            // 发送队列消息[拼团到期未成团更改状态为已失败]
            (new Beanstalk())->put(json_encode(['queue' => 'groupExpireChangeStatus',
                'id' => $group_activity_id, 'time' => date('Y-m-d H:i:s')]),
                $groupGoodsData['continue_time'] * 3600);
            $grouper = [];
            // 开新团
            $group_msg_flag = 0;
        }
        // 新增拼团活动附表信息
        $this->instanceArr['groupActivityAttachModel']
            ->allowField(true)
            ->isUpdate(false)
            ->save(['member_id' => $member_id, 'group_activity_id' => $group_activity_id]);
        array_push($grouper, [
            'group_activity_attach_id' => $this->instanceArr['groupActivityAttachModel']->group_activity_attach_id,
            'nickname' => $memberInfo['nickname'],
            'web_open_id' => $memberInfo['web_open_id'],
            'subscribe_time' => $memberInfo['subscribe_time'],
            'micro_open_id' => $memberInfo['micro_open_id'],
            'member_id' => $memberInfo['member_id'],
            'phone' => $memberInfo['phone'],
        ]);
        if (!empty($this->order_attach_update)) {
            foreach ($this->order_attach_update as $key => &$value) {
                // 增加店铺订单拼团活动主表关联
                $value['group_activity_id'] = $group_activity_id;
                // 增加店铺订单拼团活动附表关联
                $value['group_activity_attach_id'] = $this->instanceArr['groupActivityAttachModel']->group_activity_attach_id;
            }
        } else {
            // 立即购买拼团货到付款
            if ($store_id) {
                $this->retAttachData[$store_id] = array_merge($this->retAttachData[$store_id], [
                    'group_activity_id' => $group_activity_id,
                    'group_activity_attach_id' => $this->instanceArr['groupActivityAttachModel']->group_activity_attach_id,
                ]);
            }
        }
        if (!empty($grouper)) {
            foreach ($grouper as $item) {
                $this->instanceArr['redisService']->zAdd($this->prefix . 'goods_' . $goods_id, time() . $group_msg_flag, $item['group_activity_attach_id'] . '@_@' . $item['nickname']);
                if ($group_msg_flag == 2) {
                    // 推送消息[拼团成功]
                    $pushServer = app('app\\interfaces\\behavior\\Push');
                    $pushServer->send([
                        'tplKey' => 'active_goods_state',
                        'openId' => $item['web_open_id'],
                        'subscribe_time' => $item['subscribe_time'],
                        'microId' => $item['micro_open_id'],
                        'phone' => $item['phone'],
                        'data' => [3, $groupGoodsData['store_name'], $item['nickname'], $goods_name],
                        'inside_data' => [
                            'member_id' => $item['member_id'],
                            'type' => 1,
                            'jump_state' => '2',
                            'attach_id' => $item['group_activity_attach_id'],
                            'file' => $groupGoodsData->getData('file'),
                        ],
                        'sms_data' => [],
                    ]);
                }
            }
        }
        $this->groupActivityAttachId = $this->instanceArr['groupActivityAttachModel']->group_activity_attach_id;
        return [
            'code' => 0,
            'message' => '拼团处理成功',
        ];
    }
    
    /**
     * 更新订单数据
     * @throws \Exception
     */
    private function saveData()
    {
        // 更新商品数据
        if (!empty($this->goods_update)) {
            foreach ($this->goods_update as &$g) {
                $g['sales_volume'] = Db::raw('sales_volume + ' . $g['sales_volume']);
            }
            (new Goods())->allowField(true)
                ->isUpdate(true)
                ->saveAll($this->goods_update);
        }
        // 更新店铺数据
        if (!empty($this->store_update)) {
            (new Store())->allowField(true)
                ->isUpdate(true)
                ->saveAll($this->store_update);
        }
        // 更新店铺订单数据
        if (!empty($this->order_attach_update)) {
            (new OrderAttach())->allowField(true)
                ->isUpdate(true)
                ->saveAll($this->order_attach_update);
        }
        // 更新商品订单数据
        if (!empty($this->order_goods_update)) {
            (new OrderGoods())->allowField(true)
                ->isUpdate(true)
                ->saveAll($this->order_goods_update);
        }
    }
}