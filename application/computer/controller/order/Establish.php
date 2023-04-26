<?php
declare(strict_types = 1);

namespace app\computer\controller\order;

use app\computer\model\CutActivity;
use app\computer\model\Member;
use app\computer\model\Order;
use app\computer\model\OrderGoods;
use app\common\service\Beanstalk;
use app\common\service\Distribution;
use app\computer\controller\BaseController;
use app\computer\model\Consumption;
use app\computer\model\Goods;
use app\computer\model\IntegralOrder;
use app\computer\model\OrderAttach;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;

/**
 * 建立订单
 * Class Establish
 * @package app\interfaces\controller\order
 */
class Establish extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login',
        //检查订单状态是否满足可支付
        'check_pay' => ['only' => 'balance_pay,we_chat_pay,pay_type'],
    ];


    /**
     * 确认订单
     * @param RSACrypt $crypt
     * @param Order $order
     * @param CutActivity $cutActivity
     * @param Member $member
     * @return mixed
     */
    public function confirm(RSACrypt $crypt,
                            Order $order,
                            CutActivity $cutActivity,
                            Member $member)
    {
        try {
            Db::startTrans();
            $param = $crypt->request();
            $member_id = Session::get('member_info')['member_id'];
            //解密
            $order_data = $crypt->singleDec($param['info_data']);
            //数据合并
            $order_data['member_address_id'] = $param['member_address_id'];
            $order_data['member_id'] = $member_id;
            $order_data['member_packet_id'] = $param['member_packet_id'] ?? '';
            $order_data['member_platform_coupon_id'] = trim(
                    $param['member_platform_coupon_id'],
                    ','
                ) ?? '';//去掉使用红包id两头字符串
            $store_set = $param['store_set'];
            foreach ($order_data['store_set'] as $key => &$v) {

                $v = array_merge($store_set[$key], $v);
                $v['pay_type'] = in_array(
                    $store_set[$key]['pay_type'],
                    $v['pay_type_set']
                ) ? $store_set[$key]['pay_type'] : 1;
                //如果选择不开发票则删除对应发票信息
                if (empty($v['invoice_set']) or $v['invoice_set']['detail_type'] == 'no') {
                    $v['invoice_set'] = '';
                }
                if (!empty($v['invoice_set']) && $v['is_added_value_tax'] != 1 && $v['invoice_set']['invoice_type'] == 1) {
                    return ['code' => -100, 'message' => '店铺不支持开具增值税发票', 'store_index' => $key];
                }
                unset($v['pay_type_set']);
                unset($v['is_added_value_tax']);
            }
            // 判断活动是否开启
            $function_status_map = [
                //拼团关闭
                ['status' => self::$functionStatus['is_group'] == 0 && $order_data['order_type'] == 2, 'message' => '拼团活动已关闭，暂时不能下单'],
                //砍价关闭
                ['status' => self::$functionStatus['is_cut'] == 0 && $order_data['order_type'] == 3, 'message' => '砍价活动已关闭，暂时不能下单'],
                // 限时抢购关闭
                ['status' => self::$functionStatus['is_limit'] == 0 && $order_data['order_type'] == 4, 'message' => '限时抢购活动已关闭，暂时不能下单'],
            ];
            foreach ($function_status_map as $status_map_v) {
                if ($status_map_v['status']) {
                    exception($status_map_v['message']);
                }
            }
            //数据验证
            $order->valid($order_data, 'confirm');

            $orderActService = app('app\\common\\service\\OrderAct');
            // 获取订单数据
            $data = $orderActService->outData($order_data);
            if ($data['code']) {
                return $crypt->response($data, true);
            }
            // 保存订单数据
            $order->saveOrder($data);
            if (!array_key_exists('order_number', $data['data'])) {
                exception(self::$errMsg);
            }
            // 砍价活动绑定店铺订单id并终止砍价活动
            if ($order_data['order_type'] == 3) {
                $cutActivityUpdate = [];
                foreach ($data['attachData'] as $key => $value) {
                    if (array_key_exists('cut_activity_id', $value) && $value['cut_activity_id']) {
                        array_push(
                            $cutActivityUpdate,
                            [
                                'cut_activity_id' => $value['cut_activity_id'],
                                'status' => 2,
                                'order_attach_id' => $value['order_attach_id'],
                            ]
                        );
                    }
                }
                if ($cutActivityUpdate) {
                    $cutActivity
                        ->allowField(true)
                        ->isUpdate(true)
                        ->saveAll($cutActivityUpdate);
                }
            }

            // 插入消息队列
            (new Beanstalk())->put(
                json_encode(
                    [
                        'queue' => 'orderExpire',
                        'id' => $data['data']['order_id'],
                        'time' => date('Y-m-d H:i:s'),
                    ]
                ),
                15 * 60
            );
            // 检测用户是否设置过支付密码
            $memberInfo = $member
                ->where(
                    [
                        ['member_id', '=', $order_data['member_id']],
                    ]
                )
                ->with(['distributionRecord'])
                ->field('member_id,nickname,phone,sex,distribution_superior,cumulative_order_sum,pay_password')
                ->find();
            if (!empty($data['distribution']['goods']) || !empty($data['distribution']['distributor_goods'])) {
                $distributionGoodsArr = (new Distribution())->opera($data['distribution'], $memberInfo);
                if (!empty($distributionGoodsArr['distributionGoodsArr'])) {
                    $order_goods_update = [];
                    // 当前会员为稳定型分销商并分销了此次支付商品
                    foreach ($distributionGoodsArr['distributionGoodsArr'] as $_dga) {
                        $order_goods_update[$_dga] = [
                            'order_goods_id' => $_dga,
                            'is_distribution' => 1,
                        ];
                    }
                    if (!empty($order_goods_update)) {
                        (new OrderGoods())->allowField(true)->isUpdate(true)->saveAll($order_goods_update);
                    }
                }
            }
            $total_price = number_format($data['data']['total_price'] - $data['data']['total_cod_price'], 2, '.', '');
            Db::commit();
            return
                [
                    'code' => 0,
                    'message' => config('message.')[0][0],
                    'result' => [
                        'total_price' => $total_price > 0 ? $total_price : "0",
                        'order_data' => urlsafe_b64encode(
                            $crypt->singleEnc(
                                [
                                    'order_number' => $data['data']['order_number'],
                                    'order_id' => $data['data']['order_id'],
                                    'total_price' => $total_price > 0 ? $total_price : "0",
                                    'attach' => "pay|3",
                                ]
                            )
                        ),//订单信息加密并编码成url可用
                        'has_pay_password' => $memberInfo['pay_password'] ? 1 : 0,
                        'group_activity_attach_id' => $data['group_activity_attach_id'],
                    ],
                ];
        } catch (\Exception $e) {
            Db::rollback();
            return $crypt->response(
                ['code' => -100, 'message' => (self::$errMsg ?: $e->getMessage())],
                true
            );
        }
    }


    public function check_order_valid(Request $request, RSACrypt $RSACrypt)
    {
        $request_data = $request::param('order_data');
        //转码并解密加密数据
        $order_data = $RSACrypt->singleDec(urlsafe_b64decode($request_data));
        $attach = explode('|', $order_data['attach']);
        //$title = ['pay' => '订单支付', 'recharge' => '充值订单', 'exchange' => '积分订单'];
        switch ($attach) {
            case 'pay':
                $order_status = OrderAttach::where([['order_number|order_attach_number', '=', Request::post('order_number', '')]])->value('status', null);
                return json($order_status === 0 ? ['code' => 0] : ['code' => -100, 'message' => '订单状态异常']);
                break;
        }
        return json(['code' => 0]);

    }


    /**
     * 选择支付方式
     * @param Request $request
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function pay_type(Request $request, RSACrypt $crypt)
    {
        $request_data = $request::get('order_data');
        //转码并解密加密数据
        $data = $crypt->singleDec(urlsafe_b64decode($request_data));
        $type = 0;
        switch (explode('|', $data['attach'])) {
            //支付
            case 'pay':
                $type = 1;
                break;
            //积分换购
            case 'exchange':
                $type = 2;
                break;
            //充值
            case 'recharge ':
                break;
        }
        return $this->fetch('', ['data' => $data, 'type' => $type]);
    }


    /**
     * 微信支付
     * @param Request $request
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function we_chat_pay(Request $request, RSACrypt $crypt)
    {
        $request_data = $request::get('order_data');
        //转码并解密加密数据
        $data = $crypt->singleDec(urlsafe_b64decode($request_data));
        $attach = explode('|', $data['attach']);
        $title = ['pay' => '订单支付', 'recharge' => '充值订单', 'exchange' => '积分订单'];
        $anew_pay = [
            'pay' => '/pc2.0/order/pay_type?order_data=' . $request_data,
            'recharge' => '/pc2.0/recharge/index?recharge_id=' . ($attach[3] ?? 0),
            'exchange' => '/pc2.0/order/pay_type?order_data=' . $request_data,
        ];
        return $this->fetch(
            '',
            [
                'order_number' => $data['order_number'],
                'total_price' => $data['total_price'],
                'pay_type' => $attach[0],//订单类型  pay 支付订单  recharge  充值 exchange 积分换购
                'pay_title' => $title[$attach[0]],
                'anew_pay' => $anew_pay[$attach[0]],
            ]
        );
    }

    /**
     * 余额支付
     * @param Request $request
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function balance_pay(Request $request, RSACrypt $crypt)
    {
        //转码并解密加密数据
        $data = $crypt->singleDec(urlsafe_b64decode($request::get('order_data')));

        $pay_password = Member::where([['member_id', '=', Session::get('member_info')['member_id']]])->value('pay_password');
        $status = !empty($pay_password) ? 1 : 2;
        $type = 0;
        switch (explode('|', $data['attach'])) {
            //支付
            case 'pay':
                $type = 1;
                break;
            //积分换购
            case 'exchange':
                $type = 2;
                break;
            //充值
            case 'recharge ':
                break;
        }
        return $this->fetch(
            '',
            [
                'order_number' => $data['order_number'],
                'total_price' => $data['total_price'],
                'status' => $status,
                'type' => $type,
                'pay_type' => explode('|', $data['attach'])[0],//订单类型  pay 支付订单  recharge  充值 exchange 积分换购
            ]
        );
    }


    /**
     * 支付成功页面
     * @param Goods $goods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pay_success(Goods $goods)
    {
        $member_id = Session::get('member_info')['member_id'];
        //猜你喜欢
        $recommend_list = recommend_list($goods, 10, $member_id, 1);
        return $this->fetch(
            '',
            [
                'recommend_list' => $recommend_list,
            ]
        );
    }

    /**
     * 微信检查订单状态
     * @param Request $request
     * @param OrderAttach $orderAttach
     * @param Consumption $consumption
     * @param IntegralOrder $integralOrder
     * @return array
     */
    public function check_order_status(Request $request, OrderAttach $orderAttach, Consumption $consumption, IntegralOrder $integralOrder)
    {
        $data = $request::post();
        $status = -100;
        $url = '';
        switch ($data['pay_type']) {
            //订单支付
            case 'pay':
                $pay_count = $orderAttach->where(
                    [['order_number|order_attach_number', '=', $data['order_number']], ['status', 'in', [1, 2, 3]]]
                )->count();
                if ($pay_count > 0) {
                    $status = 0;
                    $url = '/pc2.0/order/order_list';
                }
                break;
            //充值
            case 'recharge':
                $pay_status = $consumption->where(
                    [['order_number|order_attach_number', '=', $data['order_number']]]
                )->value('status');
                if ($pay_status == 1) {
                    $status = 0;
                    $url = '/pc2.0/recharge/balance_record';
                }
                break;
            //积分换购
            case 'exchange':
                $pay_status = $integralOrder
                    ->where(
                        [
                            ['order_number', '=', $data['order_number']],
                        ]
                    )->value('status');
                if ($pay_status != 3) {
                    $status = 0;
                    $url = '/pc2.0/integral/conversion_record';
                }
                break;
        }
        return ['code' => 0, 'status' => $status, 'url' => $url ?? ''];
    }

    //判断订单是否支付完成
    protected function check_pay()
    {
        $data = (new RSACrypt)->singleDec(urlsafe_b64decode(Request::get('order_data')));
        //如果订单数据错误
        if (empty($data)) {
            $this->redirect('/pc2.0/my/index');
        }
        $return_url = '';
        switch (explode('|', $data['attach'])[0]) {
            //订单支付
            case 'pay':
                $pay_count = OrderAttach::where(
                    [['order_number|order_attach_number', '=', $data['order_number']], ['status', 'in', [1, 2, 3]]]
                )->count();
                if ($pay_count > 0) {
                    $return_url = '/pc2.0/order/order_list';
                }
                break;
            //充值
            case 'recharge':
                $pay_status = Consumption::where(
                    [['order_number|order_attach_number', '=', $data['order_number']]]
                )->value('status');
                if ($pay_status == 1) {
                    $return_url = '/pc2.0/recharge/balance_record';
                }
                break;
            //积分换购
            case 'exchange':
                $pay_status = IntegralOrder::where(
                    [
                        ['order_number', '=', $data['order_number']],
                    ]
                )->value('status');
                if ($pay_status != 3) {
                    $return_url = '/pc2.0/integral/conversion_record';
                }
                break;
        }
        if (!empty($return_url)) {
            $this->redirect($return_url);
        }
    }
}