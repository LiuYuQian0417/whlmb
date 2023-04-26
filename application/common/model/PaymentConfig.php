<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 支付方式模型
 * Class Manage
 * @package app\common\model
 */
class PaymentConfig extends BaseModel
{
    protected $pk = 'payment_config_id';

    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
    }

}