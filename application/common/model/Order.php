<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Cache;

/**
 * 订单主表
 * Class Order
 * @package app\common\model
 */
class Order extends BaseModel
{
    protected $pk = 'order_id';

    /**
     * 关联订单子表
     * @return \think\model\relation\HasMany
     */
    public function orderAttach()
    {
        return $this->hasMany('OrderAttach', 'order_id', 'order_id');
    }

    /**
     * 关联订单商品
     * @return \think\model\relation\HasMany
     */
    public function orderGoods()
    {
        return $this->hasMany('OrderGoods', 'order_id', 'order_id');
    }

    /**
     * 关联店铺自提点
     * @return \think\model\relation\HasOne
     */
    public function take()
    {
        return $this->hasOne('Take', 'take_id', 'take_id');
    }

    /**
     * 关联用户(相对一对多)
     * @return \think\model\relation\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo('Member', 'member_id', 'member_id');
    }

    /**
     * 保存订单
     * @param $args
     * @return bool
     * @throws \Exception
     */
    public function saveOrder(&$args)
    {
        if ($args['code'] === 0) {
            // 生成主订单数据
            $args['data']['order_number'] = get_order_sn();
            // 存储order数据
            $this->allowField(true)->isUpdate(false)->save($args['data']);
            $args['data']['order_id'] = $this->order_id;
            array_walk($args['attachData'], function (&$v) use ($args) {
                $v['case_pay_type'] = $args['data']['origin_type'];
                $v['order_number'] = $args['data']['order_number'];
                $v['order_attach_number'] = get_order_sn();
            });
            // 生成子订单(按店铺拆单)数据
            $attachRes = $this->orderAttach()->saveAll($args['attachData']);
            // 查询用户抬头和税号缓存
            $invoiceCache = Cache::store("file")->tag('invoice_history')->get('invoice_rt_' . $args['distribution']['member_id'], [
                'personal' => [], 'company' => [], 'tax' => [],
            ]);
            $invoiceData = [];
            if ($attachRes) {
                foreach ($attachRes as $key => $val) {
                    foreach ($args['attachData'] as $_k => &$_v) {
                        if ($_v['store_id'] == $val['store_id']) {
                            $_v['order_attach_id'] = $val['order_attach_id'];
                            if ($_v['is_invoice'] && !empty($_v['invoice_data'])) {
                                // 含有发票数据
                                $_v['invoice_data']['store_id'] = $_v['store_id'];
                                $_v['invoice_data']['order_attach_id'] = $_v['order_attach_id'];
                                if (!$_v['invoice_data']['invoice_type']) {
                                    // 普通发票
                                    $_v['invoice_data'] = [
                                        'store_id' => $_v['invoice_data']['store_id'],
                                        'order_attach_id' => $_v['invoice_data']['order_attach_id'],
                                        'invoice_type' => $_v['invoice_data']['invoice_type'],
                                        'rise' => $_v['invoice_data']['rise'],
                                        'rise_name' => $_v['invoice_data']['rise_name'],
                                        'detail_type' => $_v['invoice_data']['detail_type'],
                                        'taxer_number' => $_v['invoice_data']['rise'] == 2 ? $_v['invoice_data']['taxer_number'] : '',
                                    ];
                                }
                                $invoiceData[] = $_v['invoice_data'];
                                $inv = false;
                                $flag = '';
                                if ($_v['invoice_data']['rise'] == 1 && !$_v['invoice_data']['invoice_type']) {
                                    $flag = 'personal';
                                    $inv = [
                                        'rise_name' => $_v['invoice_data']['rise_name'],
                                    ];
                                } else if ($_v['invoice_data']['rise'] == 2 && !$_v['invoice_data']['invoice_type']) {
                                    $flag = 'company';
                                    $inv = [
                                        'rise_name' => $_v['invoice_data']['rise_name'],
                                        'taxer_number' => $_v['invoice_data']['taxer_number'],
                                    ];
                                } else if ($_v['invoice_data']['invoice_type']) {
                                    $inv = [
                                        'rise_name' => $_v['invoice_data']['rise_name'],
                                        'taxer_number' => $_v['invoice_data']['taxer_number'],
                                        'address' => $_v['invoice_data']['address'],
                                        'phone' => $_v['invoice_data']['phone'],
                                        'bank' => $_v['invoice_data']['bank'],
                                        'account' => $_v['invoice_data']['account'],
                                    ];
                                    $flag = 'tax';
                                }
                                // 记录抬头和税号历史
                                if ($inv && (!in_array(json_encode($inv), $invoiceCache[$flag]) || empty($invoiceCache[$flag]))) {
                                    array_unshift($invoiceCache[$flag], json_encode($inv));
                                    if (count($invoiceCache[$flag]) > 10) {
                                        $invoiceCache[$flag] = array_slice($invoiceCache[$flag], 0, 10);
                                    }
                                }
                            }
                        }
                    }
                    foreach ($args['goodsData'] as $_k => &$_v) {
                        if ($_v['store_id'] == $val['store_id']) {
                            $_v['order_attach_id'] = $val['order_attach_id'];
                        }
                    }
                }
            }
            // 生成订单商品数据
            $og = $this->orderGoods()->saveAll($args['goodsData']);
            $dist_goods = [];
            // 有货到付款商品,需要补充数据
            foreach ($og as $_og) {
                if (in_array($_og['goods_id'], $args['distribution']['distribution_goods'])) {
                    $dist_goods[] = $_og['order_goods_id'];
                }
                if (!empty($args['distribution']['goods'])) {
                    foreach ($args['distribution']['goods'] as &$_adg) {
                        if ($_og['goods_id'] == $_adg['goods_id']) {
                            $_adg['order_goods_id'] = $_og['order_goods_id'];
                            $_adg['order_attach_id'] = $_og['order_attach_id'];
                        }
                    }
                }
            }
            if (!empty($dist_goods)) {
                $args['distribution']['distribution_goods'] = $dist_goods;
            }
            if (!empty($invoiceData)) {
                (new Invoice())
                    ->allowField(true)
                    ->isUpdate(false)
                    ->saveAll($invoiceData);
            }
            if (!empty($invoiceCache)) {
                Cache::store('file')->tag('invoice_history')->set('invoice_rt_' . $args['distribution']['member_id'], $invoiceCache);
            }
            return true;
        }
        return false;
    }
}