<?php
declare(strict_types=1);

namespace app\interfaces\controller\common;

use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 生成订单号管理
 * Class Order
 * @package app\interfaces\controller\common
 */
class Order extends BaseController
{

    /**
     * 获取订单号 - Joy
     * @param RSACrypt $crypt
     * @return array
     */
    public function number(RSACrypt $crypt)
    {
        return $crypt->response([
            'code' => 0,
            'message' => '生成成功',
            'result' => get_order_sn(),
        ], true);
    }
}