<?php
declare(strict_types=1);

namespace app\computer\model;

use \app\common\model\OrderGoods as OrderGoodsModel;

/**
 * 订单商品
 * Class OrderGoods
 * @package app\common\model
 */
class OrderGoods extends OrderGoodsModel
{
    /**
     * 退款订单退款状态
     * @param $value
     * @param $data
     * @return string
     */
    public function getRefundStatusAttr($value, $data)
    {
        //4.2 退款成功 4.3 退货成功 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意)
        //5.3 商家同意退货 5.4 申请退货(退货第二步,填写物流)
        //5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
        switch ($data['status'])
        {
            case 4.2:
                $value = '仅退款 退款成功';
                break;
            case 4.3:
                $value = '退货退款 退货成功';
                break;
            case 5.1:
                $value = '仅退款 退款中';
                break;
            case 5.2:
            case 5.3:
            case 5.4:
                $value = '退货退款 退款中';
                break;
            case 5.5:
                $value = '仅退款 退款失败';
                break;
            case 5.6:
                $value = '退货退款 退款成功';
                break;
            case 5.7:
                $value = '退货退款 退款失败';
                break;
        }
        return $value;
    }

    /**
     * 退款操作
     * @param $data
     * @return array
     */
    public static function refund_operation($data)
    {
        //4.2 退款成功 4.3 退货成功 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意)
        //5.3 商家同意退货 5.4 申请退货(退货第二步,填写物流)
        //5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
        $value = [
            'text'      => '',
            'operation' => '',
            'action'    => '',
        ];
        switch ($data['status'])
        {
            case 1.1:
            case 2.1:
                if ($data['pay_type'] == 1)
                {
                    $value['text'] = '退款';
                    $value['action'] = "onclick='after_sale({$data['order_goods_id']})'";
                    $value['operation'] = "<span style='cursor: pointer;' {$value['action']}>{$value['text']}</span>";
                }
                break;
            case 3.1:
            case 4.1:
                if ($data['after_sale_time'] != -1)
                {
                    $value['text'] = '申请售后';
                    $value['action'] = "onclick='after_sale({$data['order_goods_id']})'";
                    $value['operation'] = "<span style='cursor: pointer;'  {$value['action']}>{$value['text']}</span>";
                }
                break;
            case 4.2:
                $value['text'] = '退款成功';
                $value['action'] = "onclick='after_sale_details({$data['order_goods_id']})'";
                $value ['operation'] = "<span style='cursor: pointer;'  {$value['action']}>{$value['text']}</span>";
                break;
            case 4.3:
                $value['text'] = '退货成功';
                $value['action'] = "onclick='after_sale_details({$data['order_goods_id']})'";
                $value['operation'] = "<span style='cursor: pointer;'  {$value['action']}>{$value['text']}</span>";
                break;
            case 5.1:
            case 5.2:
                $value['text'] = '申请退款中';
                $value['action'] = "onclick='after_sale_details({$data['order_goods_id']})'";
                $value['operation'] = "<span style='cursor: pointer;'  {$value['action']}>{$value['text']}</span>";
                break;
            case 5.3:
                $value['text'] = '同意退货,请填写物流单号';
                $value['action'] = "onclick='after_sale_details({$data['order_goods_id']})'";
                $value['operation'] = "<span style='cursor: pointer;'  {$value['action']}>{$value['text']}</span>";
                break;
            case 5.4:
                $value['text'] = '申请退货中';
                $value['action'] = "onclick='after_sale_details({$data['order_goods_id']})'";
                $value['operation'] = "<span style='cursor: pointer;'  {$value['action']}>{$value['text']}</span>";
                break;
            case 5.5:
            case 5.7:
                $value['text'] = '退款失败';
                $value['action'] = "onclick='after_sale_details({$data['order_goods_id']})'";
                $value['operation'] = "<span style='cursor: pointer;'  {$value['action']}>{$value['text']}</span>";
                break;
            case 5.6:
                $value['text'] = '退货失败';
                $value['action'] = "onclick='after_sale_details({$data['order_goods_id']})'";
                $value['operation'] = "<span style='cursor: pointer;'  {$value['action']}>{$value['text']}</span>";
                break;
        }
        return $value;
    }
}