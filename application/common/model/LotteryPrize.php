<?php
declare(strict_types=1);

namespace app\common\model;

class LotteryPrize extends BaseModel
{
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
}