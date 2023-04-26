<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/13 0013
 * Time: 8:46
 */

namespace app\common\model;


use think\Db;

class AlterPriceLog extends BaseModel
{
    protected $pk = 'alter_price_log_id';

    private $object_list = [];

    /**
     * 客服修改订单商品金额
     *
     * @param $param
     *
     * @throws \think\Exception\DbException
     */
    public function customerUpdateOrderPrice($param)
    {
        $this->object_list['order_goods'] = $order_goods = OrderGoods::get(
                [
                    ['order_attach_id', '=', $param['order_attach_id']],
                    ['goods_id', '=', $param['goods_id']],
                ]
            ) ?? exception(
                '订单不存在',
                401
            );
        //查询客服是否有权限修改当前商品
        Customer::where(
            [
                ['store_id', '=', $this->object_list['order_goods']->store_id],
                ['customer_id', '=', $param['customer_id']],
            ]
        )->count() > 0 ?: exception('无权修改当前订单', 401);
        $this->UpdateOrderPrice($param, 1);

    }

    /**
     * 店铺修改订单商品金额
     *
     * @param $param
     *
     * @throws \think\Exception\DbException
     */
    public function storeUpdateOrderPrice($param)
    {
        $this->object_list['order_goods'] = $order_goods = OrderGoods::get($param['order_goods_id']) ?? exception(
                '订单不存在',
                401
            );
        $this->UpdateOrderPrice($param, 2);
    }


    /**
     * 从新更新订单订单号
     *
     * @param int $former_order_attach_number 旧订单号
     */
    private function updateOrderAttachNumber($former_order_attach_number)
    {
        //查询所有有此字段的表订单号从新赋值
        $query_str = 'select TABLE_NAME from information_schema.columns where column_name=\'order_attach_number\' and TABLE_SCHEMA = \'' . config(
                                                                                                                                           )['database']['database'] . '\'';
        $table_list = Db::query($query_str);
        $new_order_attach_number = get_order_sn();
        foreach ($table_list as $v)
        {
            $update_str = 'UPDATE ' . $v['TABLE_NAME'] . ' SET order_attach_number =\'' . $new_order_attach_number . '\' WHERE order_attach_number = ' . $former_order_attach_number;
            Db::query($update_str);
        }
    }

    /**
     * 修改订单商品金额
     *
     * @param $param
     * @param $alter_type
     *
     * @throws \think\Exception\DbException
     */
    private function UpdateOrderPrice($param, $alter_type)
    {
        $order_goods = $this->object_list['order_goods'];
        if ((string)$order_goods->status !== '0.1')
        {
            exception('已经支付订单无法修改', 401);
        }
        //查询商品订单数据
        $order_attach = OrderAttach::get($order_goods->order_attach_id);
        //判断是增加还是减少订单金额
//        $param['alter_price'] = (int)$param['alter_price'];
//        $param['freight'] = (int)$param['freight'];
        if ($param['freight'] < 0)
        {
            if (abs($param['freight']) > $order_goods->sub_freight_price)
            {
                exception('运费金额不能低于0元', 401);
            }
        }
        //提前更新商品运费金额
        $order_goods->sub_freight_price += $param['freight'];
        if ($param['alter_price'] < 0)
        {
            $min_alter_price = $order_goods['single_price'] * $order_goods['quantity'] + $order_goods['sum_alter_goods_price'] + $order_goods['sub_freight_price'] - $order_goods['sub_share_shop_coupon_price'] - $order_goods['sub_share_platform_coupon_price'] - $order_goods['subtotal_share_platform_packet_price'] - 0.01;
            if (abs($param['alter_price']) > $min_alter_price)
            {
                exception('订单金额不能低于' . $min_alter_price . '元', 401);
            }
        }
        //新的修改价格
        $new_alter_price = $param['alter_price'] + $param['freight'];
        $order = Order::get($order_attach->order_id);

        /******************************更新商品订单数据***********************************/

        $order_goods->sum_alter_goods_price += $new_alter_price;
        $order_goods->sum_alter_freight_price += $param['freight'];
        $order_goods->save();

        /*******************************更新店铺订单商品金额******************************/

        $order_attach->subtotal_price += $new_alter_price;
        $order_attach->sum_alter_goods_price += $new_alter_price;
        $order_attach->sum_alter_freight_price += $param['freight'];
        $order_attach->subtotal_freight_price += $param['freight'];
        $order_attach->save();

        /******************************更新店铺订单商品金额*******************************/

        $order->total_price += $new_alter_price;
        $order->total_freight += $param['freight'];
        $order->save();

        /*******************************更新订单号，支付用*******************************/

        $this->updateOrderAttachNumber($order_attach->order_attach_number);
        //插入价格修改记录
        $this->save(
            [
                'order_attach_id' => $order_goods['order_attach_id'],
                'order_goods_id'  => $param['order_goods_id'],
                'customer_id'     => $param['customer_id'] ?? '',
                'price'           => $param['alter_price'],
                'reason'          => $param['reason'] ?? '',
                'freight'         => $param['freight'],
                'alter_type'      => $alter_type,
            ]
        );
    }
}