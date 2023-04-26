<?php
declare(strict_types=1);

namespace app\computer\controller\pay;

use app\computer\model\Order;
use app\common\service\Inform;
use app\common\service\Lock;
use app\computer\controller\BaseController;
use app\computer\model\Consumption;
use app\computer\model\IntegralOrder;
use app\computer\model\IntegralRecord;
use app\computer\model\Member;
use app\computer\model\Integral as IntegralModel;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Session;

/**
 * 余额支付
 * Class Balance
 * @package app\computer\controller\pay
 */
class Balance extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login',
    ];

    /**
     * 执行支付
     * @param RSACrypt $crypt
     * @param Order $order
     * @return mixed
     */
    public function exec(RSACrypt $crypt, Order $order)
    {
        try
        {
            Db::startTrans();
            $args = $crypt->request();
            $args['member_id'] = Session::get('member_info')['member_id'];
            $validRet = $order->valid($args, 'balanceExec');
            if ($validRet['code'])
            {
                return $crypt->response($validRet, TRUE);
            }
            $args['pay_channel'] = 3;   // 余额支付
            $args['case_pay_type'] = 3;   // pc支付
            switch ($args['pay_type'])
            {
                //订单支付
                case 'pay':
                    $orderActService = app('app\\common\\service\\OrderAct');
                    $args['trade_no'] = NULL;
                    $ret = $orderActService->execPayOrder($args);
                    break;
                //积分订单支付
                case 'exchange':
                    $result = $this->redemption_money_common(
                        [
                            'member_id'    => Session::get('member_info')['member_id'],
                            'order_number' => $args['out_trade_no'],
                            'pay_pass'     => $args['pay_password'],
                            'pay_channel'  => 3,
                        ]
                    );
                    if ($result !== TRUE)
                    {
                        exception($result);
                    } else
                    {
                        $ret = ['code' => 0, 'groupActivityAttachId' => 0];
                    };
                    break;
                default:
                    exception('订单错误');
            }
            if ($ret['code'])
            {
                return $crypt->response($ret, TRUE);
            }
            Db::commit();
            return $crypt->response(
                [
                    'code'                     => 0,
                    'message'                  => config('message.')[0][0],
                    'group_activity_attach_id' => $ret['groupActivityAttachId'] ?? 0,
                ],
                TRUE
            );
        } catch (\Exception $e)
        {
            Db::rollback();
            return $crypt->response(
                ['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()],
                TRUE
            );
        }
    }

    //积分商品组合支付方法
    public function redemption_money_common($args)
    {
        $member = (new Member);
        $integralRecord = (new IntegralRecord);
        $consumption = (new Consumption);
        $integralOrder = (new IntegralOrder);
        $inform = (new Inform);
        $lock = (new Lock());
        // 查询订单信息
        $order_info = $integralOrder
            ->alias('io')
            ->join('integral i', 'i.integral_id = io.integral_id')
            ->where(
                [
                    ['io.order_number', '=', $args['order_number']],
                    ['io.status', '=', 3],            //未支付
                ]
            )
            ->field(
                'io.integral_order_id,io.member_id,io.integral,io.price,io.number,io.status,
                io.integral_name,io.member_id,io.order_number,io.number,io.from,io.integral_id,
                i.integral_number'
            )
            ->find();
        if (is_null($order_info))
        {
            return '积分商品或订单不存在';
        }
        // 检查用户积分
        $member_info = $member
            ->where('member_id', $args['member_id'])
            ->field('pay_points,usable_money,pay_password')
            ->find();
        // 支付方式为余额
        if ($args['pay_channel'] == 3)
        {
            // 设置支付密码
            if (!$member_info['pay_password'])
            {
                return '未设置支付密码';
            }
            // 匹配密码
            if (passEnc($args['pay_pass']) <> $member_info['pay_password'])
            {
                return '支付密码不正确';
            }
            if ($order_info['price'] && $member_info['usable_money'] < $order_info['price'])
            {
                return '余额不足';
            }
        }
        // 判断用户积分或余额是否够用
        if ($order_info['integral'] > 0 && $member_info['pay_points'] < ($order_info['integral'] * $order_info['number']))
        {
            return '积分不足';
        }
        $getLock = $lock->lock(['integral_' . $order_info['integral_id']], 10000);
        if ($getLock === FALSE)
        {
            return '网络繁忙,请重试';
        }
        // 会员减少积分
        $decMem = [
            'pay_points' => Db::raw('pay_points-' . $order_info['integral']),
        ];
        // 支付方式为余额
        if ($args['pay_channel'] == 3)
        {
            // 会员同时减去余额
            $decMem['usable_money'] = Db::raw('usable_money - ' . $order_info['price']);
        } else
        {
            if ($args['total_fee'] != $order_info['price'])
            {
                return '支付金额不符合';
            }
        }
        // 判断库存成功执行
        if ($order_info['integral_number'] > 0)
        {
            try
            {
                Db::startTrans();
                // 积分记录
                $integralRecord
                    ->allowField(TRUE)
                    ->isUpdate(FALSE)
                    ->save(
                        [
                            'member_id' => $args['member_id'],
                            'type'      => 1,
                            'integral'  => $order_info['integral'],
                            'describe'  => '兑换' . $order_info['integral_name'] . '商品',
                        ]
                    );
                // 消费记录
                $consumption
                    ->allowField(TRUE)
                    ->isUpdate(FALSE)
                    ->save(
                        [
                            'member_id'    => $args['member_id'],
                            'type'         => 2,
                            'order_number' => $order_info['order_number'],
                            'price'        => $order_info['price'],
                            'way'          => $order_info['from'],
                            'balance'      => $member_info['usable_money'] - $order_info['price'],
                            'status'       => 1,
                        ]
                    );
                $member
                    ->where(
                        [
                            ['member_id', '=', $order_info['member_id']],
                        ]
                    )
                    ->update($decMem);
                // 减少库存 增加销量
                (new IntegralModel)
                    ->where(
                        [
                            ['integral_id', '=', $order_info['integral_id']],
                        ]
                    )
                    ->inc('add_number', $order_info['number'])
                    ->dec('integral_number', $order_info['number'])
                    ->update();
                // 更改订单状态
                $order_info->status = 0;        //已支付(待发货)
                if (array_key_exists('trade_no', $args) && $args['trade_no'])
                {
                    $order_info->trade_no = $args['trade_no'];
                }
                $order_info->pay_channel = $args['pay_channel'];
                $order_info->save();
                // 积分推送
                $inform->integral_inform(0, 'integral', $order_info['integral'], $order_info['member_id'], 1);
                Db::commit();
                // 兑换成功
                return TRUE;
            } catch (\Exception $e)
            {
                Db::rollback();
                $lock->unlock($getLock);
                return $e->getMessage();
            }
        }
        // 库存不足
        return '商品库存不足';
    }

}