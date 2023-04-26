<?php
declare(strict_types=1);

namespace app\interfaces\behavior;


use app\common\model\MemberPacket;
use app\common\model\RedPacket;
use think\Db;

class PtPacket
{
    /**
     * 首次消费赠送红包
     * @param $args
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function firstConsumption($args)
    {
        $packet = new RedPacket();
        $arr = $packet
            ->where([
                ['type', '=', 3],   // 3被邀会员首次消费红包
                ['status', '=', 1], // 已上架
            ])
            ->where('total_num', ['=', 0], ['>', 'exchange_num'], 'or')
            ->where('receive_start_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d H:i:s')], 'or')
            ->where('receive_end_time', ['exp', Db::raw('is null')], ['<=', date('Y-m-d H:i:s')], 'or')
            ->field('red_packet_id,title,actual_price,min_actual_price,max_actual_price,exchange_num,total_num,genre,
            start_time,end_time,extend_time')
            ->find();
        if (!is_null($arr)) {
            // 赠送优惠券
            $inc = [
                'member_id' => $args['member_id'],
                'coupon_id' => $arr['red_packet_id'],
                'title' => $arr['title'],
                'red_packet_id' => $arr['red_packet_id'],
                'type' => 3,
                'actual_price' => $arr['genre'] ?
                    mt_rand($arr['min_actual_price'], $arr['max_actual_price']) :
                    $arr['actual_price'],
                'full_subtraction_price' => 0,
                'status' => 0,
                'create_time' => date('Y-m-d H:i:s'),
                'start_time' => is_null($arr['extend_time']) ? $arr['start_time'] : date('Y-m-d H:i:s'),
                'end_time' => is_null($arr['extend_time']) ? $arr['end_time'] :
                    date('Y-m-d H:i:s', strtotime('+' . $arr['extend_time'] . ' hour')),
            ];
            if ($arr['total_num'] > 0) {
                // 增加领取数量
                $arr->exchange_num += 1;
                $arr->save();
            }
            $memberPacket = new MemberPacket();
            $memberPacket->allowField(true)->isUpdate(false)->save($inc);
            // 到期更改状态为已过期和到期提醒
            $time = strtotime(date('Y-m-d H:i:s', strtotime('+' . $arr['extend_time'] . ' hour'))) - time();
            if ($time > 0) {
                $msg = json_encode([
                    'queue' => 'packetExpireChangeStatus',
                    'id' => $memberPacket->member_packet_id,
                    'time' => date('Y-m-d H:i:s'),
                ]);
                (new \app\common\service\Beanstalk())->put($msg, $time);
                $new_time = ($time - 10800) <= 0 ? 0 : ($time - 10800);
                $new_msg = json_encode(['queue' => 'packetExpireRemind',
                    'id' => $memberPacket->member_packet_id, 'uid' => $args['member_id'],
                    'time' => date('Y-m-d H:i:s')]);
                (new \app\common\service\Beanstalk())->put($new_msg, $new_time);
            }
        }
    }
}