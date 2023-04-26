<?php
declare(strict_types = 1);

namespace app\interfaces\controller\pay;

use app\common\model\Order;
use app\common\service\Lock;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;

/**
 * 余额支付
 * Class Balance
 * @package app\interfaces\controller\pay
 */
class Balance extends BaseController
{
    /**
     * 执行支付
     * @param RSACrypt $crypt
     * @param Order $order
     * @param Lock $lock
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exec(RSACrypt $crypt, Order $order,Lock $lock)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $order->valid($args, 'balanceExec');
        $orderActService = app('app\\common\\service\\OrderAct');
        $args['pay_channel'] = 3;   // 余额支付
        $args['trade_no'] = null;
        $lr = $lock->lock([$args['member_id']], 3000);
        if ($lr) {
            Db::startTrans();
            $ret = $orderActService->execPayOrder($args);
            if ($ret['code']) {
                Db::rollback();
                return $crypt->response($ret, true);
            }
            Db::commit();
            return $crypt->response([
                'code' => 0,
                'message' => '支付成功',
                'group_activity_attach_id' => $ret['groupActivityAttachId'],
                'cut_activity_id' => $ret['cutActivityId'],
            ], true);
        }
        $lock->unlock($lr);
        return $crypt->response([
            'code' => -1,
            'message' => '不可重复提交',
        ], true);
    }
}