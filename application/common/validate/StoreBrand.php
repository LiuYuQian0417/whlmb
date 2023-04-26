<?php
// 平台品牌
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class StoreBrand extends Validate
{
    // 验证规则
    protected $rule = [
      'brand_id|品牌编号'=>'require',
      'brand_name|品牌名称'=>'require',
      'brand_letter|品牌英文名称'=>'require',
      'brand_first_char|品牌首字母'=>'require',
      'store_brand_id|商家品牌编号'=>'require'
    ];

    // 验证信息
    protected $message = [
      'brand_id.require'=>'不能为空',
      'brand_name.require'=>'不能为空',
      'brand_letter.require'=>'不能为空',
      'brand_first_char.require'=>'不能为空',
      'store_brand_id.require'=>'不能为空',

    ];

    // 验证场景
    protected $scene = [
        'create' => ['brand_name,brand_letter,brand_first_char,brand_id'],
        'edit'   => ['brand_name,brand_letter,brand_first_char,brand_id,store_brand_id'],
    ];
}