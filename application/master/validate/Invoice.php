<?php

namespace app\master\validate;

use think\Validate;

class Invoice extends Validate
{
    protected $rule = [
        'order_attach_id|订单信息' => 'require',
        'invoice_attr|操作类型' => 'require',
        'invoice_number|发票号码' => ['require', 'reg' => '(^[0-9]\d*$)', 'max' => 20],
        'invoice_open_type|开票类型' => 'require',
        'rise|开票对象' => 'require',
        'rise_name|发票抬头' => 'require',
        'taxer_number|纳税人识别号' => 'requireCallback:check_require',
        'address|注册地址' => 'requireCallback:check_address_require',
        'phone|注册电话' => ['requireCallback:check_phone_require', 'reg' => '^(1[35846]\d{9})$'],
        'bank|开户银行' => 'requireCallback:check_bank_require',
        'account|银行账号' => ['requireCallback:check_account_require', 'reg' => '(^([1-9]{1})(\d{15,18})$)'],
        'express_value|快递公司' => 'requireCallback:check_express_value_require',
        'express_number|运单编号' => 'requireCallback:check_express_number_require',
    ];

    protected $message = [
        'order_attach_id.require' => '不可为空',
        'invoice_attr.require' => '不可为空',
        'invoice_number.require' => '不可为空',
        'invoice_number.reg' => '格式错误',
        'invoice_number.max' => '最多20位',
        'invoice_open_type.require' => '不可为空',
        'rise.require' => '不可为空',
        'rise_name.require' => '不可为空',
        'taxer_number.require' => '不可为空',
        'address.require' => '不可为空',
        'phone.require' => '不可为空',
        'phone.reg' => '格式错误',
        'bank.require' => '不可为空',
        'account.require' => '不可为空',
        'account.reg' => '格式错误',
        'express_value.require' => '不可为空',
        'express_number.require' => '不可为空',
    ];

    protected $scene = [
        'fill_open' => ['order_attach_id', 'invoice_attr', 'invoice_number', 'invoice_open_type', 'rise', 'rise_name', 'taxer_number',
            'address', 'phone', 'bank', 'account', 'express_value', 'express_number'],
        'examine' => ['express_value', 'express_number', 'invoice_number'],
    ];

    protected function check_require($value, $data)
    {
        // 开票类型专票  或  开票对象企业
        if ($data['invoice_open_type'] == 2 || $data['rise'] == 2) {
            return true;
        }
        return false;
    }

    protected function check_address_require($value, $data)
    {
        // 开票类型专票
        if ($data['invoice_open_type'] == 2) {
            return true;
        }
        return false;
    }

    protected function check_phone_require($value, $data)
    {
        // 开票类型专票
        if ($data['invoice_open_type'] == 2) {
            return true;
        }
        return false;
    }

    protected function check_bank_require($value, $data)
    {
        // 开票类型专票
        if ($data['invoice_open_type'] == 2) {
            return true;
        }
        return false;
    }

    protected function check_account_require($value, $data)
    {
        // 开票类型专票
        if ($data['invoice_open_type'] == 2) {
            return true;
        }
        return false;
    }

    protected function check_express_value_require($value, $data)
    {
        // 开票类型专票  纸质
        if ($data['invoice_open_type'] == 1 || $data['invoice_type'] == 1) {
            return true;
        }
        return false;
    }

    protected function check_express_number_require($value, $data)
    {
        // 开票类型专票  纸质
        if ($data['invoice_open_type'] == 1 || $data['invoice_type'] == 1) {
            return true;
        }
        return false;
    }
}