<?php
declare(strict_types=1);

namespace app\computer\controller\common;

use app\computer\model\Article;
use app\computer\model\Goods;
use app\computer\model\Integral;
use app\computer\model\LotteryActivity;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Request;

/**
 * App - Web页面
 * Class Html
 * @package app\computer\controller\common
 */
class Html extends BaseController
{


    /**
     * 其他文章详情 - web页面
     * @param Request $request
     * @param Article $article
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_view(Request $request, Article $article)
    {

        $info = $article
            ->where('article_id',  $request::instance()->param('article_id'))
            ->field('title,content')
            ->find();
        if (!$info) {
            return ['code' => -100, 'message' => '文章不存在'];
        }
        return ['code' => 0,'info' => $info];

    }

    public function ajax_article_view(Request $request,RSACrypt $crypt,Article $article)
    {
        if ($request::isPost()){
            try{
                $param = $crypt->request();
                $info = $article
                    ->where('article_id', $param['article_id'])
                    ->field('title,content')
                    ->find();
                if (!$info){
                    return $crypt->response(
                        [
                            'code'   => 0,
                            'message' => '文章不存在',
                        ]
                    );
                }
                return $crypt->response(
                    [
                        'code'   => 0,
                        'result' => $info,
                    ]
                );
        } catch (\Exception $e){
                Db::rollback();
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
            }
        }
    }


    /***废弃**/
//
//    /**
//     * 商品详情 - web页面
//     * @param Request $request
//     * @param Goods $goods
//     * @return array|\think\response
//     * @throws \think\db\exception\DataNotFoundException
//     * @throws \think\db\exception\ModelNotFoundException
//     * @throws \think\exception\DbException
//     */
//    public function goods_view(Request $request, Goods $goods)
//    {
//        $info = $goods
//            ->alias('g')
//            ->join('store s','g.store_id = s.store_id and '.self::store_auth_sql('s'))
//            ->where(self::goods_where([['g.goods_id','=',$request->get('goods_id')]],'g'))
//            ->field('g.goods_name,g.web_content')
//            ->find();
//        if (!$info) {
//            return ['code' => -100, 'message' => '商品不存在'];
//        }
//        return web_page($info['goods_name'], $info['web_content']);
//
//    }
//
//
//    /**
//     * 抽奖活动 - web页面
//     * @param Request $request
//     * @param LotteryActivity $activity
//     * @return string
//     */
//    public function draw_activity_view(Request $request, LotteryActivity $activity)
//    {
//        $action_rule = $activity->where('activity_id', $request->get('activity_id'))->value('action_rule') ?? '';
//        $__action_rule = explode(',', $action_rule);
//        $_action_rule = '<div>积分抽奖（活动规则）</div>';
//        foreach ($__action_rule as $v) {
//            $_action_rule .= '<div class="m-txt">' . $v . '</div>';
//        }
//        return web_page('抽奖规则', $_action_rule);
//
//    }
//
//    /**
//     * 积分文章详情 - web页面
//     * @param Request $request
//     * @param Integral $integral
//     * @return array|\think\response
//     * @throws \think\db\exception\DataNotFoundException
//     * @throws \think\db\exception\ModelNotFoundException
//     * @throws \think\exception\DbException
//     */
//    public function integral_view(Request $request, Integral $integral)
//    {
//        $info = $integral
//            ->where('integral_id', $request->get('integral_id'))
//            ->field('integral_name,web_content')
//            ->find();
//        if (!$info) {
//            return ['code' => -100, 'message' => '积分文章不存在'];
//        }
//        return web_page($info['integral_name'], $info['web_content']);
//    }
}