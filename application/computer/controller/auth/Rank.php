<?php
declare(strict_types = 1);

namespace app\computer\controller\auth;

use app\computer\model\Member;
use app\computer\model\MemberRank;
use app\computer\controller\BaseController;
use app\computer\model\SignTask;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;
use think\facade\Session;

/**
 * 会员等级
 * Class Register
 * @package app\computer\controller\auth
 */
class Rank extends BaseController
{
    
    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['only' => 'index'],
    ];
    
    
    /**
     * 会员等级展示
     * @param SignTask $signTask
     * @param MemberRank $memberRank
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(SignTask $signTask, MemberRank $memberRank)
    {
        
        $member_id = Session::get('member_info')['member_id'] ?? 0;
        
        // 成长值
        $result['growth_value'] = countGrowth($member_id);
        // 现在的等级
        $result['now'] = $memberRank->where('min_points', '<=', $result['growth_value'])
            ->field('member_rank_id,rank_name,mark,max_points,min_points')
            ->order('min_points', 'desc')
            ->find();
        // 读取签到状态
        $sign = $signTask
            ->where([
                ['member_id', '=', $member_id],
                ['create_time', '=', date('Y-m-d')],
            ])
            ->field('integral,continuous_days')
            ->find();
        $result['continuous_days'] = is_null($sign) ? 0 : $sign->continuous_days;
        // 是否签到状态
        $result['sign_state'] = is_null($sign) ? 0 : 1;
        
        // 等级列表
        $result['rank_list'] = $memberRank
            ->field('member_rank_id,rank_name,mark,min_points,max_points,file2,discount')
            ->order('member_rank_id', 'asc')
            ->select();
        
        // 当前等级索引
        $result['next_index'] = array_search($result['now']['member_rank_id'], array_column($result['rank_list']->toArray(), 'member_rank_id'));
        
        $result['next_number'] = $memberRank->where('min_points', '>', $result['growth_value'])->value('min_points');
        $result['next_number'] = empty($result['next_number']) ? 0 : $result['next_number'] - $result['growth_value'];
        
        return $this->fetch('', ['code' => 0, 'result' => $result]);
    }
    
    //    /**
    //     * 会员专享价 - 废弃
    //     * @param MemberRank $memberRank
    //     * @return array|mixed|\think\response\View
    //     * @throws \think\db\exception\DataNotFoundException
    //     * @throws \think\db\exception\ModelNotFoundException
    //     * @throws \think\exception\DbException
    //     */
    //    public function premium_price(MemberRank $memberRank)
    //    {
    //
    //        // 等级列表
    //        $result = $memberRank
    //            ->field('rank_name,discount')
    //            ->order('max_points', 'asc')
    //            ->select()
    //            ->toArray();
    //
    //        return view('premium_price', ['result' => $result]);
    //    }
}