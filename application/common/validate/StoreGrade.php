<?php
declare(strict_types = 1);
namespace app\common\validate;

use  think\Validate;

class StoreGrade extends Validate
{
    protected $rule = [
        'store_grade_id|店铺等级信息'  => 'require',
        'name|店铺等级名称'            => 'require',
        'goods_number|发布商品数量'    => 'require',
        'template_number|选择模板数量' => 'require',
        'price|店铺等级费用'           => 'require'
    ];

    protected $message = [
        'store_grade_id.require'  => '不可为空',
        'name.regex'              => '不可为空',
        'goods_number.require'    => '不可为空',
        'template_number.require' => '不可为空',
        'price.require'           => '不可为空'
    ];

    protected $scene = [
        'create' => ['name', 'goods_number', 'template_number', 'price'],
        'edit'   => ['store_grade_id','name', 'goods_number', 'template_number', 'price'],
    ];
}