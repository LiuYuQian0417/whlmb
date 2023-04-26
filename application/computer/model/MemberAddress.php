<?php
declare(strict_types=1);

namespace app\computer\model;


use \app\common\model\MemberAddress as MemberAddressModel;

/**
 * 会员收货地址模型
 * Class Manage
 * @package app\common\model
 */
class MemberAddress extends MemberAddressModel
{

    function getStreetIdAttr($value,$data){
        if (isset($data['street']) && $data['street']) {
            $value = (new Area)
                ->where([
                            ['area_name', '=', $data['street']],
                        ])
                ->value('area_id');
        }
        return $value;
    }

}