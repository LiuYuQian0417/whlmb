<?php
declare(strict_types = 1);

namespace app\interfaces\controller\common;

use app\common\model\Integral;
use app\common\model\IntegralOrder;
use app\common\model\LotteryOrder;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\interfaces\controller\BaseController;
use app\common\model\Express as ExpressModel;
use app\common\service\Dada as DadaService;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;

/**
 * 公用文件 - 物流、快递
 * Class Express
 * @package app\interfaces\controller\goods
 */
class Express extends BaseController
{
    
    /**
     * 快递（物流）详情
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @param OrderGoods $orderGoods
     * @param Integral $integral
     * @param IntegralOrder $integralOrder
     * @param LotteryOrder $lotteryOrder
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function view(RSACrypt $crypt,
                         OrderAttach $orderAttach,
                         OrderGoods $orderGoods,
                         Integral $integral,
                         IntegralOrder $integralOrder,
                         LotteryOrder $lotteryOrder)
    {
        $param = $crypt->request();
        // 快递100
        $data['customer'] = config('user.common.express.customer');
        $data['param'] = json_encode(['com' => $param['express_value'], 'num' => $param['express_number']]);
        $data["sign"] = strtoupper(md5($data['param'] . config('user.common.express.sign') . $data['customer']));
        $result = express_post($data, 'http://poll.kuaidi100.com/poll/query.do');
        $address = '';
        $goods_view = [];
        if ($param['type'] == 'order') {
            // 地址详情
            $find = $orderAttach
                ->alias('oa')
                ->join('order o', 'oa.order_id = o.order_id')
                ->where([
                    ['oa.order_attach_id', '=', $param['order_id']],
                ])
                ->field('address_province,address_city,address_area,address_details')
                ->find();
            // 地址
            $address = $find['address_province'] . $find['address_city'] . $find['address_area'] . $find['address_details'];
            // 商品详情
            $goods_view = $orderGoods
                ->where([
                    ['order_attach_id', '=', $param['order_id']],
                ])
                ->field('goods_name,file,subtotal_price as price')
                ->find();
            $goods_view['single_price'] = 0;    // 当做积分
        } elseif ($param['type'] == 'integral') {
            // 地址详情
            $find = $integralOrder
                ->where([
                    ['integral_order_id', '=', $param['order_id']],
                ])
                ->field('integral_id,province,city,area,address')
                ->find();
            // 地址
            $address = $find['province'] . $find['city'] . $find['area'] . $find['address'];
            // 商品详情
            $goods_view = $integral
                ->where([
                    ['integral_id', '=', $find['integral_id']],
                ])
                ->field('integral_name as goods_name,file,integral as single_price,price')
                ->find();
        } elseif ($param['type'] == 'draw') {
            // 地址详情
            $find = $lotteryOrder
                ->where([
                    ['lottery_order_id', '=', $param['order_id']],
                ])
                ->field('province,city,area,address,prize_title as goods_name,file')
                ->find();
            // 地址
            $address = $find['province'] . $find['city'] . $find['area'] . $find['address'];
            $goods_view = [
                'goods_name' => $find['goods_name'],
                'file' => $find['file'],
                'single_price' => 0,
                'price' => 0,
            ];
        } elseif ($param['type'] == 'invoice') {
            // 发票
            $find = $orderAttach
                ->alias('oa')
                ->join('order o', 'oa.order_id = o.order_id')
                ->where([
                    ['oa.order_attach_id', '=', $param['order_id']],
                ])
                ->field('address_province,address_city,address_area,address_details')
                ->find();
            // 地址
            $address = $find['address_province'] . $find['address_city'] . $find['address_area'] . $find['address_details'];
            // 商品详情
            $goods_view = $orderGoods
                ->where([
                    ['order_attach_id', '=', $param['order_id']],
                ])
                ->field('goods_name,file,subtotal_price as price')
                ->find();
            $goods_view['single_price'] = 0;    // 当做积分
        }
        //根据快递编号查询快递名称
        if (!empty($param['express_value'])) {
            $result['express_name'] = ExpressModel::where([['code', '=', $param['express_value']]])->value('name', '');
        }
        if (isset($result['result']) && $result['result'] === false) {
            $result['message'] = '暂无物流信息';
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'address' => $address,
            'goods_view' => $goods_view,
        ], true);
    }
    
    /**
     * 达达物流详情
     * @param RSACrypt $crypt
     * @param ExpressModel $express
     * @param OrderAttach $orderAttach
     * @return array|mixed
     * @throws \EasyWeChat\Kernel\Exceptions\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function dadaExpress(RSACrypt $crypt,
                                ExpressModel $express,
                                OrderAttach $orderAttach)
    {
        $param = $crypt->request();
        $express->valid($param, 'dada');
        $attach = $orderAttach
            ->alias('a')
            ->join('dada_merchant dm', 'dm.store_id = a.store_id', 'left')
            ->join('order o', 'o.order_id = a.order_id', 'left')
            ->where([
                ['a.order_attach_id', '=', $param['order_attach_id']],
            ])
            ->field('dm.source_id,a.order_attach_id,a.create_time,a.pay_time,o.address_lng,o.address_lat')
            ->find();
        if (empty($attach['source_id'])) {
            return $crypt->response([
                'code' => -1,
                'message' => '达达信息错误',
            ], true);
        }
        $Data = new DadaService($attach['source_id'], ['order_id' => $attach['order_attach_id']]);
        $dadaExpress = $Data->request('api/order/status/query');
        $dadaExpress['time'] = [
            'create_time' => $attach['create_time'],
            'pay_time' => $attach['pay_time'],
            'address_lng' => $attach['address_lng'],
            'address_lat' => $attach['address_lat'],
        ];
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $dadaExpress,
        ], true);
    }
    
    /**
     * 物流列表
     * @param RSACrypt $crypt
     * @param ExpressModel $express
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function expressList(RSACrypt $crypt,
                                ExpressModel $express)
    {
        // 获取缓存数据
        $expressArrange = Cache::get('expressListData', '');
        if ($expressArrange === '' || empty(unserialize($expressArrange))) {
            // 查询物流列表
            $expressData = $express
                ->where([
                    ['express_id', '>', 0],
                ])
                ->field('delete_time', true)
                ->select()
                ->toArray();
            $expressArrange = [];
            foreach ($expressData as $key => $value) {
                $expressArrange[$value['brand_first_char']]['prefix'] = $value['brand_first_char'];
                $expressArrange[$value['brand_first_char']]['list'][] = $value;
            }
            sort($expressArrange);
            Cache::set('expressListData', serialize($expressArrange));
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => is_array($expressArrange) ? $expressArrange : unserialize($expressArrange),
        ], true);
    }
}