<?php


namespace app\client\validate;


use think\Validate;

class StoreGoodsClassify extends Validate
{
    protected $rule = [
        'title|' => 'require',
        'sort|' => 'require|number',
        'status|' => 'require|in:0,1',
        'parent_id|' => 'require|number',
    ];

    protected $message = [
        'title.require' => '请输入分类名称',
        'sort.require' => '请输入排序',
        'sort.number' => '排序只能是数字',
        'status.require' => '请选择显示状态',
        'status.in' => '显示状态格式错误',
        'parent_id.require' => '请输入父分类ID',
        'parent_id.number' => '父分类ID格式错误',
    ];

    protected $scene = [
        'fast_create' => [
            'title',
            'sort',
            'status',
            'parent_id',
        ],
    ];
}