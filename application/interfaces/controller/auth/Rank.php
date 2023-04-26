<?php
declare(strict_types=1);

namespace app\interfaces\controller\auth;

use app\common\model\Member;
use app\common\model\MemberRank;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 会员等级 - Joy
 * Class Register
 * @package app\interfaces\controller\auth
 */
class Rank extends BaseController
{

    /**
     * 会员等级展示
     * @param RSACrypt $crypt
     * @param Member $member
     * @param MemberRank $memberRank
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Member $member,
                          MemberRank $memberRank)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 读取个人信息
        $result = $member
            ->where([
                ['member_id', '=', $param['member_id']],
                ['status', '=', 1],
            ])
            ->field('avatar,nickname')
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -1,
                'message' => '用户不存在',
            ], true);
        }
        // 成长值
        $result['growth_value'] = countGrowth($param['member_id']);
        // 现在的等级
        $result['now'] = $memberRank
            ->where([
                ['min_points', '<=', $result['growth_value']],
            ])
            ->field('member_rank_id,rank_name,mark,max_points,min_points')
            ->order(['min_points' => 'desc'])
            ->find() ?: json([]);
        // 下一个等级
        $result['next'] = json([]);
        if (!is_null($result['now'])) {
            $result['next'] = $memberRank
                ->where([
                    ['min_points', '>', $result['growth_value']],
                ])
                ->field('rank_name,mark,min_points')
                ->order(['min_points' => 'asc'])
                ->find() ?: json([]);
        }
        // 等级列表
        $result['rank_list'] = $memberRank
            ->field('member_rank_id,rank_name,mark,min_points,max_points,file2')
            ->order('min_points', 'asc')
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }

    /**
     * 会员专享价
     * @param MemberRank $memberRank
     * @return array|mixed|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function premium_price(MemberRank $memberRank)
    {
        // 等级列表
        $result = $memberRank
            ->field('rank_name,discount')
            ->order('min_points', 'asc')
            ->withAttr('discount', function ($value) {
                if ($value >= 100) {
                    return '无折扣';
                } else {
                    return number_format($value / 10, 1, '.', '') . '折';
                }
            })
            ->select();
        return view('premium_price', ['result' => empty($result) ? [] : $result->toArray()]);
    }

    /**
     * 会员卡
     * @param RSACrypt $crypt
     * @param Member $member
     * @param MemberRank $memberRank
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function card(RSACrypt $crypt,
                         Member $member,
                         MemberRank $memberRank)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 读取个人信息
        $result = $member
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->field('nickname,card_number,usable_money,pay_password')
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -1,
                'message' => '用户不存在',
            ], true);
        }
        // 是否存在支付密码
        $result['pay_state'] = $result['pay_password'] ? 1 : 0;
        // 删除
        unset($result['pay_password']);
        // 成长值
        $result['growth_value'] = countGrowth($param['member_id']);
        // 现在的等级
        $result['now'] = $memberRank
            ->where([
                ['min_points', '<=', $result['growth_value']],
            ])
            ->field('mark,rank_name')
            ->order(['min_points' => 'desc'])
            ->find() ?: json([]);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
}