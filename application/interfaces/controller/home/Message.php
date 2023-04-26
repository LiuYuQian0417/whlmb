<?php
declare(strict_types=1);

namespace app\interfaces\controller\home;

use app\common\model\Member;
use app\common\model\Message as MessageModel;
use app\common\model\MessageExamine;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 消息 - Joy
 * Class Home
 * @package app\interfaces\controller\home
 */
class Message extends BaseController
{
    
    /**
     * 消息列表
     * @param RSACrypt $crypt
     * @param Member $member
     * @param MessageModel $message
     * @param MessageExamine $messageExamine
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function index(RSACrypt $crypt,
                          Member $member,
                          MessageModel $message,
                          MessageExamine $messageExamine)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $message->valid($param, 'interfaces_index');
        // 状态 0 普通通知 1 物流通知 2 优惠通知
        $condition = [
            ['status', '=', 1],     // 是否显示
            ['member_id', ['=', $param['member_id']], ['=', 0], 'or'],
            ['type', '=', $param['type']],
        ];
        if ($param['type'] == 2) {
            // 查看用户注册以后的系统消息
            $regTime = $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])->value('register_time');
            if (!is_null($regTime)) {
                $condition[] = ['create_time', '>=', $regTime];
            }
        }
        $me = $messageExamine
            ->where([
                ['member_id', '=', $param['member_id']],
                ['type', '=', $param['type']],
            ])
            ->field('message_examine_id,type,create_time')
            ->find();
        if (is_null($me)) {
            $messageExamine
                ->allowField(true)
                ->save([
                    'member_id' => $param['member_id'],
                    'type' => $param['type'],
                ]);
        } else {
            // 更新查看时间
            $me->create_time = date('Y-m-d H:i:s');
            $me->save();
        }
        $result = $message
            ->where($condition)
            ->field('title,`describe`,file,jump_state,attach_id,end_time,unix_timestamp(end_time) as end_time_stamp,
            DATE_FORMAT(create_time,"%Y-%m-%d") as date_time,unix_timestamp(now()) as current_time_stamp')
            ->append(['current_time'])
            ->order('create_time', 'desc')
            ->paginate(20, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
        
    }
    
    /**
     * 消息统计
     * @param RSACrypt $crypt
     * @param Member $member
     * @param MessageModel $message
     * @param MessageExamine $messageExamine
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function statistics(RSACrypt $crypt,
                               Member $member,
                               MessageModel $message,
                               MessageExamine $messageExamine)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $result = [];
        foreach (['common', 'express', 'activity'] as $key => $value) {
            $result[$value] = 0;
            if ($param['member_id']) {
                $base = $message
                    ->where([
                        ['type', '=', $key],
                        ['status', '=', 1],
                        ['member_id', '=', ($value == 'activity') ? 0 : $param['member_id']],
                    ]);
                $ct = $messageExamine
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['type', '=', $key],
                    ])
                    ->value('create_time');
                $rt = $member
                    ->where([
                        ['member_id', '=', $param['member_id']],
                    ])
                    ->value('register_time');
                $extraSql = 'create_time > \'' . ($ct ?: $rt) . '\'';
                $base->whereRaw($extraSql);
                $result[$value] = $base->count();
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
}