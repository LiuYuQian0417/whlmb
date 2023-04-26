<?php
declare(strict_types=1);

namespace app\computer\validate;

use  think\Validate;

class IntegralOrder extends Validate
{
    protected $rule = [
        'member_id|会员信息' => 'require',
        'member_address_id|收货地址' => 'require',
        'integral_id|商品信息' => 'require',
        'integral_order_id|订单信息' => 'require',
        'number|商品数量' => 'require',
        'province|省' => 'require',
        'city|市' => 'require',
        'area|区' => 'require',
        'street|街道' => 'require',
        'address|详细地址' => 'require',
        'lat|纬度' => 'require',
        'lng|经度' => 'require',
        'name|收件人' => 'require',
        'phone|收件人手机号' => 'require',
        'status|状态' => 'require',
        'express_value|快递名称' => 'require',
        'express_number|快递单号' => 'require',
        'pay_pass|支付密码' => 'require',
        'order_number|订单号' => 'require',
    ];

    protected $message = [
        'member_id.require' => '不可为空',
        'member_address_id.require' => '收货地址必须选择',
        'integral_id.require' => '不可为空',
        'integral_order_id.require' => '不可为空',
        'number.require' => '不可为空',
        'province.require' => '不可为空',
        'city.require' => '不可为空',
        'area.require' => '不可为空',
        'street.require' => '不可为空',
        'address.require' => '不可为空',
        'lat.require' => '不可为空',
        'lng.require' => '不可为空',
        'name.require' => '不可为空',
        'phone.require' => '不可为空',
        'status.require' => '不可为空',
        'express_value.require' => '不可为空',
        'express_number.require' => '不可为空',
        'pay_pass.require' => '不可为空',
        'order_number.require' => '不可为空',
    ];

    protected $scene = [
        'redemption' => ['integral_id', 'member_address_id'],
        'preOrder' => ['integral_id', 'number', 'province', 'city', 'area', 'street', 'address', 'lat', 'lng', 'name', 'phone', 'pay_pass'],
        'redemption_money' => ['order_number', 'pay_pass'],
        'conversion_view' => ['integral_order_id'],
        'confirm_receipt' => ['integral_order_id', 'status'],
        'edit' => ['integral_order_id', 'express_value', 'express_number'],
        'conversion_record_delete' => ['integral_order_id', 'member_id'],
    ];
}