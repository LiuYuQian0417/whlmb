<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Cache;
use think\facade\Config;

/**
 * 收藏内容模型
 * Class CollectArticle
 * @package app\common\model
 */
class CollectArticle extends BaseModel
{
    protected $pk = 'collect_article_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
        self::afterInsert(function ($e) {
            (new Article())
                ->where([
                    ['article_id', '=', $e->article_id],
                ])
                ->setInc('collect');
            // 写入数量
            $prefix = Config::get('cache.default')['prefix'];
            Cache::handler()->zIncrBy($prefix . 'collect_article', 1, $e->member_id);
        });
    }

    /**
     * @param $value
     * @param $data
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
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

    /**
     * 获取文章有效状态
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsInvalidAttr($value, $data)
    {
        $ret = 0;
        if (!$data['status'] || !is_null($data['delete_time'])) {
            $ret = 1;
        }
        return $ret;
    }
}