<?php


namespace app\master\validate;


use think\Validate;

class GoodsClassify extends Validate
{
    protected $rule = [
        'title|' => 'require',
        'sort|' => 'require|number',
        'keyword|' => 'require',
        'status|' => 'require|in:0,1',
    ];

    protected $message = [
        'title.require' => '请填写分类名称',
        'sort.require' => '请填写排序',
        'sort.number' => '排序只能是数字',
        'keyword.require' => '请填写关键字',
        'status.require' => '请选择显示状态',
        'status.in' => '显示状态参数错误',
    ];

    protected $scene = [
        'fast_create' => [
            'title',
            'sort',
            'status',
        ]
    ];
}