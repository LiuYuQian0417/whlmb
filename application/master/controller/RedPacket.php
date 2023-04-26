<?php
// 红包管理
declare(strict_types=1);

namespace app\master\controller;

use app\common\service\Beanstalk;
use think\Controller;
use think\facade\Request;
use app\common\model\RedPacket as RedPacketModel;
use app\common\model\MemberPacket as MemberPacketModel;

class RedPacket extends Controller
{
    // 红包列表
    public function index(Request $request, RedPacketModel $redPacket)
    {
        try {
            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['red_packet_id', 'neq', 0];
            $condition[] = ['type', 'neq', 1];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];
            if (isset($param['type']) && $param['type'] != -1) $condition[] = ['type', '=', $param['type']];

            $data = $redPacket->where($condition)
                ->field('description,update_time,delete_time', true)
                ->order('red_packet_id', 'asc')->paginate(15, false, ['query' => $param]);


        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        // 检查注册红包，消费红包是否已存在
        $checkRegister = $redPacket->where('type', 2)->find() ? 1 : 2;
        $checkConsumption = $redPacket->where('type', 3)->find() ? 1 : 2;

        return $this->fetch('', [
            'data' => $data,
            'checkRegister' => $checkRegister,
            'checkConsumption' => $checkConsumption
        ]);
    }


    // 创建红包
    public function create(Request $request, RedPacketModel $redPacket)
    {
        if ($request::isPost()) {

            try {
                // 获取数据
                $param = $request::post();

                $valid = ($param['type'] > 1) ? 'platform_create' : 'create';

                // 验证
                $check = $redPacket->valid($param, $valid);
                if ($check['code']) return $check;

                // 写入
                $operation = $redPacket->allowField(true)->save($param);

                if ($operation && $valid == 'create') {
                    // 生成消息到优惠券使用到期下架队列
                    (new Beanstalk())->put(json_encode(['queue' => 'packetGetExpireChangeStatus',
                        'id' => $redPacket->red_packet_id, 'time' => date('Y-m-d H:i:s')]),
                        (strtotime($param['receive_end_time']) - time()));
                }

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/red_packet/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch('', [

        ]);
    }


    // 编辑红包
    public function edit(Request $request, RedPacketModel $redPacket)
    {
        if ($request::isPost()) {

            try {
                // 获取数据
                $param = $request::post();

                $valid = ($param['type'] > 1) ? 'platform_create' : 'create';

                // 验证
                $check = $redPacket->valid($param, $valid);
                if ($check['code']) return $check;

                // 编辑
                $operation = $redPacket->allowField(true)->isUpdate(true)->save($param);

                if ($operation && $valid == 'create') {
                    // 生成消息到优惠券使用到期下架队列
                    (new Beanstalk())->put(json_encode(['queue' => 'packetGetExpireChangeStatus',
                        'id' => $param['red_packet_id'], 'time' => date('Y-m-d H:i:s')]),
                        (strtotime($param['receive_end_time']) - time()));
                }

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/red_packet/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch('create', [
            'item' => $redPacket::get($request::get('red_packet_id_id')),
        ]);
    }


    // 删除红包
    public function destroy(Request $request, RedPacketModel $redPacket)
    {
        if ($request::isPost()) {
            try {
                // 删除
                $redPacket::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 用户领取列表
     * @param Request $request
     * @param MemberPacketModel $memberPacket
     * @return array|mixed
     */
    public function memberPacket(Request $request, MemberPacketModel $memberPacket)
    {
        try {
            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['red_packet_id', 'neq', 0];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['create_time', 'between time', [$begin, $end]]);
            }
            if (!empty($param['keyword'])) $condition[] = ['title|phone', 'like', '%' . $param['keyword'] . '%'];
            if (isset($param['type']) && $param['type'] != -1) $condition[] = ['a.type', '=', $param['type']];
            if (isset($param['status']) && $param['status'] != -1) $condition[] = ['a.status', '=', $param['status']];
            $data = $memberPacket
                ->alias('a')
                ->join('member member', 'member.member_id = a.member_id', 'left')
                ->where($condition)
                ->where(['red_packet_id' => $param['red_packet_id']])
                ->field('a.*,type as typeName, a.status as statusName, member.phone')
                ->order('member_packet_id', 'desc')
                ->paginate(15, false, ['query' => $param]);

        } catch (\Exception $e) {
//            return $e->getMessage();
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
            'red_packet_id' => $param['red_packet_id']
        ]);
    }
}