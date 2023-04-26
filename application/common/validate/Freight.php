<?php
// 运费模板验证器
declare(strict_types = 1);

namespace app\common\validate;

use think\Validate;

class Freight extends Validate
{
    // 验证规则
    protected $rule = [
        'freight_attach_id|编号'      => 'require',
        'freight_id|运费模板'           => 'require',
        'upper_num|首件'              => 'require',
        'base_amount|运费'            => 'require',
        'extend_num_unit|续件'        => 'require',
        'extend_amount|运费'          => 'require',
        'distribution_area_id|配送区域' => 'require',
    ];


    // 验证信息
    protected $message = [
        'freight_attach_id.require'    => '不能为空',
        'freight_id.require'           => '不能为空',
        'upper_num.require'            => '不能为空',
        'base_amount.require'          => '不能为空',
        'extend_num_unit.require'      => '不能为空',
        'extend_amount.require'        => '不能为空',
        'distribution_area_id.require' => '不能为空'
    ];

    // 验证场景
    protected $scene = [
        // 写入
        'create' => ['upper_num', 'base_amount', 'extend_num_unit', 'extend_amount'],
        // 编辑
        'edit'   => ['freight_attach_id', 'upper_num', 'base_amount', 'extend_num_unit', 'extend_amount'],
    ];
}


