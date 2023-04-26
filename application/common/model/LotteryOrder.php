<?php
declare(strict_types=1);

namespace app\common\model;


class LotteryOrder extends BaseModel
{
    protected $pk = 'lottery_order_id';

    /**
     * 图片源路径
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getFileExtraAttr($value, $data)
    {
        return $data['file'];
    }

    /**
     * 关联用户(相对一对多)
     * @return \think\model\relation\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo('Member', 'member_id', 'member_id');
    }

}