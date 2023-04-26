<?php
declare(strict_types=1);

namespace app\computer\model;

use \app\common\model\OrderAttach as OrderAttachModel;
use mrmiao\encryption\RSACrypt;

/**
 * 订单子表
 * Class OrderAttach
 * @package app\common\model
 */
class OrderAttach extends OrderAttachModel
{
    /*************************************************************pc添加******************************************************/
    /**
     * 订单操作方式获取器
     * @param $value
     * @param $data
     * @return array
     */
    public function getOrderOperationAttr($value, $data)
    {
        //1同城速递 2预约自提 3快递邮寄 4线下支付
        $takeStatusText = [1 => '待配送', 2 => '待自提', 3 => '待发货'];
        //查看物流
        $express_view = "onclick=\"main.express_view({'express_value':'{$data['express_value']}','express_number':'{$data['express_number']}','order_id':'{$data['order_attach_id']}','type':'order'});\"";
        $value = [
            'OrderStatusText' => '',//订单状态
            'operation'       => '',//订单操作
            'takeStatusText'  => '',  //订单收货状态
        ];
        switch ($data['status'])
        {
            //待付款
            case 0:
                $order_data = urlsafe_b64encode(
                    (new RSACrypt)->singleEnc(
                        [
                            'order_number' => $data['order_attach_number'],
                            'order_id'     => $data['order_attach_id'],
                            'total_price'  => $data['subtotal_price'],
                            'attach'       => "pay|3",
                        ]
                    )
                );
                $value['OrderStatusText'] = '等待付款';
                $value['operation'] = "<a href='/pc2.0/order/pay_type?order_data={$order_data};'  class='pl primary-color border-color'>付款</a><a href='javascript:order_cancel({$data['order_attach_id']});'>取消订单</a>";
                break;
            //待配送/待发货
            case 1:
//                $value['operation'] = "<a href='javascript:order_cancel({$data['order_attach_id']});'>取消订单</a>";
                $value['OrderStatusText'] = $takeStatusText[$data['distribution_type']];
                break;
            //配送中/待自提/待收货
            case 2:
                $takeStatusText = [1 => '配送中', 2 => '待自提', 3 => '待收货',];
                switch ($data['distribution_type'])
                {
                    //配送方式 1同城速递 2预约自提 3快递邮寄 4线下支付
                    case 1:
                        $value['OrderStatusText'] = '配送中';
                        //判断是否使用达达配送
                        if (($data['dada'] ?? 0) != 0)
                        {
                            $value['operation'] .= "<a href='javascript:;' {$express_view}> 查看物流</a>";
                        }
                        $value['operation'] .= "<a href='javascript:confirm_collect({$data['order_attach_id']});' class='btn primary-background-color'>确认收货</a>";
                        break;
                    case 2:
                        $value['OrderStatusText'] = '待自提';
                        $value['operation'] .= "<a href='javascript:confirm_collect({$data['order_attach_id']});' class='btn primary-background-color'>确认提货</a>";
                        break;
                    case 3:
                        $value['OrderStatusText'] = '待收货';
                        $value['operation'] .= "<a href='javascript:;' {$express_view}> 查看物流</a>";
                        $value['operation'] .= "<a href='javascript:confirm_collect({$data['order_attach_id']});' class='btn primary-background-color'>确认收货</a>";
                        break;
                }
                break;
            //已完成
            case 3:
                $takeStatusText = [1 => '已收货', 2 => '已自提', 3 => '已收货',];
                $value['OrderStatusText'] = '交易成功';
                switch ($data['distribution_type'])
                {
                    //配送方式 1同城速递 2预约自提 3快递邮寄 4线下支付
                    case 1:
                        //判断是否使用达达配送
                        if (($data['dada'] ?? 0) != 0)
                        {
                            $value['operation'] .= "<a href='javascript:;' {$express_view}> 查看物流</a>";
                        }
                        break;
                    case 3:
                        $value['operation'] .= "<a href='javascript:;' {$express_view}> 查看物流</a>";
                        break;
                }
                $value['operation'] .= "<a href='javascript:evaluate({$data['order_attach_id']});' class='pl primary-color border-color'>评价</a>";
                break;
            //已关闭
            case 4:
                $value['OrderStatusText'] = '已关闭';
                if(!$data['is_all_refund']){
                    //正常关闭
                    $takeStatusText = [1 => '已收货', 2 => '已自提', 3 => '已收货',];
                    switch ($data['distribution_type'])
                    {
                        //配送方式 1同城速递 2预约自提 3快递邮寄 4线下支付
                        case 1:
                            //判断是否使用达达配送
                            if (($data['dada'] ?? 0) != 0)
                            {
                                $value['operation'] .= "<a href='javascript:;' {$express_view}> 查看物流</a>";
                            }
                            break;
                        case 3:
                            $value['operation'] .= "<a href='javascript:;' {$express_view}> 查看物流</a>";
                            break;
                    }
                }
                break;
            //退款中
            case 5:
                if (empty($data['deal_time']))
                {
                    $takeStatusText = [1 => '配送中', 2 => '待自提', 3 => '待收货',];
                } else
                {
                    $takeStatusText = [1 => '已收货', 2 => '已自提', 3 => '已收货',];
                }
                //如果发货
                if (!empty($data['deliver_time']))
                {
                    switch ($data['distribution_type'])
                    {
                        //配送方式 1同城速递 2预约自提 3快递邮寄 4线下支付
                        case 1:
                            //判断是否使用达达配送
                            if (($data['dada'] ?? 0) != 0)
                            {
                                $value['operation'] .= "<a href='javascript:;' {$express_view}> 查看物流</a>";
                            }
                            break;
                        case 3:
                            $value['operation'] .= "<a href='javascript:;' {$express_view}> 查看物流</a>";
                            break;
                    }
                }
                $value['OrderStatusText'] = '退款中';
                break;
            //已取消
            case 6:
                $value['OrderStatusText'] = '已取消';
                $value['operation'] = "";
                break;
        }
        //$data['distribution_type'] 配送方式 1同城速递 2预约自提 3快递邮寄 4线下支付
        $value['takeStatusText'] = $takeStatusText[$data['distribution_type']];
        return $value;
    }


    /**
     * 订单支付数据获取器
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getOrderDataAttr($value, $data)
    {
        return urlsafe_b64encode(
            (new RSACrypt)->singleEnc(
                [
                    'order_number' => $data['order_attach_number'],
                    'order_id'     => $data['order_attach_id'],
                    'total_price'  => $data['subtotal_price'],
                    'attach'       => "pay|3",
                ]
            )
        );
    }

    /**
     * 订单详情  物流名称信息获取器
     */

    public function getExpressNameAttr()
    {
        if ($this['express_value'])
        {
            return Express::where([['code', '=', $this['express_value']]])->value('name', '');
        }
        return '';
    }


    /**
     * 查询获取订单发票信息
     */

    public function getInvoiceDetailAttr()
    {
        $field = 'i.invoice_id,i.invoice_type,
        i.detail_type,i.taxer_number,i.address,i.phone,i.bank,i.account,i.invoice_open_type,
        i.express_value,i.express_number,i.download_links,i.billing_type,s.is_added_value_tax,
        i.rise,i.rise_name,i.order_attach_id';
        $data = (new Invoice)
            ->alias('i')
            ->join('store s', 's.store_id = i.store_id')
            ->where([['i.order_attach_id', 'eq', $this['order_attach_id']]])
            ->field($field)
            ->order(['i.billing_type' => 'asc', 'i.create_time' => 'desc'])
            ->find();
        // 增值税发票->纸质
        if (!is_null($data) && $data['invoice_open_type'] == 2)
        {
            $data['invoice_open_type'] = 1;
            $data['order_attach_number'] = $this['order_attach_number'];
            $data['create_time'] = $this['create_time'];
            $data['status'] = $this['status'];
        }
        return !is_null($data) ? $data : [];
    }

    /**
     * 订单详情(关联订单商品)
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsDetails()
    {
        return self::orderGoods()
            ->field('goods_id,order_goods_id,order_attach_id,goods_attr,attr,quantity,single_price,status,
            sub_freight_price,goods_name,goods_name_style,file,`describe`,sub_fullSub_price,discount_price,original_price,
            sub_share_platform_coupon_price,sub_share_shop_coupon_price,subtotal_share_platform_packet_price,sum_alter_goods_price,goods_sn,
            sum_alter_freight_price,subtotal_price')
            ->with(['orderGoodsRefundList']);
    }

    /*************************************************************pc添加******************************************************/
}