<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class StoreArticle extends Validate
{
    protected $rule = [
        'store_id|店铺信息'           => 'require',
        'store_article_id|店铺动态信息' => 'require',
        'title|店铺动态标题'            => 'require|unique:store_article',
    ];

    protected $message = [
        'store_id.require'         => '不可为空',
        'store_article_id.require' => '不可为空',
        'title.require'            => '不可为空',
        'title.unique'             => '不能重复',
    ];

    protected $scene = [
        'article_list' => ['store_id'],
        'article_view' => ['store_article_id'],
        'create'       => ['title'],
        'edit'         => ['store_id', 'title'],
    ];
}