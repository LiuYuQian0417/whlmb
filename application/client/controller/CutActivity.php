<?php
// 团购活动
declare(strict_types = 1);

namespace app\client\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\CutActivityAttach as CutActivityAttachModel;

class CutActivity extends Controller
{
    /**
     * 砍价详情
     * @param Request $request
     * @param CutActivityAttachModel $activityAttach
     * @return array|mixed
     */
    public function editAL(Request $request, CutActivityAttachModel $activityAttach)
    {
        try {
            $param = $request::get();

            $data = $activityAttach
                ->where('cut_activity_id', $param['cut_activity_id'])
                ->alias('a')
                ->join('member member', 'member.member_id = a.helper', 'left')
                ->field('avatar,nickname,phone,cut_price,a.create_time')
                ->paginate(10, false);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('edit', [
            'data' => $data,
        ]);
    }

}