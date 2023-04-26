<?php
declare(strict_types=1);

namespace app\computer\controller\auth;

use app\computer\model\Article;
use app\computer\model\MemberPacket as MemberPacketModel;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;
use think\facade\Session;

/**
 * 我的红包
 * Class MemberCoupon
 * @package app\computer\controller\auth
 */
class MemberPacket extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login'=>['only' => 'index']
    ];


    /**
     * 红包列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberPacketModel $memberPacket
     * @return mixed
     */
    public function index(Request $request, RSACrypt $crypt, MemberPacketModel $memberPacket)
    {
        try {

            // 获取参数
            $param = $request::instance()->param();
            $member_id = Session::get('member_info')['member_id'];


            // 条件
            $condition[] = ['member_id', '=', $member_id];


            if (!empty($param['status'])){
                if ($param['status'] == 3){
                    $condition[] = ['status', '=', 0];
                    $condition[] = ['end_time', '>=', date('Y-m-d')];
                }
                if ($param['status'] == 1){
                    $condition[] = ['status', '=', $param['status']];
                }
                if($param['status'] == 2){
                    $condition[] = ['end_time', '<', date('Y-m-d')];
                }
            }else{
                $condition[] = ['status', '=', 0];
                $condition[] = ['end_time', '>=', date('Y-m-d')];
            }

            // 查询是否已经领取
            $result = $memberPacket
                ->where($condition)
                ->field('red_packet_id,title,actual_price,full_subtraction_price,start_time,end_time')
                ->paginate(12, false, ['query' => $param]);

            // 未使用/已使用/已过期
            $statistics['unused'] = $memberPacket
                ->where([
                    ['member_id', '=', $member_id],
                    ['status', '=', '0'],
                    ['end_time', '>=', date('Y-m-d')]
                ])
                ->count();
            $statistics['been_used'] = $memberPacket
                ->where([
                    ['member_id', '=', $member_id],
                    ['status', '=', '1']
                ])
                ->count();
            $statistics['have_expired'] = $memberPacket
                ->where([
                    ['member_id', '=', $member_id],
                    ['end_time', '<', date('Y-m-d')]
                ])
                ->count();

        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }

        return $this->fetch('',['code' => 0, 'result' => $result, 'statistics' => $statistics]);
    }

    /***********废弃***********/

//    /**
//     * 红包使用说明 - web页面
//     * @param Article $article
//     * @return array|\think\response
//     * @throws \think\db\exception\DataNotFoundException
//     * @throws \think\db\exception\ModelNotFoundException
//     * @throws \think\exception\DbException
//     */
//    public function instructions(Article $article)
//    {
//        $info = $article
//            ->where('article_classify_id', 10)
//            ->field('title,content')
//            ->find();
//        if (!$info) {
//            return json(['code' => -100, 'message' => '文章不存在']);
//        }
//        return web_page($info['title'], $info['content']);
//    }

}