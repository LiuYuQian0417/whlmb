<?php
/**
 * 同城速递表验证器
 */
declare(strict_types = 1);

namespace app\common\validate;

use think\Validate;

class DistributionCity extends Validate
{
    // 验证规则
    protected $rule = [
//        'distribution_city_id|编号'  => 'require',
        'store_id|店铺编号'            => 'require',
        // 'type|配送方式'=>'require',
        'distribution_type|配送范围方式' => 'require',
        'start_price|起送价'          => 'require',
        'basic_freight|基础运费'       => 'require',
        'staircase|竞价阶梯'           => 'require',
        'distribution_area_id|配送区域'           => 'requireIf:distribution_type,2',
        'discount|满足优惠条件' => ['requireCallback:check_discount', 'reg' => '(^[0-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)'],
        'postage|邮递优惠邮费' => ['requireCallback:check_postage', 'reg' => '(^[0-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)'],
    ];


    // 验证消息
    protected $message = [
        'distribution_city_id' => '不可为空',
        'store_id'             => '不可为空',
        'distribution_type'    => '不可为空',
        'start_price'          => '不可为空',
        'basic_freight'        => '不可为空',
        'staircase'            => '不可为空',
        'distribution_area_id'            => '不可为空',
        'discount'            => '不可为空',
        'discount.reg'            => '正整数或两位小数',
        'postage'            => '不可为空',
        'postage.reg'            => '正整数或两位小数',
    ];

    protected function check_discount($value, $data)
    {

        if (isset($data['discount_postage_rules'])) {
            return true;
        }
        return false;
    }

    protected function check_postage($value, $data)
    {

        if (isset($data['discount_postage_rules'])) {
            return true;
        }
        return false;
    }
}

