<?php
declare(strict_types=1);

namespace app\common\model;

class RedPacket extends BaseModel
{
    protected $pk = 'red_packet_id';

    // 红包分类
    public function getTypNameTextAttr($value, $data)
    {
        $types = [0 => '店铺红包', 1 => '平台红包', 2 => '邀请红包', 3 => '首次消费红包'];
        return $types[$data['type']];
    }

    // 优惠券金额获取器
    public function getRedPacketPriceTextAttr($value, $data)
    {
        $actualPrice = '';
        switch ($data['type']) {
            case 0;
                $actualPrice = $data['actual_price'];
                break;
            case 1;
                $actualPrice = $data['actual_price'];
                break;
            case 2;
                $actualPrice = $data['min_actual_price'] . '-' . $data['max_actual_price'];
                break;
            case 3;
                $actualPrice = $data['actual_price'];
                break;
            default;
        }
        return $actualPrice;
    }

    // 优惠券总量获取器
    public function getNumNameTextAttr($value, $data)
    {
        switch ($data['type']) {
            case 0;
                $numName = $data['exchange_num'] . '/' . $data['total_num'];
                break;
            case 1;
                $numName = $data['exchange_num'] . '/' . $data['total_num'];
                break;
            default;
                $numName = '无限制';
        }
        return $numName;
    }

    // 使用时间获取器
    public function getTimeSlotTextAttr($value, $data)
    {
        switch ($data['type']) {
            case 0;
                $timeSlot = $data['start_time'] . '/' . $data['end_time'];
                break;
            case 1;
                $timeSlot = $data['start_time'] . '/' . $data['end_time'];
                break;
            default;
                $timeSlot = '领取后有效期' . $data['extend_time'] . '小时';
        }
        return $timeSlot;
    }

    /**
     * 领取时间获取器
     * @param $value
     * @param $data
     * @return string
     */
    public function getReceiveTimeTextAttr($value, $data)
    {
        switch ($data['type']) {
            case 0;
                $receiveTime = $data['receive_start_time'] . '/' . $data['receive_end_time'];
                break;
            case 1;
                $receiveTime = $data['receive_start_time'] . '/' . $data['receive_end_time'];
                break;
            default;
                $receiveTime = '无限制';
        }
        return $receiveTime;
    }
}
