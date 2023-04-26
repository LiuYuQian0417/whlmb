<?php
declare(strict_types=1);

namespace app\common\model;

use think\Db;
use think\Exception;
use think\facade\Env;

class CheckOrder extends BaseModel
{

    protected $pk = 'check_order_id';

    /**
     * 每天凌晨对前一天的账单
     * @return bool
     * @throws \Exception
     */
    public function reconciliation()
    {
        $day_time = date('Y-m-d');
        // $day_time = "2019-10-09";
        $the_day_before = date('Y-m-d', strtotime('-1 day' . $day_time));
        $where = [
            ['check_order_id', '=', 0],
            ['status', '=', '4'],
            ['update_time', '>=', $the_day_before],
            ['update_time', '<', $day_time],
            ['pay_channel', '<>', 0],
            ['sale_after_status', '=', 0],
        ];
        //查出前一天所有满足可以结算的订单
        try {
            $OrderAttachModel = new OrderAttach;
            $order_attach_data = $OrderAttachModel::withTrashed()->with(
                [
                    'orderGoodsWD' => function ($query) {
                        $query->with(
                            ['distributionBook' => function ($query) {
                                $query->field('order_goods_id,(distributor_a_brokerage+distributor_b_brokerage+distributor_c_brokerage) as brokerage');
                            }])->field(
                            'store_id,order_attach_id,order_goods_id,status,quantity,single_price,discount,original_price,sub_share_shop_coupon_price,sub_share_platform_coupon_price,subtotal_share_platform_packet_price,sub_freight_price,sum_alter_goods_price,sum_alter_freight_price'
                        );
                    },
                ]
            )->where($where)->field('order_attach_id,store_id,pay_channel,status,order_attach_id,store_id')->select();
            //处理订单金额
            $store_check_info = $_store_check_info = $this->reconciliation_data($order_attach_data, $the_day_before);
            //没有需要对账的订单
            if (empty($store_check_info)) {
                return $the_day_before . '---没有可出账订单';
            }
            //判断对应店铺是否生成过对账单
            $check_order = self::where(
                [
                    ['store_id', 'in', array_keys($store_check_info)],
                    ['time', '=', date('Y-m-d', strtotime('-1 day' . $day_time))],
                ]
            )->field('check_order_id,store_id')->select();
            //店铺订单结算对应数据
            $update_attach_goods_check = [];
            //更新的对账单信息
            $exist_check_order = [];
            foreach ($check_order as $key => $check_order_v) {
                unset($store_check_info[$check_order_v['store_id']]['time']);
                unset($store_check_info[$check_order_v['store_id']]['store_id']);
                //存在的对应订单key值
                $update_attach_goods_check[] = [
                    'where' => [
                        [
                            'order_attach_id',
                            'in',
                            trim(
                                $store_check_info[$check_order_v['store_id']]['order_attach_id'],
                                ','
                            ),
                        ],
                    ],
                    'data' => [
                        'is_checking' => 1,
                        'check_order_id' => $check_order_v['check_order_id'],
                        'check_time' => date('Y-m-d H:i:s'),
                    ],
                ];
                $exist_store_check_info = array_keys($store_check_info[$check_order_v['store_id']]);
                //生成需要更新的数据
                $exist_check_order[]['check_order_id'] = $check_order_v['check_order_id'];
                foreach ($exist_store_check_info as $exist_store_check_info_v) {
                    $exist_check_order[$key][$exist_store_check_info_v] = [
                        'inc',
                        $store_check_info[$check_order_v['store_id']][$exist_store_check_info_v],
                    ];
                }
                unset($store_check_info[$check_order_v['store_id']]);
            }
            Db::startTrans();
            //更新存在的对账单
            self::isUpdate(true)->saveAll($exist_check_order);
            //新增对账单  返回对应id
            $check_order_ids = self::isUpdate(false)->saveAll($store_check_info);
            //生成需要更新的数据
            foreach ($check_order_ids as $check_order_ids_v) {
                $update_attach_goods_check[] = [
                    'where' => [
                        [
                            'order_attach_id',
                            'in',
                            trim($check_order_ids_v['order_attach_id'], ','),
                        ],
                    ],
                    'data' => [
                        'is_checking' => 1,
                        'check_order_id' => $check_order_ids_v['check_order_id'],
                        'check_time' => date('Y-m-d H:i:s'),
                    ],
                ];
            }
            //更新商品订单对应结算数据
            foreach ($update_attach_goods_check as $update_order_goods_check_v) {
                OrderAttach::update($update_order_goods_check_v['data'], $update_order_goods_check_v['where']);
            }
            Db::commit();
            //写入日志
            $refundLogMsg = str_repeat('-', 20) . date('Y-m-d H:i:s') . str_repeat('-', 20) . PHP_EOL;
            $refundLogMsg .= 'message:订单入账成功' . PHP_EOL;
            $refundLogMsg .= 'order_auction_id:' . implode(',', array_column($order_attach_data->toArray(), 'order_attach_id')) . PHP_EOL;
            $this->log_file_put($refundLogMsg);
        } catch (\Exception $e) {
            Db::rollback();
            //写入日志
            $refundLogMsg = str_repeat('-', 20) . date('Y-m-d H:i:s') . str_repeat('-', 20) . PHP_EOL;
            $refundLogMsg .= 'message:订单入账失败' . PHP_EOL;
            $refundLogMsg .= 'order_auction_id:' . implode(',', array_column($order_attach_data->toArray(), 'order_attach_id')) . PHP_EOL;
            $refundLogMsg .= 'error_message:' . $e->getMessage() . PHP_EOL;
            $this->log_file_put($refundLogMsg);
            return 'info:-' . $e->getMessage() . 'data:-' . var_export($order_attach_data->toArray(), TRUE);
        }
        //店铺增加余额
        return $this->charge_off($the_day_before);
    }


    /**
     * 处理对账数据
     * @param array $order_attach_data 对账订单数据
     * @param $the_day_before
     * @return array                每个店铺对应的对账数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function reconciliation_data($order_attach_data, $the_day_before)
    {
        //支付类型
        $pay_type = [
            1 => 'wechat_price',
            2 => 'alipay_price',
            3 => 'balance_price',
            4 => 'unionpay_price',
            5 => 'offline_price',
            6 => 'pay_on_delivery',
        ];
        $store_check_info = [];

        // 店铺ID列表   - 用来记录所有需要处理对账数据的店铺的ID
        $_storeIdList = [];

        foreach ($order_attach_data as $order_attach) {
            if ($order_attach['pay_channel'] == 0) {
                continue;
            }

            // 为 店铺ID列表 赋值
            $_storeIdList[$order_attach['store_id']] = $order_attach['store_id'];

            $store_check_info[$order_attach['store_id']]['store_id'] = $order_attach->store_id;
            $store_check_info[$order_attach['store_id']]['time'] = $the_day_before;
            //店铺数据对应订单id
            $store_check_info[$order_attach['store_id']]['order_attach_id'] = $store_check_info[$order_attach['store_id']]['order_attach_id'] ?? '';
            $store_check_info[$order_attach['store_id']]['order_attach_id'] .= ',' . $order_attach['order_attach_id'];
            //总的订单数量
            $store_check_info[$order_attach['store_id']]['order_number'] = $store_check_info[$order_attach['store_id']]['order_number'] ?? 0;
            $store_check_info[$order_attach['store_id']]['order_number'] += 1;
            foreach ($order_attach->order_goods_w_d as $k => $order_goods) {
                //支付金额
                $store_check_info[$order_goods['store_id']]['sum_pay_price'] = $store_check_info[$order_goods['store_id']]['sum_pay_price'] ?? 0;
                $store_check_info[$order_goods['store_id']]['sum_pay_price'] += $pay_price = $order_goods['single_price'] * $order_goods['quantity'] + $order_goods['sub_freight_price'] - $order_goods['sub_share_shop_coupon_price'] - $order_goods['sub_share_platform_coupon_price'] - $order_goods['subtotal_share_platform_packet_price'] + $order_goods['sum_alter_goods_price'] ;
                //总的运费金额
                $store_check_info[$order_goods['store_id']]['sum_freight_price'] = $store_check_info[$order_goods['store_id']]['sum_freight_price'] ?? 0;
                $store_check_info[$order_goods['store_id']]['sum_freight_price'] += $order_goods['sub_freight_price'];
                //总的佣金金额
                $store_check_info[$order_goods['store_id']]['sum_brokerage_price'] = $store_check_info[$order_goods['store_id']]['sum_brokerage_price'] ?? 0;
                $store_check_info[$order_goods['store_id']]['sum_brokerage_price'] += $order_goods['distribution_book']['brokerage'] ?? 0;
                //平台承担活动款
                $store_check_info[$order_goods['store_id']]['sum_activity_price'] = $store_check_info[$order_goods['store_id']]['sum_activity_price'] ?? 0;
                $store_check_info[$order_goods['store_id']]['sum_activity_price'] += $activity_price = ($order_goods['original_price'] - $order_goods['single_price']) * $order_goods['quantity'] + $order_goods['sub_share_platform_coupon_price'] + $order_goods['subtotal_share_platform_packet_price'];
                //应付商家金额
                $store_check_info[$order_goods['store_id']]['should_be_price'] = $store_check_info[$order_goods['store_id']]['should_be_price'] ?? 0;
                switch ($order_goods['status']) {
                    //评价完结订单
                    case '4.1':
                        $store_check_info[$order_goods['store_id']]['should_be_price'] += $pay_price + $activity_price;
                        break;
                    //退款成功(仅退款) 订单   减去已经退款金额
                    case '4.2':
                        //总的退款金额
                        $refund_price = $order_goods->orderGoodsRefund->refund_amount;
                        $store_check_info[$order_goods['store_id']]['sum_refund_price'] = $store_check_info[$order_goods['store_id']]['sum_refund_price'] ?? 0;
                        $store_check_info[$order_goods['store_id']]['sum_refund_price'] += $refund_price;//总退款金额
                        //应付商家金额
                        $store_check_info[$order_goods['store_id']]['should_be_price'] += $pay_price + $activity_price - $refund_price > 0 ? $pay_price + $activity_price - $refund_price : 0;//应付商家金额
                        break;
                    //退货成功
                    case '4.3':
                        //总的退款金额
                        $refund_price = $order_goods->orderGoodsRefund->refund_amount;
                        $store_check_info[$order_goods['store_id']]['sum_refund_price'] = $store_check_info[$order_goods['store_id']]['sum_refund_price'] ?? 0;
                        $store_check_info[$order_goods['store_id']]['sum_refund_price'] += $refund_price;//总退款金额
                        //应付商家金额
                        $store_check_info[$order_goods['store_id']]['should_be_price'] += $pay_price + $activity_price - $refund_price > 0 ? $pay_price + $activity_price - $refund_price : 0;//应付商家金额
                        break;
                    //其他状态
                    default:
                        $store_check_info[$order_goods['store_id']]['should_be_price'] += $pay_price + $activity_price;
                }
                //   去除佣金价格
                $store_check_info[$order_goods['store_id']]['should_be_price'] =  $store_check_info[$order_goods['store_id']]['should_be_price'] - $store_check_info[$order_goods['store_id']]['sum_brokerage_price'];


                //对应支付类型金额累计
                $store_check_info[$order_goods['store_id']][$pay_type[$order_attach['pay_channel']]] = $store_check_info[$order_goods['store_id']][$pay_type[$order_attach['pay_channel']]] ?? 0;
                $store_check_info[$order_goods['store_id']][$pay_type[$order_attach['pay_channel']]] += $pay_price;
            }

        }

        // 如果计算出来的信息为空的话 直接返回
        if (empty($store_check_info)) {
            return $store_check_info;
        }

        // 自营类目ID 列表 store_id => []Costs
        $_storeCostsList = [];
        // 获取店铺的 自营类目 列表
        $_storeCategoryDataList = Store::withTrashed()->with([
            'costs'
        ])->where([
            ['store_id', 'in', join(',', $_storeIdList)]
        ])->field([
            'store_id',
            'category',
        ])->select();

        // 判断是否获取到值 如果未获取到值的话直接赋值为空数组 否则 转换为数组
        $_storeCategoryDataList = $_storeCategoryDataList ? $_storeCategoryDataList->toArray() : [];

        // 转换为 K => V >>> store_id => []Costs
        foreach ($_storeCategoryDataList as $_key => $_storeCategory) {
            // 转换 K => V
            $_storeCostsList[$_storeCategory['store_id']] = $_storeCategory['costs'];
        }

        if (!empty($store_check_info)){
            // 计算平台服务费
            foreach ($store_check_info as &$check_info_v) {
                // 计算服务费
                $_costs = $this->mathStoreCosts($check_info_v['should_be_price'], $_storeCostsList[$check_info_v['store_id']]);
                // 设置服务费
                $check_info_v['costs'] = $_costs;
                // 平台应付商家的金额扣除平台服务费
                $check_info_v['should_be_price'] = $check_info_v['should_be_price'] - $_costs;
            }
        }

        //$store_check_info 里面的数据已经每个店铺一条数据
        return $store_check_info;
    }

    /**
     * 针对店铺的费用匹配需要计算的服务费
     * @param int $price
     * @param array $costsList
     * @return float|string
     */
    private function mathStoreCosts($price, $costsList)
    {
        if (empty($costsList)) {
            return 0.00;
        }

        // 当前的 营业额超
        $_currentTurnover = 0;
        // 当前的 收取比例
        $_currentPercent = 0;

        // 循环当前的费用 设置 匹配 最符合的那一项
        foreach ($costsList as $costs) {
            if ($price >= $costs['turnover'] && $costs['turnover'] > $_currentTurnover) {
                $_currentTurnover = $costs['turnover'];
                $_currentPercent = $costs['percent'];
            }
        }

        // 开始计算 保持两位小数 向上取
        // (平台费 * (比例 / 100) +0.004) 取两位小数
        // number_format 默认四舍五入 + 0.004 可以保证向上取 2 位小数
        return number_format($price * ($_currentPercent / 100) + 0.004, 2,'.','');
    }

    /**
     * 出账   每天凌晨出前天的账单
     * @param $the_day_before
     * @return string
     * @throws \Exception
     */
    public function charge_off($the_day_before)
    {
        try {
            $check_order_info = $this->where([['check_status', '=', 1]])->field(
                'check_order_id,store_id,check_status,should_be_price'
            )->select();
            if (!$check_order_info->isEmpty()) {
                $store_data = [];
                $check_order_id = [];
                foreach ($check_order_info as $check_order_info_v) {
                    $store_data[] = [
                        'store_id' => $check_order_info_v['store_id'],
                        'balance' => ['inc', $check_order_info_v['should_be_price']],
                    ];
                    $check_order_id[] = $check_order_info_v['check_order_id'];
                }
                Db::startTrans();
                //更新对账状态
                $number = $this->where('check_order_id', 'in', $check_order_id)->update(['check_status' => 2]);
                if (empty($number)) {
                    Db::rollback();
                    return '对账单状态更新失败' . var_export($check_order_id, TRUE);
                }
                //更新店铺资金
                $storeModel = new Store;
                $storeModel->isUpdate(true)->saveAll($store_data);
                Db::commit();
            }
            //写入日志
            $refundLogMsg = str_repeat('-', 15) . date('Y-m-d H:i:s') . str_repeat('-', 15) . PHP_EOL;
            $refundLogMsg .= 'message:对账单出账成功' . PHP_EOL;
            $refundLogMsg .= 'check_order_id:' . implode(',', array_column($check_order_info->toArray(), 'check_order_id')) . PHP_EOL;
            $this->log_file_put($refundLogMsg);
            return $the_day_before . '账单余额入账成功------time:' . date('Y-m-d H:i:s');
        } catch (Exception $e) {
            Db::rollback();
            //写入日志
            $refundLogMsg = str_repeat('-', 15) . date('Y-m-d H:i:s') . str_repeat('-', 15) . PHP_EOL;
            $refundLogMsg .= 'message:对账单出账失败' . PHP_EOL;
            $refundLogMsg .= 'check_order_id:' . implode(',', array_column($check_order_info->toArray(), 'check_order_id')) . PHP_EOL;
            $refundLogMsg .= 'error_message:' . $e->getMessage() . PHP_EOL;
            $this->log_file_put($refundLogMsg);
            return 'message:' . $e->getMessage() . ';账单:' . $the_day_before . 'data' . var_export($store_data, TRUE);
        }
    }


    /**
     * 获得对应账单下的所有订单详情
     * @param array $order_attach_where 条件
     * @param bool $is_all
     * @return array
     * @throws Exception
     * @throws \think\exception\DbException
     */
    public function get_check_info(array $order_attach_where, $is_all = false)
    {
        if (empty($order_attach_where)) {
            throw new Exception('条件参数不能为空');
        }
        array_push($order_attach_where, ['sale_after_status', '=', 0]);
        //支付类型
        $pay_type = [
            1 => 'wechat_price',
            2 => 'alipay_price',
            3 => 'balance_price',
            4 => 'unionpay_price',
            5 => 'offline_price',
            6 => 'pay_on_delivery',
        ];
        $order_attach_data = OrderAttach::withTrashed()->with(
            [
                'orderGoods' => function ($query) {
                    $query->with(
                        ['distributionBook' => function ($query) {
                            $query->field('order_goods_id,sum(distributor_a_brokerage+distributor_b_brokerage+distributor_c_brokerage) brokerage');
                        }])->field(
                        'store_id,order_attach_id,order_goods_id,status,quantity,single_price,discount,original_price,sub_share_shop_coupon_price,sub_share_platform_coupon_price,subtotal_share_platform_packet_price,sub_freight_price,sum_alter_goods_price,sum_alter_freight_price'
                    );
                },
            ]
        )->where($order_attach_where)->field(
            'create_time,is_checking,order_attach_id,pay_channel,order_attach_number,order_id,deal_time,subtotal_coupon_price,subtotal_share_platform_packet_price,subtotal_share_platform_coupon_price,subtotal_freight_price,check_time,store_id,pay_channel,status,sum_alter_goods_price,sum_alter_freight_price'
        );
        if ($is_all) {
            $order_attach_data = $order_attach_data->select();
        } else {
            $order_attach_data = $order_attach_data->paginate(10,FALSE,['query'=>input()]);
        }
        $order_number = 0;//订单总数
        $should_be_price = 0;//应付金额
        foreach ($order_attach_data as &$v) {
            $order_number++;
            $order_goods['original_price'] = 0;
            $order_goods['single_price'] = 0;
            $order_goods['quantity'] = 0;
            $order_goods['sum_brokerage_price'] = 0;
            $order_goods['sub_freight_price'] = 0;
            $order_goods['sub_share_shop_coupon_price'] = $v['subtotal_coupon_price'];
            $order_goods['sub_share_platform_coupon_price'] = $v['subtotal_share_platform_coupon_price'];
            $order_goods['subtotal_share_platform_packet_price'] = $v['subtotal_share_platform_packet_price'];
            $order_goods['sum_alter_goods_price'] = $v['sum_alter_goods_price'];
            $order_goods['sum_alter_freight_price'] = $v['sum_alter_freight_price'];
            $order_goods['refund_price'] = 0;//退款金额
            foreach ($v->order_goods as $order_goods_v) {
                //商品原价
                $order_goods['original_price'] += $order_goods_v['original_price'];
                //单品价格(交易价])
                $order_goods['single_price'] += $order_goods_v['single_price'];
                //数量
                $order_goods['quantity'] = $order_goods_v['quantity'];
                //商品运费金额
                $order_goods['sub_freight_price'] += $order_goods_v['sub_freight_price'];
                //  改价金额
                // $order_goods['sum_alter_goods_price'] += $order_goods_v['sum_alter_goods_price'];
                // $order_goods['sum_alter_freight_price'] += $order_goods_v['sum_alter_freight_price'];

                //佣金金额
                $order_goods['sum_brokerage_price'] += $order_goods_v['distribution_book']['brokerage'] ?? 0;
                switch ($order_goods_v['status']) {
                    //评价完结订单
                    case '4.1':
                        break;
                    //退款成功(仅退款) 订单   减去已经退款金额
                    case '4.2':
                        //总的退款金额、
                        $order_goods['refund_price'] += isset($order_goods_v->orderGoodsRefund) ? $order_goods_v->orderGoodsRefund->refund_amount : 0;
                        break;
                    //退货成功
                    case '4.3':
                        //总的退款金额
                        $order_goods['refund_price'] += isset($order_goods_v->orderGoodsRefund) ? $order_goods_v->orderGoodsRefund->refund_amount : 0;
                        break;
                }
            }
            $pay_price = $order_goods['single_price'] * $order_goods['quantity'] + $order_goods['sub_freight_price'] - $order_goods['sub_share_shop_coupon_price'] - $order_goods['sub_share_platform_coupon_price'] - $order_goods['subtotal_share_platform_packet_price']+ $order_goods['sum_alter_goods_price'] + $order_goods['sum_alter_freight_price'];
            $pay_price = number_format($pay_price,2,'.','');
            $v['activity_price'] = ($order_goods['original_price'] - $order_goods['single_price']) * $order_goods['quantity'] + $order_goods['sub_share_platform_coupon_price'] + $order_goods['subtotal_share_platform_packet_price'];
            $v->pay_price = $pay_price;//支付金额
            $v[$pay_type[$v['pay_channel']]] = $pay_price;//对应支付类型金额
            // dump($pay_price);
            // dump($v['activity_price']);
            // dump($order_goods['refund_price']);
            // dump($order_goods['sum_brokerage_price']);

            $v->should_be_price = $pay_price + $v['activity_price'] - $order_goods['refund_price'] - $order_goods['sum_brokerage_price']; //应付商家金额
            $v->refund_price = $order_goods['refund_price'];
            // dump($v->should_be_price);
            // $v->should_be_price =  number_format($v->should_be_price,2,'.','');
            $v->sum_brokerage_price = $order_goods['sum_brokerage_price'];

            $should_be_price += $v['should_be_price'];
            // $pay_price = number_format($pay_price,2);
            // $v['should_be_price'] = number_format($v['should_be_price'],2)
            // dump($should_be_price);
        }
        return [
            'order_number' => $order_number,
            'should_be_price' => number_format($should_be_price,2),
            'order_attach_data' => $order_attach_data,
        ];
    }

    /**
     * 导出cvs文件表格
     * @param $csv_header   表头标题
     * @param $csv_body     对应数据
     * @param $title        文件名
     */
    public function create_exoirt($csv_header, $csv_body, $title)
    {
        //表头标题
        $_csv_header = [
            'client_propertyList' => ['订单总数量','收款金额','活动款项','运费', '退款金额', '分销返佣', '平台服务费', '本期应结', '结算状态','账单日期'],
            'client_property_examine' => [
                '订单号',
                '下单时间',
                '确认收货时间',
                '出账时间',
                '付款金额	',
                '平台承担活动款',
                '运费',
                '退款金额',
                '本期应结',
                '结算状态',
            ],
            'master_property_list' => ['起止日期', '所属店铺', '订单总数量', '付款金额', '平台承担活动款	', '运费', '退款金额', '本期应结', '结算状态'],
            'master_details' => ['账户变动时间', '类型', '名称/备注', '收入/支出', '状态'],
            'master_order_list' => ['商品名称', '退款状态', '单价', '数量', '订单号', '支付方式', '买家', '下单时间', '实收款', '订单状态', '收货地址', '联系人姓名', '联系方式'],
        ];
        $this->putCsv1($title, $csv_body, $_csv_header[$csv_header]);
    }

    public function putCsv1($csvFileName, $dataArr, $haderText)
    {
        require(Env::get('root_path') . 'extend/PHPExcel/Classes/PHPExcel.php');
        //文本格式化映射
        $_format_map=[
            'number'=>\PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00,//数字
        ];
        //生成字母表头
        $_letter = range('A', 'Z');
        //判断数据长度
        $data_count = count($haderText);
        //根据数据长度生成字母
        $_title_letter = [];
        for ($i = 0; $i < $data_count; $i++) {
            $_title_letter[] = $_letter[$i];
        }
        // 实例化excel类
        $objPHPExcel = new \PHPExcel();
        // 操作第一个工作表
        $objPHPExcel->setActiveSheetIndex(0);
        // 设置sheet名
        $objPHPExcel->getActiveSheet()->setTitle($csvFileName);
        // 设置表格宽度
        foreach ($_title_letter as $v) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($v)->setWidth(20);
        }
        // 列名表头文字加粗
        $objPHPExcel->getActiveSheet()->getStyle('A1:Z1')->getFont()->setBold(true);
        // 列表头文字居中
        $objPHPExcel->getActiveSheet()->getStyle('A1:Z1')->getAlignment()
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        // 列名赋值
        foreach ($_title_letter as $k => $v) {
            $objPHPExcel->getActiveSheet()->setCellValue($v . '1', $haderText[$k]);
        }
        // 数据起始行
        $row_num = 2;
        // 向每行单元格插入数据
        foreach ($dataArr as $value) {
            // 设置所有垂直居中
            $objPHPExcel->getActiveSheet()->getStyle('A' . $row_num . ':' . 'Z' . $row_num)->getAlignment()
                ->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            // 设置价格为数字格式
//            $objPHPExcel->getActiveSheet()->getStyle('D' . $row_num)->getNumberFormat()
//                ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
            // 居中
            $objPHPExcel->getActiveSheet()->getStyle('A' . $row_num . ':' . 'Z' . $row_num)->getAlignment()
                ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);


            // 设置单元格数值
            foreach ($_title_letter as $k => $v) {
                if(is_array($value[$k])){
                    if(isset($value[$k]['type_format'])){
                        // 设置格式
                        $objPHPExcel->getActiveSheet()->getStyle($v . $row_num)->getNumberFormat()
                            ->setFormatCode($_format_map[$value[$k]['type_format']]);
                    }
                    $objPHPExcel->getActiveSheet()->setCellValue($v . $row_num, $value[$k]['value']);
                }else{
                    $objPHPExcel->getActiveSheet()->setCellValue($v . $row_num, $value[$k]);
                }
            }
            $row_num++;
        }
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;charset=utf-8;filename="' . $csvFileName . '.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');

        exit;
    }

    public function putCsv($csvFileName, $dataArr, $haderText)
    {
        $handle = fopen($csvFileName, "w");//写方式打开
        if (!$handle) {
            return '文件打开失败';
        }
        $header = implode(',', $haderText) . PHP_EOL;
        // 处理内容
        $content = '';
        foreach ($dataArr as $k => $v) {

            $content .= implode(',', $v) . PHP_EOL;
        }
        $csvData = $header . $content;
        // 写入并关闭资源

        header("Content-type:text/csv;");
        header("Content-Disposition:attachment;filename=" . $csvFileName);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $csvContent = iconv("utf-8", "gb2312", $csvData);
        echo $csvContent;
        exit();
    }

    //如果日志文件不存在则创建
    public function mkdirs($dir, $mode = 0766)
    {
        if (is_dir($dir) || @mkdir($dir, $mode)) return TRUE;
        if (!$this->mkdirs(dirname($dir), $mode)) return FALSE;
        return @mkdir($dir, $mode);
    }

    //写入日志
    private function log_file_put($refundLogMsg)
    {
        // 记录入账信息
        $refundLogPath = Env::get('root_path') . 'public/msg/CheckOrder';
        //如果日志文件不存在则创建
        $this->mkdirs($refundLogPath);
        $refundLogPath .= '/CheckOrder.text';
        file_put_contents($refundLogPath, $refundLogMsg, FILE_APPEND);
    }
}