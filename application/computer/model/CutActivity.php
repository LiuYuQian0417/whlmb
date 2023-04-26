<?php
declare(strict_types=1);

namespace app\computer\model;
use mrmiao\encryption\RSACrypt;
use app\common\model\CutActivity as CutActivityModel;

/**
 * 砍价活动主表
 * Class CutActivity
 * @package app\common\model
 */
class CutActivity extends CutActivityModel
{
    /**
     * pc 支付
     */
    public function getOrderDataAttr($value, $data)
    {

        $crypt = new RSACrypt();
        return urlsafe_b64encode(
            $crypt->singleEnc(
                [
                    'order_number' => $data['order_number'],
                    'order_id'     => $data['order_id'],
                    'total_price'  => $data['present_price'],
                    'attach'       => "pay|3",
                ]
            )
        );
    }
}