<?php
// 商品分类验证器
declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

class BrandClassify extends Validate
{
    // 验证规则
    protected $rule = [
        'brand_classify_id|编号' => 'require',
        'brand_classify_name|品牌分类标题' => 'require|max:8',
        'status|审核状态' => 'require',
    ];


    // 验证信息
    protected $message = [
        'friendship_link_id.require' => '不可为空',
        'friendship_title.require' => '不可为空',
        'friendship_title.max' => '不能超过8个字符',
        'friendship_url.require' => '不可为空',
    ];


    // 验证场景
    protected $scene = [
        'create' => ['brand_classify_name', 'status'],
        'edit' => ['brand_classify_id', 'brand_classify_name', 'status'],
    ];
}