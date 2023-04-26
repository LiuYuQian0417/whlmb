<?php
// 会员消费明细表
namespace app\common\validate;

use think\Db;
use think\Validate;

class StoreCapital extends Validate
{
    // 验证规则
    protected $rule = [
        'store_id|店铺信息' => 'require',
        'province|开户省' => 'require',
        'city|开户市' => 'require',
        'area|开户区' => 'require',
        'account_name|开户名' => 'require',
        'account_bank_name|开户行' => 'require',
        'bank_number|银行卡号' => ['require', 'reg' => '(^([1-9]{1})(\d{15,18})$)'],
        'price|提现金额'    => ['require', 'reg' => '(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)', 'checkPrice'],
        'capital_id|提现信息' => 'require',
        'status|提现状态' => 'require',
        'reason_1_5|失败原因' => 'requireCallback:check_require',
        'reason_1_3|失败原因' => 'requireCallback:check_require'
    ];


    // 验证信息
    protected $message = [
        'store_id.require' => '不能为空',
        'province.require'     => '不能为空',
        'city.require'    => '不能为空',
        'area.require'  => '不能为空',
        'account_name.require'  => '不能为空',
        'account_bank_name.require'  => '不能为空',
        'bank_number.require'  => '不能为空',
        'bank_number.reg'  => '格式错误',
        'price.require'  => '不能为空',
        'price.reg'  => '正整数或保留两位小数',
        'capital_id.require' => '不能为空',
        'status.require' => '不能为空',
        'reason_1_5.require' => '不能为空',
        'reason_1_3.require' => '不能为空',
    ];


    // 验证场景
    protected $scene = [
        'create' => ['store_id', 'province', 'city', 'area', 'account_name', 'account_bank_name', 'bank_number', 'price'],
        'is_check' => ['capital_id', 'status', 'reason_1_5'],
        'is_complete' => ['capital_id', 'status', 'reason_1_3'],
    ];

    // 验证提现金额
    protected function checkPrice($value, $rule, $data = [])
    {
        $balance = Db::name('store')->where([['store_id', 'eq', $data['store_id']]])->value('balance');
        if ($value > 0) {
            if ($balance >= $value) {
                return true;
            } else {
                return '不能大于可用余额';
            }
        } else {
            return '不能为0';
        }
    }

    protected function check_require($value, $data)
    {
        // 无操作广告
        if ($data['status'] == 1.5 || $data['status'] == 1.3 && $data['status'] != '') {
            return true;
        }
        return false;
    }
}