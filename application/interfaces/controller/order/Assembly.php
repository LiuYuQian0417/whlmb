<?php
declare(strict_types=1);

namespace app\interfaces\controller\order;

use app\common\model\Take;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

class Assembly extends BaseController
{
    /**
     * 店铺自提点
     * @param RSACrypt $crypt
     * @param Take $take
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function takeList(RSACrypt $crypt,
                             Take $take)
    {
        $args = $crypt->request();
        $take->valid($args, 'takeList');
        $text = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
        $order = 'find_in_set(' . $text[date('w')] . '`t.week`)';
        $takeData = $take
            ->alias('t')
            ->where([
                ['t.store_id', '=', $args['store_id']],
                ['t.city', '=', $args['city_name']],
            ])
            ->field('t.take_id,t.store_id,t.take_name,t.contacts_name,t.contacts_phone,t.start_hours,t.end_hours,
                round(str_distance(point(lng,lat),point(' . $args['lng'] . ',' . $args['lat'] . '))*111.195,1) as distance,t.address')
            ->order($order)
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $takeData,
        ], true);
    }
}