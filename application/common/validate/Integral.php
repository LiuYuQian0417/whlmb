<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Integral extends Validate
{
    protected $rule = [
        'member_id|会员信息'              => 'require',
        'integral_id|商品信息'            => 'require',
        'integral_name|商品名称'          => 'require',
        'file|商品图片'                   => 'require',
        'multiple_file|商品组图'          => 'require',
        'integral_classify_id|商品分类'   => 'require',
        'integral|所需积分'               => ['require', 'reg' => '(^[1-9]\d*$)'],
        'type|兑换方式'                   => 'require',
        'price|所需金额'                  => ['requireCallback:check_require'],
        'integral_number|商品库存'        => ['require', 'reg' => '(^[1-9]\d*$)'],
        'warn_number|商品库存预警值'      => ['require', 'reg' => '(^[1-9]\d*$)', 'lt' => 'integral_number'],
    ];

    protected $message = [
        'member_id.require'            => '不可为空',
        'integral_id.require'          => '不可为空',
        'integral_name.require'        => '不可为空',
        'file.require'                 => '不可为空',
        'multiple_file.require'        => '不可为空',
        'type.require'                 => '不可为空',
        'integral_classify_id.require' => '不可为空',
        'integral.require'             => '不可为空',
        'integral.reg'                 => '正整数',
        'price.require'                => '不可为空',
        'integral_number.require'      => '不可为空',
        'integral_number.reg'          => '正整数',
        'warn_number.require'          => '不可为空',
        'warn_number.reg'              => '正整数',
        'warn_number.lt'               => '小于商品库存'
    ];

    protected $scene = [
        'goods'      => ['type'],
        'view'       => ['integral_id'],
        'conversion' => ['integral_id'],
        'create'     => ['type', 'integral_classify_id', 'integral_name', 'integral', 'integral_number', 'warn_number', 'price', 'file', 'multiple_file'],
        'edit'       => ['integral_id', 'integral_classify_id', 'type', 'integral_name', 'integral', 'integral_number', 'warn_number', 'price', 'file', 'multiple_file']
    ];

    protected function check_require($value, $data)
    {
        // 积分换购
        if ($data['type'] == 1) {
            return true;
        }
        return false;
    }
}