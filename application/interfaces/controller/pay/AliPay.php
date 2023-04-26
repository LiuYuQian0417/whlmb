<?php
declare(strict_types=1);
/**
 * 关于支付宝回调.
 * User: Heng
 * Date: 2019/1/25
 * Time: 15:35
 */

namespace app\interfaces\controller\pay;

use app\common\model\Consumption;
use app\common\model\IntegralOrder;
use app\common\model\IntegralRecord;
use app\common\model\Member;
use app\common\service\Inform;
use app\common\service\Lock;
use app\interfaces\controller\auth\Integral;
use app\interfaces\controller\auth\Recharge;
use think\Db;
use think\facade\Env;
use think\facade\Request;
use app\common\service\OrderAct;

/**
 * 支付宝
 * Class AliPay
 * @package app\interfaces\controller\pay
 */
class AliPay
{
    /**
     * 支付宝支付回调
     * @param Request $request
     * @return bool
     * @throws \Exception
     */
    public function notifyurl(Request $request)
    {
        header("Content-type: text/html; charset=utf-8");
        require Env::get('vendor_path') . 'alipay/pagepay/service/AlipayTradeService.php';
        require Env::get('vendor_path') . 'alipay/pagepay/buildermodel/AlipayTradeRefundContentBuilder.php';
        require Env::get('vendor_path') . 'alipay/config.php';
        global $ali_config;
        $data = $request::param();
        $alipaySevice = new \AlipayTradeService($ali_config);
        $alipaySevice->writeLog("======================支付宝回调开始======================");
        $alipaySevice->writeLog(var_export($data, true));
        $result = $alipaySevice->check($data);
        if ($result) {
            //交易状态
            $trade_status = $data['trade_status'];
            $alipaySevice->writeLog($trade_status);
            //支付宝交易号
            if ($trade_status == 'TRADE_FINISHED') {
                $alipaySevice->writeLog("验证失败");
            } else if ($trade_status == 'TRADE_SUCCESS') {
                $f = true;
                try {
                    $bodyArr = explode('|', $data['body']);
                    $alipaySevice->writeLog($data['out_trade_no']);
                    switch ($bodyArr[0]) {
                        case 'pay' :
                            $orderActService = (new OrderAct());
                            // 支付方式:2支付宝
                            $data['pay_channel'] = 2;
                            $data['case_pay_type'] = $bodyArr[1];   // 来源端
                            $data['total_fee'] = $data['total_amount'];
                            Db::startTrans();
                            $ret = $orderActService->execPayOrder($data);
                            Db::commit();
                            $f = $ret['code'] == 0 ? true : $ret['message'];
                            if ($f === true) {
                                $alipaySevice->writeLog('======================支付成功======================');
                            }
                            break;
                        case 'recharge':
                            $f = (new Recharge())->rechargeBody([
                                'member_id' => $bodyArr[2],
                                'total_fee' => $data['total_amount'],
                                'recharge_id' => $bodyArr[3],
                                'out_trade_no' => $data['out_trade_no'],
                                'way' => 1,
                                'cash_fee' => $data['buyer_pay_amount'],    // 消息模板[当前版本仅微信支付]
                                'trade_no' => $data['trade_no'],
                            ]);
                            if ($f === true) {
                                $alipaySevice->writeLog('======================充值成功======================');
                            }
                            break;
                        case 'exchange':
                            $integral = new \app\common\model\Integral();
                            $member = new Member();
                            $integralRecord = new IntegralRecord();
                            $consumption = new Consumption();
                            $integralOrder = new IntegralOrder();
                            $inform = new Inform();
                            $lock = new Lock();
                            $args['trade_no'] = $data['trade_no'];
                            $args['order_number'] = $data['out_trade_no'];
                            $args['pay_channel'] = 2;               // 支付方式 2支付宝
                            $args['total_fee'] = $data['total_amount'];
                            $args['member_id'] = $bodyArr[2];
                            $f = (new Integral())->redemption_money_common($integral, $member, $integralRecord,
                                $consumption, $integralOrder, $inform, $lock, $args);
                            if ($f === true) {
                                $alipaySevice->writeLog('======================兑换成功======================');
                            }
                            break;
                        default:
                            $f = 'body参数传入错误---' . $data['body'];
                            break;
                    }
                } catch (\Exception $e) {
                    Db::rollback();
                    $f = $e->getMessage();
                } finally {
                    if ($f !== true) {
                        $alipaySevice->writeLog('错误：' . $f);
                        // 原路退还
                        $RequestBuilder = new \AlipayTradeRefundContentBuilder();
                        $RequestBuilder->setOutTradeNo($data['out_trade_no']);
                        $RequestBuilder->setTradeNo($data['trade_no']);
                        $RequestBuilder->setRefundAmount($data['total_amount']);
                        $RequestBuilder->setOutRequestNo($data['out_trade_no']);
                        $RequestBuilder->setRefundReason('商品购买失败');
                        $alipaySevice->Refund($RequestBuilder);
                    }
                    return "success";
                }
            }
        }
    }
}