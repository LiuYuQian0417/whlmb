<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 发票主表
 * Class Invoice
 * @package app\common\model
 */
class Invoice extends BaseModel
{
    protected $pk = 'invoice_id';

    /**
     * 发票类型获取器
     * @param $data
     * @param $value
     * @return mixed
     */
    public function getInvoiceTypeTextAttr($data, $value)
    {
        $typeArr = ['普通发票', '增值税专用发票'];
        return $typeArr[$value['invoice_type']];
    }

    /**
     * 开票类型获取器
     * @param $data
     * @param $value
     * @return mixed
     */
    public function getBillingTypeTextAttr($data, $value)
    {
        $billingTypeArr = ['1' => '正票', '2' => '红票'];
        return $billingTypeArr[$value['billing_type']];
    }

    public function getDetailTypeAttr($value)
    {
        return ['商品明细', '商品类型'][$value];
    }

    /**
     * 发票主表 订单附属表 关联
     * @return \think\model\relation\HasOne
     */
    public function OrderAttach()
    {
        return $this
            ->hasOne('order_attach', 'order_attach_id', 'order_attach_id')
            ->field('order_attach_id,subtotal_price,status as order_status,subtotal_freight_price');
    }

    /**
     * 发票主表 订单商品表 关联
     * @return \think\model\relation\HasMany
     */
    public function OrderGoods()
    {
        return $this
            ->hasMany('order_goods', 'order_attach_id', 'order_attach_id')
            ->field('order_attach_id,attr,goods_name,quantity,single_price,sub_share_shop_coupon_price,goods_id,
            sub_share_platform_coupon_price,subtotal_share_platform_packet_price,sub_fullSub_price,goods_unit,status');
    }

    /**
     * 发票主表 店铺表 关联
     * @return \think\model\relation\HasOne
     */
    public function Store()
    {
        return $this->hasOne('store', 'store_id', 'store_id')->field('store_name');
    }
}