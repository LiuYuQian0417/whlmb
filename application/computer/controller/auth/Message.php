<?php
/**
 * 关于消息.
 * User: Heng
 * Date: 2019/2/21
 * Time: 16:45
 */

namespace app\computer\controller\auth;

use think\facade\Request;
use mrmiao\encryption\RSACrypt;
use app\computer\model\Member as MemberModel;
use app\computer\controller\BaseController;
use app\computer\model\MemberMessage as MemberMessageModel;
use app\computer\model\MessageExamine as MessageExamineModel;

class Message extends BaseController
{
    /**
     * 消息列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberMessageModel $message
     * @param MemberModel $member
     * @param MessageExamineModel $messageExamine
     * @return mixed
     */
    public function index(Request $request, RSACrypt $crypt, MemberMessageModel $message, MemberModel $member, MessageExamineModel $messageExamine)
    {
        if ($request::isPost()) {
            try {
                // 接参
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;

                // 验证
                $check = $message->valid($param, 'interfaces_index');
                if ($check['code']) return $crypt->response($check);

                $condition[] = ['status', '=', 1];

                $condition[] = ['member_id', ['=', $param['member_id']], ['=', 0], 'or'];

                if ($param['type'] <> NuLL) $condition[] = ['type', '=', $param['type']];

                if ($param['type'] == 2) $condition[] = ['create_time', '>=', $member->where('member_id', $param['member_id'])->value('register_time')];

                $message_examine_id = $messageExamine
                    ->where([
                                ['member_id', '=', $param['member_id']],
                                ['type', '=', $param['type']]
                            ])
                    ->value('message_examine_id');

                if ($message_examine_id) {
                    $messageExamine->allowField(true)->save($param, ['message_examine_id' => $message_examine_id]);
                } else {
                    $messageExamine->allowField(true)->save($param);
                }

                $result = $message
                    ->where($condition)
                    ->field('title,`describe`,file,express_value,express_number,express_type,jump_state,
                    attach_id,end_time,DATE_FORMAT(create_time,"%Y-%m-%d") as date_time')
                    ->order('create_time', 'desc')
                    ->append(['current_time'])
                    ->paginate(20);

                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }

    }


    /**
     * 消息统计
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @param MemberMessageModel $message
     * @param MessageExamineModel $messageExamine
     * @return mixed
     */
    public function statistics(Request $request, RSACrypt $crypt, MemberModel $member, MemberMessageModel $message, MessageExamineModel $messageExamine)
    {
        if ($request::isPost()) {
            try {

                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request()->mid ?? '';

                $result = [];
                foreach (['common', 'express', 'activity'] as $key => $value) {

                    $result[$value] = $param['member_id'] ? $message
                        ->where([
                                    ['type', '=', $key],
                                    ['status', '=', 1],
                                    ['member_id', '=', ($value == 'activity') ? 0 : $param['member_id']],
                                    ['create_time', '>', $messageExamine->where([['member_id', '=', $param['member_id']], ['type', '=', $key]])->value('create_time') ?: $member->where('member_id', $param['member_id'])->value('register_time')]
                                ])
                        ->count() : 0;

                }

                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
}