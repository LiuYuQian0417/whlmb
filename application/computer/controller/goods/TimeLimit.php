<?php
declare(strict_types=1);

namespace app\computer\controller\goods;

use app\computer\model\Limit;
use app\computer\model\LimitInterval;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 限时抢购
 * Class Search
 * @package app\computer\controller\goods
 */
class TimeLimit extends BaseController
{

    /**
     * 抢购分类
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Limit $limit
     * @param LimitInterval $limitInterval
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, RSACrypt $crypt, Limit $limit, LimitInterval $limitInterval)
    {
        // 初始数据
        $result = $limitInterval
            ->where(
                [
                    ['end_time', '>', date('H')],
                ]
            )
            ->field('limit_interval_id,interval_name,start_time,end_time')
            // ->append(['status'])
            ->limit(2)
            ->select()
            ->toArray();

        $param = $request::instance()->param();


        if (empty($param['interval_id']))
        {
            $param['interval_id'] = $result[0]['limit_interval_id'];
        }

        // 验证
        $check = $limit->valid($param, 'index');
        if ($check['code'])
        {
            return $crypt->response($check);
        }

        // 初始数据
        $data = $limit->alias('limit')
            ->join('goods goods', 'limit.goods_id = goods.goods_id')
            ->join('store s', 's.store_id = goods.store_id and '.self::store_auth_sql('s'))
            ->where(self::goods_where([
                                          ['goods.is_limit', '=', 1],
                                          ['limit.status', '=', 1],
                                          ['is_putaway', '=', 1],
                                          ['up_shelf_time', '<=', date('Y-m-d')],
                                          ['down_shelf_time', '>=', date('Y-m-d')],
                                          ['exchange_num', '>', 0],
                                      ],'goods')

            )
            ->whereRaw('find_in_set(' . $param['interval_id'] . ',limit.interval_id)')
            ->field('goods.goods_id,goods_name,file,shop_price,time_limit_price,available_sale,exchange_num')
            ->paginate(10, FALSE, ['query' => $param]);

        // 距离结束时间 or 距离开始时间
        $find = $limitInterval
            ->where('limit_interval_id', $param['interval_id'])
            ->field('start_time,end_time')
            ->find();

        // 如果在时间范围内
        if ($find['start_time'] <= date('H') && $find['end_time'] > date('H'))
        {
            $when['state'] = 1;
            $when['time'] = strtotime(date('Y-m-d ' . $find['end_time'] . ':00:00')) - time();
        } else
        {
            $when['state'] = 2;
            $when['time'] = strtotime(date('Y-m-d ' . $find['start_time'] . ':00:00')) - time();
        }

        return $this->fetch('', ['code' => 0, 'result' => $result, 'data' => $data, 'when' => $when]);


    }

}