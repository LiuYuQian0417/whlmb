<?php


namespace app\master\validate;


use think\Validate;

class Tag extends Validate
{
    protected $rule = [
        'id|' => 'require|number',
        'tag_classify_id|' => 'require|number',
        'name|' => 'require|length:2,6',
        'content|' => 'require|max:300',
    ];

    protected $message = [
        'id.require' => '标签ID必填',
        'id.number' => '标签ID格式错误',
        'tag_classify_id.require' => '请选择标签分类',
        'tag_classify_id.number' => '标签分类ID格式错误',
        'name.require' => '请填写标签名称',
        'name.length' => '标签名称应在2-6个字符以内',
        'content.require' => '请输入服务内容',
        'content.max' => '服务内容应在300个字符以内',
    ];

    protected $scene = [
        'create' => [
            'tag_classify_id',
            'name',
            'content',
        ],
        'edit_get' => [
            'id',
        ],
        'edit_post' => [
            'id',
            'tag_classify_id',
            'name',
            'content',
        ],
        'delete' => [
            'id'
        ]
    ];
}