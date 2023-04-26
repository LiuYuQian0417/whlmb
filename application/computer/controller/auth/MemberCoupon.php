<?php
declare(strict_types=1);

namespace app\computer\controller\auth;

use app\computer\model\Article;
use app\computer\model\IntegralRecord;
use app\computer\model\Member;
use app\computer\model\MemberCoupon as MemberCouponModel;
use app\computer\model\Coupon;
use app\common\service\Beanstalk;
use app\common\service\Lock;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Request;
use think\facade\Session;
use app\computer\model\Store;



/**
 * 我的优惠券
 * Class MemberCoupon
 * @package app\computer\controller\auth
 */
class MemberCoupon extends BaseController
{
    protected $beforeActionList = [
        //检查是否登录
        'is_login'=>['only' => 'index,get,exchange']
    ];

    /**
     * 领取优惠券
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberCouponModel $memberCoupon
     * @param Coupon $coupon
     * @param Lock $lock
     * @return mixed
     */
    public function get(Request $request, RSACrypt $crypt, MemberCouponModel $memberCoupon, Coupon $coupon, Lock $lock)
    {

        if ($request::isPost()) {
            try {

                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 验证
                $check = $memberCoupon->valid($param, 'get');
                if ($check['code']) return $crypt->response($check);

                $coupon_info = $coupon->where('coupon_id', $param['coupon_id'])->field('exchange_num,limit_num')->find();

                // 查询是否已经领取
                $number = $memberCoupon
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['coupon_id', '=', $param['coupon_id']]
                    ])
                    ->count();

                if ($coupon_info['limit_num'] != 0 && $number >= $coupon_info['limit_num']) {
                    return $crypt->response(['code' => -100, 'message' => config('message.')[-8][-1]]);
                }

                // 事务处理
                Db::startTrans();

                // redis锁设置
                $getLock = $lock->lock(['coupon_' . $param['coupon_id']], 10000);

                // 成功执行
                if ($getLock) {

                    if ($coupon_info['exchange_num'] > 0) {
                        $result = array_merge($coupon
                            ->where('coupon_id', $param['coupon_id'])
                            ->field('title,type,actual_price,full_subtraction_price,start_time,end_time')
                            ->find()->toArray(), $param);

                        $memberCoupon->allowField(true)->save($result);

                        // 时间计算
                        $time = strtotime($result['end_time']) - time();


                        self::sendMsg($time, $memberCoupon->member_coupon_id, $param['member_id']);

                    }

                    // 解锁
                    $lock->unlock($getLock);

                    Db::commit();

                    return $crypt->response(['code' => 0, 'message' => config('message.')[0][6], 'number' => ($coupon_info['limit_num'] != 0 && ($number + 1) >= $coupon_info['limit_num']) ? 1 : 0]);

                }

                return $crypt->response(['code' => -100, 'message' => config('message.')[-8][-2]]);

            } catch (\Exception $e) {
                Db::rollback();
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }


    /**
     * 换取优惠券
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberCouponModel $memberCoupon
     * @param Coupon $coupon
     * @param Lock $lock
     * @param Member $member
     * @param IntegralRecord $integralRecord
     * @return mixed
     */
    public function exchange(Request $request, RSACrypt $crypt, MemberCouponModel $memberCoupon, Coupon $coupon, Lock $lock, Member $member, IntegralRecord $integralRecord)
    {
        if ($request::isPost()) {
            try {

                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 验证
                $check = $memberCoupon->valid($param, 'exchange');
                if ($check['code']) return $crypt->response($check);

                $info = $coupon
                    ->where('coupon_id', $param['coupon_id'])
                    ->field('title,exchange_num,integral,limit_num')
                    ->find();

                // 查询已领取数量
                $get_count = $memberCoupon
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['coupon_id', '=', $param['coupon_id']],
                    ])
                    ->count('member_coupon_id');

                if ($info['limit_num'] > 0 && $get_count >= $info['limit_num']) {
                    return $crypt->response(['code' => -100, 'message' => config('message.')[-8][-1]]);
                }

                // 事务处理
                Db::startTrans();

                // redis锁设置
                $getLock = $lock->lock(['coupon_' . $param['coupon_id']], 10000);

                // 成功执行
                if ($getLock) {

                    // 检查用户积分
                    $pay_points = $member->where('member_id', $param['member_id'])->value('pay_points');

                    // 判断用户积分是否够用
                    if ($pay_points < $info['integral']) {

                        // 解锁
                        $lock->unlock($getLock);

                        return $crypt->response(['code' => -100, 'message' => config('message.')[-8][-3]], true);

                    }

                    if ($info['exchange_num'] > 0) {

                        $result = array_merge($coupon
                            ->where('coupon_id', $param['coupon_id'])
                            ->field('title,type,actual_price,full_subtraction_price,end_time,start_time')
                            ->find()->toArray(), $param);

                        $memberCoupon->allowField(true)->save($result);

                        // 积分记录
                        $integralRecord->allowField(true)->save([
                            'member_id' => $param['member_id'],
                            'type' => 1,
                            'integral' => $info['integral'],
                            'describe' => '兑换' . $info['title'] . '优惠券'
                        ]);

                        // 会员减少积分
                        $member->where('member_id', $param['member_id'])->setDec('pay_points', $info['integral']);
                        //添加优惠券到期提醒和到期更改状态
                        $time = strtotime($result['end_time']) - time();
                        self::sendMsg($time, $memberCoupon->member_coupon_id, $param['member_id']);
                    }

                    // 解锁
                    $lock->unlock($getLock);

                    Db::commit();

                    return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

                }

                return $crypt->response(['code' => -100, 'message' => config('message.')[-8][-2]]);

            } catch (\Exception $e) {
                Db::rollback();
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }

    /**
     * 优惠券列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberCouponModel $memberCoupon
     * @return mixed
     */
    public function index(Request $request, RSACrypt $crypt, MemberCouponModel $memberCoupon,Store $store)
    {
        try {
            // 获取参数
            $param = $request::instance()->param();
            $membe_id = Session::get('member_info')['member_id'];

            // 条件
            $condition = [];

            $condition[] = ['member_coupon.member_id', '=', $membe_id];

            if (!empty($param['status'])){
                 if($param['status'] == 2){
                    $condition[] = ['member_coupon.end_time', '<', date('Y-m-d')];
                }else{
                    $condition[] = ['member_coupon.status', '=', 1];
                }
            }else{
                $condition[] = ['member_coupon.end_time', '>=', date('Y-m-d')];
                $condition[] = ['member_coupon.status', '=', 0];

            }


            // 查询是否已经领取
            $result = $memberCoupon->alias('member_coupon')
                    ->join('coupon coupon', 'coupon.coupon_id = member_coupon.coupon_id')
                    ->where($condition)
                    ->field('coupon.coupon_id,coupon.title,coupon.actual_price,coupon.classify_str,
                    coupon.full_subtraction_price,member_coupon.type,member_coupon.goods_classify_id,
                    member_coupon.store_id,coupon.start_time,coupon.end_time')
                    ->paginate(12, false, ['query' => $param]);
//            halt($result);

            foreach($result as &$value){
                $value['condition'] = implode(',',$store ->where([['store_id','in',$value['classify_str']]])->column('store_name'));
            }
            // 未使用/已使用/已过期
            $statistics['unused'] = $memberCoupon
                ->where([
                    ['member_id', '=', $membe_id],
                    ['status', '=', '0'],
                    ['end_time', '>=', date('Y-m-d')]
                ])
                ->count();
            $statistics['been_used'] = $memberCoupon
                ->where([
                    ['member_id', '=', $membe_id],
                    ['status', '=', '1']
                ])
                ->count();
            $statistics['have_expired'] = $memberCoupon
                ->where([
                    ['member_id', '=', $membe_id],
                    ['end_time', '<', date('Y-m-d')]
                ])
                ->count();



            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        return $this->fetch('',['code' => 0, 'result' => $result, 'statistics' => $statistics]);
    }

        /**
     * 发送优惠券队列消息[包括提醒和到期更改状态]
     * @param $time
     * @param $id
     * @param $uid
     */
    private function sendMsg($time, $id, $uid)
    {
        if ($time > 0) {
            $msg = json_encode(['queue' => 'couponExpireChangeStatus',
                                'id' => $id, 'uid' => $uid, 'time' => date('Y-m-d H:i:s')]);
            // 到期未使用更改状态
            (new Beanstalk())->put($msg, $time);
            // 到期提醒
            $new_time = ($time - 86400 * 3) <= 0 ? 0 : ($time - 86400 * 3);
            $new_msg = json_encode(['queue' => 'couponExpireChangeStatus',
                                    'id' => $id, 'uid' => $uid, 'time' => date('Y-m-d H:i:s')]);
            (new Beanstalk())->put($new_msg, $new_time);
        }
    }


    /********废弃******/
//    /**
//     * 优惠券使用说明 - web页面
//     * @param Article $article
//     * @return array|\think\response
//     * @throws \think\db\exception\DataNotFoundException
//     * @throws \think\db\exception\ModelNotFoundException
//     * @throws \think\exception\DbException
//     */
//    public function instructions(Article $article)
//    {
//        $info = $article
//            ->where('article_classify_id', 9)
//            ->field('title,content')
//            ->find();
//        if (!$info) {
//            return ['code' => -100, 'message' => '文章不存在'];
//        }
//        return web_page($info['title'], $info['content']);
//    }


}