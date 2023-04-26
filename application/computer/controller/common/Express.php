<?php
declare(strict_types=1);

namespace app\computer\controller\common;

use app\computer\model\Integral;
use app\computer\model\IntegralOrder;
use app\computer\model\LotteryOrder;
use app\computer\model\OrderAttach;
use app\computer\model\OrderGoods;
use app\computer\controller\BaseController;
use app\computer\model\Express as ExpressModel;
use app\common\service\Dada as DadaService;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Request;

/**
 * 公用文件 - 物流、快递
 * Class Express
 * @package app\computer\controller\goods
 */
class Express extends BaseController
{

    /**
     * 快递（物流）详情
     * @param Request $request
     * @param RSACrypt $crypt
     * @param ExpressModel $express
     * @param OrderAttach $orderAttach
     * @param OrderGoods $orderGoods
     * @param Integral $integral
     * @param IntegralOrder $integralOrder
     * @param LotteryOrder $lotteryOrder
     * @return mixed
     */
    public function view(Request $request, RSACrypt $crypt, ExpressModel $express, OrderAttach $orderAttach, OrderGoods $orderGoods, Integral $integral, IntegralOrder $integralOrder, LotteryOrder $lotteryOrder)
    {

        if ($request::isPost()) {
            try {
                $param = $crypt->request();
                $check = $express->valid($param, 'view');
                if ($check['code']) return $crypt->response($check);
                $config=config('user.')['express'];
                // 快递100
                $data['customer'] = $config['customer'];
                $data['param'] = json_encode(['com' => $param['express_value'], 'num' => $param['express_number']]);
                $data["sign"] = strtoupper(md5($data['param'] . $config['sign'] . $data['customer']));
                $result = express_post($data, 'http://poll.kuaidi100.com/poll/query.do');
                $address = '';
                $goods_view = [];
                if ($param['type'] == 'order') {
                    // 地址详情
                    $find = $orderAttach->alias('order_attach')
                        ->join('order order', 'order_attach.order_id = order.order_id')
                        ->where('order_attach_id', $param['order_id'])
                        ->field('address_province,address_city,address_area,address_details')
                        ->find();

                    // 地址
                    $address = $find['address_province'] . $find['address_city'] . $find['address_area'] . $find['address_details'];

                    // 商品详情
                    $goods_view = $orderGoods
                        ->where('order_attach_id', $param['order_id'])
                        ->field('goods_name,file,single_price')
                        ->find();

                } else if ($param['type'] == 'integral') {

                    // 地址详情
                    $find = $integralOrder
                        ->where('integral_order_id', $param['order_id'])
                        ->field('integral_id,province,city,area,address')
                        ->find();

                    // 地址
                    $address = $find['province'] . $find['city'] . $find['area'] . $find['address'];

                    // 商品详情
                    $goods_view = $integral
                        ->where('integral_id', $find['integral_id'])
                        ->field('integral_name as goods_name,file,integral as single_price,price')
                        ->find();

                } else if ($param['type'] == 'draw') {

                    // 地址详情
                    $find = $lotteryOrder->where('lottery_order_id', $param['order_id'])->field('province,city,area,address,prize_title as goods_name,file')->find();
                    // 地址
                    $address = $find['province'] . $find['city'] . $find['area'] . $find['address'];
                    $goods_view = [
                        'goods_name' => $find['goods_name'],
                        'file' => $find['file'],
                    ];

                }
                return $crypt->response(['code' => 0, 'result' => $result, 'address' => $address, 'goods_view' => $goods_view]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }




    /*****废弃****/
//    public function expressList(RSACrypt $crypt, ExpressModel $express)
//    {
//        try {
//            // 获取缓存数据
//            $expressArrange = Cache::get('expressListData', '');
//            if ($expressArrange === '' || empty(unserialize($expressArrange))) {
//                // 查询物流列表
//                $expressData = $express
//                    ->where([['express_id', '>', 0]])
//                    ->field('delete_time', true)
//                    ->select()
//                    ->toArray();
//                $expressArrange = [];
//                foreach ($expressData as $key => $value) {
//                    $expressArrange[$value['brand_first_char']]['prefix'] = $value['brand_first_char'];
//                    $expressArrange[$value['brand_first_char']]['list'][] = $value;
//                }
//                sort($expressArrange);
//                Cache::set('expressListData', serialize($expressArrange));
//            }
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0],
//                'result' => is_array($expressArrange) ? $expressArrange : unserialize($expressArrange)], true);
//        } catch (\Exception $e) {
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//        }
//    }
//
//    /**
//     * 达达物流详情
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param ExpressModel $express
//     * @param OrderAttach $orderAttach
//     * @return array|mixed
//     */
//    public function dadaExpress(Request $request, RSACrypt $crypt, ExpressModel $express, OrderAttach $orderAttach)
//    {
//
//        if ($request::isPost()) {
//            try {
//
//                $param = $crypt->request();
//
//                $check = $express->valid($param, 'dada');
//                if ($check['code']) return $crypt->response($check);
//
//                $attach = $orderAttach
//                    ->alias('a')
//                    ->join('dada_merchant dada_merchant', 'dada_merchant.store_id = a.store_id', 'left')
//                    ->join('order order', 'order.order_id = a.order_id', 'left')
//                    ->where(['a.order_attach_id' => $param['order_attach_id']])
//                    ->field('dada_merchant.source_id,a.order_attach_id,a.create_time,a.pay_time,order.address_lng,order.address_lat')
//                    ->find();
//
//                if (empty($attach['source_id'])) return ['code' => -100, 'message' => '达达信息错误'];
//
//                $Data = new DadaService($attach['source_id'], ['order_id' => $attach['order_attach_id']]);
//                $dadaExpress = $Data->request('api/order/status/query');
//
//                $dadaExpress['time'] = [
//                    'create_time' => $attach['create_time'],
//                    'pay_time'    => $attach['pay_time'],
//                    'address_lng'    => $attach['address_lng'],
//                    'address_lat'    => $attach['address_lat'],
//                ];
//                return $crypt->response(['code' => 0, 'result' => $dadaExpress]);
//
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//            }
//        }
//    }
}