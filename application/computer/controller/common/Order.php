<?php
declare(strict_types=1);

namespace app\computer\controller\common;

use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 生成订单号管理
 * Class Order
 * @package app\computer\controller\common
 */
class Order extends BaseController
{

    /**
     * 生成订单号 - Joy - 废弃
     * @param Request $request
     * @param RSACrypt $crypt
     * @return array
     */
//    public function number(Request $request, RSACrypt $crypt)
//    {
//
//        if ($request::isPost()) {
//            try {
//
//                return $crypt->response(['code' => 0, 'result' => get_order_sn()]);
//
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//            }
//        }
//    }
}