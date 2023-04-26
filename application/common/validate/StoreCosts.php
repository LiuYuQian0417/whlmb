<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class StoreCosts extends Validate
{
    protected $rule = [
        'store_classify_id|主营类目' => 'require',
        'turnover|营业额'      => ['require', 'reg' => '(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)'],
        'percent|使用费百分比'      => ['require', 'reg' => '(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)'],
        'store_costs_id|费用信息'      => 'require',
    ];

    protected $message = [
        'store_classify_id.require' => '不可为空',
        'turnover.require'      => '不可为空',
        'turnover.reg'      => '正整数或两位小数',
        'percent.require'      => '不可为空',
        'percent.reg'      => '正整数或两位小数',
        'store_costs_id.require'      => '不可为空',
    ];

    protected $scene = [
        'create' => ['store_classify_id', 'percent', 'turnover'],
        'turnover' => ['store_classify_id', 'turnover', 'store_costs_id'],
        'percent' => ['store_classify_id', 'percent', 'store_costs_id'],
    ];
}