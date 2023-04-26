<?php
declare(strict_types=1);

namespace app\interfaces\controller\shopping;

use app\common\model\Cart as CartModel;
use app\common\model\CollectGoods;
use app\common\model\Goods;
use app\common\model\GoodsAttr;
use app\common\model\GoodsClassify;
use app\common\model\MemberAddress;
use app\common\model\MemberCoupon;
use app\common\model\MemberPacket;
use app\common\model\Products;
use app\common\model\Store;
use app\common\service\Freight;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;

/**
 * 购物车 - Joy
 * Class Cart
 * @package app\interfaces\controller\goods
 */
class Cart extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    /**
     * 加入购物车
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param Store $store
     * @param Goods $goodsModel
     * @param Products $products
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(RSACrypt $crypt,
                           CartModel $cartModel,
                           Store $store,
                           Goods $goodsModel,
                           Products $products)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $cartModel->valid($param, 'create');
        // 检查购物车
        $cart = $cartModel
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['goods_id', '=', $param['goods_id']],
                    ['goods_attr', '=', $param['goods_attr']],
                ]
            )
            ->field('cart_id,number')
            ->find();
        // 查看是否是本店商品
        $member_id = $store
            ->where(
                [
                    ['store_id', '=', $param['store_id']],
                ]
            )
            ->value('member_id');
        // 判断是否是本店商品
        if ($member_id == $param['member_id'])
        {
            return $crypt->response(
                [
                    'code'    => -100,
                    'message' => '不能购买自己的商品',
                ],
                TRUE
            );
        }
        $param['discount'] = discount($param['member_id']) ?: '100';
        // 判断是否库是否够
        if ($param['goods_attr'])
        {
            $goods = $products
                ->alias('p')
                ->join('goods g', 'p.goods_id = g.goods_id')
                ->where(
                    [
                        ['p.goods_id', '=', $param['goods_id']],
                        ['goods_attr', '=', $param['goods_attr']],
                    ]
                )
                ->field(
                    'p.products_id,attr_goods_number,attr_shop_price as shop_price,attr_goods_weight as goods_weight,
                p.goods_id,is_group,is_bargain,is_limit,g.is_vip,g.is_group,g.is_bargain,g.is_limit'
                )
                ->find();
            $param['products_id'] = $goods['products_id'];
            // 判断库存是否够
            if (($goods['attr_goods_number'] - $cart['number']) < $param['number'])
            {
                return $crypt->response(
                    [
                        'code'    => -1,
                        'message' => '商品库存不够,请改变数量再添加',
                    ],
                    TRUE
                );
            }
        } else
        {
            // 查看商品价格和库存
            $goods = $goodsModel
                ->where(
                    [
                        ['goods_id', '=', $param['goods_id']],
                    ]
                )
                ->field('shop_price,goods_number,goods_weight,is_group,is_bargain,is_limit,is_vip')
                ->find();
            // 判断库存是否够
            if (($goods['goods_number'] - $cart['number']) < $param['number'])
            {
                return $crypt->response(
                    [
                        'code'    => -1,
                        'message' => '商品库存不够,请改变数量再添加',
                    ],
                    TRUE
                );
            }
        }
        // 计算价格
        $price = ($param['price'] = $goods['shop_price']) * (!$goods['is_vip'] ? 1 : $param['discount'] / 100);
        $param['discount_price'] = $goods['shop_price'] - $price;
        // 判断活动是否开启
        if (Env::get('is_group', 1) == 0 && $goods['is_group'] == 1)
        {
            return $crypt->response(
                [
                    'code'    => -1,
                    'message' => '拼团活动已关闭，暂时不能添加',
                ],
                TRUE
            );
        }
        if (Env::get('is_cut', 1) == 0 && $goods['is_bargain'] == 1)
        {
            return $crypt->response(
                [
                    'code'    => -2,
                    'message' => '砍价活动已关闭，暂时不能添加',
                ],
                TRUE
            );
        }
        if (Env::get('is_limit', 1) == 0 && $goods['is_limit'] == 1)
        {
            return $crypt->response(
                [
                    'code'    => -3,
                    'message' => '限时抢购活动已关闭，暂时不能添加',
                ],
                TRUE
            );
        }
        // 库存计算
        // $number = (($goods['goods_number'] - $cart['number']) < $param['number']) ? 0 : $param['number'];
        $param['goods_weight'] = $goods['goods_weight'];
        // 判断是更新数量还是新增
        if ($cart['cart_id'])
        {
            $cartModel
                ->where(
                    [
                        ['member_id', '=', $param['member_id']],
                        ['goods_id', '=', $param['goods_id']],
                        ['goods_attr', '=', $param['goods_attr']],
                    ]
                )
                ->setInc('number', $param['number']);
        } else
        {
            $cartModel->allowField(TRUE)->save($param);
        }
        return $crypt->response(
            [
                'code'    => 0,
                'message' => '添加成功',
            ],
            TRUE
        );
    }

    /**
     * 购物车列表
     * @param RSACrypt $crypt
     * @param CartModel $cart
     * @param Goods $goods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          CartModel $cart,
                          Goods $goods)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $condition[] = ['c.member_id', '=', $param['member_id']];
        if ($param['store_id'])
        {
            $condition[] = ['c.store_id', '=', $param['store_id']];
        }
        $base = $cart
            ->alias('c')
            ->where($condition)
            ->field(
                'c.cart_id,c.store_id,c.goods_id,c.goods_name,c.file,g.market_price,
            c.file as cart_file,c.number,ifnull(p.attr_shop_price,g.shop_price) as price,
            g.goods_classify_id,c.products_id,c.attr,c.goods_attr,g.is_group,g.is_bargain,g.is_limit'
            )
            ->append(
                [
                    'inventory',
                    'store_name',
                    'store_status',
                    'collect_status',
                    'coupon_status',
                    'goods_classify_id',
                ]
            )
            ->order(['c.create_time' => 'desc']);
        // 有效购物车
        $result = $base
            ->join('products p', 'p.products_id = c.products_id', 'left')
            ->join('goods g', 'g.goods_id = c.goods_id and ' . self::$goodsAuthSql)
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->select();
        $lostWhere = [];
        if (!$result->isEmpty())
        {
            if (!empty($valid = array_column($result->toArray(), 'cart_id')))
            {
                $lostWhere = [
                    ['c.cart_id', 'not in', implode(',', $valid)],
                ];
            }
            $result = $this->cartGrouping($result->toArray(), 'list', 'store_id', 'store_name', 'coupon_status');
        }
        // 无效购物车
        $lose = $base
            ->removeOption('join')
            ->join('products p', 'p.products_id = c.products_id', 'left')
            ->join('goods g', 'g.goods_id = c.goods_id')
            ->join('store s', 's.store_id = g.store_id')
            ->where($lostWhere)
            ->select();
        // 折扣
        $discount = discount($param['member_id']) ?: '100';
        $recommend = recommend_list($goods, 4, $param['member_id']);
        return $crypt->response(
            [
                'code'           => 0,
                'message'        => '查询成功',
                'is_coupon'      => Env::get('is_coupon', 1),
                'result'         => $result,
                'lost'           => $lose,
                'lost_count'     => count($lose),
                'recommend_list' => $recommend,
                'discount'       => $discount,
            ],
            TRUE
        );
    }

    /**
     * 购物车控制增加数量
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param Products $products
     * @param Goods $goodsModel
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add_number(RSACrypt $crypt,
                               CartModel $cartModel,
                               Products $products,
                               Goods $goodsModel)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $cartModel->valid($param, 'add_number');
        // 检查购物车
        $cart = $cartModel
            ->where(
                [
                    ['cart_id', '=', $param['cart_id']],
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->field('cart_id,number,goods_id,goods_attr')
            ->find();
        if ($cart['goods_attr'])
        {
            $number = $products
                ->where(
                    [
                        ['goods_id', '=', $cart['goods_id']],
                        ['goods_attr', '=', $cart['goods_attr']],
                    ]
                )
                ->value('attr_goods_number');
        } else
        {
            $number = $goodsModel
                ->where(
                    [
                        ['goods_id', '=', $cart['goods_id']],
                    ]
                )
                ->value('goods_number');
        }
        // 判断库存是否够
        if (($number - $cart['number']) < $param['number'])
        {
            return $crypt->response(
                [
                    'code'    => -1,
                    'message' => '商品库存不够,请改变数量再添加',
                ],
                TRUE
            );
        }
        // 新增
        $cartModel
            ->where(
                [
                    ['cart_id', '=', $param['cart_id']],
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->setInc('number', $param['number']);
        return $crypt->response(
            [
                'code'    => 0,
                'message' => '增加成功',
            ],
            TRUE
        );
    }


    /**
     * 购物车控制减少数量
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function reduce_number(RSACrypt $crypt,
                                  CartModel $cartModel)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $cartModel->valid($param, 'reduce_number');
        // 查询数量
        $number = $cartModel
            ->where(
                [
                    ['cart_id', '=', $param['cart_id']],
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->value('number');
        if ($number == 1)
        {
            return $crypt->response(
                [
                    'code'    => -1,
                    'message' => '数量剩余1,,请勿继续减少商品数量',
                ],
                TRUE
            );
        }
        // 增减
        $cartModel
            ->where(
                [
                    ['cart_id', '=', $param['cart_id']],
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->setDec('number', $param['number']);
        return $crypt->response(
            [
                'code'    => 0,
                'message' => '减少成功',
            ],
            TRUE
        );
    }

    /**
     * 购物车删除
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @return mixed
     */
    public function delete(RSACrypt $crypt,
                           CartModel $cartModel)
    {
        $param = $crypt->request();
        $cartModel->valid($param, 'delete');
        // 删除
        $cartModel::destroy($param['cart_id'], TRUE);
        return $crypt->response(
            [
                'code'    => 0,
                'message' => '删除成功',
            ],
            TRUE
        );
    }

    /**
     * 购物车编辑
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param Products $products
     * @param Goods $goodsModel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update(RSACrypt $crypt,
                           CartModel $cartModel,
                           Products $products,
                           Goods $goodsModel)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $cartModel->valid($param, 'update');
        if ($param['goods_attr'])
        {
            $number = $products
                ->where(
                    [
                        ['goods_id', '=', $param['goods_id']],
                        ['goods_attr', '=', $param['goods_attr']],
                    ]
                )
                ->value('attr_goods_number');
        } else
        {
            $number = $goodsModel
                ->where(
                    [
                        ['goods_id', '=', $param['goods_id']],
                    ]
                )
                ->value('goods_number');
        }
        // 判断库存是否够
        if ($number < $param['number'])
        {
            return $crypt->response(
                [
                    'code'    => -100,
                    'message' => '商品库存不够,请改变数量再添加',
                ],
                TRUE
            );
        }
        // 查询是否有相同属性的购物车商品
        $same = $cartModel
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['goods_id', '=', $param['goods_id']],
                    ['goods_attr', '=', $param['goods_attr']],
                    ['cart_id', '<>', $param['cart_id']],
                ]
            )
            ->field('cart_id,number')
            ->find();
        if (!is_null($same))
        {
            // $param['number'] += $same['number'];
            $same->number += $param['number'];
            $same->save();
        } else
        {
            // 更新
            $cartModel
                ->allowField(TRUE)
                ->isUpdate(TRUE)
                ->save($param);
        }
        return $crypt->response(
            [
                'code'    => 0,
                'message' => '编辑成功',
            ]
        );

    }

    /**
     * 商品收藏
     * @param RSACrypt $crypt
     * @param CollectGoods $collectGoods
     * @param CartModel $cart
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function collect(RSACrypt $crypt,
                            CollectGoods $collectGoods,
                            CartModel $cart)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $collectGoods->valid($param, 'collect_cart');
        // 取出已收藏的数据
        $goods_id = $collectGoods
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->column('goods_id');
        $arr = [];
        foreach (explode(',', $param['goods_id']) as $value)
        {
            if (!in_array($value, $goods_id))
            {
                $arr[] = [
                    'member_id' => $param['member_id'],
                    'goods_id'  => $value,
                ];
            }
        }
        Db::startTrans();
        // 批量新增
        if (!empty($arr))
        {
            $collectGoods
                ->allowField(TRUE)
                ->isUpdate(FALSE)
                ->saveAll($arr);
        }
        // 删除购物车选中数据
        $cart
            ->where(
                [
                    ['cart_id', 'in', $param['cart_id']],
                ]
            )
            ->delete(TRUE);
        Db::commit();
        return $crypt->response(
            [
                'code'    => 0,
                'message' => '收藏成功',
            ],
            TRUE
        );

    }

    /**
     * 商品规格
     * @param RSACrypt $crypt
     * @param Goods $goods
     * @param GoodsAttr $goodsAttr
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function attr(RSACrypt $crypt,
                         Goods $goods,
                         GoodsAttr $goodsAttr)
    {
        $param = $crypt->request();
        $goods->valid($param, 'attr');
        $goodsData = $goods
            ->where(
                [
                    ['goods_id', '=', $param['goods_id']],
                ]
            )
            ->field('attr_type_id,goods_number,goods_id')
            ->find();
        // 商品规格
        $attrCollect = [];
        $attrArr = $goodsAttr
            ->alias('ga')
            ->where(
                [
                    ['ga.goods_id', '=', $goodsData['goods_id']],
                ]
            )
            ->join('attr a', 'a.attr_id = ga.attr_id')
            ->field('ga.goods_attr_id,ga.attr_value,a.attr_id,a.attr_name')
            ->order(['ga.attr_id' => 'asc'])
            ->select();
        if (!$attrArr->isEmpty())
        {
            foreach ($attrArr as $item)
            {
                if (!array_key_exists($item['attr_id'], $attrCollect))
                {
                    $attrCollect[$item['attr_id']] = [
                        'attr_id'    => $item['attr_id'],
                        'attr_name'  => $item['attr_name'],
                        'goods_attr' => [],
                    ];
                }
                array_push(
                    $attrCollect[$item['attr_id']]['goods_attr'],
                    [
                        'attr_id'       => $item['attr_id'],
                        'attr_value'    => $item['attr_value'],
                        'goods_attr_id' => $item['goods_attr_id'],
                    ]
                );
            }
        }
        $result['attr'] = $attrCollect ? array_values($attrCollect) : [];
        $result['goods_number'] = $goodsData['goods_number'];
        return $crypt->response(
            [
                'code'   => 0,
                'result' => $result,
            ],
            TRUE
        );

    }

    /**
     * 加入购物车 - 确认订单
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @param CartModel $cartModel
     * @param MemberCoupon $memberCoupon
     * @param MemberPacket $memberPacket
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function confirm_order(RSACrypt $crypt,
                                  MemberAddress $memberAddress,
                                  CartModel $cartModel,
                                  MemberCoupon $memberCoupon,
                                  MemberPacket $memberPacket,
                                  GoodsClassify $goodsClassify)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $cartModel->valid($param, 'confirm_order');
        $condition[] = ['member_id', '=', $param['member_id']];
        if ($param['member_address_id'])
        {
            $condition[] = ['member_address_id', '=', $param['member_address_id']];
        }
        // 默认地址
        $address = $memberAddress
            ->where($condition)
            ->field('member_address_id,name,phone,province,city,area,street,address,lat,lng')
            ->append(['address_info'])
            ->order(['is_default' => 'desc', 'member_address_id' => 'asc'])
            ->find();
        $result = $cartModel
            ->alias('c')
            ->where(
                [
                    ['cart_id', 'in', $param['cart_id']],
                ]
            )
            ->join('goods g', 'g.goods_id = c.goods_id')
            ->join('products p', 'p.products_id = c.products_id', 'left')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->field(
                'c.cart_id,c.store_id,c.goods_id,c.goods_name,c.file,ifnull(p.attr_shop_price,g.shop_price) as price
            ,c.number,c.products_id,c.discount,c.attr,c.goods_attr,c.goods_weight,g.goods_classify_id,g.freight_id,
            g.is_freight,s.logo,c.discount_price,s.is_added_value_tax'
            )
            ->order('goods_id', 'desc')
            ->append(['distribution', 'store_name', 'is_added_value_tax'])
            ->select()
            ->toArray();
        // 优惠券数组
        $coupon = $classifyArr = [];
        $memberPacketPrice = $dealPrice = 0;
        // 转格式
        $result = $this->cartGrouping(
            $result,
            'list',
            'store_id',
            'store_name',
            'logo',
            'distribution',
            'is_added_value_tax'
        );

        $frightArgs = [];
        // 计算金额 优惠券赋值
        foreach ($result as $key => &$value)
        {

            // 店铺总金额
            $dealPrice += $value['total_price'] = $this->total_price($value['list']);
            // 店铺商品总数量
            $value['total_number'] = $this->total_number($value['list']);
            $store_coupon = [];
            // 开启优惠券
            if (Env::get('is_coupon', 1) == 1)
            {
                // 店铺优惠券列表
                $store_coupon = $memberCoupon
                    ->where(
                        [
                            ['store_id', '=', $value['store_id']],
                            ['member_id', '=', $param['member_id']],
                            ['full_subtraction_price', '<=', $value['total_price']],
                            ['status', '=', 0],
                            ['start_time', '<=', date('Y-m-d')],
                            ['end_time', '>=', date('Y-m-d')],
                            ['type', '=', 0],
                        ]
                    )
                    ->field('member_coupon_id,title,store_id,actual_price,full_subtraction_price,start_time,end_time')
                    ->order(['actual_price' => 'desc', 'full_subtraction_price' => 'asc'])
                    ->find();
            }
            $_classifyArr = [];
            foreach ($value['list'] as $_key => $_val)
            {
                if ($getClassify = getParCate($_val['goods_classify_id'], $goodsClassify))
                {
                    if ($cid = reset($getClassify)['goods_classify_id'])
                    {
                        array_push(
                            $_classifyArr,
                            [
                                'goods_id' => $_val['goods_id'],
                                'cid'      => $cid,
                                'price'    => $_val['price'] * $_val['number'],
                            ]
                        );
                    }
                }
                array_push(
                    $frightArgs,
                    [
                        'flag_id'      => $_val['cart_id'],
                        'goods_id'     => $_val['goods_id'],
                        'goods_attr'   => is_null($_val['goods_attr']) ? '' : $_val['goods_attr'],
                        'store_id'     => $_val['store_id'],
                        'freight_id'   => $_val['freight_id'],
                        'is_freight'   => $_val['is_freight'],
                        'quantity'     => $_val['number'],
                        'sub_price'    => fmtPrice(($_val['price']) * $_val['number']),
                        'goods_weight' => $_val['goods_weight'],
                    ]
                );
            }
            // 存在就存放
            if ($store_coupon)
            {
                $store_coupon['state'] = 'store';
                $coupon[] = $store_coupon;
                $memberPacketPrice += $store_coupon['actual_price'];
                $sic = 0;
                foreach ($_classifyArr as $_k => &$_v)
                {
                    $last = 0;
                    // 循环的最后一步
                    if (count($_classifyArr) == $_k + 1)
                    {
                        $last = fmtPrice($store_coupon['actual_price'] - $sic);
                    }
                    if ($last === 0)
                    {
                        $sic += $last = floor(
                                $_v['price'] / $value['total_price'] * $store_coupon['actual_price'] * 100
                            ) / 100;
                    } else
                    {
                        $_v['price'] -= $last;
                    }
                }
            }
            $classifyArr = array_merge($classifyArr, $_classifyArr);
        }
        $freightData = [];
        if ($frightArgs)
        {
            $freightService = new Freight(
                $frightArgs, !is_null($address) ? [
                'street_id' => $address['address_info']['street_id'] ?: $address['address_info']['area_id'],
                'city_name' => $address['city'],
                'lng'       => $address['lng'],
                'lat'       => $address['lat'],
            ] : []
            );
            $freightData = $freightService->calculation();
        }
        foreach ($result as &$_item)
        {
            $_item['freight'] = [
                'express_freight_sup'   => 0,
                'express_freight_price' => 0.00,
                'city_freight_sup'      => 0,
                'city_freight_price'    => 0.00,
                'is_pay_delivery'       => 0,
                'take_freight_sup'      => 0,
                'take_freight_list'     => [],
                'default_express_type'  => 0,
                'city_freight_msg'      => '',
            ];
            if ($freightData)
            {
                foreach ($freightData as $_val)
                {
                    if ($_val['store_id'] == $_item['store_id'])
                    {
                        if ($_val['express_freight_sup'])
                        {
                            $_item['freight']['express_freight_sup'] = 1;
                        }
                        if (!$_item['freight']['default_express_type'])
                        {
                            $_item['freight']['default_express_type'] = $_val['default_express_type'];
                        }
                        $_item['freight']['express_freight_price'] += $_val['express_freight_price'];
                        $_item['freight']['express_freight_price'] = fmtPrice(
                            $_item['freight']['express_freight_price']
                        );
                        if ($_val['city_freight_sup'])
                        {
                            $_item['freight']['city_freight_sup'] = 1;
                        }
                        $_item['freight']['city_freight_price'] += $_val['city_freight_price'];
                        $_item['freight']['city_freight_price'] = fmtPrice($_item['freight']['city_freight_price']);
                        if ($_val['take_freight_sup'])
                        {
                            $_item['freight']['take_freight_sup'] = 1;
                        }
                        if (empty($_item['freight']['take_freight_list']) && $_val['take_freight_list'])
                        {
                            $_item['freight']['take_freight_list'] = $_val['take_freight_list'];
                        }
                        if ($_val['is_pay_delivery'])
                        {
                            $_item['freight']['is_pay_delivery'] = 1;
                        }
                        $_item['freight']['city_freight_msg'] = $_val['city_freight_msg'];
                    }
                }
            }
        }
        $newClassifyArr = [];
        if ($classifyArr)
        {
            // 整理同分类价格累和
            foreach ($classifyArr as $item)
            {
                if (!array_key_exists($item['cid'], $newClassifyArr))
                {
                    $newClassifyArr[$item['cid']] = 0;
                }
                $newClassifyArr[$item['cid']] += $item['price'];
            }
        }
        if ($newClassifyArr)
        {
            $where = [];
            foreach ($newClassifyArr as $_newClassifyArr_key => $_newClassifyArr_val)
            {
                $where[] = '(mc.full_subtraction_price <= ' . $_newClassifyArr_val . ' and find_in_set(\'' . $_newClassifyArr_key . '\',c.classify_str))';
            }
            $where = implode(' or ', $where);
            $common_coupon = [];
            // 开启优惠券
            if (Env::get('is_coupon') == 1)
            {
                // 平台优惠券
                $common_coupon = $memberCoupon
                    ->alias('mc')
                    ->where(
                        [
                            ['mc.member_id', '=', $param['member_id']],
                            ['mc.status', '=', 0],
                            ['mc.start_time', '<=', date('Y-m-d')],
                            ['mc.end_time', '>=', date('Y-m-d')],
                            ['mc.type', '=', 1],
                        ]
                    )
                    ->whereRaw($where)
                    ->join('coupon c', 'c.coupon_id = mc.coupon_id')
                    ->field(
                        'mc.member_coupon_id,mc.title,mc.actual_price,
                    mc.full_subtraction_price,mc.start_time,mc.end_time,c.classify_str'
                    )
                    ->order(['actual_price' => 'desc', 'full_subtraction_price' => 'asc'])
                    ->select();
            }
            // 存在就存放
            if ($common_coupon_count = count($common_coupon))
            {
                $common_coupon = $common_coupon->toArray();
                if ($common_coupon_count != 1)
                {
                    // 进行排序,按优惠券金额倒序
                    array_multisort(array_column($common_coupon, 'actual_price'), SORT_DESC, $common_coupon);
                }
                $cc = reset($common_coupon);
                $memberPacketPrice += $cc['actual_price'];
                $cc['state'] = 'platform';
                $coupon[] = $cc;
            }
        }
        // 红包
        $dealPrice -= $memberPacketPrice;
        $packet = [];
        // 开启红包
        if (Env::get('is_red_packet', 1) == 1)
        {
            $packet = $memberPacket
                ->where(
                    [
                        ['status', '=', 0],
                        ['member_id', '=', $param['member_id']],
                        ['start_time', '<=', date('Y-m-d H:i:s')],
                        ['end_time', '>=', date('Y-m-d H:i:s')],
                    ]
                )
                ->field(
                    'member_packet_id,actual_price,title,abs(actual_price - ' . $dealPrice . ') as sort_price,
                date_format(start_time,"%Y-%m-%d") as start_time,date_format(end_time,"%Y-%m-%d") as end_time'
                )
                ->order(['actual_price' => 'desc'])
                ->select();
        }
        if ($packet)
        {
            $packet = $packet->toArray();
            array_multisort(array_column($packet, 'sort_price'), SORT_ASC, $packet);
        }
        return $crypt->response(
            [
                'code'         => 0,
                'message'      => '查询成功',
                'address'      => $address ?: json([]),
                'result'       => $result,
                'coupon'       => $coupon,
                'coupon_price' => fmtPrice($memberPacketPrice),
                'packet'       => $packet,
            ],
            TRUE
        );

    }

    /**
     * 立即购买 - 确认订单
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @param Store $store
     * @param MemberCoupon $memberCoupon
     * @param MemberPacket $memberPacket
     * @param Goods $goodsModel
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common_confirm_order(RSACrypt $crypt,
                                         MemberAddress $memberAddress,
                                         Store $store,
                                         MemberCoupon $memberCoupon,
                                         MemberPacket $memberPacket,
                                         Goods $goodsModel,
                                         GoodsClassify $goodsClassify)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $store->valid($param, 'common_confirm_order');
        $condition[] = ['member_id', '=', $param['member_id']];
        if (array_key_exists('member_address_id', $param) && $param['member_address_id'])
        {
            $condition[] = ['member_address_id', '=', $param['member_address_id']];
        }
        // 默认地址
        $address = $memberAddress
            ->where($condition)
            ->field('member_address_id,name,phone,province,city,area,street,address,lat,lng')
            ->append(['address_info'])
            ->order(['is_default' => 'desc', 'member_address_id' => 'asc'])
            ->find();
        // 商品详情
        $goods_info = $goodsModel
            ->alias('g')
            ->where(
                [
                    ['g.goods_id', '=', $param['goods_id']],
                ]
            )
            ->whereRaw(self::$goodsAuthSql)
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->join(
                'products p',
                'p.goods_id = g.goods_id and products_id = ' .
                ($param['products_id'] ?: 'null'),
                'left'
            )
            ->field(
                'g.goods_id,g.freight_id,g.goods_weight,g.is_freight,g.goods_classify_id,g.shop_price,
                    p.products_id,p.goods_attr,p.attr_shop_price,p.attr_goods_weight,g.is_group,g.is_bargain,
                    g.is_limit,g.is_vip,s.store_id,s.store_name,s.logo,s.is_pay_delivery,s.is_city,s.is_shop,
                    s.is_express,s.is_added_value_tax,g.group_price,g.cut_price,g.time_limit_price,p.attr_group_price,
                    p.attr_time_limit_price'
            )
            ->find();
        if (is_null($goods_info))
        {
            return $crypt->response(
                [
                    'code'    => -1,
                    'message' => '商品已下架',
                ],
                TRUE
            );
        }
        $discount = discount($param['member_id']) ?: '100';     // 折扣
        $discount_price = 0;
        if (!$goods_info['is_limit'] && !$goods_info['is_bargain'] && !$goods_info['is_group']
            || (array_key_exists('is_original', $param) && $param['is_original']))
        {
            $discount_price = $goods_info['is_vip'] ? (100 - $discount) *
                ($param['products_id'] ? $goods_info['attr_shop_price'] : $goods_info['shop_price']) / 100 : 0; // 折扣优惠价
        }
        $goods_info['original_price'] = $goods_info['products_id'] ? $goods_info['attr_shop_price'] : $goods_info['shop_price'];
        $goods_info->hidden(['shop_price', 'attr_shop_price']);
        $freightInt = [
            'args'    => [
                [
                    'goods_id'     => $goods_info['goods_id'],
                    'store_id'     => $goods_info['store_id'],
                    'goods_attr'   => $goods_info['goods_attr'] ?: '',
                    'is_freight'   => $goods_info['is_freight'],
                    'freight_id'   => $goods_info['freight_id'],
                    'sub_price'    => $total_price = fmtPrice(
                        ($goods_info['original_price'] - $discount_price) * $param['number']
                    ),
                    'quantity'     => $param['number'],
                    'goods_weight' => is_null($goods_info['attr_goods_weight']) ?
                        $goods_info['goods_weight'] : $goods_info['attr_goods_weight'],
                ],
            ],
            'address' => [],
        ];
        if ($address)
        {
            $freightInt['address'] = [
                'street_id' => $address['address_info']['street_id'] ?: $address['address_info']['area_id'],
                'city_name' => $address['city'],
                'lat'       => $address['lat'],
                'lng'       => $address['lng'],
            ];
        }
        $freightService = app('app\\common\\service\\Freight', $freightInt);
        $freightInfo = $freightService->calculation();
        $packet = $coupon = $_classifyArr = [];
        $memberPacketPrice = 0;
        // 非活动商品才可以进行优惠(优惠券,红包)
        if (!$goods_info['is_group'] && !$goods_info['is_bargain'] && !$goods_info['is_limit']
            || (array_key_exists('is_original', $param) && $param['is_original']))
        {
            $store_coupon = [];
            // 开启优惠券功能
            if (Env::get('is_coupon', 1) == 1)
            {
                // 店铺优惠券列表[因为店铺没有分类,拔高选择]
                $store_coupon = $memberCoupon
                    ->where(
                        [
                            ['store_id', '=', $param['store_id']],
                            ['member_id', '=', $param['member_id']],
                            [
                                'full_subtraction_price',
                                '<=',
                                fmtPrice($goods_info['original_price'] * $param['number']),
                            ],
                            ['status', '=', 0],
                            ['start_time', '<=', date('Y-m-d')],
                            ['end_time', '>=', date('Y-m-d')],
                            ['type', '=', 0],
                        ]
                    )
                    ->field('member_coupon_id,title,store_id,actual_price,full_subtraction_price,start_time,end_time')
                    ->order(['actual_price' => 'desc', 'full_subtraction_price' => 'asc'])
                    ->find();
            }
            if ($getClassify = getParCate($goods_info['goods_classify_id'], $goodsClassify))
            {
                if ($cid = reset($getClassify)['goods_classify_id'])
                {
                    array_push(
                        $_classifyArr,
                        [
                            'goods_id' => $goods_info['goods_id'],
                            'cid'      => $cid,
                            'price'    => $total_price,
                        ]
                    );
                }
            }
            // 存在就存放
            if ($store_coupon)
            {
                $store_coupon['state'] = 'store';
                $coupon[] = $store_coupon;
                $memberPacketPrice += $store_coupon['actual_price'];
                $sic = 0;
                foreach ($_classifyArr as $_k => &$_v)
                {
                    $last = 0;
                    // 循环的最后一步
                    if (count($_classifyArr) == $_k + 1)
                    {
                        $last = number_format($store_coupon['actual_price'] - $sic, 2, '.', '');
                    }
                    if ($last === 0)
                    {
                        $sic += $last = floor($_v['price'] / $total_price * $store_coupon['actual_price'] * 100) / 100;
                    } else
                    {
                        $_v['price'] -= $last;
                    }
                }
            }
            $newClassifyArr = [];
            if ($_classifyArr)
            {
                // 整理同分类价格累和
                foreach ($_classifyArr as $item)
                {
                    if (!array_key_exists($item['cid'], $newClassifyArr))
                    {
                        $newClassifyArr[$item['cid']] = 0;
                    }
                    $newClassifyArr[$item['cid']] += $item['price'];
                }
            }
            if ($newClassifyArr)
            {
                $where = [];
                foreach ($newClassifyArr as $_newClassifyArr_key => $_newClassifyArr_val)
                {
                    $where[] = '(mc.full_subtraction_price <= ' . $_newClassifyArr_val . ' and find_in_set(\'' . $_newClassifyArr_key . '\',c.classify_str))';
                }
                $where = implode(' or ', $where);
                $common_coupon = [];
                // 开启优惠券功能
                if (Env::get('is_coupon') == 1)
                {
                    // 平台优惠券
                    $common_coupon = $memberCoupon
                        ->alias('mc')
                        ->where(
                            [
                                ['mc.member_id', '=', $param['member_id']],
                                ['mc.status', '=', 0],
                                ['mc.start_time', '<=', date('Y-m-d')],
                                ['mc.end_time', '>=', date('Y-m-d')],
                                ['mc.type', '=', 1],
                            ]
                        )
                        ->whereRaw($where)
                        ->join('coupon c', 'c.coupon_id = mc.coupon_id')
                        ->field(
                            'mc.member_coupon_id,mc.title,mc.actual_price,mc.full_subtraction_price,
                                mc.start_time,mc.end_time,c.classify_str'
                        )
                        ->order(['actual_price' => 'desc', 'full_subtraction_price' => 'asc'])
                        ->select();
                }
                // 存在就存放
                if ($common_coupon_count = count($common_coupon))
                {
                    $common_coupon = $common_coupon->toArray();
                    if ($common_coupon_count != 1)
                    {
                        // 进行排序,按优惠券金额倒序
                        array_multisort(array_column($common_coupon, 'actual_price'), SORT_DESC, $common_coupon);
                    }
                    $cc = reset($common_coupon);
                    $memberPacketPrice += $cc['actual_price'];
                    $cc['state'] = 'platform';
                    $coupon[] = $cc;
                }
            }
            // 红包
            $total_price -= $memberPacketPrice;
            // 开启红包
            if (Env::get('is_red_packet') == 1)
            {
                $packet = $memberPacket
                    ->where(
                        [
                            ['status', '=', 0],
                            ['member_id', '=', $param['member_id']],
                            ['start_time', '<=', date('Y-m-d H:i:s')],
                            ['end_time', '>=', date('Y-m-d H:i:s')],
                        ]
                    )
                    ->field(
                        'member_packet_id,actual_price,title,abs(actual_price - ' . $total_price . ') as sort_price,
                    date_format(start_time,"%Y-%m-%d") as start_time,date_format(end_time,"%Y-%m-%d") as end_time'
                    )
                    ->order(['actual_price' => 'desc'])
                    ->select();
            }
            if ($packet)
            {
                $packet = $packet->toArray();
                array_multisort(array_column($packet, 'sort_price'), SORT_ASC, $packet);
            }
        }
        return $crypt->response(
            [
                'code'           => 0,
                'message'        => '查询成功',
                'address'        => $address ?: json([]),
                'result'         => $goods_info,
                'coupon'         => $coupon,
                'coupon_price'   => $memberPacketPrice ? fmtPrice($memberPacketPrice) : 0,
                'packet'         => $packet,
                'freight'        => $freightInfo,
                'discount'       => $discount,
                'discount_price' => fmtPrice($discount_price),
            ],
            TRUE
        );

    }

    /**
     * 会员购物车商品数量
     * @param RSACrypt $crypt
     * @param CartModel $cart
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function number(RSACrypt $crypt,
                           CartModel $cart)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $result = 0;
        if ($param['member_id'])
        {
            $base = $cart
                ->alias('c')
                ->where(
                    [
                        ['c.member_id', '=', $param['member_id']],
                    ]
                );
            // 只查询有效购物车
            $result = $base
                ->join('goods g', 'g.goods_id = c.goods_id and ' . self::$goodsAuthSql)
                ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
                ->sum('number');
        }
        return $crypt->response(
            [
                'code'    => 0,
                'message' => '查询成功',
                'result'  => $result,
            ],
            TRUE
        );
    }

    /**
     * 读取两级
     * @param $data
     * @param $list
     * @param $val
     * @param $val2
     * @param $val3
     * @param $val4
     * @param $val5
     * @return array
     */
    protected function cartGrouping($data, $list, $val, $val2, $val3 = '', $val4 = '', $val5 = '')
    {
        if (empty($data))
        {
            return [];
        }
        $result = array_values(
            array_reduce(
                $data,
                function ($value, $key) use ($val, $val2, $val3, $val4, $val5, $list)
                {
                    if ($val)
                    {
                        $value[$key[$val]][$val] = $key[$val];
                    }
                    if ($val2)
                    {
                        $value[$key[$val]][$val2] = $key[$val2];
                    }
                    if ($val3)
                    {
                        $value[$key[$val]][$val3] = $key[$val3];
                    }
                    if ($val4)
                    {
                        $value[$key[$val]][$val4] = $key[$val4];
                    }
                    if ($val5)
                    {
                        $value[$key[$val]][$val5] = $key[$val5];
                    }
                    $value[$key[$val]][$list][] = $key;
                    return $value;
                }
            )
        );
        return $result;
    }

    /**
     * 求和
     * @param $data
     * @return float
     */
    protected function total_price($data)
    {
        $price = 0;
        foreach ($data as $item)
        {
            $price += $item['price'] * $item['number'];
        }
        return floatval(fmtPrice($price));
    }

    /**
     * 求总数
     * @param $data
     * @return integer
     */
    protected function total_number($data)
    {
        $number = 0;
        foreach ($data as $item)
        {
            $number += $item['number'];
        }
        return $number;
    }
}