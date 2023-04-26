<?php
declare(strict_types = 1);

namespace app\common\service;


class Inform
{
    /**
     * 关于积分的通知方法
     * @param $type int 状态 0 普通通知 1 物流通知 2 优惠通知
     * @param $jump_state string 跳转点
     * @param $integral 积分数量
     * @param $info array 会员ID 0 为全部接收
     * @param $figure
     * @param int $attach_id
     * @param string $file string 图片地址串
     */
    public function integral_inform($type, $jump_state, $integral, $info, $figure, $attach_id = 0, $file = 'image/integral.png')
    {
        // 推送消息[积分提醒][只含站内信]
        app('app\\interfaces\\behavior\\Push', true)->send([
            'tplKey' => 'integral_remind',
            'openId' => $info['web_open_id']??'',
            'subscribe_time' => $info['subscribe_time']??'',
            'microId' => $info['micro_open_id']??'',
            'phone' => $info['phone']??'',
            'data' => [$figure, $integral],
            'inside_data' => [
                'member_id' => $info['member_id']??'',
                'type' => $type,
                'attach_id' => $attach_id,
                'jump_state' => $jump_state,
                'file' => $file,
            ],
        ], 3);
    }
    
    /**
     * 关于优惠券的通知方法
     * @param $type int 状态 0 普通通知 1 物流通知 2 优惠通知
     * @param $jump_state string 跳转状态
     * @param $info
     * @param $figure
     * @param int $attach_id
     * @param string $file string 图片地址串
     */
    public function coupon_inform($type, $jump_state, $info, $figure, $attach_id = 0, $file = 'image/coupon.png')
    {
        // 推送消息[优惠券提醒][只含站内信]
        app('app\\interfaces\\behavior\\Push', true)->send([
            'tplKey' => 'coupon_remind',
            'openId' => $info['web_open_id']??'',
            'subscribe_time' => $info['subscribe_time']??'',
            'microId' => $info['micro_open_id']??'',
            'phone' => $info['phone']??'',
            'data' => [$figure],
            'inside_data' => [
                'member_id' => $info['member_id']??'',
                'type' => $type,
                'attach_id' => $attach_id,
                'jump_state' => $jump_state,
                'file' => $file,
            ],
        ], 3);
    }
    
    /**
     * 关于红包的通知方法
     * @param $type
     * @param $jump_state
     * @param $face_value
     * @param $info
     * @param $figure
     * @param int $attach_id
     * @param string $file
     * @return void
     */
    public function packet_inform($type, $jump_state, $face_value, $info, $figure, $attach_id = 0, $file = 'image/packet.png')
    {
        // 推送消息[红包提醒][只含站内信]
        app('app\\interfaces\\behavior\\Push', true)->send([
            'tplKey' => 'packet_remind',
            'openId' => $info['web_open_id'],
            'subscribe_time' => $info['subscribe_time'],
            'microId' => $info['micro_open_id'],
            'phone' => $info['phone'],
            'data' => [$figure, $face_value],
            'inside_data' => [
                'member_id' => $info['member_id'],
                'type' => $type,
                'attach_id' => $attach_id,
                'jump_state' => $jump_state,
                'file' => $file,
            ],
        ], 3);
    }
}