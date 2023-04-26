<?php
declare(strict_types = 1);

namespace app\interfaces\controller\pay;

use app\common\model\Consumption;
use app\common\model\IntegralOrder;
use app\common\model\IntegralRecord;
use app\common\model\Member;
use app\common\service\Inform;
use app\common\service\Lock;
use app\common\service\OrderAct;
use app\interfaces\controller\auth\Integral;
use app\interfaces\controller\auth\Recharge;
use app\interfaces\controller\BaseController;
use EasyWeChat\Factory;
use think\Db;
use think\facade\Env;
use think\facade\Request;

/**
 * 微信回调
 * Class wxPay
 * @package app\interfaces\controller\pay
 */
class WxPay extends BaseController
{
    private $msg = '';
    
    /**
     * 支付回调
     * @param Request $request
     * @throws \EasyWeChat\Kernel\Exceptions\Exception
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function paidNotify(Request $request)
    {
        if ($request::isPost()) {
            $argsXml = file_get_contents('php://input');
            if ($argsXml) {
                //禁止引用外部xml实体
                libxml_disable_entity_loader(true);
                $this->msg = date('Y-m-d H:i:s') . " ======================微信回调开始======================" . PHP_EOL;
                $argsArr = json_decode(json_encode(simplexml_load_string($argsXml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
                $this->msg .= var_export($argsArr, true) . PHP_EOL;
                
                $text = ['app', 'applet', 'pc_login', 'mobile'];
                $attachArr = explode('|', $argsArr['attach']);
                $app = Factory::payment(config('wechat.')[$text[$attachArr[1] - 1]]);
                // 查询微信订单合法性
                $queryRet = $app->order->queryByOutTradeNumber($argsArr['out_trade_no']);
                $this->msg .= var_export($queryRet, true) . PHP_EOL;
                if ($queryRet['return_code'] === 'SUCCESS' &&
                    $queryRet['result_code'] === 'SUCCESS' &&
                    $queryRet['trade_state'] === 'SUCCESS'
                ) {
                    $res = $app->handlePaidNotify(function ($msg, $fail) use ($attachArr, $app) {
                        $f = true;
                        try {
                            switch ($attachArr[0]) {
                                case 'pay':
                                    $orderActService = (new OrderAct());
                                    $msg['pay_channel'] = 1;                        // 支付方式1微信
                                    $msg['case_pay_type'] = $attachArr[1];
                                    $msg['trade_no'] = $msg['transaction_id'];      // 微信流水号
                                    $msg['total_fee'] /= 100;
                                    Db::startTrans();
                                    $ret = $orderActService->execPayOrder($msg);
                                    $this->msg .= var_export($ret, true) . PHP_EOL;
                                    Db::commit();
                                    $f = $ret['code'] == 0 ? true : $ret['message'];
                                    if ($f === true) {
                                        $this->msg .= '======================支付成功======================' . PHP_EOL;
                                    }
                                    break;
                                case 'recharge':
                                    $f = (new Recharge())->rechargeBody([
                                        'member_id' => $attachArr[2],
                                        'total_fee' => $msg['total_fee'] / 100,
                                        'recharge_id' => $attachArr[3],
                                        'out_trade_no' => $msg['out_trade_no'],
                                        'way' => 2,                             //资金方式：1支付宝2微信3银行卡4余额5线下
                                        'cash_fee' => $msg['cash_fee'] / 100,   // 消息模板[当前版本仅微信支付]
                                        'trade_no' => $msg['transaction_id'],
                                    ]);
                                    if ($f === true) {
                                        $this->msg .= '======================充值成功======================' . PHP_EOL;
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
                                    $args['trade_no'] = $msg['transaction_id'];
                                    $args['order_number'] = $msg['out_trade_no'];
                                    $args['pay_channel'] = 1;               // 支付方式 1微信
                                    $args['total_fee'] = $msg['total_fee'] / 100;
                                    $args['member_id'] = $attachArr[2];
                                    $f = (new Integral())->redemption_money_common($integral, $member, $integralRecord,
                                        $consumption, $integralOrder, $inform, $lock, $args);
                                    if ($f === true) {
                                        $this->msg .= '======================兑换成功======================' . PHP_EOL;
                                    }
                                    break;
                                default:
                                    $f = 'attach参数传入错误---' . $msg['attach'] . PHP_EOL;
                                    break;
                            }
                        } catch (\Exception $e) {
                            Db::rollback();
                            $f = '错误：' . $e->getMessage() . PHP_EOL;
                        } finally {
                            if ($f !== true) {
                                $this->msg .= '错误：' . $f . PHP_EOL;
                                // 原路退回[根据微信订单号]
//                                $refundResult = $app->refund->byTransactionId($msg['transaction_id'], get_order_sn(),
//                                    (int)$msg['cash_fee'], (int)$msg['cash_fee'], ['refund_desc' => $f]);
                                $this->msg .= date('Y-m-d') . var_export($refundResult, true) . PHP_EOL;
                            }
                            return true;
                        }
                    });
                    $res->send();
                }
            }
        }
    }
    
    public function __destruct()
    {
        $path = Env::get('root_path') . 'public/wx_pay_log/' . date('Y-m') . DIRECTORY_SEPARATOR;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $path .= date('d') . '.log';
        file_put_contents($path, $this->msg, FILE_APPEND);
    }
}