<?php
/**
 * 关于发票.
 * User: Heng
 * Date: 2019/2/1
 * Time: 14:01
 */

namespace app\client\controller;

use think\Db;
use think\exception\ValidateException;
use think\facade\Session;
use think\Response;
use think\Controller;
use think\facade\Env;
use think\facade\Request;
use app\common\model\Invoice as InvoiceModel;
use app\common\model\OrderAttach as OrderAttachModel;
use app\common\model\OrderGoods as OrderGoodsModel;
use app\common\model\DadaMerchant;
use app\common\model\Express as ExpressModel;
use app\master\validate\Invoice as InvoiceValid;
use app\common\model\Store;

class Invoice extends Controller
{
    /**
     * 发票列表
     * @param Request $request
     * @param InvoiceModel $invoice
     * @return array|mixed
     */
    public function index(Request $request, InvoiceModel $invoice, OrderAttachModel $orderAttach)
    {
        try {
            $param = $request::param();

            // 筛选
            // 平台单店暂用store_id = 1  多店铺商家端改成自己的id
            $condition[] = ['a.store_id', 'eq', Session::get('client_store_id')];
            $condition[] = ['a.status', 'neq', 6];
            $condition[] = ['a.status', 'neq', 0];
            $condition[] = ['a.is_all_refund', 'eq', 0];

            if (array_key_exists('invoice_type', $param) && $param['invoice_type'] != -1) {
                $condition[] = ['invoice_type', 'eq', $param['invoice_type']];
            }
            if (array_key_exists('order_attach_number', $param) && $param['order_attach_number']) {
                $condition[] = ['a.order_attach_number', 'eq', $param['order_attach_number']];
            }
            if (array_key_exists('rise_name', $param) && $param['rise_name']) {
                $condition[] = ['rise_name', 'eq', $param['rise_name']];
            }
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['ishop_invoice.create_time', 'between time', [$begin, $end]]);
            }

            if (array_key_exists('invoice_status', $param) && $param['invoice_status'] == -1) {
                $exp = [];
            } else {
                // 默认查询正常开票
                $exp[0] = ['ishop_invoice.invoice_id', 'exp', Db::raw('is not null')];
                if (array_key_exists('invoice_status', $param) && $param['invoice_status'] != -1) {
                    if ($param['invoice_status'] == 0) {
                        $exp[0] = ['ishop_invoice.invoice_id', 'exp', Db::raw('is not null')];
                    } else {
                        $exp[0] = ['ishop_invoice.invoice_id', 'exp', Db::raw('is null')];
                    }
                }
            }

            $data = $orderAttach->alias('a')
                ->join('ishop_invoice ishop_invoice', 'a.order_attach_id = ishop_invoice.order_attach_id', 'left')
                ->where($condition)
                ->where($exp)
                ->where(function ($e) {
                    $e->where('billing_type', 'eq', '0')->whereOr('billing_type', 'null');
                })
                ->field('a.order_attach_id,a.order_attach_number,a.store_id,is_invoice,invoice_type,rise_name,ishop_invoice.create_time,invoice_id')
                ->order('a.create_time', 'desc')
                ->paginate(10, false, ['query' => $param]);

            return $this->fetch('', [
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发票设置
     * @param Request $request
     * @return array|mixed
     */
    public function settings(Request $request)
    {

        if ($request::isPost())
        {
            Db::startTrans();
            try
            {
                $param = $request::post();

                (new Store())->where('store_id', Session::get('client_store_id'))->update(['is_added_value_tax' => $param['checked']]);

                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/invoice/settings'];
            } catch (\Exception $e) {
                return ['code' => 0, 'message' => config('message.')[-1]];
            }
        }

        $store = (new Store())->where('store_id', Session::get('client_store_id'))->field('is_added_value_tax,store_id')->find();
        return $this->fetch('',[
            'store' => $store,
        ]);
    }

    /**
     * 开票
     * @param Request $request
     * @param OrderAttachModel $orderAttach
     * @param ExpressModel $express
     * @param OrderGoodsModel $orderGoods
     * @param InvoiceValid $invoiceValid
     * @param InvoiceModel $invoice
     * @return array|mixed
     */
    public function examine(Request $request, ExpressModel $express, InvoiceModel $invoice, InvoiceValid $invoiceValid)
    {
        if ($request::isPost())
        {
            Db::startTrans();
            try
            {
                $param = $request::post();
                // 开票类型invoice_open_type = 2 invoice_type = 1（专票）
                if ($param['invoice_open_type'] == 2) {
                    $param['invoice_type'] = 1;
                } else {
                    $param['invoice_type'] = 0;
                }

                if (!$invoiceValid->scene('examine')->check($param)) {
                    return ['code' => -100, 'message' => $invoiceValid->getError()];
                };

                $reg = "/^([hH][tT]{2}[pP]:\/\/|[hH][tT]{2}[pP][sS]:\/\/|www\.)(([A-Za-z0-9-~]+)\.)+([A-Za-z0-9-~\/])+$/";
                if (!empty($param['download_links'])) {
                    if (!preg_match($reg, $param['download_links'])) {
                        return ['code' => -100, 'message' => '发票下载链接格式错误'];
                    }
                }

                $param['billing_type'] = 1; // 正票
                $param['manage_nickname'] = Session::get('client_nickname');
                $param['bill_time'] = date('Y-m-d H:i:s');

                $invoice->isUpdate(true)->allowField(true)->save($param);

                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/invoice/index'];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => 0, 'message' => config('message.')[-1]];
            }
        }

        try {
            $invoiceArr = $invoice->where('invoice_id', $request::get('invoice_id'))->find();

            $result = $this->getOrder($invoiceArr['order_attach_id']);

            return $this->fetch('', [
                'item' => $result['item'],
                'express' => $express::all(),
                'comment' => $result['comment'],
                'time' => $result['time'],
                'goods' => $result['goods'],
                'invoiceArr' => $invoiceArr,
                'express_name'  => $result['express_name'],
            ]);
        } catch (\Exception $e) {
            $this->error('网络异常请重试');
        }
    }

    /**
     * 补开发票
     * @param Request $request
     * @param OrderAttachModel $orderAttach
     * @param ExpressModel $express
     * @param InvoiceValid $invoiceValid
     * @param InvoiceModel $invoice
     * @return mixed
     */
    public function fill_open(Request $request, OrderAttachModel $orderAttach, ExpressModel $express, InvoiceValid $invoiceValid, InvoiceModel $invoice)
    {

        if ($request::isPost())
        {
            Db::startTrans();
            try
            {
                $param = $request::post();
                // 开票类型invoice_open_type = 2 invoice_type = 1（专票）
                if ($param['invoice_open_type'] == 2) {
                    $param['invoice_type'] = 1;
                } else {
                    $param['invoice_type'] = 0;
                }
                if (!$invoiceValid->scene('fill_open')->check($param)) {
                    return ['code' => -100, 'message' => $invoiceValid->getError()];
                };

                $reg = "/^([hH][tT]{2}[pP]:\/\/|[hH][tT]{2}[pP][sS]:\/\/|www\.)(([A-Za-z0-9-~]+)\.)+([A-Za-z0-9-~\/])+$/";
                if (!empty($param['download_links'])) {
                    if (!preg_match($reg, $param['download_links'])) {
                        return ['code' => -100, 'message' => '发票下载链接格式错误'];
                    }
                }

                if (!empty($invoice->where('order_attach_id', $param['order_attach_id'])->find())) return ['code' => -100, 'message' => '该订单已开具发票'];
                // 正票 补开  store_id = 1 （单店铺）多店铺改成自己的id
                $param['store_id'] = Session::get('client_store_id');
                $param['billing_type'] = 1;
                $param['invoice_attr'] = 2; // 补开发票
                $param['bill_time'] = date('Y-m-d H:i:s');
                $param['manage_nickname'] = Session::get('client_nickname');
                $param['stagger'] = 5; // 补开发票

                $invoice->allowField(true)->save($param);
                // 开票
                $orderAttach->where('order_attach_id', $param['order_attach_id'])->update(['is_invoice' => 1]);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/invoice/index'];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => 0, 'message' => config('message.')[-1]];
            }
        }

        try {
            $result = $this->getOrder($request::get('order_attach_id'));

            $is_added_value_tax = (new Store())->where('store_id', Session::get('client_store_id'))->value('is_added_value_tax');
            return $this->fetch('', [
                'item' => $result['item'],
                'express' => $express::all(),
                'comment' => $result['comment'],
                'time' => $result['time'],
                'goods' => $result['goods'],
                'is_added_value_tax' => $is_added_value_tax,
                'express_name'  => $result['express_name'],
            ]);
        } catch (\Exception $e) {
            $this->error('网络异常请重试');
        }
    }

    /**
     * 已开发票列表
     * @param Request $request
     * @param InvoiceModel $invoice
     * @return array|mixed
     */
    public function open(Request $request, InvoiceModel $invoice)
    {
        try {
            $param = $request::param();

            // 筛选
            // 平台单店暂用store_id = 1  多店铺商家端改成自己的id
            $condition[] = ['a.store_id', 'eq', Session::get('client_store_id')];
            $condition[] = ['a.billing_type', 'neq', 0];
            if (array_key_exists('invoice_type', $param) && $param['invoice_type'] != -1) {
                $condition[] = ['invoice_type', 'eq', $param['invoice_type']]; // 发票类型
            }
            if (array_key_exists('order_attach_number', $param) && $param['order_attach_number']) {
                $condition[] = ['order_attach_number', 'eq', $param['order_attach_number']];
            }
            if (array_key_exists('rise_name', $param) && $param['rise_name']) {
                $condition[] = ['rise_name', 'like', '%' . $param['rise_name'] . '%'];  // 发票抬头
            }
            if (array_key_exists('invoice_number', $param) && $param['invoice_number']) {
                $condition[] = ['invoice_number', 'like', '%' . $param['invoice_number'] . '%'];  // 发票号码
            }
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['a.bill_time', 'between time', [$begin, $end]]);
            }
            if (array_key_exists('stagger', $param) && $param['stagger'] != -1) {
                $condition[] = ['stagger', 'eq', $param['stagger']];  // 操作类型
            }
            if (array_key_exists('billing_type', $param) && $param['billing_type'] != -1) {
                $condition[] = ['billing_type', 'eq', $param['billing_type']];  // 开票类型
            }

            // 正票数量
            $writeInvoice = $invoice
                ->where([
                    ['billing_type', '=', 1],
                    ['store_id', 'eq', Session::get('client_store_id')]
                ])
                ->count();

            // 红票数量
            $redInvoice = $invoice
                ->where([
                    ['billing_type', '=', 2],
                    ['store_id', 'eq', Session::get('client_store_id')]
                ])
                ->count();

            $invoice_id = $invoice->group('order_attach_id')->column('max(invoice_id)');

            $data = $invoice
                ->alias('a')
                ->join('order_attach order_attach', 'a.order_attach_id = order_attach.order_attach_id')
                ->where($condition)
                ->where('invoice_id', 'in', implode(',', $invoice_id))
                ->field('a.*,order_attach_number')
                ->order('a.bill_time', 'desc')
                ->paginate(10, false, ['query' => $param]);

            return $this->fetch('', [
                'data' => $data,
                 'write' => $writeInvoice,
                 'red'   => $redInvoice,
                 'red'   => $redInvoice,
            ]);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 冲红
     * @param Request $request
     * @param ExpressModel $express
     * @param InvoiceModel $invoice
     * @return array|mixed
     */
    public function RCW(Request $request, ExpressModel $express, InvoiceModel $invoice)
    {
        if ($request::isPost())
        {
            Db::startTrans();
            try
            {
                $param = $request::post();

                if ($invoice->where('invoice_id', $param['invoice_id'])->value('billing_type') != 1) return ['code' => -100, 'message' => '开票类型错误'];

                $param['billing_type'] = 2; // 红票
                $param['manage_nickname'] = Session::get('client_nickname');
                $invoice->isUpdate(true)->allowField(true)->save($param);

                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/invoice/open'];
            } catch (\Exception $e) {
                return ['code' => 0, 'message' => config('message.')[-1]];
            }
        }

        try {
            $invoiceArr = $invoice->where('invoice_id', $request::get('invoice_id'))->find();
            $result = $this->getOrder($invoiceArr['order_attach_id']);

            return $this->fetch('', [
                'item' => $result['item'],
                'express' => $express::all(),
                'comment' => $result['comment'],
                'time' => $result['time'],
                'goods' => $result['goods'],
                'invoiceArr' => $invoiceArr,
                'express_name'  => $result['express_name'],
            ]);
        } catch (\Exception $e) {
            halt($e->getMessage());
            $this->error('网络异常请重试');
        }

    }

    /**
     * 重开
     * @param Request $request
     * @param OrderAttachModel $orderAttach
     * @param ExpressModel $express
     * @param InvoiceModel $invoice
     * @param InvoiceValid $invoiceValid
     * @return mixed
     */
    public function reopen(Request $request, OrderAttachModel $orderAttach, ExpressModel $express, InvoiceModel $invoice, InvoiceValid $invoiceValid)
    {
        if ($request::isPost())
        {
            Db::startTrans();
            try
            {
                $param = $request::post();
                // 开票类型invoice_open_type = 2 invoice_type = 1（专票）
                if ($param['invoice_open_type'] == 2) {
                    $param['invoice_type'] = 1;
                } else {
                    $param['invoice_type'] = 0;
                }
                // 重开发票invoice_attr  不用特殊记录
                $param['invoice_attr'] = 0;
                if (!$invoiceValid->scene('fill_open')->check($param)) {
                    return ['code' => -100, 'message' => $invoiceValid->getError()];
                };

                $reg = "/^([hH][tT]{2}[pP]:\/\/|[hH][tT]{2}[pP][sS]:\/\/|www\.)(([A-Za-z0-9-~]+)\.)+([A-Za-z0-9-~\/])+$/";
                if (!empty($param['download_links'])) {
                    if (!preg_match($reg, $param['download_links'])) {
                        return ['code' => -100, 'message' => '发票下载链接格式错误'];
                    }
                }

                // 正票 补开  store_id = 1 （单店铺）多店铺改成自己的id
                $param['store_id'] = Session::get('client_store_id');
                $param['billing_type'] = 1;
                $param['invoice_attr'] = 2; // 补开发票
                $param['bill_time'] = date('Y-m-d H:i:s');
                $param['manage_nickname'] = Session::get('client_nickname');

                $invoice->allowField(true)->save($param);
                // 开票
                $orderAttach->where('order_attach_id', $param['order_attach_id'])->update(['is_invoice' => 1]);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/invoice/open'];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => 0, 'message' => config('message.')[-1]];
            }
        }

        try {

            $invoiceArr = $invoice->where([
                ['invoice_id', '=', $request::get('invoice_id')],
                ['billing_type', '=', 2]
            ])->order('invoice_id', 'desc')->find();

            $result = $this->getOrder($invoiceArr['order_attach_id']);

            $is_added_value_tax = (new Store())->where('store_id', Session::get('client_store_id'))->value('is_added_value_tax');
            return $this->fetch('', [
                'item' => $result['item'],
                'express' => $express::all(),
                'comment' => $result['comment'],
                'time' => $result['time'],
                'goods' => $result['goods'],
                'invoiceArr' => $invoiceArr,
                'is_added_value_tax' => $is_added_value_tax,
                'express_name'  => $result['express_name'],
            ]);
        } catch (\Exception $e) {
            $this->error('网络异常请重试');
        }
    }

    // 订单详情
    public function getOrder($order_attach_id)
    {
        $item = (new OrderAttachModel())::withTrashed()
            ->alias('order_attach')
            ->join('order order', 'order.order_id=order_attach.order_id', 'left')
            ->join('member member', 'member.member_id = order_attach.member_id', 'left')
            ->join('group_activity group_activity', 'order_attach.group_activity_id = group_activity.group_activity_id', 'left')
            ->join('member member1', 'group_activity.owner = member1.member_id', 'left')
            ->join('group_goods group_goods', 'group_goods.group_goods_id = group_activity.group_goods_id', 'left')
            ->join('take take', 'order_attach.take_id = take.take_id', 'left')
            ->join('store store', 'store.store_id = order_attach.store_id', 'left')
            ->where(['order_attach.order_attach_id' => $order_attach_id])
            ->relation('orderGoods')
            ->field('order_attach.*,member.username,member.username,member.nickname,order.*,group_activity.surplus_num,group_activity.status as group_activity_status,
            member1.nickname as owner_nickname,group_goods.group_num,member1.phone as owner_phone,take.take_name,take.province as take_province,take.city as take_city,
            take.area as take_area,take.address as take_address,store.shop,store.is_pay_delivery')
            ->find();
        $expressName = Db::name('express')->where(['code' => $item['express_value']])->value('name');
        $goods = (new OrderGoodsModel())
            ->withTrashed()
            ->with(['orderGoodsRefund' => function ($e) {
                $e->field('is_get_goods,refuse_reason,order_goods_id');
            }])
            ->where(['order_goods.order_attach_id' => $order_attach_id])
            ->alias('order_goods')
            ->join('goods_evaluate goods_evaluate', 'order_goods.order_goods_id = goods_evaluate.order_goods_id', 'left')
            ->field('order_goods.*,(single_price*quantity) as goodsTotal,goods_evaluate_id,goods_evaluate.create_time as goods_evaluate_create_time')
            ->select();

        $comment = 0;
        $time = '';
        foreach ($item['orderGoods'] as $k => $v) {
            if ($v['status'] == 4.1) $comment = 1;
            if (!empty($goods[0]['goods_evaluate_id'])) $time = $goods[0]['goods_evaluate_create_time'];
        }

        return ['item' => $item, 'goods' => $goods, 'time' => $time, 'comment' => $comment, 'express_name' => $expressName];
    }
}