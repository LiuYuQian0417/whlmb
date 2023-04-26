<?php
declare(strict_types = 1);

namespace app\common\service;

use app\common\model\DistributionCity;
use app\common\model\Take;
use think\facade\Env;

/**
 * 运费处理
 * Class Freight
 * @package app\common\service
 */
class Freight
{
    /**
     * 含运费模板id和数量的数据
     * @var array 标识,店铺id,运费模板主表id,数量
     */
    private $args;
    /**
     * 运费模板主id
     * @var array 运费模板主表id集合
     */
    private $freight_id;
    /**
     * 所有店铺id
     * @var
     */
    private $store_id;
    /**
     * 商品id
     * @var
     */
    private $goods_id = [];
    /**
     * 用户定位城市城市&街道id
     * @var
     */
    private static $street_id = 0;
    private static $city_name = 0;
    /**
     * 用户收货地址(lng经度,lat纬度)
     * @var
     */
    private $lng = 0;
    private $lat = 0;

    /**
     * 构造
     * Freight constructor.
     * @param $args
     * @param $address
     */
    public function __construct($args, $address)
    {
        $this->args = $args;
        // 定位失败情况默认为0
        if ($address) {
            // 城市id
            self::$city_name = array_key_exists('city_name', $address) ? $address['city_name'] : '';
            // 街道id
            self::$street_id = $address['street_id'];
            $this->lng = $address['lng'];
            $this->lat = $address['lat'];
        }
        $this->freight_id = array_unique(array_column($args, 'freight_id'));
        $this->store_id = array_column($args, 'store_id');
        foreach ($args as $item) {
            (array_key_exists('goods_id', $item)) ?
                array_push($this->goods_id, $item['goods_id']) :
                array_push($this->goods_id, $item['flag_id']);
        }
    }

    /**
     * 城市级联检测用户定位区域有效性
     * @param $args
     * @param bool $flag 快递邮寄检测true同城速递检测false
     * @return mixed
     */
    private static function areaConvert($args, $flag = true)
    {
        if ($flag) {
            // 不支持全国配送且没有子类模板,不支持快递配送
            if (empty($args['freight_express_list']) && $args['all_address_express'] == 0) {
                return [];
            }
            $data = [
                'freight_express_classify_id' => $args['freight_express_classify_id'],
                'type' => $args['type'],
                'express' => $args['express'],
                'discount_postage_rules' => $args['discount_postage_rules'],
                'discount' => $args['discount'],
                'postage' => $args['postage'],
                'distribution_area_id' => 'all',
            ];
            foreach ($args['freight_express_list'] as $_val) {
                if ($_val['distribution_area_id']) {
                    foreach (explode(',', $_val['distribution_area_id']) as $value) {
                        $valid = [
                            'upper_num' => $_val['upper_num'],
                            'base_amount' => $_val['base_amount'],
                            'extend_num_unit' => $_val['extend_num_unit'],
                            'extend_amount' => $_val['extend_amount'],
                            'distribution_area_id' => $value,
                        ];
                        if (self::$street_id) {
                            if (strpos(strval(rtrim(strval(self::$street_id), '0')), rtrim($value, '0')) === 0 ||
                                strpos(rtrim($value, '0'), strval(rtrim(strval(self::$street_id), '0'))) === 0) {
                                // 首次添加
                                if ($data['distribution_area_id'] == 'all') {
                                    $data = array_merge($data, $valid);
                                } else {
                                    // 地区更细化时才会更改
                                    if ($data['distribution_area_id'] < $value) {
                                        $data = array_merge($data, $valid);
                                    }
                                }
                            }
                        } else {
                            // 不确定其地址
                            $data = array_merge($data, $valid);
                        }
                    }
                }
            }
            if ($data['distribution_area_id'] == 'all') {
                if ($args['all_address_express'] == 1) {
                    // 支持全国配送(使用分类配置并不符合地址)
                    $data['upper_num'] = $args['upper_num'];
                    $data['base_amount'] = $args['base_amount'];
                    $data['extend_num_unit'] = $args['extend_num_unit'];
                    $data['extend_amount'] = $args['extend_amount'];
                } else {
                    $data = [];
                }
            }
            return $data;
        } else {
            if ($args && self::$street_id) {
                foreach (explode(',', $args) as $item) {
                    if (strpos(strval(rtrim(strval(self::$street_id), '0')), rtrim($item, '0')) === 0 ||
                        strpos(rtrim($item, '0'), strval(rtrim(strval(self::$street_id), '0'))) === 0) {
                        return true;
                    }
                }
            }
            return false;
        }
    }

    /**
     * 获取商品快递邮寄运费模板信息
     * @return array
     */
    private function getFreightExpressInfo()
    {
        $freightExpressClassifyModel = app('app\\common\\model\\FreightExpressClassify');
        $data = $freightExpressClassifyModel
            ->alias('fec')
            ->where([
                        ['freight_express_classify_id', 'in', implode(',', array_unique($this->freight_id))],
                    ])
            ->with(['freightExpressList'])
            ->field('fec.freight_express_classify_id,fec.express,fec.store_id,fec.type,fec.all_address_express,
            fec.base_amount,fec.extend_num_unit,fec.extend_amount,fec.upper_num,fec.discount_postage_rules,
            fec.discount,fec.postage')
            ->order(['freight_express_classify_id' => 'asc'])
            ->select();
        return $data ?: [];
    }

    /**
     * 获取同城速递运费模板信息
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getCityInfo()
    {
        $data = (new DistributionCity())
            ->alias('sc')
            ->where([
                        ['sc.store_id', 'in', implode(',', $this->store_id)],
                        ['s.is_city', '=', 1]   // 店铺开启同城配送
                    ])
            ->join('store s', 's.store_id = sc.store_id')
            ->field('sc.distribution_city_id,sc.store_id,sc.type,sc.distribution_type,sc.radius,
            sc.distribution_area_id,sc.distribution_area_name,sc.start_price,sc.basic_freight,sc.staircase,
            sc.starting_radius,sc.lift_weight,sc.distance_increase,sc.distance_increase_price,sc.weight_increase,
            sc.weight_increase_price,s.lng as store_lng,s.lat as store_lat,s.is_city,s.store_name,
            round(st_distance(point(s.lng,s.lat),point(' . $this->lng . ',' . $this->lat . '))*111.195,3) as user_distance,
            sc.discount_postage_rules,sc.discount,sc.postage')
            ->order(['distribution_city_id' => 'asc'])
            ->select();
        return $data ?: [];
    }

    /**
     * 获取商品并关联店铺的物流状态
     * @return array
     */
    private function getGoodsStoreExpress()
    {
        $goodsModel = app('app\\common\\model\\Goods');
        $info = [];
        if ($this->goods_id) {
            $info = $goodsModel
                ->alias('g')
                ->where([['goods_id', 'in', implode(',', $this->goods_id)]])
                ->join('store s', 's.store_id = g.store_id')
                ->field('s.store_id,s.is_express,s.is_pay_delivery,s.is_shop,s.is_city,
                g.goods_id,g.freight_status,g.freight_price,g.express,g.express_one_city,
                g.express_self_lifting,g.default_express_type,s.is_delivery')
                ->select();
        }
        return $info;
    }

    /**
     * 获取店铺自提点数据
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getTakeInfo()
    {
        $data = [];
        if ($this->store_id) {
            $data = (new Take())
                ->alias('t')
                ->with(['takeStore'])
                ->where([
                            ['t.status', '=', 1],
                            ['t.store_id', 'in', implode(',', $this->store_id)],
                            // 是否开启门店自提
                            ['s.is_shop', '=', 1]
                            //                    ['t.city', '=', self::$city_name],
                        ])
                ->join('store s', 's.store_id = t.store_id and s.delete_time is null and s.status = 4 and if(s.end_time,unix_timestamp(s.end_time) > unix_timestamp(),1)')
                ->field('t.take_id,t.store_id,t.take_name,t.contacts_name,t.contacts_phone,t.start_hours,
                t.end_hours,t.week,t.province,t.city,t.area,t.address')
                ->append(['take_store.city_id'])
                ->select();
        }
        return is_object($data) ? $data->toArray() : $data;
    }

    /**
     * 计算运费
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function calculation()
    {
        // 获取商品快递邮寄运费模板信息
        $freightExpressData = self::getFreightExpressInfo();
        // 获取同城速递运费模板信息
        $cityData = ($this->lng && $this->lat) ? self::getCityInfo() : [];
        // 获取商品并关联店铺物流状态
        $expressData = self::getGoodsStoreExpress();
        Env::load(Env::get('app_path') . 'common/ini/.config');
        // 全部商品快递邮寄运费为0,全部商品不支持同城速递且金额为0
        array_walk($this->args, function (&$v) use ($expressData) {
            foreach ($expressData as $_exp) {
                if ($v['goods_id'] == $_exp['goods_id']) {
                    // 默认全部不支持快递邮寄,下文将比较地区是否符合,最终决定是否支持快递邮寄
                    $v['express_freight_sup'] = 0;
                    $v['is_pay_delivery'] = Env::get('is_pay_delivery') ? $_exp['is_pay_delivery'] : 0;
                    // 商品是否免运费
                    if (!$_exp['is_delivery']) {
                        // 固定不支持全国快递
                        $v['express_freight_price'] = -3;
                    } else {
                        if ($_exp['is_express']) {
                            $v['express_freight_price'] = -1;
                        } else {
                            // 快递邮寄固定运费,-2表示固定运费的0元
                            if ($_exp['freight_status'] == 0) {
                                $v['express_freight_price'] = ($_exp['freight_price'] > 0) ? $_exp['freight_price'] : -2;
                            } else {
                                // 运费模板
                                $v['express_freight_price'] = 0;
                            }
                        }
                    }
                    $v['express_post_list'] = $v['city_post_list'] = [];
                    // 店铺是否支持同城速递,不支持下文也不继续操作
                    $v['city_freight_sup'] = $_exp['is_city'];
                    // 同城速递运费,包邮-1(使用商品包邮设置,店铺没有同城包邮设置)
                    $v['city_freight_price'] = $_exp['is_express'] ? -1 : 0;
                    $v['city_freight_msg'] = '';
                    // 店铺是否支持用户门店自提 不支持下文也不继续操作
                    $v['take_freight_sup'] = $_exp['is_shop'];
                    // 默认配送方式   1快递邮寄 2同城速递 3到店自提
                    $v['default_express_type'] = $_exp['default_express_type'];
                }
            }
            // 自提点列表
            $v['take_freight_list'] = [];
            // 自提店铺城市id
            $v['store_city_id'] = null;
        });
        $allowFreightExpressData = [];
        if (count($freightExpressData)) {
            // 筛选符合条件的快递邮寄配送区域
            foreach ($freightExpressData->toArray() as $v) {
                if ($ins = self::areaConvert($v)) {
                    array_push($allowFreightExpressData, $ins);
                }
            }
        }
        if (count($cityData)) {
            // 筛选符合条件的店铺同城配送区域数据
            $cityData = array_filter($cityData->toArray(), function ($v) {
                $chs = false;
                // 按服务半径
                if ($v['distribution_type'] == 1 && $v['is_city']) {
                    // 返回用户和店铺之间的直线距离(两位小数的公里数)
                    $chs = $v['user_distance'] <= $v['radius'];
                }
                // 按行政区域
                if ($v['distribution_type'] == 2 && $v['is_city']) {
                    // 匹配区域有效性
                    $chs = self::areaConvert($v['distribution_area_id'], false);
                }
                return $chs;
            });
        }
        // 进行运费计算,distribution_type配送方式 1同城速递 2预约自提 3快递邮寄
        $list = $cityList = [];
        $takeInfo = self::getTakeInfo();
        $takeInfoStoreId = [];
        if ($takeInfo) {
            $takeInfoStoreId = array_unique(array_column($takeInfo, 'store_id'));
        }
        foreach ($this->args as $key => &$value) {
            // TODO 处理快递邮寄
            if ($value['express_freight_price'] == -1 || $value['express_freight_price'] == -2) {
                // 快递包邮或固定运费0元,将运费置0
                $value['express_freight_sup'] = 1;
                $value['express_freight_price'] = 0;
            } elseif ($value['express_freight_price'] == 0) {
                // 快递模板
                if ($allowFreightExpressData) {
                    foreach ($allowFreightExpressData as $_value) {
                        if ($value['freight_id'] == $_value['freight_express_classify_id']) {
                            $value['express_freight_sup'] = 1;
                            if (!array_key_exists($value['freight_id'], $list)) {
                                $list[$value['freight_id']] = [
                                    'type' => $_value['type'],
                                    'express' => $_value['express'],
                                    'upper_num' => $_value['upper_num'],
                                    'base_amount' => $_value['base_amount'],
                                    'extend_num_unit' => $_value['extend_num_unit'],
                                    'extend_amount' => $_value['extend_amount'],
                                    'shop_total_price' => 0,
                                    'discount_postage_rules' => $_value['discount_postage_rules'],
                                    'discount' => $_value['discount'],
                                    'postage' => $_value['postage'],
                                    'goods' => [],  // 同一模板商品统计计量单位和对应商品id
                                ];
                            }
                            $list[$value['freight_id']]['shop_total_price'] += $value['sub_price'];
                            array_push($list[$value['freight_id']]['goods'], [
                                'goods_id' => $value['goods_id'],
                                'goods_attr' => $value['goods_attr'],
                                'sub_price' => $value['sub_price'], // 减去会员折扣的单价*数量
                                'quantity' => $value['quantity'],
                                'goods_weight' => $value['goods_weight'] * $value['quantity'],
                            ]);
                            if ($_value['discount_postage_rules']) {
                                array_push($value['express_post_list'], [
                                    'discount_postage_rules' => $_value['discount_postage_rules'],
                                    'discount' => $_value['discount'],
                                    'postage' => $_value['postage'],
                                ]);
                            }
                        }
                    }
                }
            } elseif ($value['express_freight_price'] > 0) {
                // 固定运费
                $value['express_freight_sup'] = 1;
                $value['express_freight_price'] *= $value['quantity'];
            } else {
                // 异常价格置0,默认不支持(包括固定不支持快递)
                $value['express_freight_price'] = 0;
            }
            // TODO 支持同城速递并处理同城速递
            if ($value['city_freight_sup']) {
                // 默认不支持
                $value['city_freight_sup'] = 0;
                if ($cityData) {
                    // 启用同城配送模板
                    foreach ($cityData as $_cityData) {
                        if ($value['store_id'] == $_cityData['store_id']) {
                            if (!array_key_exists($value['store_id'], $cityList)) {
                                $cityList[$value['store_id']] = [
                                    'start_price' => $_cityData['start_price'],
                                    'basic_freight' => $_cityData['basic_freight'],
                                    'staircase' => $_cityData['staircase'],
                                    'starting_radius' => $_cityData['starting_radius'],
                                    'lift_weight' => $_cityData['lift_weight'],
                                    'distance_increase' => $_cityData['distance_increase'],
                                    'distance_increase_price' => $_cityData['distance_increase_price'],
                                    'weight_increase' => $_cityData['weight_increase'],
                                    'weight_increase_price' => $_cityData['weight_increase_price'],
                                    'user_distance' => $_cityData['user_distance'],
                                    'shop_total_price' => 0,
                                    'store_id' => $value['store_id'],
                                    'store_name' => $_cityData['store_name'],
                                    'discount_postage_rules' => $_cityData['discount_postage_rules'],
                                    'discount' => $_cityData['discount'],
                                    'postage' => $_cityData['postage'],
                                    'goods' => [],  // 同一模板商品统计重量和对应商品id
                                ];
                            }
                            $cityList[$value['store_id']]['shop_total_price'] += $value['sub_price'];
                            array_push($cityList[$value['store_id']]['goods'], [
                                'goods_id' => $value['goods_id'],
                                'goods_attr' => $value['goods_attr'],
                                'sub_price' => $value['sub_price'], // 减去会员折扣的单价*数量
                                'quantity' => $value['quantity'],
                                'goods_weight' => $value['goods_weight'] * $value['quantity'],
                                'free_flag' => ($value['city_freight_price'] == 0), // 包邮false 不包邮true
                            ]);
                            if ($_cityData['discount_postage_rules']) {
                                array_push($value['city_post_list'], [
                                    'discount_postage_rules' => $_cityData['discount_postage_rules'],
                                    'discount' => $_cityData['discount'],
                                    'postage' => $_cityData['postage'],
                                ]);
                            }
                        }
                    }
                }
            }
            $value['store_city_id'] = '';
            $value['take_freight_list'] = [];
            // TODO 支持门店自提处理自提点
            if ($value['take_freight_sup']) {
                if (in_array($value['store_id'], $takeInfoStoreId)) {
                    foreach ($takeInfo as $_key => $_value) {
                        if ($_value['store_id'] == $value['store_id']) {
                            // 店铺所在城市
                            $value['store_city_id'] = $_value['take_store']['city_id'];
                            array_push($value['take_freight_list'], $_value);
                        }
                    }
                } else {
                    // 没有设置自提点,不支持自提
                    $value['take_freight_sup'] = 0;
                }
            }
        }
        // 包含快递邮寄数据处理
        $action_express_goods = $action_city_goods = $action_city_store = [];
        if ($list) {
            foreach ($list as $list_key => $list_val) {
                // 统计商品重量和数量
                $list_val['goods_weight_count'] = array_sum(array_column($list_val['goods'], 'goods_weight'));
                $list_val['goods_count'] = array_sum(array_column($list_val['goods'], 'quantity'));
                // 模式满包邮优先
                if ($list_val['discount_postage_rules']) {
                    // 开启满包邮设置
                    if ($list_val['discount_postage_rules'] == 2 && $list_val['type'] == 2) {
                        $list_val['discount_postage_rules'] = 3;
                    }
                    $contrastRule = ['shop_total_price', 'goods_count', 'goods_weight_count'][$list_val['discount_postage_rules'] - 1];
                    if ($list_val[$contrastRule] < $list_val['discount']) {
                        // 条件不满足,则进入切换规则
                        $list_val['discount_postage_rules'] = 0;
                    }
                    // 1买家承担运费 2卖家承担运费
                    $list_val['total'] = $list_val['express'] == 1 ? $list_val['postage'] : 0;
                }
                if (!$list_val['discount_postage_rules']) {
                    // 计算开始,先加首运费
                    $list_val['total'] = $list_val['base_amount'];
                    // 计算续件(尺寸大于首N尺寸情况下)
                    if ($list_val[['goods_count', 'goods_weight_count'][$list_val['type'] - 1]] > $list_val['upper_num']
                        && $list_val['upper_num'] > 0 && $list_val['extend_num_unit'] > 0 && $list_val['extend_amount']) {
                        // 附件倍数
                        $leave = ceil(($list_val[['goods_count', 'goods_weight_count'][$list_val['type'] - 1]]
                                          - $list_val['upper_num']) / $list_val['extend_num_unit']);
                        $list_val['total'] += $leave * $list_val['extend_amount'];
                    }
                }
                // 将此模板金额分摊下去
                $sta_price = 0;
                foreach ($list_val['goods'] as $k => $item) {
                    if ($list_val['total'] == 0) {
                        // 包邮
                        $action_express_goods[$item['goods_id'] . $item['goods_attr']] = 0;
                    } else {
                        if ($k == count($list_val['goods']) - 1) {
                            $action_express_goods[$item['goods_id'] . $item['goods_attr']] = $list_val['total'] - $sta_price;
                            break;
                        }
                        if ($list_val['discount_postage_rules']) {
                            $sta_price += $action_express_goods[$item['goods_id'] . $item['goods_attr']] =
                                fmtPrice(($item[['sub_price', 'quantity', 'goods_weight'][$list_val['discount_postage_rules'] - 1]]
                                             / $list_val[['shop_total_price', 'goods_count', 'goods_weight_count'][$list_val['discount_postage_rules'] - 1]])
                                         * $list_val['total']);
                        } else {
                            // 统计商品数量 计量单位 1件 2重量
                            $sta_price += $action_express_goods[$item['goods_id'] . $item['goods_attr']] =
                                fmtPrice($item[$list_val['type'] == 1 ? 'quantity' : 'goods_weight']
                                         / $list_val[$list_val['type'] == 1 ? 'goods_count' : 'goods_weight_count']
                                         * $list_val['total']);
                        }
                    }
                }
            }
        }
        // 包含同城速递数据处理
        if ($cityList) {
            foreach ($cityList as $cityList_key => $cityList_val) {
                // 检测是否符合起送价
                if ($cityList_val['shop_total_price'] >= $cityList_val['start_price']) {
                    // 统计商品重量和数量
                    $cityList_val['goods_weight_count'] = array_sum(array_column($cityList_val['goods'], 'goods_weight'));
                    $cityList_val['goods_count'] = array_sum(array_column($cityList_val['goods'], 'quantity'));
                    // 模式满包邮优先
                    if ($cityList_val['discount_postage_rules']) {
                        // 开启满包邮设置
                        $contrastRule = ['shop_total_price', 'goods_count', 'goods_weight_count'][$cityList_val['discount_postage_rules'] - 1];
                        if ($cityList_val[$contrastRule] < $cityList_val['discount']) {
                            // 条件不满足,则进入切换规则
                            $cityList_val['discount_postage_rules'] = 0;
                        }
                        $cityList_val['total'] = $cityList_val['postage'];
                    }
                    if (!$cityList_val['discount_postage_rules']) {
                        // 计算开始,先加基础运费
                        $cityList_val['total'] = $cityList_val['basic_freight'];
                        // 阶梯价格开启
                        if ($cityList_val['staircase'] && $cityList_val['distance_increase']) {
                            //阶梯倍数[距离阶梯]
                            $laseRadius = ($cityList_val['user_distance'] - floatval($cityList_val['starting_radius']));
                            $leave = ceil(($laseRadius <= 0 ? 0 : $laseRadius) / floatval($cityList_val['distance_increase']));
                            $cityList_val['total'] += $leave * $cityList_val['distance_increase_price'];
                            //阶梯倍数[重量阶梯]
                            $laseWeight = ($cityList_val['goods_weight_count'] - floatval($cityList_val['lift_weight']));
                            if ($cityList_val['weight_increase'] > 0) {
                                $weightLeave = ceil(($laseWeight <= 0 ? 0 : $laseWeight) / $cityList_val['weight_increase']);
                                $cityList_val['total'] += $weightLeave * $cityList_val['weight_increase_price'];
                            }
                        }
                    }
                    // 将此模板金额分摊下去
                    $sta_price = 0;
                    foreach ($cityList_val['goods'] as $k => $item) {
                        if (!$item['free_flag'] || $cityList_val['total'] == 0) {
                            // 包邮
                            $action_city_goods[$item['goods_id'] . $item['goods_attr']] = 0;
                        } else {
                            if ($k == count($cityList_val['goods']) - 1) {
                                $action_city_goods[$item['goods_id'] . $item['goods_attr']] = $cityList_val['total'] - $sta_price;
                                break;
                            }
                            if ($cityList_val['discount_postage_rules']) {
                                $sta_price += $action_city_goods[$item['goods_id'] . $item['goods_attr']] =
                                    fmtPrice(($item[['sub_price', 'quantity', 'goods_weight'][$cityList_val['discount_postage_rules'] - 1]]
                                                 / $cityList_val[['shop_total_price', 'goods_count', 'goods_weight_count'][$cityList_val['discount_postage_rules'] - 1]])
                                             * $cityList_val['total']);
                            } else {
                                // 默认是按重量分配,若重量数为0,则按件数分配
                                $sta_price += $action_city_goods[$item['goods_id'] . $item['goods_attr']] =
                                    fmtPrice($item[$cityList_val['goods_weight_count'] > 0 ? 'goods_weight' : 'quantity']
                                             / $cityList_val[$cityList_val['goods_weight_count'] > 0 ? 'goods_weight_count' : 'goods_count']
                                             * $cityList_val['total']);
                            }
                        }
                    }
                } else {
                    // 不足起送价
                    $action_city_store[$cityList_val['store_id']] = sprintf("店铺【%s】不足起送价%s", $cityList_val['store_name'], $cityList_val['start_price']);
                }
            }
        }
        foreach ($this->args as &$_args) {
            if ($action_express_goods) {
                foreach ($action_express_goods as $_action_goods_key => $_action_goods_val) {
                    if ($_args['goods_id'] . $_args['goods_attr'] == $_action_goods_key) {
                        $_args['express_freight_price'] = $_action_goods_val;
                    }
                }
            }
            $_args['city_freight_sup'] = 0;
            $_args['city_freight_price'] = 0;
            if (!empty($action_city_goods)) {
                foreach ($action_city_goods as $_action_goods_key => $_action_goods_val) {
                    if ($_args['goods_id'] . $_args['goods_attr'] == $_action_goods_key) {
                        $_args['city_freight_sup'] = 1;
                        $_args['city_freight_price'] = $_action_goods_val;
                    }
                }
            }
            if (!empty($action_city_store)) {
                foreach ($action_city_store as $_action_store_key => $_action_store_val) {
                    if ($_args['store_id'] == $_action_store_key) {
                        $_args['city_freight_msg'] = $_action_store_val;
                    }
                }
            }
        }
        array_walk($this->args, function (&$v) {
            $v['express_freight_price'] = fmtPrice($v['express_freight_price']);
            $v['city_freight_price'] = fmtPrice($v['city_freight_price']);
        });
        return $this->args;
    }

}