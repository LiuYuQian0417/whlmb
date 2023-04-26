<?php

namespace app\common\validate;

use think\Validate;

class Book extends Validate
{
    // 验证规则
    protected $rule = [
        'id|编号' => 'require',
        'writer_id|作者' => 'require',
        'title|书名' => 'require',
        'price|现价' => 'require',
        'old_price|原价' => 'require',
        'introduce|简介' => 'require',
        'rotation|多图' => 'require',
        'image|封面' => 'require',
        'class_id|分类' => 'require',
        'status|审核状态' => 'require',
        'type|上下架' => 'require',

    ];

    // 验证信息
    protected $message = [
        'id.require' => '不能为空',
        'writer_id.require' => '不能为空',
        'title.require' => '不能为空',
        'price.require' => '不能为空',
        'old_price.require' => '不能为空',
        'introduce.require' => '不能为空',
        'rotation.require' => '不能为空',
        'image.require' => '不能为空',
        'class_id.require' => '不能为空',
    ];

    // 验证场景
    protected $scene = [
        'create' => ['writer_id', 'title', 'price', 'old_price', 'introduce','rotation','image','class_id'],
        'edit' => ['id','writer_id', 'title', 'price', 'old_price', 'introduce','rotation','image','class_id'],
    ];
}