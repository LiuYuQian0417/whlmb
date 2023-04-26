<?php
// 团购商品分类
namespace app\common\validate;

use think\Validate;

class GroupClassify extends Validate
{
    // 验证规则
    protected $rule = [
        'group_classify_id|编号' => 'require',
        'title|团购分类名称'         => 'require|unique:group_classify,title',
    ];


    // 验证信息
    protected $message = [
        'group_classify_id.require' => '不能为空',
        'title.require'             => '不能为空',
        'title.unique'              => '重复',
    ];


    // 验证场景
    protected $scene = [
        'create' => ['title'],
        'edit'   => ['group_classify_id', 'title']
    ];


}