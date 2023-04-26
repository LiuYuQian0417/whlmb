<?php
// 意见反馈

declare(strict_types = 1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\Feedback as FeedbackModel;

class Feedback extends Controller
{
    /**
     * 意见反馈列表
     * @param Request $request
     * @param FeedbackModel $feedback
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function index(Request $request, FeedbackModel $feedback)
    {

        // try {
            // 获取数据
            $param = $request::param();

            // 筛选条件
            $condition[] = ['feedback_id', '>', 0];
            if (!empty($param['keyword'])) $condition[] = ['contact|type','like','%'. $param['keyword'].'%'];

            // 获取数据
            $data = $feedback->where($condition)->field('feedback_id,type,contact,create_time')->order('create_time', 'desc')
                ->paginate(10, false, ['query' => $param]);


        // } catch (\Exception $e) {
        //     return ['code' => -100, 'message' => $e->getMessage()];
        // }


        return $this->fetch('', [
            'data' => $data
        ]);
    }


    /**
     * 查看意见反馈详情
     * @param Request $request
     * @param FeedbackModel $feedback
     * @return mixed
     */
    public function edit(Request $request, FeedbackModel $feedback)
    {
        $item = $feedback->where('feedback_id',$request::get('feedback_id'))->find();

        return $this->fetch('create',[
            'item'=>$item
        ]);
    }

    public function destroy(Request $request, FeedbackModel $feedback)
    {
        if ($request::isPost()) {

            try {

                $feedback::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }
}