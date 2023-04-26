<?php
// 会员消费明细表
namespace app\common\validate;

use think\Db;
use think\Validate;

class Consumption extends Validate
{
    // 验证规则
    protected $rule = [
        'consumption_id|编号' => 'require',
        'member_id|会员信息'    => 'require',
        'type|消费类型'         => 'require',
        'price|金额'          => 'require|checkPrice',
        'way|资金方式'          => 'require',
        'status|审核状态'       => 'require',
    ];


    // 验证信息
    protected $message = [
        'consumption_id.require' => '不能为空',
        'member_id.require'      => '不能为空',
        'type.require'           => '不能为空',
        'price.require'          => '不能为空',
        'way.require'            => '不能为空',
        'status.require'         => '不能为空',
    ];


    // 验证场景
    protected $scene = [
        'create'         => ['member_id', 'type', 'price', 'way', 'status'],
        'edit'           => ['consumption_id', 'member_id', 'type', 'price', 'way', 'status'],
    ];

    // 验证提现金额
    protected function checkPrice($value,$rule,$data=[])
    {
        if ($data['type'] == 1) {
            $usableMoney = Db::name('member')->where([['member_id','eq',$data['member_id']]])->value('usable_money');
            if ($value > 0) {
                if ($usableMoney >= $value) {
                    return true;
                } else {
                    return '不能大于可用资金';
                }
            } else {
                return '提现金额不能为0';
            }
        }
        return true;
    }
}