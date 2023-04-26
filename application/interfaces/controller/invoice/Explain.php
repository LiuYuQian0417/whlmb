<?php
declare(strict_types=1);

namespace app\interfaces\controller\invoice;

use app\common\model\Invoice;
use mrmiao\encryption\RSACrypt;
use app\common\model\Invoice as InvoiceModel;
use app\interfaces\controller\BaseController;
use think\facade\Cache;

/**
 * 发票
 * Class Explain
 * @package app\interfaces\controller\invoice
 */
class Explain extends BaseController
{

    /**
     * 发票详情
     * @param RSACrypt $crypt
     * @param InvoiceModel $invoice
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function detail(RSACrypt $crypt,
                           InvoiceModel $invoice)
    {
        $args = $crypt->request();
        $where = [
            ['i.order_attach_id', 'eq', $args['order_attach_id']],
            ['oa.is_invoice', '=', 1],          // 订单需要开具发票
        ];
        $field = 'i.invoice_id,i.invoice_type,oa.create_time,oa.order_attach_number,i.rise_name,
        i.detail_type,i.taxer_number,i.address,i.phone,i.bank,i.account,i.invoice_open_type,
        i.express_value,i.express_number,i.download_links,i.billing_type,s.is_added_value_tax,
        i.rise,i.order_attach_id,oa.status';
        $data = $invoice
            ->alias('i')
            ->join('order_attach oa', 'i.order_attach_id = oa.order_attach_id')
            ->join('store s', 's.store_id = i.store_id')
            ->where($where)
            ->field($field)
            ->order(['i.billing_type' => 'asc','i.create_time' => 'desc'])
            ->find();
        // 增值税发票->纸质
        if (!is_null($data) && $data['invoice_open_type'] == 2) {
            $data['invoice_open_type'] = 1;
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => !is_null($data) ? $data : json([]),
        ], true);
    }

    /**
     * 获取缓存数据
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRiseHistory(RSACrypt $crypt)
    {
        $args['member_id'] = request(0)->mid;
        $data = Cache::store('file')->tag('invoice_history')->get('invoice_rt_' . $args['member_id'], []);
        if (!empty($data)) {
            foreach ($data as $_key => &$_value) {
                $_value = array_map(function ($v) {
                    return json_decode($v, true);
                }, $_value);
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data ?: [
                'personal' => [], 'company' => [], 'tax' => [],
            ],
        ], true);
    }

    /**
     * 编辑发票[只能未支付订单修改]
     * @param RSACrypt $crypt
     * @param InvoiceModel $invoice
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editInvoice(RSACrypt $crypt,
                                Invoice $invoice)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $invoice->valid($param);
        // 判断当前发票状态
        $invoiceBill = $invoice
            ->where([
                ['invoice_id', '=', $param['invoice_id']],
            ])
            ->value('billing_type');
        if ($invoiceBill) {
            return $crypt->response([
                'code' => -1,
                'message' => '已开发票,无法修改',
            ], true);
        }
        $invoice
            ->allowField(true)
            ->isUpdate(true)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '修改成功',
        ], true);
    }
}