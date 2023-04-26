<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\Article;
use app\common\model\IntegralRecord;
use app\common\model\Member;
use app\common\model\MemberCoupon as MemberCouponModel;
use app\common\model\Coupon;
use app\common\service\Beanstalk;
use app\common\service\Lock;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;

/**
 * 我的优惠券 - Joy
 * Class MemberCoupon
 * @package app\interfaces\controller\auth
 */
class MemberCoupon extends BaseController
{
    private $is_coupon = 1;
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        $this->is_coupon = Env::get('is_coupon', 1);
    }
    
    /**
     * 检测当前模式是否支持优惠券
     * @return mixed
     */
    protected function checkHasCoupon()
    {
        // 不显示
        if (!$this->is_coupon) {
            return [
                'code' => -1,
                'message' => '当前模式不支持优惠券',
            ];
        }
        return null;
    }
    
    /**
     * 领取优惠券
     * @param RSACrypt $crypt
     * @param MemberCouponModel $memberCoupon
     * @param Coupon $coupon
     * @param Lock $lock
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get(RSACrypt $crypt,
                        MemberCouponModel $memberCoupon,
                        Coupon $coupon,
                        Lock $lock)
    {
        if (!is_null($check = self::checkHasCoupon())) {
            return $crypt->response($check, true);
        };
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $memberCoupon->valid($param, 'get');
        $coupon_info = $coupon
            ->where([
                ['coupon_id', '=', $param['coupon_id']],
                ['status', '=', 1],
            ])
            ->field('exchange_num,limit_num,classify_str,title,type,actual_price,
            full_subtraction_price,start_time,end_time,type')
            ->find();
        if (is_null($coupon_info)) {
            return $crypt->response([
                'code' => -2,
                'message' => '优惠券不存在',
            ], true);
        }
        $coupon_info[['store_id', 'goods_classify_id'][$coupon_info['type']]] = $coupon_info['classify_str'];
        // 检测是否已经领取
        $number = $memberCoupon
            ->where([
                ['member_id', '=', $param['member_id']],
                ['coupon_id', '=', $param['coupon_id']]
            ])
            ->count();
        if ($coupon_info['limit_num'] != 0 && $number >= $coupon_info['limit_num']) {
            return $crypt->response([
                'code' => -3,
                'message' => '该优惠券已到领取上限,请勿重复领取',
            ], true);
        }
        $getLock = $lock->lock(['coupon_' . $param['coupon_id']], 10000);
        if ($getLock) {
            // 优惠券剩余数量有效
            if ($coupon_info['exchange_num'] > 0) {
                $result = array_merge($param,$coupon_info->toArray());
                $memberCoupon->allowField(true)->save($result);
                // 时间计算
                $time = strtotime($result['end_time']) - time();
                self::sendMsg($time, $memberCoupon->member_coupon_id, $param['member_id']);
            }
            $lock->unlock($getLock);
            // 限领状态
            $limitNum = ($coupon_info['limit_num'] != 0 && ($number + 1) >= $coupon_info['limit_num']) ? 1 : 0;
            return $crypt->response([
                'code' => 0,
                'message' => '领取成功',
                'number' => $limitNum,
            ], true);
        }
        return $crypt->response([
            'code' => -100,
            'message' => '网络错误,请重试',
        ], true);
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
            $new_msg = json_encode(['queue' => 'couponExpireRemind',
                'id' => $id, 'uid' => $uid, 'time' => date('Y-m-d H:i:s')]);
            (new Beanstalk())->put($new_msg, $new_time);
        }
    }
    
    /**
     * 换取优惠券
     * @param RSACrypt $crypt
     * @param MemberCouponModel $memberCoupon
     * @param Coupon $coupon
     * @param Lock $lock
     * @param Member $member
     * @param IntegralRecord $integralRecord
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exchange(RSACrypt $crypt,
                             MemberCouponModel $memberCoupon,
                             Coupon $coupon,
                             Lock $lock,
                             Member $member,
                             IntegralRecord $integralRecord)
    {
        if (!is_null($check = self::checkHasCoupon())) {
            return $crypt->response($check, true);
        };
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $memberCoupon->valid($param, 'exchange');
        $info = $coupon
            ->where([
                ['coupon_id', '=', $param['coupon_id']],
                ['status', '=', 1],
            ])
            ->field('title,exchange_num,integral,limit_num')
            ->find();
        if (is_null($info)) {
            return $crypt->response([
                'code' => -1,
                'message' => '优惠券不存在或已下架',
            ], true);
        }
        $getCount = $memberCoupon
            ->where([
                ['member_id', '=', $param['member_id']],
                ['coupon_id', '=', $param['coupon_id']],
            ])
            ->count('member_coupon_id');
        if ($info['limit_num'] != 0 && $getCount >= $info['limit_num']) {
            return $crypt->response([
                'code' => -100,
                'message' => '该优惠券已到领取上限,请勿重复领取',
            ], true);
        }
        $pay_points = $member
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->value('pay_points');
        // 判断用户积分是否够用
        if ($pay_points < $info['integral']) {
            return $crypt->response([
                'code' => -2,
                'message' => '您的积分不足',
            ], true);
        }
        if ($info['exchange_num'] <= 0) {
            return $crypt->response([
                'code' => -3,
                'message' => '剩余兑换数量不足',
            ], true);
        }
        $couponInfo = $coupon
            ->where([
                ['coupon_id', '=', $param['coupon_id']],
            ])
            ->field('title,type,actual_price,full_subtraction_price,end_time,start_time,classify_str')
            ->find();
        $couponInfo[['store_id', 'goods_classify_id'][$couponInfo['type']]] = $couponInfo['classify_str'];
        $result = array_merge($param,$couponInfo->toArray());
        $getLock = $lock->lock(['coupon_' . $param['coupon_id']], 10000);
        if ($getLock) {
            Db::startTrans();
            $memberCoupon->allowField(true)->save($result);
            // 积分记录
            $integralRecord->allowField(true)->save([
                'member_id' => $param['member_id'],
                'type' => 1,
                'integral' => $info['integral'],
                'describe' => '兑换' . $info['title'] . '优惠券'
            ]);
            // 会员减少积分
            $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->setDec('pay_points', $info['integral']);
            //添加优惠券到期提醒和到期更改状态
            $time = strtotime($result['end_time']) - time();
            self::sendMsg($time, $memberCoupon->member_coupon_id, $param['member_id']);
            $lock->unlock($getLock);
            Db::commit();
            return $crypt->response([
                'code' => 0,
                'message' => '换取成功',
            ], true);
        }
        return $crypt->response([
            'code' => -100,
            'message' => '网络繁忙,请重试',
        ], true);
    }
    
    /**
     * 优惠券列表
     * @param RSACrypt $crypt
     * @param MemberCouponModel $memberCoupon
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          MemberCouponModel $memberCoupon)
    {
        if (!is_null($check = self::checkHasCoupon())) {
            return $crypt->response($check, true);
        };
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $condition = [
            ['mc.member_id', '=', $param['member_id']],
            ['mc.status', '=', $param['status']],
        ];
        if ($param['status'] != 1) {
            $condition[] = ['mc.end_time', ($param['status'] ? '<' : '>='), date('Y-m-d')];
        }
        // 用户优惠券列表
        $result = $memberCoupon
            ->alias('mc')
            ->where($condition)
            ->field('mc.member_coupon_id,mc.coupon_id,mc.title,mc.actual_price,mc.start_time,
            mc.end_time,mc.full_subtraction_price,mc.type,mc.goods_classify_id,mc.store_id')
            ->order(['mc.create_time' => 'desc', 'end_time' => 'asc'])
            ->paginate(10, false);
        $statistics = [
            // 未使用统计
            'unused' => $memberCoupon
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['status', '=', '0'],
                    ['end_time', '>=', date('Y-m-d')],
                ])
                ->count(),
            //已使用统计
            'been_used' => $memberCoupon
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['status', '=', '1']
                ])
                ->count(),
            //已过期[未使用!]
            'have_expired' => $memberCoupon
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['end_time', '<', date('Y-m-d')],
                    ['status', '=', 2],
                ])
                ->count(),
        ];
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'statistics' => $statistics,
        ], true);
    }
    
    /**
     * 优惠券使用说明 - web页面
     * @param Article $article
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function instructions(Article $article)
    {
        if (!is_null($check = self::checkHasCoupon())) {
            return "<div style='text-align: center;padding: 30px 0;'>" . $check['message'] . "</div>";
        };
        $info = $article
            ->where('article_classify_id', 9)
            ->field('title,web_content')
            ->find();
        if (!$info) {
            return "<div style='text-align: center;padding: 30px 0;'>文章不存在</div>";
        }
        return web_page($info['title'], $info['web_content']);
    }
    
}