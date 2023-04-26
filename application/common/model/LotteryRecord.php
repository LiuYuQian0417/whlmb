<?php
declare(strict_types=1);

namespace app\common\model;

class LotteryRecord extends BaseModel
{
    protected $pk = 'lottery_record_id';

    /**
     * 抽奖记录和会员一对一
     */
    public function member()
    {
        return $this->hasOne('Member', 'member_id', 'member_id');
    }

}