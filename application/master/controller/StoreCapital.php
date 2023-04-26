<?php
// 资产
declare(strict_types = 1);

namespace app\master\controller;

use app\common\model\CheckOrder;
use app\common\model\Consumption as ConsumptionModel;
use app\common\model\Distribution;
use app\common\model\DistributionBook as DistributionBookModel;
use app\common\model\DistributionWithdraw as DistributionWithdrawModel;
use app\common\model\MemberCoupon;
use app\common\model\MemberPacket;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\OrderGoodsRefund;
use think\exception\ValidateException;
use app\common\model\Recharge as RechargeModel;
use app\common\model\StoreCosts;
use think\facade\Request;
use app\common\model\StoreCapital as StoreCapitalModel;
use app\common\model\Store as StoreModel;
use think\Controller;
use think\facade\Session;
use think\Db;
use app\common\model\CheckOrder as CheckOrderModel;

class StoreCapital extends Controller
{
    /**
     * 财务-资产概况
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author Malson 2019-05-08
     *
     */
    public function general()
    {
        $_now = date('Y-m-d');
        // 金钱列表
        $_moneyList = [
            'PLATFORM_IN' => 0.00,    // 进账金额
            'PLATFORM_OUT' => 0.00,    // 出账金额
            'PLATFORM_GROSS_PROFIT' => 0.00,    // 平台毛利
            'PLATFORM_RECHARGE' => 0.00,    // 商城充值统计(用户自己充值)
            'PLATFORM_HANDLING_FEE' => 0.00,    // 平台使用费
            'PLATFORM_ORDER_REFUND' => 0.00,    // 订单退款
            // 进账预览
            'IN_REVIEW' => [
                'WE_PAY' => 0.00, // 微信支付
                'ALI_PAY' => 0.00, // 支付宝支付
                'BALANCE' => 0.00, // 余额支付
                'CASH_ON_DELIVERY' => 0.00, // 货到付款
                'OFFLINE' => 0.00, // 线下支付
            ],
            // 出账预览
            'OUT_PREVIEW' => [
                'MERCHANT_SETTLEMENT' => 0.00,    // 商家结算
                'DISTRIBUTION' => 0.00,    // 分销佣金结算
                'ORDER_REFUND' => 0.00,    // 订单退款
                'PLATFORM_COUPON' => 0.00,    // 平台优惠券
                'PLATFORM_RED_ENVELOPE' => 0.00,    // 平台红包
                'MEMBER_DISCOUNT' => 0.00,    // 会员折扣
            ],
            // 充值统计
            'CHARGE_STATISTICS' => [
                'MANUAL_CHARGE' => 0.00,    //后台手动充值
                'TOTAL_MEMBER_CHARGE' => 0.00,    // 商城会员充值合计
            ],
            // 商家结算统计
            'MERCHANT_SETTLEMENT_STATISTICS' => [
                'WAIT_SETTLEMENT' => 0.00,  // 待结算金额
                'WAS_SETTLEMENT' => 0.00,  // 已结算金额
                'HANDLING_FEE_SETTLEMENT' => 0.00,  // 已结算平台使用费
            ],
            // 商家提现统计
            'MERCHANT_WITHDRAW_STATISTICS' => [
                'PENDING_REVIEW' => 0.00,  // 待审核
                'TRANSFER' => 0.00,  // 转账中
                'REVIEW_FAILED' => 0.00,  // 审核失败
                'TRANSFER_FAILED' => 0.00,  // 提现失败
            ],
            // 今日销售情况
            'TODAY_SHELL_INFO' => [
                'IN' => 0.00, // 入账
                'OUT' => 0.00, // 出账
                'GROSS_PROFIT' => 0.00, // 毛利
            ],
            // 近七日进账金额
            '7_DAY_IN' => [],
            // 近七日出账金额
            '7_DAY_OUT' => [],
        ];
        
        $_dateArr = [];
        
        /**
         * 进账金额
         */
        // 订单入账的金额
        $_moneyInFromOrder = (float)OrderAttach::where([
                ['pay_channel', '>', '0'], // 支付方式为 微信 和 支付宝 线下付款
                ['status', 'in', '1,2,3,4,5'],      // 订单状态为 待配送 配送中 已完成 退款中
            ])->sum('subtotal_price') ?? 0;
        
        // 用户充值入账的金额
        $_moneyInFromUserRecharge = (float)ConsumptionModel::where([
                ['type', '=', '0'],         // 充值
                ['way', 'in', '1,2,3']    // 支付宝,微信,银行卡
            ])->sum('price') ?? 0;
        
        // 店铺充值入账的金额
        $_moneyInFromStoreRecharge = (float)StoreCapitalModel::where([
                ['status', '=', '0'],     // 充值
            ])->sum('price') ?? 0;
        
        $_moneyList['PLATFORM_IN'] = $_moneyInFromOrder + $_moneyInFromUserRecharge + $_moneyInFromStoreRecharge;
        
        /**
         * 包括商家订单支付金额、平台优惠券、平台红包、分销返佣、用户退款金额
         */
        // 给商家结算的金额
        $_moneyOutFromStoreSettle = (float)StoreCapitalModel::where([
                ['status', '=', '1.2'],
            ])->sum('price') ?? 0;
        
        // 平台优惠券
        $_moneyPlatFromCoupon = (float)OrderAttach::where([
            ['status', 'notin', '0,6'],
            ['pay_channel', '>', '0'],
        ])->sum('subtotal_share_platform_coupon_price');
        //            MemberCoupon::where([
        //                ['type', '=', '1'],     // 优惠券分类 平台
        //                ['status', '=', '1'],   // 状态 已使用
        //            ])->sum('actual_price') ?? 0;
        
        // 平台红包
        $_monetPlatFromPacket = (float)OrderAttach::where([
            ['status', 'notin', '0,6'],
            ['pay_channel', '>', '0'],
        ])->sum('subtotal_share_platform_packet_price');
        //            MemberPacket::where([
        //                ['type', '>', 0],       // 平台红包,会员注册红包,被邀会员首次消费红包
        //                ['status', '=', '1'],   // 状态 已使用
        //            ])->sum('actual_price') ?? 0;
        
        // 已结算佣金金额
        $_moneyOutFromCommissionSettle = (float)DistributionWithdrawModel::where([
                ['status', '=', 1],
            ])->fieldRaw('SUM(price - service_charge) as commissionSettle')->find()['commissionSettle'] ?? 0;
        
        // 给会员充值的余额
        $_moneyOutFromRechargeToMember = (float)ConsumptionModel::where([
                ['type', '=', '0'],     // 充值
                ['way', 'in', '5']      // 线下(平台后台支付)
            ])->sum('price') ?? 0;
        
        // 用户退款金额
        // $_moneyOutFromUserRefund = (float)OrderAttach::where([
        //         ['pay_channel', '>', '0'],   // 支付方式为 微信 和 支付宝 线下付款
        //         ['status', 'in', '4'],          // 订单状态为 已关闭
        //     ])->sum('subtotal_price') ?? 0;
        $_moneyOutFromUserRefund = (float)OrderGoodsRefund::withTrashed()
                ->where([
                    ['status', '=', '1'],          // 已退款
                ])->sum('refund_amount') ?? 0;
        
        $_moneyList['PLATFORM_OUT'] = $_moneyOutFromStoreSettle +
            $_moneyPlatFromCoupon +
            $_monetPlatFromPacket +
            $_moneyOutFromUserRefund +
            $_moneyOutFromCommissionSettle;
        //            $_moneyOutFromRechargeToMember;
        
        /**
         * 平台毛利 进账金额-出账金额
         */
        $_moneyList['PLATFORM_GROSS_PROFIT'] = $_moneyList['PLATFORM_IN'] - $_moneyList['PLATFORM_OUT'];
        
        
        /**
         * 商城充值统计 用户自行充值的余额
         */
        $_moneyList['PLATFORM_RECHARGE'] = (float)ConsumptionModel::where([
                ['type', '=', '0'],       // 类型是充值
                ['way', 'in', '1,2,3']    // 支付宝,微信,银行卡
            ])->sum('price') ?? 0;
        /**
         * 平台使用费
         */
        $_moneyList['PLATFORM_HANDLING_FEE'] = (float)CheckOrder::where([['check_status', '=', 2]])//退款审核成功  退货审核成功
        ->sum('costs', 0);
        
        /**
         * 订单退款
         */
        $_moneyList['PLATFORM_ORDER_REFUND'] = (float)OrderGoodsRefund::where('(type=1 and status =1) or (type = 3 and status = 1)')//退款审核成功  退货审核成功
        ->sum('refund_amount', 0);
        
        /**
         * 进账预览
         */
        
        // 微信支付
        $_moneyList['IN_REVIEW']['WE_PAY'] = (float)OrderAttach::where([
                ['pay_channel', '=', '1'],     // 支付类型 微信
                ['status', 'in', '1,2,3,4'],     // 订单状态 待配送 配送中 已完成
            ])->sum('subtotal_price') ?? 0;
        
        // 支付宝支付
        $_moneyList['IN_REVIEW']['ALI_PAY'] = (float)OrderAttach::where([
                ['pay_channel', '=', '2'],      // 支付类型 支付宝
                ['status', 'in', '1,2,3,4'],      //
            ])->sum('subtotal_price') ?? 0;
        
        // 余额支付
        $_moneyList['IN_REVIEW']['BALANCE'] = (float)OrderAttach::where([
                ['pay_channel', '=', '3'],      // 支付类型 余额
                ['status', 'in', '1,2,3,4'],      // 支付类型 微信
            ])->sum('subtotal_price') ?? 0;
        
        // 货到付款
        $_moneyList['IN_REVIEW']['CASH_ON_DELIVERY'] = (float)OrderAttach::where([
                ['pay_channel', '=', '6'],      // 支付类型 货到付款
                //                ['status', 'in', '1'],      // 支付类型 微信
            ])->sum('subtotal_price') ?? 0;
        
        // 线下订单
        $_moneyList['IN_REVIEW']['OFFLINE'] = (float)OrderAttach::where([
                ['pay_channel', '=', '5'],      // 支付类型 货到付款
            ])->sum('subtotal_price') ?? 0;
        
        /**
         * 出账预览
         */
        
        // 商家结算
        // 商家订单实际支付金额
        $_moneyList['OUT_PREVIEW']['MERCHANT_SETTLEMENT'] = (float)OrderAttach::where([
                ['status', 'in', '1,2,3,4'],         // 完成的订单
            ])->sum('subtotal_price') ?? 0;
        
        // 分销佣金结算
        $_moneyList['OUT_PREVIEW']['DISTRIBUTION'] = (float)DistributionBookModel::where([
                ['status', '=', 1],
            ])->fieldRaw('SUM(distributor_a_brokerage + distributor_b_brokerage + distributor_c_brokerage) as commissionSettle')->find()['commissionSettle'] ?? 0;
        
        // 订单退款
        $_moneyList['OUT_PREVIEW']['ORDER_REFUND'] = (float)OrderGoodsRefund::where('(type=1 and status =1) or (type = 3 and status = 1)')//退款审核成功  退货审核成功
        ->sum('refund_amount', 0);
        
        // 平台优惠券
        $_moneyList['OUT_PREVIEW']['PLATFORM_COUPON'] = $_moneyPlatFromCoupon;
        //            (float)MemberCoupon::where([
        //                ['type', '=', '1'],     // 平台优惠券
        //                ['status', '=', '1'],   // 已使用
        //            ])->sum('actual_price') ?? 0;
        
        // 平台红包
        $_moneyList['OUT_PREVIEW']['PLATFORM_RED_ENVELOPE'] = $_monetPlatFromPacket;
        //            (float)MemberPacket::where([
        //                ['type', 'in', '1,2,3'],    // 平台红包,会员注册红包,被邀请会员首次消费红包
        //                ['status', '=', '1'],       // 已使用
        //            ])->sum('actual_price') ?? 0;
        
        // 会员折扣
        $_moneyList['OUT_PREVIEW']['MEMBER_DISCOUNT'] = (float)OrderGoods::where([
                ['status', '<>', '0.1'],
                ['status', '<>', '6.1'] // 已支付
            ])->fieldRaw('SUM(IFNULL(discount_price*quantity,0)) AS discount')
                ->find()['discount'] ?? 0;
        
        /**
         * 充值统计
         */
        
        // 后台手动充值合计
        $_moneyList['CHARGE_STATISTICS']['MANUAL_CHARGE'] = $_moneyOutFromRechargeToMember;
        
        // 商城会员充值合计
        $_moneyList['CHARGE_STATISTICS']['TOTAL_MEMBER_CHARGE'] = $_moneyList['PLATFORM_RECHARGE'];
        
        
        /**
         * 商家结算统计
         */
        // 待结算金额
        $_moneyList['MERCHANT_SETTLEMENT_STATISTICS']['WAIT_SETTLEMENT'] = (float)OrderAttach::where([
            ['is_checking', '=', 0],
            ['status', 'not in', '0,6'],
        ])->field(
            'SUM(subtotal_price + subtotal_share_platform_coupon_price + subtotal_share_platform_packet_price) as wait_settlement'
        )->find()['wait_settlement'];
        
        //已结算金额
        $_moneyList['MERCHANT_SETTLEMENT_STATISTICS']['WAS_SETTLEMENT'] = (float)StoreCapitalModel::where([
                ['status', 'in', '1.2'],         // 提现成功
            ])->sum('price') ?? 0;
        // 已结算平台使用费
        $_moneyList['MERCHANT_SETTLEMENT_STATISTICS']['HANDLING_FEE_SETTLEMENT'] = (float)DistributionWithdrawModel::where([
                ['status', '=', '1'],           // 已确认转账
            ])->sum('service_charge') ?? 0;
        
        /**
         * 商家提现统计
         */
        
        // 待审核
        $_moneyList['MERCHANT_WITHDRAW_STATISTICS']['PENDING_REVIEW'] = StoreCapitalModel::where([
                ['status', '=', '1.1'],
            ])->count() ?? 0;
        
        // 转账中
        $_moneyList['MERCHANT_WITHDRAW_STATISTICS']['TRANSFER'] = StoreCapitalModel::where([
                ['status', '=', '1.4'],
            ])->count() ?? 0;
        
        // 审核失败
        $_moneyList['MERCHANT_WITHDRAW_STATISTICS']['REVIEW_FAILED'] = StoreCapitalModel::where([
                ['status', '=', '1.5'],
            ])->count() ?? 0;
        
        // 提现失败
        $_moneyList['MERCHANT_WITHDRAW_STATISTICS']['TRANSFER_FAILED'] = StoreCapitalModel::where([
                ['status', '=', '1.3'],
            ])->count() ?? 0;
        
        /**
         * 今日销售情况
         */
        
        // 今日所有已支付的金额，包括三方支付（微信、支付宝）、线下支付（货到付款、线下订单）、余额支付
        $_moneyList['TODAY_SHELL_INFO']['IN'] = (float)OrderAttach::where([
                ['pay_time', 'between time',     // 今天
                    [
                        $_now,
                        $_now . ' 23:59:59',
                    ],
                ],
                ['status', 'in', '1,2,3,4'],          // 支付的订单
                ['pay_channel', '>', '0'],
            ])->sum('subtotal_price') ?? 0;
        
        // 今日包括商家订单支付金额、平台优惠券、平台红包、分销返佣、给会员充值余额、用户退款金额
        
        
        // 提现成功
        $_todayOutMerchantSettlement = (float)StoreCapitalModel::where([
                ['create_time', 'between time',     // 今天
                    [
                        $_now,
                        $_now . ' 23:59:59',
                    ],
                ],
                ['status', 'in', '1.2'],         // 提现成功
            ])->sum('price') ?? 0;
        
        // 平台优惠券
        $_todayOutPlatFromCoupon = (float)MemberCoupon::where([
                ['create_time', 'between time',     // 今天
                    [
                        $_now,
                        $_now . ' 23:59:59',
                    ],
                ],
                ['type', '=', '1'],     // 平台优惠券
                ['status', '=', '1'],   // 已使用
            ])->sum('actual_price') ?? 0;
        
        // 平台红包
        $_todayOutPlatFromPackage = (float)MemberPacket::where([
                ['create_time', 'between time',     // 今天
                    [
                        $_now,
                        $_now . ' 23:59:59',
                    ],
                ],
                ['type', 'in', '1,2,3'],    // 平台红包,会员注册红包,被邀请会员首次消费红包
                ['status', '=', '1'],       // 已使用
            ])->sum('actual_price') ?? 0;
        
        // 分销返佣
        $_todayOutPlatFromDistribution = (float)DistributionWithdrawModel::where([
                ['create_time', 'between time',     // 今天
                    [
                        $_now,
                        $_now . ' 23:59:59',
                    ],
                ],
                ['status', '=', 1],
            ])->fieldRaw('SUM(price - service_charge) as commissionSettle')->find()['commissionSettle'] ?? 0;
        
        
        // 给会员充值余额
        $_todayOutPlatFromCharge = (float)ConsumptionModel::where([
                ['create_time', 'between time',     // 今天
                    [
                        $_now,
                        $_now . ' 23:59:59',
                    ],
                ],
                ['type', '=', '0'],     // 充值
                ['way', 'in', '5']      // 线下(平台后台支付)
            ])->sum('price') ?? 0;
        
        
        // 用户退款金额
        $_todayOutMemberRefund = (float)OrderGoodsRefund::where([
                ['finish_time', 'between time',     // 今天
                    [
                        $_now,
                        $_now . ' 23:59:59',
                    ],
                ],
            ])->sum('refund_amount') ?? 0;
        
        // 出账
        $_moneyList['TODAY_SHELL_INFO']['OUT'] = $_todayOutMerchantSettlement +
            $_todayOutPlatFromCoupon +
            $_todayOutPlatFromPackage +
            $_todayOutPlatFromDistribution +
            $_todayOutMemberRefund;
        //            $_todayOutPlatFromCharge +
        
        // 今日进账金额-今日出账金额
        $_moneyList['TODAY_SHELL_INFO']['GROSS_PROFIT'] = $_moneyList['TODAY_SHELL_INFO']['IN'] - $_moneyList['TODAY_SHELL_INFO']['OUT'];
        
        /**
         * 准备七日的field
         */
        $_7DayInFieldList = [];
        $_7DayOutFieldList = [];
        
        // 循环次数
        $_dataOffset = $_loopTime = 7;
        // unix时间戳
        $_nowUnix = time();
        
        // 开始循环
        for ($index = 0; $index < $_loopTime; $index++) {
            // 获取日期的字符串
            $_day = date('Y-m-d', $_nowUnix - (86400 * --$_dataOffset));
            
            // 订单入账的金额
            $_7DayInFieldList['ORDER'][] = "sum(if(create_time > '{$_day} 00:00:00' AND create_time < '{$_day} 23:59:00',subtotal_price,0)) `row_{$index}`";
            // 用户充值入账的金额
            $_7DayInFieldList['USER_RECHARGE'][] = "ifNULL(sum(if(create_time >= '{$_day} 00:00:00' and create_time <= '{$_day} 23:59:00',1,0)),0) `row_{$index}`";
            // 店铺充值入账的金额
            $_7DayInFieldList['STORE_RECHARGE'][] = "ifNULL(sum(if(create_time >= '{$_day} 00:00:00' and create_time <= '{$_day} 23:59:00',price,0)),0) `row_{$index}`";
            
            // 给商家结算的金额
            $_7DayOutFieldList['STORE_SETTLE'][] = "ifNULL(sum(if(create_time >= '{$_day} 00:00:00' and create_time <= '{$_day} 23:59:00',price,0)),0) `row_{$index}`";
            // 用户退款金额
            $_7DayOutFieldList['USER_REFUND'][] = "ifNULL(sum(if(create_time >= '{$_day} 00:00:00' and create_time <= '{$_day} 23:59:00',subtotal_price,0)),0) `row_{$index}`";
            // 已结算佣金金额
            $_7DayOutFieldList['COMMISSION_SETTLE'][] = "ifNULL(sum(if(create_time >= '{$_day} 00:00:00' and create_time <= '{$_day} 23:59:00',price - service_charge,0)),0) `row_{$index}`";
            // 给会员充值的余额
            //            $_7DayOutFieldList['RECHARGE_TO_MEMBER'][] = "ifNULL(sum(if(create_time >= '{$_day} 00:00:00' and create_time <= '{$_day} 23:59:00',price,0)),0) `row_{$index}`";
            $_dateArr[] = $_day;
        }
        
        /**
         * 近七日进账金额
         */
        // 订单入账的金额
        $_7DayInFromOrder = OrderAttach::where([
            ['pay_channel', '>', '0'],
            ['status', 'in', '1,2,3,5']   // 订单状态 待配送 配送中 已完成 退款中
        ])->field($_7DayInFieldList['ORDER'])->find();
        
        // 用户充值入账的金额
        $_7DayInFromUserRecharge = ConsumptionModel::where([
            ['type', '=', '0'],         // 充值
            ['way', 'in', '1,2,3']    // 支付宝,微信,银行卡
        ])->field($_7DayInFieldList['USER_RECHARGE'])->find();
        
        // 店铺充值入账的金额
        $_7DayInFromStoreRecharge = StoreCapitalModel::where([
            ['status', '=', '0'],     // 充值
        ])->field($_7DayInFieldList['STORE_RECHARGE'])->find();
        
        
        /**
         * 近七日出账金额
         */
        // 给商家结算的金额
        $_7DayOutFromStoreSettle = StoreCapitalModel::where([
            ['status', '=', '1.2'],
        ])->field($_7DayOutFieldList['STORE_SETTLE'])->find();
        
        // 用户退款金额
        $_7DayOutFromUserRefund = OrderAttach::where([
            ['pay_channel', '>', '0'],   // 支付方式为 微信 和 支付宝 线下付款
            ['status', 'in', '4'],          // 订单状态为 已关闭
        ])->field($_7DayOutFieldList['USER_REFUND'])->find();
        
        // 已结算佣金金额
        $_7DayOutFromCommissionSettle = DistributionWithdrawModel::where([
            ['status', '=', 1],
        ])->field($_7DayOutFieldList['COMMISSION_SETTLE'])->find();
        //
        //        // 给会员充值的余额
        //        $_7DayOutFromRechargeToMember = ConsumptionModel::where([
        //            ['type', '=', '0'],     // 充值
        //            ['way', 'in', '5']      // 线下(平台后台支付)
        //        ])->field($_7DayOutFieldList['RECHARGE_TO_MEMBER'])->find();
        
        for ($i = 0; $i < $_loopTime; $i++) {
            // 近七日进账金额
            $_moneyList['7_DAY_IN'][] = $_7DayInFromOrder["row_{$i}"] + $_7DayInFromUserRecharge["row_{$i}"] + $_7DayInFromStoreRecharge["row_{$i}"];
            $_moneyList['7_DAY_OUT'][] = $_7DayOutFromStoreSettle["row_{$i}"] + $_7DayOutFromUserRefund["row_{$i}"] + $_7DayOutFromCommissionSettle["row_{$i}"];
            //            + $_7DayOutFromRechargeToMember["row_{$i}"]
        }
        
        $_moneyList['7_DAY_IN'] = json_encode($_moneyList['7_DAY_IN']);
        $_moneyList['7_DAY_OUT'] = json_encode($_moneyList['7_DAY_OUT']);
        
        $this->assign('money_list', $_moneyList);
        $this->assign('date', json_encode($_dateArr));
        $this->assign('one_more', config('user.one_more'));
        
        return $this->fetch();
    }
    
    /**
     * 账户明细
     * @param Request $request
     * @param StoreCapitalModel $storeCapital
     * @return array|mixed
     */
    public function details(Request $request, StoreCapitalModel $storeCapital, CheckOrderModel $checkOrder)
    {
        try {
            // 获取数据
            $param = $request::get();
            
            $param['start_date'] = !empty($param['start_date']) ? $param['start_date'] : '1968-01-01 00:00:00'; // 开始时间
            $param['end_date'] = !empty($param['end_date']) ? $param['end_date'] : date('Y-m-d H:i:s', time()); // 结束时间
            
            // 筛选条件
            $condition = [];
            $min = [];
            $max = [];
            // 关键词
            if (!empty($param['keyword'])) $condition[] = ['order_attach.order_attach_number|order_attach.trade_no', 'like', '%' . $param['keyword'] . '%'];
            // 支付方式 0全部 1微信 2支付宝 3余额 5货到付款
            if (isset($param['pay_channel']) && $param['pay_channel'] != 0) $condition[] = ['order_attach.pay_channel', 'eq', $param['pay_channel']];
            // 类型 0充值 1.提现 2交易订单 3退款订单
            if (isset($param['status']) && $param['status'] != -1) $condition[] = ['store_capital.status', 'eq', $param['status']];
            // 状态 0全部 1进行中 2交易成功 3退款成功
            if (isset($param['order_referer']) && $param['order_referer'] != -1 && $param['order_referer'] != 3) $condition[] = ['order_attach.is_checking', 'eq', $param['order_referer']];
            if (isset($param['order_referer']) && $param['order_referer'] == 3) $condition[] = ['store_capital.status', 'eq', $param['order_referer']];
            
            // 查询金额
            if (isset($param['min']) && $param['min'] != '') $min [] = ['store_capital.price', '>=', $param['min']];
            if (isset($param['max']) && $param['max'] != '') $max [] = ['store_capital.price', '<=', $param['max']];
            
            $data = $storeCapital
                ->alias('store_capital')
                ->join('order_attach order_attach', 'order_attach.order_attach_id = store_capital.order_attach_id', 'left')
                ->join('consumption consumption', 'consumption.consumption_id = store_capital.parameter_id', 'left')
                ->join('member member', 'consumption.member_id = member.member_id', 'left')
                ->relation(['orderGoodsRefund' => function ($g) {
                    $g->alias('a')
                        ->join('order_goods order_goods', 'order_goods.order_goods_id = a.order_goods_id', 'left')
                        ->join('order_attach order_attach', 'order_goods.order_attach_id = order_attach.order_attach_id', 'left')
                        ->field('a.*,order_attach.order_attach_number,order_goods.single_price,order_attach.create_time,order_attach.pay_channel');
                }])
                ->whereNotIn('store_capital.status', '1.1,1.3,1.4,1.5')
                ->where($condition)
                ->where($min)
                ->where($max)
                ->whereTime('store_capital.create_time', 'between', [$param['start_date'], $param['end_date']])
                ->field('store_capital.*,order_attach.order_attach_number,order_attach.is_checking,order_attach.subtotal_price,order_attach.trade_no,
                order_attach.create_time as order_attach_number_create_time,order_attach.pay_channel,consumption.member_id,member.phone')
                ->order(['store_capital.create_time' => 'desc']);
            
            if (isset($param['dc'])) {
                $data1 = $data->select();
                $simple = ['账户变动时间', '类型', '名称/备注', '收入/支出', '状态'];
                
                $status = '';
                $pay_channel = '';
                $content = '';
                $symbol = '';
                $state = '';
                $result = [];
                foreach ($data1 as $key => $value) {
                    
                    switch ($value['pay_channel']) {
                        case '1' :
                            $pay_channel = '微信';
                            break;
                        case '2' :
                            $pay_channel = '支付宝';
                            break;
                        case '3' :
                            $pay_channel = '余额';
                            break;
                        case '4' :
                            $pay_channel = '银行卡';
                            break;
                        case '5' :
                            $pay_channel = '线下';
                            break;
                        case '6' :
                            $pay_channel = '货到付款';
                            break;
                    }
                    
                    switch ($value['orderGoodsRefund']['type']) {
                        case '1' :
                            $type = '店铺未发货申请的订单退款';
                            break;
                        default :
                            $type = '店铺完成发货后申请的订单退款';
                            break;
                    }
                    
                    switch ($value['is_checking']) {
                        case '1' :
                            $is_checking = '交易成功';
                            break;
                        default :
                            $is_checking = '进行中';
                            break;
                    }
                    
                    switch ($value['status']) {
                        case '0' :
                            $status = '充值';
                            $content = '店铺充值';
                            $symbol = '+' . $value['price'];
                            $state = '充值成功';
                            break;
                        case '1.2' :
                            $status = '提现';
                            $content = '店铺提现' .
                                '提现编号：' . $value['withdraw_number'] .
                                '提现时间：' . $value['create_time'] .
                                '提现金额：' . $value['price'];
                            $symbol = '-' . $value['price'];
                            $state = '提现成功';
                            break;
                        case '2' :
                            $status = '交易订单';
                            $content = '店铺售卖商品收益' .
                                '订单编号：' . $value['order_attach_number'] .
                                '下单时间：' . $value['create_time'] .
                                '订单金额：' . $value['subtotal_price'] .
                                '支付方式：' . $pay_channel;
                            $symbol = '+' . $value['subtotal_price'];
                            $state = $is_checking;
                            break;
                        case '3' :
                            $status = '退款订单';
                            $content = $type .
                                '订单编号：' . $value['orderGoodsRefund']['order_attach_number'] .
                                '下单时间：' . $value['orderGoodsRefund']['create_time'] .
                                '订单金额：' . $value['orderGoodsRefund']['single_price'] .
                                '支付方式：' . $pay_channel;
                            $symbol = '-' . $value['orderGoodsRefund']['refund_amount'];
                            $state = '退款成功';
                            break;
                        case '4' :
                            $status = '充值赠送';
                            $content = '会员充值赠送' .
                                '会员账号：' . $value['phone'] .
                                '赠送金额：' . $value['price'];
                            $symbol = '-' . $value['price'];
                            $state = '赠送成功';
                            break;
                    }
                    
                    $result[] = [
                        ' ' . $value['create_time'], $status, $content, $symbol, $state,
                    ];
                }
                
                $checkOrder->putCsv('账户明细导出' . '.csv', $result, $simple);
            } else {
                $data = $data->paginate(10, false, ['query' => $param]);
            }
            
            $price = $storeCapital->alias('store_capital')
                ->join('order_attach order_attach', 'order_attach.order_attach_id = store_capital.order_attach_id', 'left')
                ->where($condition)
                ->where($min)
                ->where($max)
                ->whereTime('store_capital.create_time', 'between', [$param['start_date'], $param['end_date']])
                ->field('store_capital.*,IFNULL(sum(if(store_capital.status = 0 or (store_capital.status = 2 and order_attach.status not in(0,6)),price,0)),0) as income,
                IFNULL(sum(if(store_capital.status in (1.2,3),price,NULL)),0) as expend')
                ->find();
            
        } catch (\Exception $e) {
            halt($e->getMessage());
            //            return $e->getMessage();
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        
        return $this->fetch('', [
            'data' => $data,
            'price' => $price,
        ]);
    }
    
    /**
     * 对账单列表
     * @param CheckOrder $checkOrder
     * @param OrderAttach $orderAttach
     * @param Store $store
     * @param Request $request
     * @return array|mixed
     */
    public function property_list(CheckOrder $checkOrder, OrderAttach $orderAttach, Request $request)
    {
        try {
            $param = $request::param();
            //待结算
            $where = [];
            if (isset($param['shop']) && $param['shop'] != -1) $where[] = ['b.shop', '=', $param['shop']];
            if (!empty($param['store_name'])) $where[] = ['b.store_name', 'like', '%' . $param['store_name'] . '%'];
            $order_attach_data = $orderAttach::withTrashed()
                ->with('OrderGoods')
                ->alias('a')
                ->join('store b', 'a.store_id=b.store_id')
                ->where($where)
                ->where([
                    ['sale_after_status', '=', 0],
                    ['a.check_order_id', '=', 0],       // 获取未对账的订单 注释掉用以调试
                    ['a.status', '=', '4'],
                    ['a.pay_channel', '<>', 0],
                ])->select();
            $check_order_data = $checkOrder->reconciliation_data($order_attach_data, '');
            $for_the = array_sum(array_column($check_order_data, 'should_be_price'));
            
            $data = $checkOrder->alias('a')
                ->join('store b', 'a.store_id=b.store_id')
                ->where($where)
                ->order('a.time', 'desc');
            
            $result = [];
            if (isset($param['dc'])) {
                $data1 = $data->select();
                $simple = ['所属店铺', '订单总数量', '收款金额', '活动款项', '运费', '退款金额', '分销返佣', '平台服务费', '本期应结', '结算状态', '账单日期'];
                foreach ($data1 as $key => $value) {
                    $status = $value['check_status'] == 1 ? '未出账' : '已出账，已转入余额';
                    $result[] = [
                        $value['store_name'],
                        $value['order_number'],
                        $value['sum_pay_price'],
                        $value['sum_activity_price'],
                        $value['sum_freight_price'],
                        $value['sum_refund_price'],
                        $value['sum_brokerage_price'],
                        $value['costs'],
                        $value['should_be_price'],
                        $status,
                        $value['time'],
                    ];
                }
                $checkOrder->putCsv1('账单导出', $result, $simple);
            } else {
                $data = $data->paginate(10, false, ['query' => $param]);
            }
            
            $closing_already = $checkOrder->alias('a')
                ->join('store b', 'a.store_id=b.store_id')
                ->where($where)
                ->order('a.time', 'desc')
                ->sum('should_be_price');
            $store_count = $data->count();
        } catch (\Exception $e) {
            $this->error(config('message.')[-1]);
        }
        
        return $this->fetch('', [
            'data' => $data,
            'for_the' => $for_the,
            'store_count' => $store_count,
            'closing_already' => $closing_already,
        ]);
    }
    
    /**
     * @param Request $request
     * @param CheckOrder $checkOrder
     * @return array|mixed
     */
    public function property_list_examine(Request $request, CheckOrder $checkOrder)
    {
        try {
            $input_data = $request::param();
            $day_time = date('Y-m-d');
            $order_attach_where = [['check_order_id', '=', $input_data['check_order_id']]];
            if (!empty($input_data['order_attach_number'])) $order_attach_where[] = ['order_attach_number', '=', $input_data['order_attach_number']];
            if (!empty($input_data['pay_type'])) $order_attach_where[] = ['pay_type', '=', $input_data['pay_type']];
            if (isset($input_data['dc'])) {
                $check_order_info = $checkOrder->get_check_info($order_attach_where, true)['order_attach_data'];
                $_data = [];
                foreach ($check_order_info as $v) {
                    $_data[] = [$v['order_attach_number'], $v['create_time'], $v['deal_time'], $v['check_time'], $v['pay_price'], $v['activity_price'], $v['subtotal_freight_price'], $v['refund_price'], $v['should_be_price'], $v['is_checking'] == 1 ? '已出账' : '未出账'];
                }
                $checkOrder->create_exoirt('client_property_examine', $_data, $day_time . '-' . date('Y-m-d', strtotime('+1 day' . $day_time)) . '对账单详情');
                return;
            }
            $check_order_info = $checkOrder->get_check_info($order_attach_where);
            $client_storeName = '';
            if (count($check_order_info['order_attach_data']) != 0) {
                $client_storeName = StoreModel::get($check_order_info['order_attach_data'][0]['store_id'])->store_name;
            }
            
        } catch (\Exception $e) {
            halt($e->getMessage());
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        //店铺名
        return $this->fetch('', [
            'data' => $check_order_info['order_attach_data'],
            'should_be_price' => $check_order_info['should_be_price'],
            'order_number' => $check_order_info['order_number'],
            'client_storeName' => $client_storeName,
            'time' => date('Y-m-d', strtotime('+1 day ' . input('time'))),
        ]);
    }
    
    /**
     * 提现列表
     * @param Request $request
     * @param StoreCapitalModel $storeCapital
     * @return array|mixed
     */
    public function withdraw(Request $request, StoreCapitalModel $storeCapital, CheckOrderModel $checkOrder)
    {
        
        try {
            // 获取数据
            $param = $request::get();
            
            $param['start_date'] = !empty($param['start_date']) ? $param['start_date'] : '1968-01-01 00:00:00'; // 开始时间
            $param['end_date'] = !empty($param['end_date']) ? $param['end_date'] : date('Y-m-d H:i:s', time()); // 结束时间
            
            // 筛选条件
            $condition = [];
            $min = [];
            $max = [];
            // 关键词
            if (!empty($param['keyword'])) $condition[] = ['store.store_name', 'like', '%' . $param['keyword'] . '%'];
            // 支付方式 0全部  1自动打款 2银行卡线下打款 3无
            if (isset($param['type']) && $param['type'] != 0) $condition[] = ['store_capital.type', 'eq', $param['type']];
            
            // -1全部 1.1提现申请中 1.4 审核通过提现中 1.2提现成功 1.3提现失败
            if (isset($param['status']) && $param['status'] != -1) {
                $condition[] = ['store_capital.status', 'eq', $param['status']];
            } else {
                $condition[] = ['store_capital.status', 'in', '1.1,1.2,1.3,1.4,1.5'];
            }
            
            //到账类型筛选
            if (!empty($param['back_type'])) {
                $condition[] = ['store_capital.back_type', '=', $param['back_type']];
            }
            
            //时间筛选
            if (!empty($param['date'])) {
                $_date = explode(' - ', $param['date']);
                $_start_time = $_date[0];
                $_end_time = $_date[1];
                $condition[] = ['store_capital.create_time', 'BETWEEN', "{$_start_time},{$_end_time}"];
            }
            // 查询金额
            if (isset($param['min']) && $param['min'] != '') $min [] = ['price', '>=', $param['min']];
            if (isset($param['max']) && $param['max'] != '') $max [] = ['price', '<=', $param['max']];
            
            $data = $storeCapital
                ->alias('store_capital')
                ->join('store store', 'store.store_id = store_capital.store_id', 'left')
                ->where($condition)
                ->where($min)
                ->where($max)
                ->field('store_capital.*,store.store_name')
                ->order(['store_capital.create_time' => 'desc']);
            if (isset($param['dc'])) {
                $data1 = $data->select();
                $simple = ['申请时间', '商家名称', '提现金额', '开户地址', '开户名', '开户行', '开户账户'];
                
                $status = '';
                $result = [];
                foreach ($data1 as $key => $value) {
                    switch ($value['status']) {
                        case '1.1' :
                            $status = '待审核';
                            break;
                        case '1.4' :
                            $status = '审核通过转账中';
                            break;
                        case '1.2' :
                            $status = '提现成功';
                            break;
                        case '1.3' :
                            $status = '提现失败';
                            break;
                        case '1.5' :
                            $status = '审核失败';
                            break;
                    }
                    
                    $result[] = [
                        ' ' . $value['create_time'], $value['store_name'], $value['price'], $value['province'] . $value['city'] . $value['area'],
                        $value['account_name'], $value['account_bank_name'], $value['bank_number'], $status,
                    ];
                }
                
                $checkOrder->putCsv('提现记录导出' . '.csv', $result, $simple);
            } else {
                $data = $data->paginate(10, false, ['query' => $param]);
            }
            
            $price = $storeCapital->alias('store_capital')
                ->join('order_attach order_attach', 'order_attach.order_attach_id = store_capital.order_attach_id', 'left')
                ->join('store store', 'store.store_id = store_capital.store_id', 'left')
                ->where($condition)
                ->where($min)
                ->where($max)
                ->whereTime('store_capital.create_time', 'between', [$param['start_date'], $param['end_date']])
                ->field('IFNULL(sum(case when store_capital.status = 1.1 then price else 0 end),0) as N_checking,
                IFNULL(sum(case when store_capital.status = 1.4 then price else 0 end),0) as Y_checking,
                IFNULL(sum(case when store_capital.status = 1.3 then price else 0 end),0) as field,
                IFNULL(sum(case when store_capital.status = 1.2 then price else 0 end),0) as succeed,store.store_name')
                ->find();
            
        } catch (\Exception $e) {
            halt($e->getMessage());
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        
        return $this->fetch('', [
            'data' => $data,
            'price' => $price,
        ]);
    }
    
    /**
     * 处理审核状态
     * @param Request $request
     * @param StoreCapitalModel $storeCapital
     * @return array|mixed
     */
    public function isChecking(Request $request, StoreCapitalModel $storeCapital, StoreModel $store)
    {
        if ($request::isPost()) {
            
            Db::startTrans();
            
            try {
                // 获取数据
                $param = $request::post();
                
                $storeCapital->valid($param, 'is_check');
                
                $capital = $storeCapital->where(['capital_id' => $param['capital_id']])->field('store_id,price')->find();
                
                // 返回店铺余额
                if ($param['status'] == 1.5) {
                    $store->where([
                        ['store_id', 'eq', $capital['store_id']],
                    ])->setInc('balance', $capital['price']);
                }
                
                // 审核管理信息
                $param['time'] = date('Y-m-d H:i:s');
                $param['nickname'] = Session::get("manageName");
                
                // 写入
                $operation = $storeCapital->isUpdate(true)->allowField(true)->save($param);
                
                // 提交事务
                Db::commit();
                if ($operation) return ['code' => 0, 'message' => '操作成功'];
                
            } catch (ValidateException $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
            
        }
        
        $data = $storeCapital->where('capital_id', $request::get('capital_id'))->find();
        
        return $this->fetch('', [
            'capital_id' => $request::get('capital_id'),
            'item' => $data,
        ]);
    }
    
    /**
     * 处理完成状态
     * @param Request $request
     * @param StoreCapitalModel $storeCapital
     * @return array|mixed
     */
    public function isComplete(Request $request, StoreCapitalModel $storeCapital, StoreModel $store)
    {
        if ($request::isPost()) {
            
            Db::startTrans();
            
            try {
                // 获取数据
                $param = $request::post();
                $storeCapital->valid($param, 'is_complete');
                
                $capital = $storeCapital->where(['capital_id' => $param['capital_id']])->field('store_id,price')->find();
                if ($param['status'] == 1.3) {
                    $store->where([
                        ['store_id', 'eq', $capital['store_id']],
                    ])->setInc('balance', $capital['price']);
                }
                
                // 转账管理信息
                $param['time_1'] = date('Y-m-d H:i:s');
                $param['nickname_1'] = Session::get("manageName");
                
                // 写入
                $operation = $storeCapital->isUpdate(true)->allowField(true)->save($param);
                
                // 提交事务
                Db::commit();
                if ($operation) return ['code' => 0, 'message' => '操作成功'];
                
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
            
        }
        $data = $storeCapital->where('capital_id', $request::get('capital_id'))->find();
        
        return $this->fetch('', [
            'capital_id' => $request::get('capital_id'),
            'item' => $data,
        ]);
    }
    
    /**
     * 审核失败信息
     * @param Request $request
     * @param StoreCapitalModel $storeCapital
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkedFail(Request $request, StoreCapitalModel $storeCapital)
    {
        $data = $storeCapital->where('capital_id', $request::get('capital_id'))->find();
        return $this->fetch('', [
            'item' => $data,
        ]);
    }
    
    /**
     * 提现信息
     * @param Request $request
     * @param StoreCapitalModel $storeCapital
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function isOkay(Request $request, StoreCapitalModel $storeCapital)
    {
        // status1.2成功  1.3失败
        $data = $storeCapital->where('capital_id', $request::get('capital_id'))->find();
        return $this->fetch('', [
            'item' => $data,
            'status' => $request::get('status'),
        ]);
    }
}