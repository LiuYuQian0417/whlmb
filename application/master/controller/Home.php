<?php

namespace app\master\controller;

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
use EasyWeChat\Factory;

class Home extends Controller
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
     * @param Request $request
     * @param Store $store
     * @param Member $member
     * @param Goods $goods
     * @param StoreCapital $storeCapital
     * @param OrderAttach $orderAttach
     * @param OrderGoods $orderGoods
     * @param Order $order
     * @return array|mixed
     */
    public function index(Request $request, Store $store, Member $member, Goods $goods, StoreCapital $storeCapital,
                          OrderAttach $orderAttach, OrderGoods $orderGoods, Order $order)
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

            // 小程序二维码
            $applet_code_file = 'qr_code/applet/applet.png';
//
//            if (!is_file(Env::get('root_path') . 'public/' . $applet_code_file)) {
//
//                $app = Factory::miniProgram(config('wechat.')['applet']);
//
//                $response = $app->app_code->getUnlimit('flag', [
//                    'width' => 600,
//                    'page' => 'pages/home/home',
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

            // todo 访客数浏览量
            // TODO 头部开始
            // 店铺数
            $data['store'] = $store::withTrashed()->where('status', 'in', '1,3,4')->cache(TRUE, 60)->count();
            // 用户数
            $data['member'] = $member->cache(TRUE, 60)->count();
            // TODO 平台数据开始
            // 平台商品待审核
            $data['goods_review'] = $goods->where([['review_status', '=', 2]])->cache(TRUE, 60)->count();
            // 店铺待审核
            $data['store_status'] = $store->where([['status', '=', 2]])->cache(TRUE, 60)->count();
            // 提现待审核
            $data['store_withdraw'] = $storeCapital->where([['status', '=', 1.1]])->cache(TRUE, 60)->count();
            // 平台支付的金额（0点至当前时间内平台所有付款订单金额总和(拼团在成团时计入付款金额;货到付款在发货时计入付款金额，不剔除退款金额)）
            // 总额 -(拼团未支付+拼团取消)-(货到付款未支付+货到付款取消）- 正常失败额
            $data['pay_total'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('order order', 'a.order_id = order.order_id', 'left')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id', 'left')
                ->field('IFNULL(sum(case when (order.order_type = 2 and a.status = 0.1) or (order.order_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as groupFailsTotal,
                IFNULL(sum(case when (order_attach.pay_type = 2 and a.status = 0.1) or (order_attach.pay_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as payOnTotal,
                IFNULL(sum(case when (order_attach.pay_type != 2 and a.status = 0.1) or (order_attach.pay_type != 2 and a.status = 6.1) and (order.order_type != 2 and a.status = 0.1) or (order.order_type != 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as normalFailTotal,
                IFNULL(sum(a.subtotal_price),0) as total')
                ->whereTime('a.create_time', 'today')
                ->cache(TRUE, 60)
                ->find();
            $data['pay_total'] = $data['pay_total']['total'] - $data['pay_total']['groupFailsTotal'] - $data['pay_total']['payOnTotal'] - $data['pay_total']['normalFailTotal'];
            // 平台支付订单数 0点至当前时间内平台成功付款订单数，订单号唯一。场景1，拼团在成团时计入支付订单数。场景2，货到付款在发货时计入支付订单数
            $data['indent_count'] = $orderAttach::withTrashed()
                ->alias('order_attach')
                ->join('order order', 'order.order_id = order_attach.order_id', 'left')
                ->field('IFNULL(sum(case when (order.order_type = 2 and order_attach.status = 0) or (order.order_type = 2 and order_attach.status = 6) then 1 else 0 end),0) as groupFailsTotal,
                IFNULL(sum(case when (order_attach.pay_type = 2 and order_attach.status = 0) or (order_attach.pay_type = 2 and order_attach.status = 6) then 1 else 0 end),0) as payOnTotal,
                IFNULL(sum(case when (order_attach.pay_type != 2 and order_attach.status = 0) or (order_attach.pay_type != 2 and order_attach.status = 6) and (order.order_type != 2 and order_attach.status = 0) or (order.order_type != 2 and order_attach.status = 6) then 1 else 0 end),0) as normalFailTotal,
                IFNULL(count(order_attach.order_attach_id),0) as total')
                ->whereTime('order_attach.create_time', 'today')
                ->cache(TRUE, 60)
                ->find();

            $data['indent_count'] = $data['indent_count']['total'] - $data['indent_count']['groupFailsTotal'] - $data['indent_count']['payOnTotal'] - $data['indent_count']['normalFailTotal'];
            // 平台支付买家数 0点至当前时间内平台付款成功的用户数，单个用户多次付款只记一次
            $data['member_pay_count'] = $orderGoods::withTrashed()
                ->where([
                    ['status', '<>', '0.1'],
                    ['status', '<>', '6.1'],
                ])
                ->whereTime('create_time', 'today')
                ->group('member_id')
                ->cache(TRUE, 60)
                ->count();
            // 平台待发货订单 截止到当前时间内的平台未发货订单数
            $data['not_deliver'] = $orderAttach::withTrashed()
                ->where([
                    ['status', '=', 1],
                ])
                ->cache(TRUE, 60)
                ->count();
            // 平台退款维权订单 截止到当前时间内的平台退款维权订单数
            $data['is_refund'] = $orderAttach::withTrashed()
                ->where([
                    ['status', '=', 5],
                ])
                ->cache(TRUE, 60)
                ->count();
            // 平台出售中 截止到当前时间内的平台出售中的商品数
            $data['sales_goods_count'] = $goods->where([['is_putaway', 'eq', 1]])->cache(TRUE, 60)->count();
            // 平台库存预警 截止到当前时间内平台库存预警的商品数
            $data['goods_number'] = $goods->where([['is_putaway', 'eq', 1]])
                ->field('IFNULL(sum(case when goods_number <= warn_number then 1 else 0 end),0) as goods_warn_number')
                ->cache(TRUE, 60)->find();
            // 平台仓库中 截止到当前时间内的平台仓库中的商品数
            $data['warehouse_goods_count'] = $goods->where([['is_putaway', 'eq', 0]])->cache(TRUE, 60)->count();
            // 平台回收站 截止到当前时间内的平台回收站的商品数
            $data['recycle_goods_count'] = $goods::onlyTrashed()->where('forever_del_status', 0)->cache(TRUE, 60)->count();

            // TODO 自营数据开始
            // 自营支付金额
            $data['self_pay_total'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('order order', 'a.order_id = order.order_id', 'left')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id', 'left')
                ->join('store store', 'a.store_id = store.store_id', 'left')
                ->field('IFNULL(sum(case when (order.order_type = 2 and a.status = 0.1) or (order.order_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as groupFailsTotal,
                IFNULL(sum(case when (order_attach.pay_type = 2 and a.status = 0.1) or (order_attach.pay_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as payOnTotal,
                IFNULL(sum(case when (order_attach.pay_type != 2 and a.status = 0.1) or (order_attach.pay_type != 2 and a.status = 6.1) and (order.order_type != 2 and a.status = 0.1) or (order.order_type != 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) as normalFailTotal,
                IFNULL(sum(a.subtotal_price),0) as total')
                ->where('store.shop', 0)
                ->whereTime('a.create_time', 'today')
                ->cache(TRUE, 60)
                ->find();
            $data['self_pay_total'] = $data['self_pay_total']['total'] - $data['self_pay_total']['groupFailsTotal'] - $data['self_pay_total']['payOnTotal'] - $data['self_pay_total']['normalFailTotal'];
            // 自营支付订单数
            $data['self_indent_count'] = $orderAttach::withTrashed()
                ->alias('order_attach')
                ->join('order order', 'order.order_id = order_attach.order_id', 'left')
                ->join('store store', 'order_attach.store_id = store.store_id', 'left')
                ->field('IFNULL(sum(case when (order.order_type = 2 and order_attach.status = 0) or (order.order_type = 2 and order_attach.status = 6) then 1 else 0 end),0) as groupFailsTotal,
                IFNULL(sum(case when (order_attach.pay_type = 2 and order_attach.status = 0) or (order_attach.pay_type = 2 and order_attach.status = 6) then 1 else 0 end),0) as payOnTotal,
                IFNULL(sum(case when (order_attach.pay_type != 2 and order_attach.status = 0) or (order_attach.pay_type != 2 and order_attach.status = 6) and (order.order_type != 2 and order_attach.status = 0) or (order.order_type != 2 and order_attach.status = 6) then 1 else 0 end),0) as normalFailTotal,
                IFNULL(count(order_attach.order_attach_id),0) as total')
                ->where('store.shop', 0)
                ->whereTime('order_attach.create_time', 'today')
                ->cache(TRUE, 60)
                ->find();
            $data['self_indent_count'] = $data['self_indent_count']['total'] - $data['self_indent_count']['groupFailsTotal'] - $data['self_indent_count']['payOnTotal'] - $data['self_indent_count']['normalFailTotal'];
            // 自营支付买家数
            $data['self_member_pay_count'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('store store', 'a.store_id = store.store_id', 'left')
                ->where([
                    ['a.status', '<>', '0.1'],
                    ['a.status', '<>', '6.1'],
                    ['store.shop', '=', '0'],
                ])
                ->whereTime('a.create_time', 'today')
                ->group('a.member_id')
                ->cache(TRUE, 60)
                ->count();
            // 自营待发货订单
            $data['self_not_deliver'] = $orderAttach::withTrashed()
                ->alias('a')
                ->join('store store', 'store.store_id = a.store_id', 'left')
                ->where([
                    ['a.status', '=', 1],
                    ['store.shop', '=', '0'],
                ])
                ->cache(TRUE, 60)
                ->count();
            // 自营退款维权订单
            $data['self_is_refund'] = $orderAttach::withTrashed()
                ->alias('a')
                ->join('store store', 'store.store_id = a.store_id', 'left')
                ->where([
                    ['a.status', '=', 5],
                    ['store.shop', '=', '0'],
                ])
                ->cache(TRUE, 60)
                ->count();
            // 自营出售中
            $data['self_sales_goods_count'] = $goods
                ->alias('a')
                ->join('store store', 'store.store_id = a.store_id', 'left')
                ->where([
                    ['is_putaway', 'eq', 1],
                    ['store.shop', '=', '0'],
                ])
                ->cache(TRUE, 60)
                ->count();
            // 自营库存预警
            $data['self_goods_number'] = $goods
                ->alias('a')
                ->join('store store', 'store.store_id = a.store_id', 'left')
                ->where([
                    ['is_putaway', 'eq', 1],
                    ['store.shop', '=', '0'],
                ])
                ->field('IFNULL(sum(case when goods_number <= warn_number then 1 else 0 end),0) as goods_warn_number')
                ->cache(TRUE, 60)->find();
            // 自营仓库中
            $data['self_warehouse_goods_count'] = $goods
                ->alias('a')
                ->join('store store', 'store.store_id = a.store_id', 'left')
                ->where([
                    ['is_putaway', 'eq', 0],
                    ['store.shop', '=', '0'],
                ])
                ->cache(TRUE, 60)->count();
            // 自营回收站
            $data['self_recycle_goods_count'] = $goods::onlyTrashed()->alias('a')
                ->join('store store', 'store.store_id = a.store_id', 'left')
                ->where([
                    ['forever_del_status', 'eq', 0],
                    ['store.shop', '=', '0'],
                ])
                ->cache(TRUE, 60)->count();

            // TODO 店铺支付金额排行开始
            // 平台支付金额
            $data['pay'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('order order', 'a.order_id = order.order_id', 'left')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id', 'left')
                ->join('store store', 'a.store_id = store.store_id', 'left')
                ->field('(IFNULL(sum(a.subtotal_price),0) - IFNULL(sum(case when (order.order_type = 2 and a.status = 0.1) or (order.order_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) -
                IFNULL(sum(case when (order_attach.pay_type = 2 and a.status = 0.1) or (order_attach.pay_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) -
                IFNULL(sum(case when (order_attach.pay_type != 2 and a.status = 0.1) or (order_attach.pay_type != 2 and a.status = 6.1) and (order.order_type != 2 and a.status = 0.1) or (order.order_type != 2 and a.status = 6.1) then a.subtotal_price else 0 end),0)) as salesPrice')
                ->where($date)
                ->cache(TRUE, 60);
            if (!empty($param['date'])) {
                $data['pay'] = $data['pay']->find();
            } else {
                $data['pay'] = $data['pay']->whereTime('a.create_time', '-30 days')->find();
            }
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'item'              => $data,
            'time'              => date('Y-m-d H:i:s'),
            'applet_code_file'  => $request::instance()->domain() . '/' . $applet_code_file ?? '',
            'official_accounts' => $url ?? '',
            'single_store'      => config('user.one_more'),
        ]);
    }

    /**
     * 店铺支付金额排行 ajax
     * @param Request $request
     * @param OrderGoods $orderGoods
     * @return array
     */
    public function storePay(Request $request, OrderGoods $orderGoods)
    {
        try {
            $param = $request::post();

            $date = [];
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($date, ['a.create_time', 'between time', [$begin, $end]]);
            }
            // TODO 店铺支付金额排行开始
            // 店铺支付金额排行  统计时间内店铺所有付款订单金额总和(拼团在成团时计入付款金额;货到付款在发货时计入付款金额，不剔除退款金额)
            $data['store_pay'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('order order', 'a.order_id = order.order_id', 'left')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id', 'left')
                ->join('store store', 'a.store_id = store.store_id', 'left')
                ->field('(IFNULL(sum(a.subtotal_price),0) - IFNULL(sum(case when (order.order_type = 2 and a.status = 0.1) or (order.order_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) -
                IFNULL(sum(case when (order_attach.pay_type = 2 and a.status = 0.1) or (order_attach.pay_type = 2 and a.status = 6.1) then a.subtotal_price else 0 end),0) -
                IFNULL(sum(case when (order_attach.pay_type != 2 and a.status = 0.1) or (order_attach.pay_type != 2 and a.status = 6.1) and (order.order_type != 2 and a.status = 0.1) or (order.order_type != 2 and a.status = 6.1) then a.subtotal_price else 0 end),0)) as salesPrice,
                store.store_name,store.logo,store.store_id')
                ->order('salesPrice', 'desc')
                ->where($date)
                ->group('a.store_id')
                ->cache(TRUE, 60);
            if (!empty($param['date'])) {
                $data = $data['store_pay']->paginate(4, FALSE, ['query' => $param]);
            } else {
                $data = $data['store_pay']->whereTime('a.create_time', '-30 days')->paginate(4, FALSE, ['query' => $param]);
            }

            $html = '';
            if (count($data) == 0) {
                $html = '<tr class=\'emptyTable\'>
                            <td colspan="100" style="font-size: 25px;">暂无数据</td>
                        </tr>';
            }

            $_currentPage = $data->currentPage();

            foreach ($data as $k => $v) {
                $html .= '<tr data_type="storePay">' .
                    '<td>' . (($_currentPage - 1) * 4 + $k + 1) . '</td>
                        <td>
                            <div class="store">
                                <div  class="storeIcon">
                                    <img src="' . $v['logo'] . '" alt="" onerror=this.src="/template/master/resource/image/common/imageError.png">
                                </div>
                                <div class="storeName">' . $v['store_name'] . '</div>
                            </div>
                        </td>
                        <td>' . $v['salesPrice'] . '</td>
                        <!--<td>19878</td>-->
                        <!--<td>19878</td>-->' .
                    '</tr>';
            }
            return [
                'code' => 0,
                'data' => $html,
                'page' => [
                    'last_page'    => $data->lastPage(),
                    'current_page' => $data->currentPage(),
                ],
            ];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
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

            $_where = [
                ['order.origin_type', '<>', 5],
                ['a.status', '<>', '6.1'],
                ['a.status', '>', '0.1'],
            ];

            // 单店
            if (config('user.one_more') != 1) {
                $_where[] = [
                    'a.store_id',
                    '=',
                    config('user.one_store_id'),
                ];
            }

            // TODO 商品榜单开始
            // 商品支付金额 统计时间内的商品所有付款订单金额总和(拼团在成团时计入付款金额;货到付款在发货时计入付款金额，不剔除退款金额)
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
                ->where($date)
                ->where($_where)
                ->group('a.goods_id')
                ->cache(TRUE, 60);
            if (!empty($param['date'])) {
                $data['goods'] = $data['goods']->paginate(5, FALSE, ['query' => $param]);
            } else {
                $data['goods'] = $data['goods']->whereTime('a.create_time', '-30 days')->paginate(5, FALSE, ['query' => $param]);
            }

            $_currentPage = $data['goods']->currentPage();
            $html = '';
            if (count($data['goods']) == 0) {
                $html = '<tr class=\'emptyTable\'>
                            <td colspan="100" style="font-size: 25px;">暂无数据</td>
                        </tr>';
            }
            foreach ($data['goods'] as $k => $v) {
                $html .= '<tr data_type="goodsPay">
                            <td>' . (($_currentPage - 1) * 5 + $k + 1) . '</td>
                            <td>
                                <div class="goodsInfo">
                                    <img src="' . $v['file'] . '"
                                         onerror=this.src="/template/master/resource/image/common/imageError.png" alt=""
                                         class="goodsPic">
                                    <div class="det">
                                        <div class="goodsName">' . $v['goods_name'] . '</div>
                                        <div class="time">发布时间：' . $v['goods_create_time'] . '</div>
                                    </div>
                                </div>
                            </td>
                            <td>' . $v['salesPrice'] . '</td>
                        </tr>';
            }
            return [
                'code' => 0,
                'data' => $html,
                'page' => [
                    'last_page'    => $data['goods']->lastPage(),
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

            $_where = [
                ['order.origin_type', '<>', 5],
                ['a.status', '<>', '6.1'],
                ['a.status', '>', '0.1'],
            ];

            // 单店
            if (config('user.one_more') != 1) {
                $_where[] = [
                    'a.store_id',
                    '=',
                    config('user.one_store_id'),
                ];
            }


            // TODO 商品榜单开始
            // 商品销量数 统计时间内商品成功下单的数量
            $data['goods_count'] = $orderGoods::withTrashed()
                ->alias('a')
                ->join('order order', 'a.order_id = order.order_id', 'left')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id', 'left')
                ->join('goods goods', 'a.goods_id = goods.goods_id', 'left')
                ->field('IFNULL(sum(a.quantity),0) as goods_counts,goods.goods_name,goods.file,goods.create_time as goods_create_time')
                ->order('goods_counts', 'desc')
                ->where($date)
                ->where($_where)
                ->where('a.status', '<>', '6.1')
                ->group('a.goods_id')
                ->cache(TRUE, 60);
            if (!empty($param['date'])) {
                $data['goods_count'] = $data['goods_count']->paginate(5, FALSE, ['query' => $param]);
            } else {
                $data['goods_count'] = $data['goods_count']->whereTime('a.create_time', '-30 days')->paginate(5, FALSE, ['query' => $param]);
            }

            $_currentPage = $data['goods_count']->currentPage();
            $html = '';
            if (count($data['goods_count']) == 0) {
                $html = '<tr class=\'emptyTable\'>
                            <td colspan="100" style="font-size: 25px;">暂无数据</td>
                        </tr>';
            } else {
                foreach ($data['goods_count'] as $k => $v) {
                    $html .= '<tr data_type="goodsCount">
                            <td>' . (($_currentPage - 1) * 5 + $k + 1) . '</td>
                            <td>
                                <div class="goodsInfo">
                                    <img src="' . $v['file'] . '"
                                         onerror=this.src="/template/master/resource/image/common/imageError.png" alt=""
                                         class="goodsPic">
                                    <div class="det">
                                        <div class="goodsName">' . $v['goods_name'] . '</div>
                                        <div class="time">发布时间：' . $v['goods_create_time'] . '</div>
                                    </div>
                                </div>
                            </td>
                            <td>' . $v['goods_counts'] . '</td>
                        </tr>';
                }
            }

            return [
                'code' => 0,
                'data' => $html,
                'page' => [
                    'last_page'    => $data['goods_count']->lastPage(),
                    'current_page' => $data['goods_count']->currentPage(),
                ],
            ];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    // 业务流程
    public function business_process()
    {
        $one_more = config('user.one_more');
        return $this->fetch('',[
            'one_more' => $one_more,
        ]);
    }

    // 新手引导
    public function novice_guide()
    {
        $one_more = config('user.one_more');
        return $this->fetch('',[
            'one_more' => $one_more,
        ]);
    }
}