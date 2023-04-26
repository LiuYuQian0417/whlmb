<?php
declare(strict_types=1);

namespace app\computer\validate;

use  think\Validate;

class Article extends Validate
{
    protected $rule = [
        'article_id|文章信息'          => 'require',
        'title|文章标题'               => 'require',
        'file|图片'                  => 'require',
        'article_classify_id|文章分类' => 'require'
    ];

    protected $message = [
        'article_id.require'          => '不可为空',
        'title.require'               => '不可为空',
        'file.require'                => '不可为空',
        'article_classify_id.require' => '不可为空'
    ];

    protected $scene = [
        'create'       => ['title', 'file', 'article_classify_id'],
        'edit'         => ['article_id', 'title', 'article_classify_id'],
        'system_edit'  => ['article_id', 'title', 'article_classify_id'],
        'hot_view'     => ['article_id'],
        'article_list' => ['store_id'],
        'article_view' => ['article_id'],
    ];
}