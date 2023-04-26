<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 分销商
 * Class Distribution
 * @package app\common\model
 */
class DistributionWithdraw extends BaseModel
{
    protected $pk = 'distribution_withdraw_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
            $e->withdraw_number = get_order_sn();
            $e->status = 0;  //待审核
        });
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
    }
}