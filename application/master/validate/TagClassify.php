<?php


namespace app\master\validate;


use app\daemonService\customer\Validate;

class TagClassify extends Validate
{
    protected $rule = [
        'id|'   => 'require',
        'name|' => 'require|length:2,6',
    ];

    protected $message = [
        'id.require'   => '缺失标签分类ID',
        'name.require' => '请填写标签分类',
        'name.length'  => '标签分类长度应为2-6个字符',
    ];

    protected $scene = [
        'create'      => [
            'name',
        ],
        'edit_get'    => [
            'id',
        ],
        'edit_post' => [
            'id',
            'name',
        ],
        'delete'      => [
            'id',
        ],
    ];
}