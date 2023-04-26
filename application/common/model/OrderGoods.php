<?php
declare(strict_types = 1);

namespace app\common\model;

use app\common\service\Beanstalk;
use app\common\service\TemplateMessage;
use EasyWeChat\Factory;
use think\Exception;
use think\facade\Env;
use think\model\relation\BelongsTo;
use app\common\model\OrderAttach as OrderAttachModel;
use app\common\model\OrderGoodsRefund as OrderGoodsRefundModel;
use app\common\model\OrderGoods as OrderGoodsModel;
use app\common\model\Member as MemberModel;
use app\common\model\StoreCapital as StoreCapital;
use think\Db;

/**
 * 订单商品
 * Class OrderGoods
 * @package app\common\model
 */
class OrderGoods extends BaseModel
{
    protected $pk = 'order_goods_id';
    
    /**
     * 关联商品
     * @return BelongsTo
     */
    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_id', 'goods_id');
    }
    
    /**
     * 商品评价(关联商品)
     * @return BelongsTo
     */
    public function goodsEvaluate()
    {
        return self::goods()->field('goods_id,comments_number');
    }
    
    /**
     * 商品分销(关联商品)
     * @return BelongsTo
     */
    public function goodsDistribution()
    {
        return self::goods()
            ->field('goods_id,is_distribution,is_distributor,is_group,
                    is_bargain,is_limit,rebates_type,distribution_set');
    }
    
    /**
     * 关联拼团活动附表(相对一对一)
     * @return BelongsTo
     */
    public function groupActivityAttach()
    {
        return $this->belongsTo('groupActivityAttach', 'group_activity_attach_id', 'group_activity_attach_id');
    }
    
    /**
     * 订单详情(关联拼团活动附表)
     * @return BelongsTo
     */
    public function groupActivityAttachDetails()
    {
        return self::groupActivityAttach()
            ->alias('gaa')
            ->join('group_activity ga', 'ga.group_activity_id = gaa.group_activity_id')
            ->field('ga.status as group_activity_status');
    }
    
    /**
     * 关联砍价活动主表(相对一对一)
     * @return BelongsTo
     */
    public function cutActivity()
    {
        return $this->belongsTo('CutActivity', 'cut_activity_id', 'cut_activity_id');
    }
    
    /**
     * 订单详情(关联砍价活动主表)
     * @return BelongsTo
     */
    public function cutActivityDetails()
    {
        return self::cutActivity()
            ->field('status as cut_activity_status');
    }
    
    /**
     * 关联退货退款订单
     * @return \think\model\relation\HasOne
     */
    public function orderGoodsRefund()
    {
        return $this->hasOne('OrderGoodsRefund', 'order_goods_id', 'order_goods_id');
    }
    
    /**
     * 售后列表(关联退货退款订单)
     * @return \think\model\relation\HasOne
     */
    public function orderGoodsRefundList()
    {
        return self::orderGoodsRefund()
            ->field('order_goods_refund_id,order_goods_id,status');
    }
    
    /**
     * 关联店铺
     * @return BelongsTo
     */
    public function store()
    {
        return $this->belongsTo('Store', 'store_id', 'store_id');
    }
    
    /**
     * 售后列表(关联店铺)
     * @return BelongsTo
     */
    public function orderGoodsRefundStore()
    {
        return self::store()->field('store_id,store_name,logo');
    }
    
    /**
     * 店铺评价(关联店铺)
     * @return BelongsTo
     */
    public function storeEvaluate()
    {
        return self::store()->field('store_id,grade');
    }
    
    /**
     * 关联订单附表
     * @return BelongsTo
     */
    public function orderAttach()
    {
        return $this->belongsTo('OrderAttach', 'order_attach_id', 'order_attach_id');
    }
    
    /**
     * 关联商品订单评价(相对一对一)
     * @return BelongsTo
     */
    public function orderGoodsEvaluate()
    {
        return $this->belongsTo('GoodsEvaluate', 'order_goods_id', 'order_goods_id');
    }
    
    /**
     * 关联分销订单(一对一)
     * @return \think\model\relation\HasOne
     */
    public function distributionBook()
    {
        return $this->hasOne('DistributionBook', 'order_goods_id', 'order_goods_id');
    }
    
    /**
     * 售后通道关闭(关联分销订单)
     * @return \think\model\relation\HasOne
     */
    public function distributionBookCloseSale()
    {
        return $this->distributionBook()
            ->field('distribution_book_id,order_goods_id,distributor_a,distributor_b,distributor_c,
            distributor_a_brokerage,distributor_b_brokerage,distributor_c_brokerage,status');
    }
    
    /**
     * 关联买家信息
     * @return BelongsTo
     */
    public function member()
    {
        return $this->belongsTo('Member', 'member_id', 'member_id');
    }
    
    /**
     * 关联买家信息带分销商信息
     * @return BelongsTo
     */
    public function orderGoodsMember()
    {
        return self::member()
            ->field('member_id,cumulative_order_sum,nickname,web_open_id,
            subscribe_time,micro_open_id,phone')
            ->with(['distributionRecord']);
    }
    
    public function getIsDistributorAttr($value, $data)
    {
        return $value;
    }
    
    public function getIsDistributionAttr($value, $data)
    {
        return $value;
    }
    
    public function refundsGoods($param)
    {
        try {
            Db::startTrans();
            $orderGoodsRefund = new OrderGoodsRefundModel();
            $orderGoods = new OrderGoodsModel();
            $orderAttach = new OrderAttachModel();
            $Member = new MemberModel();
            $storeCapital = new StoreCapital();
            $consumptionModel = new Consumption();
            $distributionModel = new Distribution();
            //不要改掉
            $this_time = date('Y-m-d H:i:s');
            
            $result = $orderGoodsRefund->where(['order_goods_id' => $param['order_goods_id']])->find();
            $orderAttachId = $orderGoods
                ->alias('og')
                ->where(['og.order_goods_id' => $param['order_goods_id']])
                ->with(['orderGoodsMember', 'distributionBookCloseSale'])
                ->join('store s', 's.store_id = og.store_id')
                ->join('order o','o.order_id=og.order_id')
                ->field('o.total_price,og.order_goods_id,og.order_attach_id,og.redo_status,og.status,og.member_id,og.subtotal_price,og.sub_freight_price,s.store_name,og.goods_name,og.file,
                ((single_price*quantity)-sub_share_shop_coupon_price-sub_share_platform_coupon_price-subtotal_share_platform_packet_price) as goodsTotal, 
                ((single_price*quantity)+sum_alter_goods_price+sub_freight_price-sub_share_shop_coupon_price-sub_share_platform_coupon_price-subtotal_share_platform_packet_price) as goodsTotal1')
                ->find();
            $orderAttachArr = $orderAttach
                ->where(['order_attach_id' => $orderAttachId['order_attach_id']])
                ->field('member_id,pay_channel,order_id,store_id,prepay_id,order_number,order_attach_id,order_attach_number,case_pay_type,order_attach_number,trade_no')
                ->find();
                // halt($orderAttachId);
            $usable_money = $Member->where(['member_id' => $orderAttachArr['member_id']])->value('usable_money');
            $_memberInfo = $Member->where(['member_id' => $orderAttachArr['member_id']])->find();
            if ($result['delete_time'] != NULL) return ['code' => -1, 'message' => '该售后已撤销'];
            $revokeFlag = false;
            $msgType = 0;
            // 处理售后状态
            if (array_key_exists('status', $param)) {
                if ($orderAttachId['status'] != 5.1 && $orderAttachId['status'] != 5.2) return ['code' => -100, 'message' => '订单状态错误，请重新刷新页面'];
                if ($param['status'] == 1) {
                    // 同意退款
                    switch ($result['type']) {
                        case '1' : // 仅退款 （退款成功）
                            if ($orderAttachId['redo_status'] == 1.1) {
                                // $weChatRefundPrice = $param['price'];//$orderAttachId['goodsTotal1'];
                                 $weChatRefundPrice = $orderAttachId['total_price'];
                                // 待发货 算运费
                                if ($param['price'] > $orderAttachId['goodsTotal1']) return ['code' => -1, 'message' => '退款金额不得大于交易金额'];
                            } else {
                                // $weChatRefundPrice = $param['price'];//$orderAttachId['goodsTotal'];
                                $weChatRefundPrice = $orderAttachId['total_price'];
                                if ($param['price'] > $orderAttachId['goodsTotal']) return ['code' => -1, 'message' => '退款金额不得大于交易金额'];
                            }
                            
                            if ($orderAttachArr['pay_channel'] == 3) {
                                // 余额
                                $Member->where(['member_id' => $orderAttachArr['member_id']])->setInc('usable_money', $param['price']);
                                $way = 4;
                                // 个人账户明细表
                                $consumption = [
                                    'member_id' => $orderAttachArr['member_id'],
                                    'store_id' => $orderAttachArr['store_id'],
                                    'type' => 3,
                                    'order_number' => $orderAttachArr['order_number'],
                                    'order_attach_number' => $orderAttachArr['order_attach_number'],
                                    'price' => $param['price'],
                                    'way' => $way,
                                    'balance' => $usable_money + $param['price'],
                                    'status' => 1,
                                    'create_time' => date('Y-m-d H:i:s'),
                                ];
                                
                                $consumptionModel->allowField(TRUE)->save($consumption);
                            } else if ($orderAttachArr['pay_channel'] == 2) {
                                // 支付宝
                              $refundNumber = strtoupper(dechex(date('m'))) . date('d') . substr(strval(time()), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
                                    header("Content-type: text/html; charset=utf-8");
                                    require Env::get('vendor_path') . 'alipay/pagepay/service/AlipayTradeService.php';
                                    require Env::get('vendor_path') . 'alipay/pagepay/buildermodel/AlipayTradeRefundContentBuilder.php';
                                    require Env::get('vendor_path') . 'alipay/config.php';
                                    global $ali_config;
                                    $alipaySevice = new \AlipayTradeService($ali_config);
                                    $RequestBuilder = new \AlipayTradeRefundContentBuilder();
                                    $RequestBuilder->setOutTradeNo($refundNumber);
                                    $RequestBuilder->setTradeNo($orderAttachArr['trade_no']);
                                    $RequestBuilder->setRefundAmount($param['price']);
                                    $RequestBuilder->setOutRequestNo($orderAttachArr['order_attach_number'] . $param['order_goods_id']);
                                    $RequestBuilder->setRefundReason('商品退款');
                                    $ali_ret = $alipaySevice->Refund($RequestBuilder);
                                    ob_clean();
                                    if ($ali_ret->code==10000){
                                        $refund_trade_no = $ali_ret->trade_no;

                                    }else {
                                        throw new Exception($ali_ret->sub_msg, -100);
                                    }
                                $way = 1;
                            } else if ($orderAttachArr['pay_channel'] == 1) {
                                // 微信
                                $refundNumber = strtoupper(dechex(date('m'))) . date('d') . substr(strval(time()), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
                                
                                $app = $app = Factory::payment(config('wechat.')['applet']);
                                switch ($orderAttachArr['case_pay_type']) {
                                    case 1:
                                        // 读取APP配置
                                        $app = Factory::payment(config('wechat.')['app']);
                                        break;
                                    case 2:
                                        // 读取小程序配置
                                        $app = Factory::payment(config('wechat.')['applet']);
                                        break;
                                    case 3:
                                        // 读取PC配置
                                       $app = Factory::payment(config('wechat.')['mobile']);
                                        break;
                                    case 4:
                                        // 读取手机站配置
                                        $app = Factory::payment(config('wechat.')['mobile']);
                                        break;
                                }
                                
                                $_storeInfo = $orderAttachArr->store;
                                
                                $_sendData = [
                                    $orderAttachArr['order_attach_number'],
                                    ($param['price']) . "元",
                                    $result['reason'],
                                    $_storeInfo['store_name'],
                                    '原路退回',
                                ];
                                
                                // 参数分别为：微信订单号、商户退款单号、订单金额、退款金额、其他参数
                                $returnResult = $app->refund->byTransactionId($orderAttachArr['trade_no'], $refundNumber, intval($weChatRefundPrice * 100), intval($param['price'] * 100));
                                if ($returnResult['result_code'] != 'FAIL') {
                                    $param['order_goods_refund_number'] = $refundNumber;
                                    // 退款通知
                                    try {
//                                        $_template = new TemplateMessage(TemplateMessage::MICRO_APP);
//
//                                        $_template->to($_memberInfo['micro_open_id'])
//                                            ->formId($orderAttachArr['prepay_id'])
//                                            ->page('pages/home/home')
//                                            ->template('refund_success', $_sendData)
//                                            ->send();
                                    } catch (\Exception $e) {
                                    }
                                } else {
                                    throw new Exception($returnResult['err_code_des'], -100);
                                }
                                $way = 2;
                            } else if ($orderAttachArr['pay_channel'] == 4) {
                                // 银行卡
                                $way = 3;
                            } else {
                                // 线下
                                $way = 5;
                            }
                            
                            //:TODO  不要动  不要动
                            // 更改售后订单状态
                            $orderGoodsRefund
                                ->save(
                                    [
                                        'refund_amount' => $param['price'],
                                        'status' => 1,
//                                        'order_goods_refund_number' => $param['order_goods_refund_number']??0,
                                        'dispose_time' => $this_time,
                                        'deliver_time' => $this_time,
                                        'finish_time' => $this_time,
                                    ],
                                    ['order_goods_id' => $param['order_goods_id']]
                                );
                            // 更改订单商品状态
                            $orderGoods
                                ->where(['order_goods_id' => $param['order_goods_id']])
                                ->update(['status' => 4.2]);
                            // 检测是否所有同源商品售后订单都已处理成功
                            $orderGoodsArr = $orderGoods
                                ->where([
                                    ['order_id', '=', $orderAttachArr['order_id']],
                                    ['order_goods_id', '<>', $param['order_goods_id']],
                                    ['status', '<>', 4.2],
                                ])
                                ->column('order_goods_id');
                            if (empty($orderGoodsArr)) {
                                // 更改order_attach 订单状态
                                $orderAttach->where(['order_attach_id' => $orderAttachId['order_attach_id']])->update(['status' => 4, 'is_all_refund' => 1]);
                            }
                            
                            // 店铺资金记录表
                            $capital = [
                                'status' => 3,
                                'order_goods_id' => $param['order_goods_id'],
                                'store_id' => $orderAttachArr['store_id'],
                                'price' => $param['price'],
                                'create_time' => date('Y-m-d H:i:s'),
                            ];
                            $storeCapital->allowField(TRUE)->save($capital);
                            $revokeFlag = true;
                            $msgType = 1;
                            break;
                        case '2' : // 退货退款第一步 退款 （同意退款）
                            // 1未收到货直接退款  2已收到货走填写物流
                            $status = $result['is_get_goods'] == 1 ? 4.3 : 5.3;
                            
                            // 1未收到货直接退款
                            if ($result['is_get_goods'] == 1) {
                                if ($orderAttachArr['pay_channel'] == 3) {
                                    // 余额
                                    $way = 4;
                                    $Member->where(['member_id' => $orderAttachArr['member_id']])->setInc('usable_money', $param['price']);
                                    
                                    // 个人账户明细表
                                    $consumption = [
                                        'member_id' => $orderAttachArr['member_id'],
                                        'store_id' => $orderAttachArr['store_id'],
                                        'type' => 3,
                                        'order_number' => $orderAttachArr['order_number'],
                                        'order_attach_number' => $orderAttachArr['order_attach_number'],
                                        'price' => $param['price'],
                                        'way' => $way,
                                        'balance' => $usable_money + $param['price'],
                                        'status' => 1,
                                        'create_time' => date('Y-m-d H:i:s'),
                                    ];
                                    
                                    $consumptionModel->allowField(TRUE)->save($consumption);
                                } else if ($orderAttachArr['pay_channel'] == 2) {
                                    // 支付宝
                                  $refundNumber = strtoupper(dechex(date('m'))) . date('d') . substr(strval(time()), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
                                    header("Content-type: text/html; charset=utf-8");
                                    require Env::get('vendor_path') . 'alipay/pagepay/service/AlipayTradeService.php';
                                    require Env::get('vendor_path') . 'alipay/pagepay/buildermodel/AlipayTradeRefundContentBuilder.php';
                                    require Env::get('vendor_path') . 'alipay/config.php';
                                    global $ali_config;
                                    $alipaySevice = new \AlipayTradeService($ali_config);
                                    $RequestBuilder = new \AlipayTradeRefundContentBuilder();
                                    $RequestBuilder->setOutTradeNo($refundNumber);
                                    $RequestBuilder->setTradeNo($orderAttachArr['trade_no']);
                                    $RequestBuilder->setRefundAmount($param['price']);
                                    $RequestBuilder->setOutRequestNo($orderAttachArr['order_attach_number'] . $param['order_goods_id']);
                                    $RequestBuilder->setRefundReason('商品退款');
                                    $ali_ret = $alipaySevice->Refund($RequestBuilder);
                                    ob_clean();
                                    if ($ali_ret->code==10000){
                                        $refund_trade_no = $ali_ret->trade_no;

                                    }else {
                                        throw new Exception($ali_ret->sub_msg, -100);
                                    }
                                    $way = 1;
                                } else if ($orderAttachArr['pay_channel'] == 1) {
                                    // 微信
                                    $refundNumber = strtoupper(dechex(date('m'))) . date('d') . substr(strval(time()), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
                                    
                                    $_storeInfo = $orderAttachArr->store;
                                    
                                    $_sendData = [
                                        $orderAttachArr['order_attach_number'],
                                        ($param['price']) . "元",
                                        $result['reason'],
                                        $_storeInfo['store_name'],
                                        '原路退回',
                                    ];
                                    
                                    $app = $app = Factory::payment(config('wechat.')['applet']);
                                    switch ($orderAttachArr['case_pay_type']) {
                                        case 1:
                                            // 读取APP配置
                                        $app = Factory::payment(config('wechat.')['app']);
                                            break;
                                        case 2:
                                            // 读取小程序配置
                                            $app = Factory::payment(config('wechat.')['applet']);
                                            break;
                                        case 3:
                                            // 读取PC配置
                                        $app = Factory::payment(config('wechat.')['mobile']);
                                            break;
                                        case 4:
                                            // 读取手机站配置
                                        $app = Factory::payment(config('wechat.')['mobile']);
                                            break;
                                    }
                                    // $weChatRefundPrice = $param['price'];//$orderAttachId['goodsTotal1'];
                                    $weChatRefundPrice =$orderAttachId['total_price'];
                                    // 参数分别为：微信订单号、商户退款单号、订单金额、退款金额、其他参数
                                    $returnResult = $app->refund->byTransactionId($orderAttachArr['trade_no'], $refundNumber, intval($weChatRefundPrice * 100), intval($param['price'] * 100));
                                    if ($returnResult['result_code'] != 'FAIL') {
                                        $param['order_goods_refund_number'] = $refundNumber;
                                        // 退款通知
                                        try {
//                                            $_template = new TemplateMessage(TemplateMessage::MICRO_APP);
//
//                                            $_template->to($_memberInfo['micro_open_id'])
//                                                ->formId($orderAttachArr['prepay_id'])
//                                                ->page('pages/home/home')
//                                                ->template('refund_success', $_sendData)
//                                                ->send();
                                        } catch (\Exception $e) {
                                        }
                                    } else {
                                        throw new Exception($returnResult['err_code_des'], -100);
                                    }
                                    $way = 2;
                                } else if ($orderAttachArr['pay_channel'] == 4) {
                                    // 银行卡
                                    $way = 3;
                                } else {
                                    // 线下
                                    $way = 5;
                                }
                                
                                // 检测是否所有同源商品售后订单都已处理成功
                                $orderGoodsArr = $orderGoods
                                    ->where([
                                        ['order_id', '=', $orderAttachArr['order_id']],
                                        ['order_goods_id', '<>', $param['order_goods_id']],
                                        ['status', '<>', 4.2],
                                    ])
                                    ->column('order_goods_id');
                                if (empty($orderGoodsArr)) {
                                    // 更改order_attach 订单状态
                                    $orderAttach->where(['order_attach_id' => $orderAttachId['order_attach_id']])->update(['status' => 4, 'is_all_refund' => 1]);
                                }
                                
                                // 店铺资金记录表
                                $capital = [
                                    'status' => 3,
                                    'order_goods_id' => $param['order_goods_id'],
                                    'store_id' => $orderAttachArr['store_id'],
                                    'price' => $param['price'],
                                    'create_time' => date('Y-m-d H:i:s'),
                                ];
                                $storeCapital->allowField(TRUE)->save($capital);
                            }
                            //退款处理
                            $orderGoodsRefund->save(
                                ['refund_amount' => $param['price'], 'status' => 1, 'dispose_time' => $this_time],
                                ['order_goods_id' => $param['order_goods_id']]
                            );
                            $orderGoods->where(['order_goods_id' => $param['order_goods_id']])->update(['status' => $status]);
                            break;
                    }
                } else {
                    // 拒绝
                    switch ($result['type']) {
                        case '1' : // 仅退款 （退款失败）
                            $orderGoods->where(['order_goods_id' => $param['order_goods_id']])->update(['status' => 5.5]);
                            break;
                        case '2' : // 退后退款第一步 退款 （退款失败）
                            $orderGoods->where(['order_goods_id' => $param['order_goods_id']])->update(['status' => 5.7]);
                            break;
                    }
                    $orderGoodsRefund->save(
                        ['status' => 2, 'refuse_reason' => $param['refuse_reason'], 'finish_time' => $this_time],
                        ['order_goods_id' => $param['order_goods_id']]
                    
                    );
                    $msgType = 2;
                    // 退款退货失败后用户无操作自动回滚到变化状态
                    (new Beanstalk())->put(json_encode(['queue' => 'refundToRedo',
                        'id' => $param['order_goods_id'], 'time' => date('Y-m-d H:i:s')]), 60 * 60 * 24 * 2);
                }
            }
            
            // 处理收货状态
            if (array_key_exists('is_get_goods', $param)) {
                if ($param['is_get_goods'] == 1) {
                    // 未收到货
                    $orderGoodsRefund->save(
                        ['status' => 2, 'refuse_reason' => '未收到货', 'finish_time' => $this_time],
                        ['order_goods_id' => $param['order_goods_id']]
                    );
                    $orderGoods->where(['order_goods_id' => $param['order_goods_id']])->update(['status' => 5.6]);
                    $msgType = 2;
                } else {
                    // 已收到货
                    $orderGoodsRefund->save(
                        ['status' => 1, 'finish_time' => $this_time],
                        ['order_goods_id' => $param['order_goods_id']]
                    );
                    $orderGoods->where(['order_goods_id' => $param['order_goods_id']])->update(['status' => 4.3]);
                    
                    if ($orderAttachArr['pay_channel'] == 3) {
                        // 余额
                        $way = 4;
                        $Member->where(['member_id' => $orderAttachArr['member_id']])->setInc('usable_money', $result['refund_amount']);
                        
                        // 个人账户明细表
                        $consumption = [
                            'member_id' => $orderAttachArr['member_id'],
                            'store_id' => $orderAttachArr['store_id'],
                            'type' => 3,
                            'order_number' => $orderAttachArr['order_number'],
                            'order_attach_number' => $orderAttachArr['order_attach_number'],
                            'price' => $result['refund_amount'],
                            'way' => $way,
                            'balance' => $usable_money + $result['refund_amount'],
                            'status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                        ];
                        $consumptionModel->allowField(TRUE)->save($consumption);
                    } else if ($orderAttachArr['pay_channel'] == 2) {
                        // 支付宝
                        $way = 1;
                    } else if ($orderAttachArr['pay_channel'] == 1) {
                        // 微信
                        $way = 2;
                        $refundNumber = strtoupper(dechex(date('m'))) . date('d') . substr(strval(time()), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
                        
                        $_storeInfo = $orderAttachArr->store;
                        
                        $_sendData = [
                            $orderAttachArr['order_attach_number'],
                            ($result['refund_amount']) . "元",
                            $result['reason'],
                            $_storeInfo['store_name'],
                            '原路退回',
                        ];
                        
                        $app = $app = Factory::payment(config('wechat.')['applet']);
                        switch ($orderAttachArr['case_pay_type']) {
                            case 1:
                                // 读取APP配置
//                                        $app = Factory::payment(config('wechat.')['applet']);
                                break;
                            case 2:
                                // 读取小程序配置
                                $app = Factory::payment(config('wechat.')['applet']);
                                break;
                            case 3:
                                // 读取PC配置
//                                        $app = Factory::payment(config('wechat.')['applet']);
                                break;
                            case 4:
                                // 读取手机站配置
//                                        $app = Factory::payment(config('wechat.')['applet']);
                                break;
                        }
                        
                        $weChatRefundPrice = $orderAttachId['total_price'];
                        // 参数分别为：微信订单号、商户退款单号、订单金额、退款金额、其他参数
                        $returnResult = $app->refund->byTransactionId($orderAttachArr['trade_no'], $refundNumber, intval($weChatRefundPrice * 100), intval($result['refund_amount'] * 100));
                        if ($returnResult['result_code'] != 'FAIL') {
                            $param['order_goods_refund_number'] = $refundNumber;
                            // 退款通知
                            try {
//                                $_template = new TemplateMessage(TemplateMessage::MICRO_APP);
//
//                                $_template->to($_memberInfo['micro_open_id'])
//                                    ->formId($orderAttachArr['prepay_id'])
//                                    ->page('pages/home/home')
//                                    ->template('refund_success', $_sendData)
//                                    ->send();
                            } catch (\Exception $e) {
                            }
                        } else {
                            throw new Exception($returnResult['err_code_des'], -100);
                        }
                    } else if ($orderAttachArr['pay_channel'] == 4) {
                        // 银行卡
                        $way = 3;
                    } else {
                        // 线下
                        $way = 5;
                    }
                    
                    // 检测是否所有同源商品售后订单都已处理成功
                    $orderGoodsArr = $orderGoods
                        ->where([
                            ['order_id', '=', $orderAttachArr['order_id']],
                            ['order_goods_id', '<>', $param['order_goods_id']],
                            ['status', '<>', 4.3],
                        ])
                        ->column('order_goods_id');
                    if (empty($orderGoodsArr)) {
                        // 更改order_attach 订单状态
                        $orderAttach->where(['order_attach_id' => $orderAttachId['order_attach_id']])->update(['status' => 4, 'is_all_refund' => 1]);
                    }
                    
                    // 店铺资金记录表
                    $capital = [
                        'status' => 3,
                        'order_goods_id' => $param['order_goods_id'],
                        'store_id' => $orderAttachArr['store_id'],
                        'price' => $result['refund_amount'],
                        'create_time' => date('Y-m-d H:i:s'),
                    ];
                    $storeCapital->allowField(TRUE)->save($capital);
                    $revokeFlag = true;
                    $msgType = 1;
                }
            }
            if ($msgType) {
                // 推送消息[退款成功|失败]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey' => 'order_state',
                    'openId' => $orderAttachId['order_goods_member']['web_open_id'],
                    'subscribe_time' => $orderAttachId['order_goods_member']['subscribe_time'],
                    'microId' => $orderAttachId['order_goods_member']['micro_open_id'],
                    'phone' => $orderAttachId['order_goods_member']['phone'],
                    'data' => [$msgType - 1 ? 3 : 4, $orderAttachId['store_name'],
                        $orderAttachId['order_goods_member']['nickname'],
                        $orderAttachId['goods_name']],
                    'inside_data' => [
                        'member_id' => $orderAttachId['member_id'],
                        'type' => 1,
                        'jump_state' => '6',
                        'attach_id' => $msgType - 1 ? $param['order_goods_id'] : $orderAttachId['order_goods_id'],
                        'file' => $orderAttachId->getData('file'),
                    ],
                    'sms_data' => [],
                ]);
            }
            if ($revokeFlag) {
                // 检测分销商是否需要撤销资格
                // 更改会员累积订单消费金额(
                if(!is_null($orderAttachId->order_goods_member) && !is_null($orderAttachId->order_goods_member->cumulative_order_sum))
                {
	                $orderAttachId->order_goods_member->cumulative_order_sum -= ($orderAttachId['subtotal_price']-$orderAttachId['sub_freight_price']);
	                $orderAttachId->order_goods_member->save();
                }
                // 更改分销记录为已取消
                if (!is_null($orderAttachId->distribution_book_close_sale)) {
                    $orderAttachId->distribution_book_close_sale->status = 2;
                    $orderAttachId->distribution_book_close_sale->save();
                    $disChange = [];
                    if ($orderAttachId->distribution_book_close_sale->distributor_a &&
                        $orderAttachId->distribution_book_close_sale->distributor_a_brokerage > 0) {
                        // 一级分销商
                        array_push($disChange, [
                            'distribution_id' => $orderAttachId->distribution_book_close_sale->distributor_a,
                            'total_brokerage' => Db::raw('total_brokerage - ' . $orderAttachId->distribution_book_close_sale->distributor_a_brokerage)
                        ]);
                    }
                    if ($orderAttachId->distribution_book_close_sale->distributor_b &&
                        $orderAttachId->distribution_book_close_sale->distributor_b_brokerage > 0) {
                        // 二级分销商
                        array_push($disChange, [
                            'distribution_id' => $orderAttachId->distribution_book_close_sale->distributor_b,
                            'total_brokerage' => Db::raw('total_brokerage - ' . $orderAttachId->distribution_book_close_sale->distributor_b_brokerage)
                        ]);
                    }
                    if ($orderAttachId->distribution_book_close_sale->distributor_c &&
                        $orderAttachId->distribution_book_close_sale->distributor_c_brokerage > 0) {
                        // 三级分销商
                        array_push($disChange, [
                            'distribution_id' => $orderAttachId->distribution_book_close_sale->distributor_c,
                            'total_brokerage' => Db::raw('total_brokerage - ' . $orderAttachId->distribution_book_close_sale->distributor_c_brokerage)
                        ]);
                    }
                    if ($disChange) {
                        $distributionModel->allowField(true)->isUpdate(true)->saveAll($disChange);
                    }
                }
                if (!is_null($orderAttachId->order_goods_member) && !is_null($orderAttachId->order_goods_member->distribution_record)) {
                    $revokeArr = [
                        'distribution_id' => $orderAttachId['order_goods_member']['distribution_record']['distribution_id'],
                        'og_id' => [$param['order_goods_id']],
                        'cumulative_order_sum' => $orderAttachId->order_goods_member->cumulative_order_sum,
                    ];
                    (new \app\common\service\Distribution())->checkDistIsRevoke($revokeArr);
                }
            }
            // 提交事务
            Db::commit();
            return ['code' => 0, 'message' => '操作成功'];
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 我的订单统计(退换货)
     * @param $member_id
     * @param $function_status
     * @return array
     */
    public function myStatistical($member_id, $function_status)
    {
        $imgPathPrefix = 'mobile/small/image/';
        $count = ['key' => 'afterSale', 'title' => '退换/售后', 'count' => 0, 'img' => $imgPathPrefix . 'thh.png'];
        $where = [
            ['og.member_id', '=', $member_id],
            ['og.status', 'in', '5.1,5.2,5.3,5.4'],
        ];
        if (!$function_status['is_take']) {
            // 自提
            array_push($where, ['oa.distribution_type', '<>', 2]);
        }
        if (!$function_status['is_underPay']) {
            // 线下
            array_push($where, ['oa.distribution_type', '<>', 4]);
        }
        if ($member_id) {
            $count['count'] = $this
                ->alias('og')
                ->where($where)
                ->join('order_attach oa', 'oa.order_attach_id = og.order_attach_id')
                ->count('og.order_goods_id');
        }
        return $count;
    }
    
}