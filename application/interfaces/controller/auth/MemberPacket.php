<?php
declare(strict_types=1);

namespace app\interfaces\controller\auth;

use app\common\model\Article;
use app\common\model\MemberPacket as MemberPacketModel;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 我的红包 - Joy
 * Class MemberCoupon
 * @package app\interfaces\controller\auth
 */
class MemberPacket extends BaseController
{

    /**
     * 红包列表
     * @param RSACrypt $crypt
     * @param MemberPacketModel $memberPacket
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          MemberPacketModel $memberPacket)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $condition = [
            ['member_id', '=', $param['member_id']],
            ['status', '=', $param['status']],
        ];
        if ($param['status'] != 1) {
            $condition[] = ['end_time', ($param['status'] ? '<' : '>='), date('Y-m-d')];
        }
        // 用户红包列表
        $result = $memberPacket
            ->where($condition)
            ->field('member_packet_id,red_packet_id,title,actual_price,full_subtraction_price,
                start_time,end_time')
            ->order(['create_time' => 'desc', 'end_time' => 'asc'])
            ->paginate(10, false);
        $statistics = [
            // 未使用统计
            'unused' => $memberPacket
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['status', '=', '0'],
                    ['end_time', '>=', date('Y-m-d')],
                ])
                ->count(),
            // 已使用统计
            'been_used' => $memberPacket
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['status', '=', '1'],
                ])
                ->count(),
            // 已过期[未使用!]统计
            'have_expired' => $memberPacket
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['end_time', '<', date('Y-m-d')],
                    ['status', '=', 2],
                ])
                ->count(),
        ];
        return $crypt->response([
            'code' => 0,
            'result' => $result,
            'statistics' => $statistics,
        ], true);
    }

    /**
     * 红包使用说明 - web页面
     * @param Article $article
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function instructions(Article $article)
    {
        $info = $article
            ->where('article_classify_id', 10)
            ->field('title,web_content')
            ->find();
        if (!$info) {
            return "<div style='text-align: center;padding: 30px 0;'>文章不存在</div>";
        }
        return web_page($info['title'], $info['web_content']);
    }

}