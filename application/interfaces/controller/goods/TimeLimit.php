<?php
declare(strict_types=1);

namespace app\interfaces\controller\goods;

use app\common\model\Limit;
use app\common\model\LimitInterval;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 限时抢购 - Joy
 * Class Search
 * @package app\interfaces\controller\goods
 */
class TimeLimit extends BaseController
{

    /**
     * 抢购分类
     * @param RSACrypt $crypt
     * @param LimitInterval $limitInterval
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function classify(RSACrypt $crypt,
                             LimitInterval $limitInterval)
    {
        $result = $limitInterval
            ->where([
                ['end_time', '>', date('H')],
            ])
            ->field('limit_interval_id,interval_name,start_time,end_time')
            ->limit(2)
            ->select()
            ->toArray();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);

    }

    /**
     * 抢购列表
     * @param RSACrypt $crypt
     * @param Limit $limit
     * @param LimitInterval $limitInterval
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Limit $limit,
                          LimitInterval $limitInterval)
    {
        $param = $crypt->request();
        $limit->valid($param, 'index');
        $result = $limit
            ->alias('l')
            ->join('goods g', 'l.goods_id = g.goods_id')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->where([
                ['g.is_limit', '=', 1],
                ['l.status', '=', 1],
                ['is_putaway', '=', 1],
                ['up_shelf_time', '<=', date('Y-m-d')],
                ['down_shelf_time', '>=', date('Y-m-d')],
                ['exchange_num', '>', 0],
            ])
            ->whereRaw('find_in_set(' . $param['interval_id'] . ',l.interval_id)')
            ->field('g.goods_id,goods_name,file,shop_price,time_limit_price,
            available_sale,exchange_num')
            ->paginate(10, false);
        // 距离结束时间 or 距离开始时间
        $find = $limitInterval
            ->where([
                ['limit_interval_id', '=', $param['interval_id']],
            ])
            ->field('start_time,end_time')
            ->find();
        // 如果在时间范围内
        if ($find['start_time'] <= date('H') && $find['end_time'] > date('H')) {
            $when['state'] = 1;
            $when['time'] = strtotime(date('Y-m-d ' . $find['end_time'] . ':00:00')) - time();
        } else {
            $when['state'] = 2;
            $when['time'] = strtotime(date('Y-m-d ' . $find['start_time'] . ':00:00')) - time();
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'when' => $when,
        ], true);
    }
}