<?php
// 平台品牌
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class Brand extends Validate
{
    // 验证规则
    protected $rule = [
        'brand_id|编号' => 'require',
        'goods_classify_id|商品分类' => 'require',
        'brand_name|品牌名称' => 'require',
        'brand_letter|品牌英文名称' => 'require',
        'brand_first_char|品牌首字母' => 'require',
        'brand_classify_id|品牌分类ID' => 'require',
    ];

    // 验证信息
    protected $message = [
        'brand_id.require' => '不能为空',
        'goods_classify_id.require' => '不能为空',
        'brand_name.require' => '不能为空',
        'brand_letter.require' => '不能为空',
        'brand_first_char.require' => '不能为空',
        'brand_classify_id.require' => '不能为空',
        'brand_name.unique' => '不能重复',
        'brand_letter.unique' => '不能重复',
    ];

    // 验证场景
    protected $scene = [
        'create' => ['goods_classify_id', 'brand_name', 'brand_letter', 'brand_first_char', 'brand_classify_id'],
        'edit' => ['goods_classify_id', 'brand_name', 'brand_letter', 'brand_first_char', 'brand_id', 'brand_classify_id'],
    ];
}