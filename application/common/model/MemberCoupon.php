<?php
declare(strict_types = 1);

namespace app\common\model;

/**
 * 会员优惠券模型
 * Class MemberCoupon
 * @package app\common\model
 */
class MemberCoupon extends BaseModel
{
    protected $pk = 'member_coupon_id';
    
    public static function init()
    {
        parent::init();
        self::afterInsert(function ($e) {
            // 如果成功 递减数量
            (new Coupon())
                ->where([
                    ['coupon_id', '=', $e->coupon_id],
                    ['is_gift','=',0],
                ])
                ->setDec('exchange_num');
        });
    }

}