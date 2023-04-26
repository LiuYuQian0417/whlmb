<?php
declare(strict_types=1);

namespace app\common\model;

class StoreCapital extends BaseModel
{
    protected $pk = 'capital_id';

    public function orderGoods()
    {
        return $this->hasMany('orderGoods', 'order_attach_id', 'order_attach_id');
    }

    public function orderGoodsRefund()
    {
        return $this->hasOne('orderGoodsRefund', 'a.order_goods_id', 'order_goods_id');
    }

    public static function init()
    {
        self::beforeInsert(function ($e) {
            //账户明细修改  添加对应支付方式  1微信 2支付宝 3余额 4银行卡 5线下 6货到付款  添加其他订单类型的时候对照下支付方式是否一致
            switch ($e->status ?? 100)
            {
                //充值
                case 0:
                    if (!empty($e->parameter_id))
                    {
                        //查询对应订单支付方式
                        $e['pay_channel'] = Consumption::where([['consumption_id', '=', $e->parameter_id ?? 0]])
                            ->value('pay_channel', 0);
                        $_pay_channel = ['1' => 2, '2' => 1, '3' => 4, '4' => 3, '5' => 5];
                        $e['pay_channel'] = $_pay_channel[$e['pay_channel']] ?? 0;
                    }
                    break;
                //交易
                case 2:
                    if (!empty($e->order_attach_id))
                    {
                        //查询对应订单支付方式
                        $e['pay_channel'] = OrderAttach::where([['order_attach_id', '=', $e->order_attach_id ?? 0]])
                            ->value('pay_channel', 0);
                    }
                    break;
                //退款
                case 3:
                    if (!empty($e->order_goods_id))
                    {
                        //查询对应订单支付方式
                        $e['pay_channel'] = OrderGoods::alias('o_g')
                            ->join('order_attach o_a', 'o_g.order_attach_id=o_a.order_attach_id')
                            ->where([['o_g.order_goods_id', '=', $e->order_goods_id ?? 0]])
                            ->value('o_a.pay_channel', 0);
                    }
                    break;
            }
            $e->create_time = date('Y-m-d H:i:s');
        });
    }
}