<?php
declare(strict_types=1);

namespace app\computer\model;

use app\common\model\Coupon as CouponModel;
use think\facade\Session;

class Coupon extends CouponModel
{
    /**
     * 会员领取状态
     * @param $value
     * @param $data
     * @return array|int|mixed|string
     */
    public function getMemberStateAttr($value, $data)
    {
        $number = (new MemberCoupon())
            ->where([
                        ['member_id', '=', Session::get('member_info')['member_id'] ?? ''],
                        ['coupon_id', '=', $data['coupon_id']]
                    ])
            ->count();

        return ($data['limit_num'] != 0 && $number >= $data['limit_num']) ? 1 : 0;
    }


    public function getRadioWidthAttr()
    {
        return sprintf("%.2f",($this['total_num'] - $this['exchange_num'])/$this['total_num'])*100 == 0 ? 1 : sprintf("%.2f",($this['total_num'] - $this['exchange_num'])/$this['total_num'])*100 ;
    }
}