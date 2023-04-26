<?php
declare(strict_types=1);

namespace app\interfaces\controller\pay;

use app\common\model\OrderAttach;
use mrmiao\encryption\RSACrypt;

class Info
{
    /**
     * 获取支付后的活动信息
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPayInfo(RSACrypt $crypt, OrderAttach $orderAttach)
    {
        $param = $crypt->request();
        $where = [
            ['order_number|order_attach_number', '=', $param['out_trade_no']],
        ];
        $info = $orderAttach
            ->where($where)
            ->field('group_activity_attach_id,cut_activity_id')
            ->find();
        if (is_null($info)) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单不存在',
            ]);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $info,
        ], true);
    }
}