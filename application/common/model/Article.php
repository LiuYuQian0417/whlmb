<?php
declare(strict_types=1);

namespace app\common\model;

use app\common\model\ArticleAttach as ArticleAttachModel;
use app\common\model\ArticleClassify as ArticleClassifyModel;
use think\facade\Request;

/**
 * 文章附表模型
 * Class Article
 * @package app\common\model
 */
class Article extends BaseModel
{
    protected $pk = 'article_id';

    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
        self::event('after_write', function ($data) {
            $goodsId = Request::instance()->post('goods_id/a', []);
            $article_id = $data->article_id;
            (new ArticleAttachModel())
                ->where([
                    ['article_id', '=', $article_id],
                ])
                ->delete(TRUE);
            if (!empty($goodsId)) {
                $arr = array_map(function ($e) use ($article_id) {
                    return ['goods_id' => $e, 'article_id' => $article_id];
                }, $goodsId);
                (new ArticleAttachModel())->insertAll($arr);
            }
        });
    }

    public function attachCor()
    {
        return $this->hasMany('ArticleAttach', 'article_id', 'article_id');
    }

    public function getStateNameAttr($value, $data)
    {
        $status = [0 => '普通', 1 => '顶置'];
        return $status[$data['state']];
    }

    public function getClassifyNameAttr($value, $data)
    {
        return (new ArticleClassifyModel)
            ->where([
                ['article_classify_id', '=', $data['article_classify_id']],
            ])
            ->value('title');
    }

    public function getGoodsNumberAttr($value, $data)
    {
        return (new ArticleAttach())
            ->alias('a')
            ->join('goods g', 'g.goods_id = a.goods_id')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->where([
                ['article_id', '=', $data['article_id']],
                ['g.is_putaway', '=', 1],
                ['g.review_status', '=', 1],
                ['g.goods_number', '>', 0],
            ])
            ->count();
    }

    public function getGoodsFileAttr($value, $data)
    {
        return (new ArticleAttach())
            ->alias('aa')
            ->join('goods g', 'g.goods_id = aa.goods_id', 'left')
            ->where([
                ['article_id', '=', $data['article_id']],
            ])
            ->field('file')
            ->find();
    }

    public function getEndTimeAttr($value, $data)
    {
        return (new Message())->where([
            ['attach_id', '=', $data['article_id']],
            ['type', '=', 2]
        ])->value('end_time');
    }

    public function getMultipleFileRawAttr($value, $data)
    {
        if (empty($data['multiple_file'])) {
            return [];
        }
        return explode(',', $data['multiple_file']);
    }


}