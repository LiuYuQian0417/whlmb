<?php
/**
 * pc支付宝支付生成支付信息
 * Created by PhpStorm.
 * User: Faith
 * Date: 2019/4/16
 * Time: 10:33
 */

namespace app\computer\controller\pay;


use app\computer\model\IntegralOrder;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use app\computer\model\OrderAttach;
use think\facade\Request;

class AliPay
{
    public function pay(OrderAttach $orderAttach, Request $request, IntegralOrder $integralOrder)
    {
        header("Content-type: text/html; charset=utf-8");
        require Env::get('vendor_path') . 'alipay/config.php';
        require Env::get('vendor_path') . 'alipay/pagepay/service/AlipayTradeService.php';
        require Env::get('vendor_path') . 'alipay/pagepay/buildermodel/AlipayTradePagePayContentBuilder.php';
        $param = (new RSACrypt())->singleDec(
            urlsafe_b64decode($request::get('order_data'))
        );
        $attachArr = explode('|', $param['attach']);
        switch ($attachArr[0])
        {
            //支付
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
            //积分换购
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
                exception('支付信息错误');
                break;
        }
        if (empty($total_fee))
        {
            exception('支付金额有误');
        }
        // 是否1分钱支付
        $one_pay = config('user.one_pay');
        //构造参数
        $payRequestBuilder = new \AlipayTradePagePayContentBuilder();
        $payRequestBuilder->setBody($param['attach']);
        $payRequestBuilder->setSubject($param['body'] ?? '订单支付');      //订单标题
        $payRequestBuilder->setOutTradeNo($param['order_number']);    //订单号
        $payRequestBuilder->setTotalAmount($one_pay ? '0.01' : $total_fee);   //订单金额
        $aop = new \AlipayTradeService($config);
        /**
         * pagePay 电脑网站支付请求
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @param $return_url 同步跳转地址，公网可以访问
         * @param $notify_url 异步通知地址，公网可以访问
         * @return $response 支付宝返回的信息
         */
        $return_url = ['pay' => 'order/order_list', 'recharge' => '', 'exchange' => ''];
        $config['return_url'] = request()->domain() . '/pc2.0/' . $return_url[$attachArr[0]];
        $config['notify_url'] = request()->domain() . '/v2.0/ali/notifyurl';
        //$aop->pagePay   里面的方法第二个参数改成false $this->aopclientRequestExecute ($request,true);
        $response = $aop->pagePay($payRequestBuilder, $config['return_url'], $config['notify_url'], TRUE);
        return \think\facade\Response::create($response, 'html');
    }
}