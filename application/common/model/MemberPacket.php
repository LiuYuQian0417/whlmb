<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 会员红包模型
 * Class MemberPacket
 * @package app\common\model
 */
class MemberPacket extends BaseModel
{
    protected $pk = 'member_packet_id';

    // 红包分类
    public function getTypNameAttr($value, $data)
    {
        $types = [0 => '店铺红包', 1 => '平台红包', 2 => '邀请红包', 3 => '首次消费红包'];
        return $types[$data['type']];
    }

    // 红包使用状态
    public function getStatusNameAttr($value, $data)
    {
        $types = [0 => '未使用', 1 => '已使用', 2 => '已过期'];
        return $types[$data['status']];
    }
}