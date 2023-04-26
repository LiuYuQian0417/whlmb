<?php
declare(strict_types = 1);

namespace app\common\model;

use app\common\service\Dada;
use think\Db;
use think\facade\Request;

/**
 * 订单子表
 * Class OrderAttach
 * @package app\common\model
 */
class OrderAttach extends BaseModel
{
    protected $pk = 'order_attach_id';

    /**
     * 未支付订单剩余时间
     * @param $value
     * @param $data
     * @return false|int
     */
    public function getRemainingTimeAttr($value, $data)
    {
        $time = 15 * 60 - (time() - strtotime($data['create_time']));
        return ($data['status'] == 0) ? ($time > 0 ? $time : -1) : -1;
    }

    /**
     * 收货后剩余售后时间
     * @param $value
     * @param $data
     * @return int
     */
    public function getAfterSaleTimeAttr($value, $data)
    {
        $time = -1;
        if ($data['sale_after_status']) {
            $time = !is_null($data['deal_time']) ?
                (86400 * $data['after_sale_times'] + strtotime($data['deal_time'])) - time() : 0;
        }
        return $time;
    }

    /**
     * 关联订单商品表
     * @return \think\model\relation\HasMany
     */
    public function orderGoods()
    {
        return $this->hasMany('OrderGoods', 'order_attach_id', 'order_attach_id');
    }

    /**
     * 关联订单商品表带删除
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsWD()
    {
        return $this->hasMany('OrderGoods', 'order_attach_id', 'order_attach_id')->removeOption('soft_delete');
    }

    public function orderGoodsClient()
    {
        return self::orderGoods();
    }

    public function distributionBook()
    {
        return $this->hasOne('DistributionBook', 'order_goods_id', 'distributionBook');
    }

    /**
     * 订单列表(关联订单商品)
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsList()
    {
        return self::orderGoods()
            ->field('goods_id,products_id,order_goods_id,order_attach_id,goods_attr,attr,status,is_distribution,
            quantity,single_price,sub_freight_price,goods_name,goods_name_style,file,is_distributor,original_price');
    }

    /**
     * 订单详情(关联订单商品)
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsDetails()
    {
        return self::orderGoods()
            ->field('goods_id,order_goods_id,order_attach_id,goods_attr,attr,quantity,single_price,status,
            sub_freight_price,goods_name,goods_name_style,file,`describe`,sub_fullSub_price,discount_price,original_price,
            sub_share_platform_coupon_price,sub_share_shop_coupon_price,subtotal_share_platform_packet_price,sum_alter_goods_price,
            sum_alter_freight_price,subtotal_price')
            ->with(['orderGoodsRefundList']);
    }

    /**
     * 发表评价(关联订单商品)
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsReport()
    {
        // 4.1 已评价 4.2 退款成功 4.3 退货成功 6.1已取消
        return self::orderGoods()
            ->where([['status', 'not in', '4.1,4.2,4.3,6.1']])
            ->field('order_goods_id,order_attach_id,status');
    }

    /**
     * 取消订单(关联订单商品)
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsCancel()
    {
        return self::orderGoods()
            ->alias('og')
            ->join('limit l', 'l.goods_id = og.goods_id and l.delete_time is null and l.status = 1 and unix_timestamp(l.down_shelf_time) >= unix_timestamp(now())', 'left')
            ->field('og.order_goods_id,og.order_attach_id,og.goods_id,
            og.products_id,og.is_limit,og.quantity,og.status,l.limit_id');
    }

    /**
     * 删除订单(关联订单商品)
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsDestroy()
    {
        return self::orderGoods()
            ->with(['orderGoodsEvaluate', 'distributionBookCloseSale'])
            ->field('order_goods_id,order_attach_id,goods_id,status,products_id,goods_name,
            sub_share_shop_coupon_price,single_price,quantity,sub_share_platform_coupon_price,subtotal_price,
            subtotal_share_platform_packet_price,sub_freight_price,sub_fullSub_price,discount,file,
            sub_share_shop_coupon_price,sub_share_platform_coupon_price,subtotal_share_platform_packet_price,
            sub_fullSub_price,is_distributor,sum_alter_goods_price,sum_alter_freight_price');
    }

    /**
     * 确认收货(管理订单商品)
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsConfirmCollect()
    {
        return self::orderGoods()
            ->where([['status', 'in', '2.1,2.2,5.1,5.2,5.3,5.4,5.5,5.6,5.7']])
            ->with(['orderGoodsRefundList'])
            ->field('order_goods_id,order_attach_id,status,file');
    }

    /**
     * 支付(关联订单商品)
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsPay()
    {
        return self::orderGoods()
            ->alias('og')
            ->with(['goodsDistribution'])
            ->field('og.order_goods_id,og.member_id,og.order_attach_id,og.goods_id,og.products_id,
            og.quantity,og.single_price,og.discount,og.file,og.goods_name,og.sub_share_shop_coupon_price,
            og.sub_share_platform_coupon_price,og.subtotal_share_platform_packet_price,og.profit,
            og.sub_freight_price,og.sub_fullSub_price,og.subtotal_price,og.status');
    }

    /**
     * 待评价列表
     * @return \think\model\relation\HasMany
     */
    public function orderGoodsEvaluate()
    {
        return self::orderGoods()
            ->field('order_goods_id,order_attach_id,goods_name,goods_name_style,file,redo_status,status');
    }

    /**
     * 关联店铺
     * @return \think\model\relation\BelongsTo
     */
    public function store()
    {
        return $this->belongsTo('Store', 'store_id', 'store_id');
    }

    /**
     * 订单列表(关联店铺)
     * @return \think\model\relation\BelongsTo
     */
    public function storeList()
    {
        return self::store()
            ->field('store_id,store_name,logo,phone,lng,lat')
            ->removeOption('soft_delete');
    }

    /**
     * 关联订单主表(相对一对一)
     * @return \think\model\relation\BelongsTo
     */
    public function orders()
    {
        return $this->belongsTo('Order', 'order_id', 'order_id');
    }

    /**
     * 支付(关联订单主表)
     * @return \think\model\relation\BelongsTo
     */
    public function orderPay()
    {
        return self::orders()->field('order_id,order_type,total_price,order_number');
    }

    /**
     * 关联拼团主表
     * @return \think\model\relation\BelongsTo
     */
    public function groupActivity()
    {
        return $this->belongsTo('GroupActivity', 'group_activity_id', 'group_activity_id');
    }

    /**
     * 支付(关联拼团主表)
     * @return \think\model\relation\BelongsTo
     */
    public function groupActivityPay()
    {
        return self::groupActivity()->field('group_activity_id,surplus_num,status,end_time');
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
     * 支付(关联用户)
     * @return \think\model\relation\BelongsTo
     */
    public function memberPay()
    {
        return self::member()
            ->field('member_id,pay_password,usable_money,nickname,web_open_id,
            subscribe_time,micro_open_id,phone');
    }

    /**
     * 分销(关联用户[关联分销商])
     * @return \think\model\relation\BelongsTo
     */
    public function memberDistribution()
    {
        return self::member()
            ->with(['distribution'])
            ->field('member_id,nickname,phone,sex,distribution_superior,web_open_id,micro_open_id,
            subscribe_time');
    }

    /**
     * 取消订单
     * @return \think\model\relation\BelongsTo
     */
    public function orderCancel()
    {
        return self::orders()
            ->field('order_id,used_platform_member_coupon_id');
    }

    /**
     * 审核订单
     * @param $param
     * @return array
     * @throws \EasyWeChat\Kernel\Exceptions\Exception
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function examineOrder($param)
    {
        $result = $this
            ->alias('a')
            ->with(['orderGoodsList'])
            ->join('order order', 'order.order_id = a.order_id')
            ->where([
                ['a.order_attach_id', '=', $param['order_attach_id']],
            ])
            ->join('store s', 's.store_id = a.store_id')
            ->join('member m', 'm.member_id = a.member_id')
            ->field('a.subtotal_price,order.consignee_name,order.address_province,
            order.address_city,order.address_area,order.consignee_phone,order.address_lng,
            order.address_lat,a.order_attach_id,order.address_street,order.address_details,a.store_id,
            a.distribution_type,a.order_attach_number,a.dada,s.store_name,m.member_id,m.nickname,
            m.web_open_id,m.subscribe_time,m.micro_open_id,m.phone')
            ->find();
        if (array_key_exists('status', $param)) {
            switch ($param['status']) {
                case 1:
                    $orderGoodsStr = implode(',', $param['order_goods_id']);
                    $data = [];
                    // 同城情况
                    if ($result['distribution_type'] == 1) {
                        // 第三方配送
                        if ($param['delivery_method'] == 3 || $result['dada'] == 2) {
                            $param['origin_id'] = $param['order_attach_id']; // 第三方订单ID
                            $param['cargo_price'] = $result['subtotal_price']; // 订单金额
                            $param['receiver_name'] = $result['consignee_name']; //  收货人姓名
                            $param['receiver_address'] = $result['address_province'] . $result['address_city'] . $result['address_area'] . $result['address_street'] . $result['address_details']; //  收货人地址
                            $param['receiver_lat'] = $result['address_lat']; //  收货人地址维度
                            $param['receiver_lng'] = $result['address_lng']; //  收货人地址经度
                            $param['receiver_phone'] = $result['consignee_phone']; //  收货人电话
                            $param['callback'] = Request::instance()->domain() . '/client/order/dada_callback';
                            $param['dada'] = 1;
                            // todo 测试
                            $param['shop_no'] = '11047059';
                            $source_id = (new DadaMerchant())->where(['store_id' => $result['store_id']])->value('source_id');
                            $Dada = new Dada($source_id, $param);
                            if ($result['dada'] == 0) {
                                // 达达新增订单
                                $data = $Dada->request('api/order/addOrder');
                            }
                            if ($result['dada'] == 2) {
                                // 达达重发订单
                                $data = $Dada->request('api/order/reAddOrder');
                                $param['dada'] = 1;
                            }
                        } else {
                            // 非第三方配送正常发货
                            $param['status'] = 2;
                            $param['deliver_time'] = date('Y-m-d H:i:s');
                            Db::name('order_goods')
                                ->where('order_goods_id', 'in', $orderGoodsStr)
                                ->where('status', 'eq', '1.1')
                                ->update(['status' => '2.1']);
                            unset($orderGoodsStr);
                        }
                    } else {
                        // 非同城正常发货
                        $param['status'] = 2;
                        $param['deliver_time'] = date('Y-m-d H:i:s');
                        Db::name('order_goods')
                            ->where('order_goods_id', 'in', $orderGoodsStr)
                            ->where('status', 'eq', '1.1')
                            ->update(['status' => '2.1']);
                        unset($orderGoodsStr);
                    }
                    self::allowField(true)->isUpdate(true)->save($param);
                    // 推送消息[不含短信][普通订单已发货]
                    $pushServer = app('app\\interfaces\\behavior\\Push');
                    $pushServer->send([
                        'tplKey' => 'order_state',
                        'openId' => $result['web_open_id'],
                        'subscribe_time' => $result['subscribe_time'],
                        'microId' => $result['micro_open_id'],
                        'phone' => $result['phone'],
                        'data' => [2, $result['store_name'], $result['nickname']],
                        'inside_data' => [
                            'member_id' => $result['member_id'],
                            'type' => 1,
                            'jump_state' => '0',
                            'attach_id' => $param['order_attach_id'],
                            'file' => $result->order_goods_list[0]->getData('file'),
                        ],
                        'sms_data' => [],
                    ], 2);
                    if ($param['delivery_method'] == 3 || $result['dada'] == 2) {
                        return ['code' => $data['code'], 'message' => $data['message']];
                    } else {
                        return ['code' => 0, 'message' => 'OK'];
                    }
                    break;
            }
        }
    }

    /**
     * 我的订单统计(待付款,待收货,[待自提],待评价)
     * @param $member_id
     * @param $function_status
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myStatistical($member_id, $function_status)
    {
        $imgPathPrefix = 'mobile/small/image/';
        $count = [
            ['key' => 'notPay', 'title' => '待付款', 'count' => 0, 'img' => $imgPathPrefix . 'fk.png'],
            ['key' => 'stayRec', 'title' => '待收货', 'count' => 0, 'img' => $imgPathPrefix . 'dsh.png'],
            ['key' => 'stayTake', 'title' => '待自提', 'count' => 0, 'img' => $imgPathPrefix . 'dzt.png'],
            ['key' => 'stayEval', 'title' => '待评价', 'count' => 0, 'img' => $imgPathPrefix . 'dpj.png'],
        ];
        $where = [
            ['member_id', '=', $member_id],
            ['status', 'not in', '4,5,6'],
        ];
        if (!$function_status['is_take']) {
            // 自提
            array_push($where, ['distribution_type', '<>', 2]);
        }
        if (!$function_status['is_underPay']) {
            // 线下
            array_push($where, ['distribution_type', '<>', 4]);
        }
        if ($member_id) {
            $data = $this
                ->where($where)
                ->field('pay_type,status,distribution_type')
                ->order(['status' => 'asc'])
                ->select();
            if (!$data->isEmpty()) {
                foreach ($data as $_data) {
                    if ($_data['status'] == 2 && $_data['distribution_type'] != 2) {
                        $_data['status'] -= 1;
                    }
                    $count[$_data['status']]['count']++;
                }
            }
        }
        if (!$function_status['is_take']) {
            unset($count[2]);
        }
        return $count;
    }
}