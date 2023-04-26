<?php
declare(strict_types=1);

namespace app\interfaces\controller\common;

use app\common\model\Article;
use app\common\model\Goods;
use app\common\model\Integral;
use app\common\model\LotteryActivity;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;


/**
 * App - Web页面
 * Class Html
 * @package app\interfaces\controller\common
 */
class Html extends BaseController
{

    /**
     * 商品详情 - web页面
     * @param Goods $goods
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_view(Goods $goods)
    {
        $info = $goods
            ->where([
                ['goods_id', '=', Request::get('goods_id', 0)],
            ])
            ->field('goods_name,web_content')
            ->find();
        if (is_null($info)) {
            return "<div style='text-align: center;padding: 30px 0;'>暂无详情</div>";
        }
        return web_page($info['goods_name'], $info['web_content']);

    }

    /**
     * 其他文章详情 - web页面
     * @param Article $article
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_view(Article $article)
    {
        $info = $article
            ->where([
                ['article_id', '=', Request::get('article_id', 0)],
            ])
            ->field('title,web_content')
            ->find();
        if (is_null($info)) {
            return "<div style='text-align: center;padding: 30px 0;'>暂无文章</div>";
        }
        return web_page($info['title'], $info['web_content']);
    }

    /**
     * 抽奖活动 - web页面
     * @param LotteryActivity $activity
     * @return string
     */
    public function draw_activity_view(LotteryActivity $activity)
    {
        $action_rule = $activity
                ->where([
                    ['activity_id', '=', Request::get('activity_id', 0)],
                ])
                ->value('action_rule') ?? '';
        $__action_rule = explode(',', $action_rule);
        $_action_rule = '<div>积分抽奖（活动规则）</div>';
        foreach ($__action_rule as $v) {
            $_action_rule .= '<div class="m-txt">' . $v . '</div>';
        }
        return web_page('抽奖规则', $_action_rule);

    }

    /**
     * 积分文章详情 - web页面
     * @param Integral $integral
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function integral_view(Integral $integral)
    {
        $info = $integral
            ->where([
                ['integral_id', '=', Request::get('integral_id', 0)],
            ])
            ->field('integral_name,web_content')
            ->find();
        if (is_null($info)) {
            return "<div style='text-align: center;padding: 30px 0;'>暂无文章</div>";
        }
        return web_page($info['integral_name'], $info['web_content']);
    }

    /**
     * 其他文章详情 - 小程序
     * @param RSACrypt $crypt
     * @param Article $article
     * @return array
     */
    public function applet_article_view(RSACrypt $crypt,
                                        Article $article)
    {
        $con = $article
                ->where([
                    ['article_id', '=', Request::get('article_id', 0)],
                ])
                ->value('web_content') ?? '';

        $con = str_ireplace('<div class="tools"><i class="fa fa-arrow-up move-up"></i><i class="fa fa-arrow-down move-down"></i><em class="move-remove"><i class="fa fa-times" aria-hidden="true"></i> 移除</em><div class="cover"></div></div>','',$con);
        $con = str_ireplace('<div class="tools"><i class="fa fa-arrow-up move-up"></i><i class="fa fa-arrow-down move-down"></i><em class="move-remove" hidden ><i class="fa fa-times" aria-hidden="true"></i> 移除</em><div class="cover"></div></div>','',$con);
        $con = str_ireplace('<div class="tools"><i class="fa fa-arrow-up move-up disabled"></i><i class="fa fa-arrow-down move-down"></i><em class="move-remove"><i class="fa fa-times" aria-hidden="true"></i> 移除</em><div class="cover"></div></div>','',$con);
        $con = str_ireplace('<div class="tools" style="display: none;"><i class="fa fa-arrow-up move-up disabled"></i><i class="fa fa-arrow-down move-down"></i><em class="move-remove" hidden  hidden=""><i class="fa fa-times" aria-hidden="true"></i> 移除</em><div class="cover"></div></div>','',$con);

        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'content' => $con,
        ], true);
    }
}