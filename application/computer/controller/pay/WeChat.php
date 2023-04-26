<?php
declare(strict_types=1);

namespace app\computer\controller\pay;

use app\computer\model\IntegralOrder;
use app\computer\model\Member;
use app\computer\model\OrderAttach;
use common\lib\phpcode\QrCode;
use EasyWeChat\Factory;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\facade\Request;

/**
 * pc 支付 统一下单 生成二维码
 * Class WeChat
 * @package app\computer\controller\pay
 */
class WeChat extends \app\computer\controller\BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    /**
     * 生成扫码支付二维码
     * @param RSACrypt $crypt
     * @param Request $request
     * @param OrderAttach $orderAttach
     * @param IntegralOrder $integralOrder
     * @return mixed
     */
    public function payment(RSACrypt $crypt,
                            Request $request,
                            OrderAttach $orderAttach,
                            IntegralOrder $integralOrder)
    {
        try
        {
            $param =  $crypt->singleDec(
                urlsafe_b64decode($request::get('order_data'))
            );
            $attachArr = explode('|', $param['attach']);

//            dump(config('wechat.'));

            // 读取配置
            $app = Factory::payment(config('wechat.')['mobile']);
//            halt($app);
            switch ($attachArr[0])
            {
                //订单支付
                case 'pay':
                    $total_fee = $orderAttach
                        ->where(
                            [
                                ['order_number|order_attach_number', '=', $param['order_number']],
                                ['status', '=', 0],     //未支付
                            ]
                        )
                        ->sum('subtotal_price');
                    break;
                //充值
                case 'recharge';
                    $total_fee = $param['total_price'];
                    break;
                //积分兑换
                case 'exchange':
                    $total_fee = $integralOrder
                        ->where(
                            [
                                ['order_number', '=', $param['order_number']],
                                ['status', '=', 3],         //未支付
                            ]
                        )
                        ->value('price');
                    break;
                default:
                    return $crypt->response(
                        [
                            'code'    => -1,
                            'message' => 'attach参数错误--' . var_export($param, TRUE),
                        ],
                        TRUE
                    );
                    break;
            }
            // 是否1分钱支付
            $one_pay = config('user.one_pay', NULL);
            // 获取prepay_id
            $result = $app->order->unify(
                [
                    'body'             => $param['body'] ?? '订单支付',
                    'attach'           => $param['attach'],
                    'out_trade_no'     => $param['order_number'],
                    'total_fee'        => $one_pay ? '1' : $total_fee*100,
                    'spbill_create_ip' => '', // 可选，如不传该参数，SDK 将会自动获取相应 IP 地址
                    'notify_url'       => Request::domain() . '/v2.0/wx/paidNotify', // 支付结果通知网址，如果不设置则会使用配置里的默认地址
                    'trade_type'       => 'NATIVE', // 请对应换成你的支付方式对应的值类型
                    'product_id'       => $param['goods_id'] ?? ''//商品id   多个商品逗号分割
                ]
            );
//            dump($result);
            if ($result['return_code'] === 'FAIL')
            {
                return $crypt->response(
                    [
                        'code'    => -1,
                        'message' => $result['return_msg'],
                    ],
                    TRUE
                );
            } else
            {
                // 文件不存在,重新生成二维码
                require_once(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                QrCode::getQrCode(
                    $param['body'] ?? '订单支付',
                    $result['code_url'],
                    FALSE,
                    6
                );
            }
        } catch (\Exception $e)
        {
            return $e->getMessage();
        }
    }
}