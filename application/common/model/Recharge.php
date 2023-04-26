<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 充值模型
 * Class Manage
 * @package app\common\model
 */
class Recharge extends BaseModel
{
    protected $pk = 'recharge_id';

    public function getFileExtraAttr($value, $data)
    {
        return $data['file'];
    }

}