<?php
// 收藏文章验证
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

class CollectArticle extends Validate
{
    // 验证条件
    protected $rule = [
        'article_id|文章信息'           => 'require',
        'collect_article_id|收藏文章信息' => 'require',
    ];


    // 验证信息
    protected $message = [
        'article_id.require'         => '不能为空',
        'collect_article_id.require' => '不能为空',
    ];

    // 场景验证
    protected $scene = [
        'collect_article'             => ['article_id'],
        'collect_article_delete'      => ['article_id', 'collect_article_id'],
        'view_collect_article_delete' => ['article_id'],
    ];
}
