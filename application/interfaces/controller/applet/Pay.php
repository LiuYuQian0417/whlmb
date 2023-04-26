<?php
declare(strict_types = 1);

namespace app\interfaces\controller\applet;

use app\common\model\IntegralOrder;
use app\common\model\Member;
use app\common\model\OrderAttach;
use EasyWeChat\Factory;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Request;

/**
 * 小程序 - 支付 - Joy
 * Class Pay
 * @package app\interfaces\controller\applet
 */
class Pay extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    /**
     * 预支付下单(小程序,手机站)
     * @param RSACrypt $crypt
     * @param Member $member
     * @param OrderAttach $orderAttach
     * @param IntegralOrder $integralOrder
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function payment(RSACrypt $crypt,
                            Member $member,
                            OrderAttach $orderAttach,
                            IntegralOrder $integralOrder)
    {
        $param = $crypt->request();
        $member->valid($param, 'applet_payment');
        $attachArr = explode('|', $param['attach']);
        // 读取配置[根据参数选择配置端2小程序4手机站]
        $app = Factory::payment(config('wechat.')[[2 => 'applet', 4 => 'mobile'][$attachArr[1]]]);
        switch ($attachArr[0]) {
            case 'pay':
                $total_fee = $orderAttach
                    ->where([
                        ['order_number|order_attach_number', '=', $param['out_trade_no']],
                        ['status', '=', 0],     //未支付
                    ])
                    ->sum('subtotal_price');
                break;
            case 'recharge';
                $total_fee = $param['total_fee'];
                break;
            case 'exchange':
                $total_fee = $integralOrder
                    ->where([
                        ['order_number', '=', $param['out_trade_no']],
                        ['status', '=', 3],         //未支付
                    ])
                    ->value('price');
                break;
            default:
                return $crypt->response([
                    'code' => -1,
                    'message' => '参数错误',
                ], true);
                break;
        }
        if (!$total_fee) {
            return $crypt->response([
                'code' => -1,
                'message' => '金额异常',
            ], true);
        }
        // 是否1分钱支付
        $one_pay = config('user.one_pay');
        // 获取prepay_id
        $result = $app->order->unify([
            'body' => $param['body'],
            'attach' => $param['attach'],
            'out_trade_no' => $param['out_trade_no'],
            'total_fee' => $one_pay ? '1' : $total_fee * 100,
            'spbill_create_ip' => '',
            'notify_url' => Request::domain() . '/v2.0/wx/paidNotify',
            'trade_type' => 'JSAPI',
            'openid' => $param['open_id'],
        ]);
        if ($result['return_code'] === 'FAIL') {
            return $crypt->response([
                'code' => -1,
                'message' => $result['return_msg'],
            ], true);
        }
        if ($attachArr[0] == 'recharge' && $attachArr[1] == 2) {
            Cache::tag('micro-recharge')->set('micro-recharge-' . $attachArr[2], $result['prepay_id'], 3600);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '支付预下单成功',
            'result' => $app->jssdk->sdkConfig($result['prepay_id']),
        ], true);
    }
    
    /**
     * 小程序充值
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function recharge(RSACrypt $crypt,
                             Member $member)
    {
        $param = $crypt->request();
        $member->valid($param, 'applet_recharge');
        $app = Factory::payment(config('wechat.')['applet']);
        // 获取prepay_id
        $result = $app->order->unify([
            'body' => $param['body'],
            'attach' => $param['attach'],
            'out_trade_no' => $param['out_trade_no'],
            'total_fee' => $param['total_fee'] * 100,
            'spbill_create_ip' => '',
            'notify_url' => Request::domain() . '/v2.0/recharge/notify',
            'trade_type' => 'JSAPI',
            'openid' => $param['open_id'],
        ]);
        Cache::tag('micro-recharge')->set('micro-recharge-' . $param['out_trade_no'], $result['prepay_id'], 3600);
        if (!$preData = $app->jssdk->sdkConfig($result['prepay_id'])) {
            return $crypt->response([
                'code' => -1,
                'message' => '获取预下单数据失败',
            ], true);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '充值预下单成功',
            'result' => $preData,
        ], true);
    }
}