<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 文章分类模型
 * Class Manage
 * @package app\common\model
 */
class ArticleClassify extends BaseModel
{
    protected $pk = 'article_classify_id';

    public function article()
    {
        return $this->hasMany('Article', 'article_classify_id', 'article_classify_id')
            ->where([
                ['status', '=', 1],
            ])
            ->field('article_classify_id,article_id,title');
    }

}