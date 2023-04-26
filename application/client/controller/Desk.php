<?php
// 控制台主页
declare(strict_types=1);

namespace app\client\controller;

use think\Controller;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Request;
use app\common\model\Store;
use app\common\model\Member;
use app\common\model\Goods;
use app\common\model\StoreCapital;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\Order;
use think\facade\Session;
use EasyWeChat\Factory;

class Desk extends Controller
{
    /**
     * 系统设置文件路径
     * @var
     */
    private $filename;

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.config';
    }

    /**
     * 首页
     *
     * @param Request     $request
     * @param Goods       $goods
     * @param OrderAttach $orderAttach
     * @param OrderGoods  $orderGoods
     *
     * @return array|mixed
     */
    public function index(Request $request, Goods $goods, OrderAttach $orderAttach, OrderGoods $orderGoods)
    {
        try {
            $param = $request::get();

            $date = [];
            $date1 = [];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($date, ['a.create_time', 'between time', [$begin, $end]]);
            }
            if (array_key_exists('date1', $param) && $param['date1']) {
                list($begin, $end) = explode(' - ', $param['date1']);
                $end = $end . ' 23:59:59';
                array_push($date1, ['a.create_time', 'between time', [$begin, $end]]);
            }

            // TODO 店铺数据开始
            // 自营支付金额
            $data['self_pay_total'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('order order', 'a.order_id = order.order_id', 'left')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id', 'left')
                ->field('IFNULL(sum(case when (order.order_type = 2 and a.status = 0.1) or (order.order_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as groupFailsTotal,
                IFNULL(sum(case when (order_attach.pay_type = 2 and a.status = 0.1) or (order_attach.pay_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as payOnTotal,
                IFNULL(sum(case when (order_attach.pay_type != 2 and a.status = 0.1) or (order_attach.pay_type != 2 and a.status = 6.1) and (order.order_type != 2 and a.status = 0.1) or (order.order_type != 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as normalFailTotal,
                IFNULL(sum(a.subtotal_price),0) as total')
                ->where([
                    ['a.store_id', '=', Session::get('client_store_id')],
                    ['a.create_time', 'between', [date('Y-m-d') .' 0:00:00', date('Y-m-d') .' 23:59:59']]
                ])
                ->cache(true, 60)
                ->find();
            $data['self_pay_total'] = $data['self_pay_total']['total'] - $data['self_pay_total']['groupFailsTotal'] - $data['self_pay_total']['payOnTotal']- $data['self_pay_total']['normalFailTotal'];
            // 支付订单数
            $data['self_indent_count'] = $orderAttach::withTrashed()
                ->alias('order_attach')
                ->join('order order', 'order.order_id = order_attach.order_id', 'left')
                ->field('IFNULL(sum(case when (order.order_type = 2 and order_attach.status = 0) or (order.order_type = 2 and order_attach.status = 6) then 1 else 0 end),0) as groupFailsTotal,
                IFNULL(sum(case when (order_attach.pay_type = 2 and order_attach.status = 0) or (order_attach.pay_type = 2 and order_attach.status = 6) then 1 else 0 end),0) as payOnTotal,
                IFNULL(sum(case when (order_attach.pay_type != 2 and order_attach.status = 0) or (order_attach.pay_type != 2 and order_attach.status = 6) and (order.order_type != 2 and order_attach.status = 0) or (order.order_type != 2 and order_attach.status = 6) then 1 else 0 end),0) as normalFailTotal,
                IFNULL(count(order_attach.order_attach_id),0) as total')
                ->where([
                    ['order_attach.store_id', '=', Session::get('client_store_id')],
                    ['order_attach.create_time', 'between', [date('Y-m-d') .' 0:00:00', date('Y-m-d') .' 23:59:59']]
                ])
                ->cache(true, 60)
                ->find();
            $data['self_indent_count'] = $data['self_indent_count']['total'] - $data['self_indent_count']['groupFailsTotal'] - $data['self_indent_count']['payOnTotal'] - $data['self_indent_count']['normalFailTotal'];
            // 支付买家数
            $data['self_member_pay_count'] = $orderGoods::withTrashed()
                ->where([
                    ['status', '<>', '0.1'],
                    ['status', '<>', '6.1'],
                    ['store_id', '=', Session::get('client_store_id')],
                    ['create_time', 'between', [date('Y-m-d') .' 0:00:00', date('Y-m-d') .' 23:59:59']]
                ])
                ->whereTime('create_time','today')
                ->group('member_id')
                ->cache(true, 60)
                ->count();
            // 待发货订单
            $data['self_not_deliver'] = $orderAttach::withTrashed()
                ->where([
                    ['status', '=', 1],
                    ['store_id', '=', Session::get('client_store_id')]
                ])
                ->cache(true, 60)
                ->count();
            // 退款维权订单
            $data['self_is_refund'] = $orderAttach::withTrashed()
                ->where([
                    ['status', '=', 5],
                    ['store_id', '=', Session::get('client_store_id')]
                ])
                ->cache(true, 60)
                ->count();
            // 出售中
            $data['self_sales_goods_count'] = $goods
                ->where([
                    ['is_putaway', 'eq', 1],
                    ['store_id', '=', Session::get('client_store_id')]
                ])
                ->cache(true, 60)
                ->count();
            // 库存预警
            $data['self_goods_number'] = $goods
                ->where([
                    ['is_putaway', 'eq', 1],
                    ['store_id', '=', Session::get('client_store_id')]
                ])
                ->field('IFNULL(sum(case when goods_number <= warn_number then 1 else 0 end),0) as goods_warn_number')
                ->cache(true, 60)->find();
            // 仓库中
            $data['self_warehouse_goods_count'] = $goods
                ->where([
                    ['is_putaway', 'eq', 0],
                    ['store_id', '=', Session::get('client_store_id')]
                ])
                ->cache(true, 60)->count();
            // 回收站
            $data['self_recycle_goods_count'] = $goods::onlyTrashed()->alias('a')
                ->where([
                    ['forever_del_status', 'eq', 0],
                    ['store_id', '=', Session::get('client_store_id')]
                ])
                ->cache(true, 60)->count();

            // 小程序二维码
            $applet_code_file = 'qr_code/applet/applet.png';

//            if (!is_file(Env::get('root_path') . 'public/' . $applet_code_file)) {
//
//                $app = Factory::miniProgram(config('wechat.')['applet']);
//
//                $response = $app->app_code->getUnlimit('flag', [
//                    'width' => 600,
//                    'page' => 'pages/home/home'
//                ]);
//
//                if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
//                    $response->saveAs('./qr_code/applet', 'applet.png');
//                }
//            }

            // 公众号二维码
//            $app = Factory::officialAccount(config('wechat.')['mobile']);
//            $official_accounts = $app->qrcode->temporary('official_accounts', 30 * 24 * 3600);
//            $url = $app->qrcode->url($official_accounts['ticket']);
        } catch (\Exception $e) {
//            if ($this->request->host() != 'ishop.cc'){
            $this->error($e->getMessage());
//            }
        }

        return $this->fetch('', [
            'item' => $data??[],
            'time' => date('Y-m-d H:i:s'),
            'applet_code_file' => $request::instance()->domain() . '/' . $applet_code_file??'',
            'official_accounts' => $url??'',
        ]);
    }

    /**
     * 商品排行
     * @param Request $request
     * @param OrderGoods $orderGoods
     * @return array
     */
    public function goodsPay(Request $request, OrderGoods $orderGoods)
    {
        try {
            $param = $request::post();

            $date = [];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($date, ['a.create_time', 'between time', [$begin, $end]]);
            }
            // TODO 商品榜单开始
            $data['goods'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('order order', 'a.order_id = order.order_id', 'left')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id', 'left')
                ->join('goods goods', 'a.goods_id = goods.goods_id', 'left')
                ->field('(IFNULL(sum(a.subtotal_price),0) - IFNULL(sum(case when (order.order_type = 2 and a.status = 0.1) or (order.order_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) -
                IFNULL(sum(case when (order_attach.pay_type = 2 and a.status = 0.1) or (order_attach.pay_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) -
                IFNULL(sum(case when (order_attach.pay_type != 2 and a.status = 0.1) or (order_attach.pay_type != 2 and a.status = 6.1) and (order.order_type != 2 and a.status = 0.1) or (order.order_type != 2 and a.status = 6.1) then a.subtotal_price else 0 end),0)) as salesPrice,
                goods.goods_name,goods.file,goods.create_time as goods_create_time')
                ->order('salesPrice', 'desc')
                ->where(['a.store_id' => Session::get('client_store_id')])
                ->where($date)
                ->where([
                    ['order.origin_type', '<>', 5],
                    ['a.status', '<>', '6.1'],
                    ['a.status', '>', '0.1'],
                ])
                ->group('a.goods_id')
                ->cache(true, 60);
            if (!empty($param['date1'])) {
                $data['goods'] = $data['goods']->paginate(5, false, ['query' => $param]);
            } else {
                $data['goods'] = $data['goods']->whereTime('a.create_time','-30 days')->paginate(5, false, ['query' => $param]);
            }

            $html = '';
            if (count($data['goods']) == 0) {
                $html = '<tr class=\'emptyTable\'>
                            <td colspan="100" style="font-size: 25px;">暂无数据</td>
                        </tr>';
            }
            foreach ($data['goods'] as $k => $v) {
                $html .= '<tr data_type="goodsPay">
                            <td>'. ($k+1) .'</td>
                            <td>
                                <div class="goodsInfo">
                                    <img src="'. $v['file'] .'"
                                         onerror=this.src="/template/master/resource/image/common/imageError.png" alt=""
                                         class="goodsPic">
                                    <div class="det">
                                        <div class="goodsName">'. $v['goods_name'] .'</div>
                                        <div class="time">发布时间：'. $v['goods_create_time'] .'</div>
                                    </div>
                                </div>
                            </td>
                            <td>'. $v['salesPrice'] .'</td>
                        </tr>';
            }
            return [
                'code' => 0,
                'data' => $html,
                'page' => [
                    'last_page' => $data['goods']->lastPage(),
                    'current_page' => $data['goods']->currentPage(),
                ],
            ];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 销售量排行
     * @param Request $request
     * @param OrderGoods $orderGoods
     * @return array
     */
    public function goodsCountPay(Request $request, OrderGoods $orderGoods)
    {
        try {
            $param = $request::post();

            $date = [];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($date, ['a.create_time', 'between time', [$begin, $end]]);
            }
            // TODO 商品榜单开始
            $data['goods_count'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('order order', 'a.order_id = order.order_id', 'left')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id', 'left')
                ->join('goods goods', 'a.goods_id = goods.goods_id', 'left')
                ->field('IFNULL(sum(a.quantity),0) as goods_counts,goods.goods_name,goods.file,goods.create_time as goods_create_time')
                ->order('goods_counts', 'desc')
                ->where($date)
                ->where([
                    ['order.origin_type', '<>', 5],
                    ['a.status', '<>', '6.1'],
                    ['a.status', '>', '0.1'],
                ])
                ->where('a.status', '<>', '6.1')
                ->where(['a.store_id' => Session::get('client_store_id')])
                ->group('a.goods_id')
                ->cache(true, 60);
            if (!empty($param['date1'])) {
                $data['goods_count'] = $data['goods_count']->paginate(5, false, ['query' => $param]);
            } else {
                $data['goods_count'] = $data['goods_count']->whereTime('a.create_time','-30 days')->paginate(5, false, ['query' => $param]);
            }

            $html = '';
            if (count($data['goods_count']) == 0) {
                $html = '<tr class=\'emptyTable\'>
                            <td colspan="100" style="font-size: 25px;">暂无数据</td>
                        </tr>';
            } else {
                foreach ($data['goods_count'] as $k => $v) {
                    $html .= '<tr data_type="goodsCount">
                            <td>'. ($k+1) .'</td>
                            <td>
                                <div class="goodsInfo">
                                    <img src="'. $v['file'] .'"
                                         onerror=this.src="/template/master/resource/image/common/imageError.png" alt=""
                                         class="goodsPic">
                                    <div class="det">
                                        <div class="goodsName">'. $v['goods_name'] .'</div>
                                        <div class="time">发布时间：'. $v['goods_create_time'] .'</div>
                                    </div>
                                </div>
                            </td>
                            <td>'. $v['goods_counts'] .'</td>
                        </tr>';
                }
            }

            return [
                'code' => 0,
                'data' => $html,
                'page' => [
                    'last_page' => $data['goods_count']->lastPage(),
                    'current_page' => $data['goods_count']->currentPage(),
                ],
            ];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }
}