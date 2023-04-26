<?php
declare(strict_types=1);

namespace app\computer\controller\shopping;

use app\computer\model\Attr;
use app\computer\model\
{Area, Cart as CartModel, LoginCart as LoginCartModel, Goods};
use app\computer\model\CollectGoods;
use app\computer\model\Coupon;
use app\computer\model\GoodsAttr;
use app\computer\model\GoodsClassify;
use app\computer\model\MemberAddress;
use app\computer\model\MemberCoupon;
use app\computer\model\MemberPacket;
use app\computer\model\Products;
use app\computer\model\Store;
use app\common\service\Freight;
use app\computer\controller\BaseController;
use app\computer\model\MemberAddress as MemberAddressModel;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Session;

/**
 * 购物车
 * Class Cart
 * @package app\computer\controller\goods
 */
class Cart extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['only' => 'create,confirm_order,common_confirm_order,get_freight_data'],
    ];


    /**
     * 加入购物车
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param Store $store
     * @param Goods $goodsModel
     * @param Products $products
     * @param LoginCartModel $loginCartModel
     * @return mixed
     */
    public function create(Request $request, RSACrypt $crypt, CartModel $cartModel, Store $store, Goods $goodsModel, Products $products, LoginCartModel $loginCartModel)
    {
        if ($request::isPost())
        {
            try
            {
                $param = $crypt->request();
                $member_info = Session::get('member_info', NULL);
                if ($member_info)
                {
                    // 获取参数
                    $param['member_id'] = $member_info['member_id'];
                    // 验证
                    $cartModel->valid($param, 'pc_create');
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
                    $member_id = $store->where('store_id', $param['store_id'])->value('member_id');

                    // 判断是否是本店商品
                    if ($member_id == $param['member_id'])
                    {
                        return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-1]], TRUE);
                    }
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
                    $param['discount'] = (!$param['is_vip'] ? 100 : (discount(
                        $member_info['member_id']
                    )));
                    $price = ($param['price'] = $goods['shop_price']) * (!$goods['is_vip'] ? 1 : $param['discount'] / 100);
                    $param['goods_weight'] = $goods['goods_weight'];
                    $param['discount_price'] = $goods['shop_price'] - $price;
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
                } else
                {

                    // 验证
                    $check = $loginCartModel->valid($param, 'login_create');
                    if ($check['code'])
                    {
                        return $crypt->response($check);
                    }

                    // 检查购物车
                    $cart = $loginCartModel
                        ->where(
                            [
                                ['identification', '=', $param['identification']],
                                ['goods_id', '=', $param['goods_id']],
                                ['goods_attr', '=', $param['goods_attr']],
                            ]
                        )
                        ->field('login_cart_id,number')
                        ->find();

                    // 判断是否库是否够
                    if ($param['goods_attr'])
                    {

                        $goods = $products->where(
                            [['goods_id', '=', $param['goods_id']], ['goods_attr', '=', $param['goods_attr']]]
                        )->field('attr_goods_number,attr_shop_price,attr_goods_weight as goods_weight')->find();

                        if (($goods['attr_goods_number'] - $cart['number']) < $param['number'])
                        {
                            return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-3]], TRUE);
                        }

                    } else
                    {

                        // 查看商品价格和库存
                        $goods = $goodsModel->where('goods_id', $param['goods_id'])->field(
                            'shop_price,goods_number,goods_weight'
                        )->find();

                        // 判断库存是否够
                        if (($goods['goods_number'] - $cart['number']) < $param['number'])
                        {
                            return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-3]], TRUE);
                        }

                    }
                    $param['goods_weight'] = $goods['goods_weight'];
                    // 判断是更新数量还是新增
                    if ($cart['login_cart_id'])
                    {
                        $loginCartModel
                            ->where(
                                [
                                    ['identification', '=', $param['identification']],
                                    ['goods_id', '=', $param['goods_id']],
                                    ['goods_attr', '=', $param['goods_attr']],
                                ]
                            )
                            ->setInc('number', $param['number']);
                    } else
                    {
                        $loginCartModel->allowField(TRUE)->save($param);
                    }
                }
                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], TRUE);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
            }
        }
    }


    /**
     * 购物车列表
     * @param Request $request
     * @param CartModel $cart
     * @param LoginCartModel $loginCatModel
     * @param Goods $goods
     * @param Coupon $coupon
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, CartModel $cart, LoginCartModel $loginCatModel, Goods $goods, Coupon $coupon)
    {
        $member_id = Session::get('member_info')['member_id'] ?? 0;
        if ($member_id === 0)
        {
            $result = $this->not_login_list($loginCatModel, $request::get('identification', ''));
        } else
        {
            $result = $this->login_list($cart, $member_id);
        }
        foreach ($result['result'] as &$v)
        {
            $v['coupon'] = $coupon
                ->where(
                    [
                        ['status', '=', 1],
                        ['modality', '=', 0],
                        ['receive_end_time', '>=', date('Y-m-d')],
                        ['receive_start_time', '<=', date('Y-m-d')],
                        ['is_integral_exchage', '=', 0],
                        ['exchange_num', '>', 0],
                        ['is_gift', '=', 0],
                        ['classify_str', '=', $v['store_id']],
                    ]
                )
                ->field(
                    'coupon_id,title,actual_price,full_subtraction_price,total_num,exchange_num,start_time,end_time,limit_num,classify_str,type'
                )
                ->limit(3)
                ->group('coupon_id')
                ->append(['member_state'])
                ->select()->toArray();
        }
        return $this->fetch(
            '',
            [
                'code'           => 0,
                'depreciate'     => $result['depreciate'],
                'data'           => $result['result'],
                'lost'           => $result['lose'],
                'lost_count'     => count($result['lose']),
                'recommend_list' => recommend_list($goods, 5, $member_id),
            ]
        );
    }

    /**
     * 店铺优惠券列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Coupon $coupon
     * @return mixed
     */
    public function coupon_list(Request $request, RSACrypt $crypt, Coupon $coupon)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $coupon->valid($param, 'coupon_list');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                $result = $coupon
                    ->where(
                        [
                            ['status', '=', 1],
                            ['modality', '=', 0],
                            ['receive_end_time', '>=', date('Y-m-d')],
                            ['receive_start_time', '<=', date('Y-m-d')],
                            ['is_integral_exchage', '=', 0],
                            ['exchange_num', '>', 0],
                            ['classify_str', '=', $param['store_id']],
                        ]
                    )
                    ->field(
                        'coupon_id,title,actual_price,full_subtraction_price,total_num,exchange_num,start_time,end_time,limit_num'
                    )
                    ->group('coupon_id')
                    ->append(['member_state'])
                    ->select();

                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch
            (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 已经登录购物车列表
     * @param CartModel $cart
     * @param $member_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function login_list(CartModel $cart, $member_id)
    {
        //ROUND(if(products_id="" or ISNULL(products_id) or products_id = 0,g.shop_price,(select attr_shop_price from ishop_products where products_id=c.products_id))*(c.discount/100),2) discount_shop_price'
        $result = $cart
            ->alias('c')
            ->join('goods g', 'c.goods_id=g.goods_id')
            ->join('store store', 'g.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where([['c.member_id', '=', $member_id]], 'g'))
            ->field(
                'cart_id,c.store_id,c.goods_id,c.goods_name,c.file,c.file as cart_file,g.shop_price as price,c.number,products_id,c.attr,c.goods_attr,c.goods_weight,
                if(products_id="" or ISNULL(products_id) or products_id = 0,g.shop_price,(select attr_shop_price from ishop_products where products_id=c.products_id)) shop_price'
            )
            ->append(['inventory', 'is_putaway', 'store_name', 'store_status', 'collect_status'])
            ->order('c.create_time', 'desc')
            ->select()
            ->toArray();
        $depreciate = 0;
        foreach ($result as $key => $value)
        {

            if ($value['is_putaway'] <> 1 || $value['store_status'] <> 4)
            {
                $lose[] = $value;
                unset($result[$key]);
            } else
            {
                if ($value['price'] > $value['shop_price'])
                {
                    $depreciate++;
                }
            }
        }
        return [
            'result'     => $this->cartGroupingLogin(
                array_values($result),
                'list',
                'store_id',
                'store_name'
            ),
            'lose'       => $lose ?? [],
            'depreciate' => $depreciate,
        ];
    }


    /**
     * 没登录购物车列表
     * @param LoginCartModel $loginCartModel
     * @param $identification
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function not_login_list(LoginCartModel $loginCartModel, $identification)
    {
        // 列表
        $result = $loginCartModel
            ->alias('l_c')
            ->join('goods g', 'l_c.goods_id=g.goods_id')
            ->join('store store', 'g.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where([['l_c.identification', '=', $identification]], 'g'))
            ->field(
                '
            login_cart_id cart_id,l_c.store_id,l_c.goods_id,l_c.goods_name,l_c.file,l_c.file as cart_file,g.shop_price,number,l_c.attr,l_c.goods_attr,g.goods_weight,
            if(products_id="" or ISNULL(products_id),g.shop_price,(select attr_shop_price from ishop_products where products_id=l_c.products_id)) shop_price
            '
            )
            ->select()
            ->append(['inventory', 'is_putaway', 'store_name', 'store_status', 'coupon_status'])
            ->toArray();
        $depreciate = 0;
        foreach ($result as $key => $value)
        {
            if ($value['is_putaway'] <> 1 || $value['store_status'] <> 4)
            {
                $lose[] = $value;
                unset($result[$key]);
            }
            if ($value['price'] > $value['shop_price'])
            {
                $depreciate++;
            }
        }
        return [
            'result'     => $this->cartGroupingNotLogin(
                array_values($result),
                'store_id',
                'store_id',
                'list',
                'store_name',
                'store_name'
            ),
            'lose'       => $lose ?? [],
            'depreciate' => $depreciate,
        ];
    }

    /**
     * 购物车控制修改数量
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param Products $products
     * @param Goods $goodsModel
     * @param LoginCartModel $loginCartModel
     * @return mixed
     */
    public function edit_number(Request $request, RSACrypt $crypt, CartModel $cartModel, Products $products, Goods $goodsModel, LoginCartModel $loginCartModel)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $member_id = Session::get('member_info')['member_id'] ?? 0;
                if ($member_id)
                {
                    //登录 检查购物车
                    $where = [
                        ['cart_id', '=', $param['cart_id']],
                        ['member_id', '=', $member_id],
                    ];
                    $cart = $cartModel
                        ->where(
                            $where
                        )
                        ->field('cart_id,number,goods_id,goods_attr')
                        ->find();
                } else
                {
                    $where = [
                        ['login_cart_id', '=', $param['cart_id']],
                        ['identification', '=', $param['identification']],
                    ];
                    // 检查购物车
                    $cart = $loginCartModel
                        ->where(
                            $where
                        )
                        ->field('login_cart_id,number,goods_id,goods_attr')
                        ->find();
                }
                // 检查库存
                if ($cart['goods_attr'])
                {
                    // 查看商品库存
                    $number = $products->where(
                        [['goods_id', '=', $cart['goods_id']], ['goods_attr', '=', $cart['goods_attr']]]
                    )->value('attr_goods_number');
                } else
                {
                    // 查看商品库存
                    $number = $goodsModel->where('goods_id', $cart['goods_id'])->value('goods_number');
                }
                // 判断库存是否够
                if ($number < $param['number'])
                {
                    return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-3]], TRUE);
                }
                // 新增
                ($member_id ? $cartModel : $loginCartModel)->where($where)->setField('number', $param['number']);
                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);
            } catch
            (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 购物车删除
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param LoginCartModel $loginCart
     * @return mixed
     */
    public function delete(Request $request, RSACrypt $crypt, CartModel $cartModel, loginCartModel $loginCart)
    {
        if ($request::isPost())
        {
            // 获取参数
            $param = $crypt->request();
            $cartModel->valid($param, 'delete');
            //判断是登录还是没登录删除对应购物车
            if (Session::has('member_info'))
            {
                // 删除
                $cartModel::destroy(
                    function ($query) use ($param)
                    {
                        $query->where(
                            [
                                ['cart_id', 'in', $param['cart_id']],
                                ['member_id', '=', Session::get('member_info')['member_id']],
                            ]
                        );
                    }
                    ,
                    TRUE
                );
            } else
            {
                // 删除
                $loginCart::destroy(
                    function ($query) use ($param)
                    {
                        $query->where([['login_cart_id', 'in', $param['cart_id']]]);
                    },
                    TRUE
                );
            }
            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);
        }
    }

    /**
     * 购物车编辑
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param Products $products
     * @param Goods $goodsModel
     * @return mixed
     */
    public function update(Request $request, RSACrypt $crypt, CartModel $cartModel, Products $products, Goods $goodsModel)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 验证
                $check = $cartModel->valid($param, 'update');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                // 检查库存
                if ($param['goods_attr'])
                {

                    // 查看商品库存
                    $number = $products->where(
                        [['goods_id', '=', $param['goods_id']], ['goods_attr', '=', $param['goods_attr']]]
                    )->value('attr_goods_number');

                } else
                {

                    // 查看商品库存
                    $number = $goodsModel->where('goods_id', $param['goods_id'])->value('goods_number');

                }

                // 判断库存是否够
                if ($number < $param['number'])
                {
                    return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-3]], TRUE);
                }

                // 更新
                $cartModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch
            (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 商品收藏
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CollectGoods $collectGoods
     * @return mixed
     */
    public function collect(Request $request, RSACrypt $crypt, CollectGoods $collectGoods)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;

                // 验证
                $check = $collectGoods->valid($param, 'collect_cart');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                // 取出已收藏的数据
                $goods_id = $collectGoods->where('member_id', $param['member_id'])->column('goods_id');

                // 新数组
                $arr = [];

                foreach (explode(',', $param['goods_id']) as $value)
                {
                    if (!in_array($value, $goods_id))
                    {
                        $arr[] = ['member_id' => $param['member_id'], 'goods_id' => $value];
                    }
                }
                array_pop($arr);
                // 批量新增
                $collectGoods->allowField(TRUE)->saveAll($arr);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch
            (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 商品规格
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Goods $goods
     * @param Attr $attr
     * @param GoodsAttr $goodsAttr
     * @return mixed
     */
    public
    function attr(Request $request, RSACrypt $crypt, Goods $goods, Attr $attr, GoodsAttr $goodsAttr)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $goods->valid($param, 'attr');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }
                $goodsData = $goods
                    ->where('goods_id', $param['goods_id'])
                    ->field('attr_type_id,goods_number,goods_id')
                    ->find();
                // 商品规格
                $attrCollect = [];
                $attrArr = $goodsAttr
                    ->alias('ga')
                    ->where([['ga.goods_id', '=', $goodsData['goods_id']]])
                    ->join('attr a', 'a.attr_id = ga.attr_id')
                    ->field('ga.goods_attr_id,ga.attr_value,a.attr_id,a.attr_name')
                    ->order(['ga.attr_id' => 'asc'])
                    ->select();
                if ($attrArr)
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
                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch
            (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 立即购买|加入购物车  - 确认订单
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @param CartModel $cartModel
     * @param MemberCoupon $memberCoupon
     * @param MemberPacket $memberPacket
     * @param GoodsClassify $goodsClassify
     * @param Goods $goods
     * @param Area $area
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function confirm_order(Request $request,
                                  RSACrypt $crypt,
                                  MemberAddress $memberAddress,
                                  CartModel $cartModel,
                                  MemberCoupon $memberCoupon,
                                  MemberPacket $memberPacket,
                                  GoodsClassify $goodsClassify,
                                  Goods $goods,
                                  Area $area)
    {
        //防止浏览器回退后显示  重复提交
        header("Cache-control:private");
        if ($request::isPost())
        { // 获取参数
            $param = $request::post();
            //将请求参数加密
            $param_data = $crypt->singleEnc($param);
            $param['member_id'] = Session::get('member_info')['member_id'];
            //订单信息
            $info_data = [
                //下单渠道 1立即购买 2购物车
                'pay_channel'       => $param['pay_channel'] == 1 ? 1 : 2,
                //订单类型 1普通线上 2拼团 3砍价 4限时抢购 5普通线下
                'order_type'        => $param['pay_channel'] == 1 ? $param['order_type'] : 1,
                //订单类型为3砍价,砍价活动主表id,其他传null
                'cut_activity_id'   => '',
                //订单类型为2拼团,拼团活动主表id,其他null
                'group_activity_id' => '',
                //使用的积分数量
                'used_integral'     => '',
                //下单渠道为1商品id,为2购物车id串
                'id_set'            => $param[$param['pay_channel'] == 1 ? 'goods_id' : 'cart_id'],
                //端订单来源 1app 2小程序 3pc 4手机站wap 5线下支付
                'origin_type'       => '3',
                //店铺数据集合
                'store_set'         => [],
                //
                'invoice_attr'      => '',
            ];
            // 验证
            $check = $cartModel->valid($param, 'confirm_order');
            if ($check['code'])
            {
                return $crypt->response($check);
            }

            $condition[] = ['member_id', '=', $param['member_id']];
            if ($param['member_address_id'] ?? '')
            {
                $condition[] = ['member_address_id', '=', $param['member_address_id']];
            }
            // 收货地址
            $address = $memberAddress
                ->where($condition)
                ->field('member_address_id,name,phone,province,city,area,street,address,lat,lng,is_default')
                ->order(['is_default' => 'desc', 'member_address_id' => 'asc'])
                ->select();
            //商品信息  pay_channel  下单渠道   1立即购买 2购物车
            $result = $param['pay_channel'] == 1 ? $this->buy_order_data($goods, $param) : $this->cart_order_data(
                $cartModel,
                $param
            );
            // 转格式
            $result = $this->cartGroupingLogin(
                $result,
                'list',
                'store_id',
                'store_name',
                'logo',
                'goods_distribution',
                'is_vip'
            );
            // 优惠券数组
            $coupon = $classifyArr = [];
            $memberPacketPrice = $dealPrice = $vipDiscountprice = 0;
            $frightArgs = [];
            //当前会员折扣  只有是普通商品下单走会员折扣
            $_discount = ($info_data['order_type'] == 1 ? discount(
                    Session::get('member_info')['member_id'] ?? 0
                ) : 100) / 100;
            // 计算金额 优惠券赋值  vip价格优惠
            foreach ($result as $key => &$value)
            {
                // 店铺总金额 和商品总金额
                $sum_goods_total_price = $dealPrice += $value['total_price'] = $this->total_price($value['list']);
                if ($value['is_vip'] == 1)
                {
                    $_discount_price = round($value['total_price'] * $_discount, 2);
                    $vipDiscountprice += ($value['total_price'] - $_discount_price);
                    $value['total_price'] = $_discount_price;
                }
                // 店铺商品总数量
                $value['total_number'] = $this->total_number($value['list']);
                // 店铺优惠券列表   开启优惠券功能  如果是拼团砍价不支持使用优惠券
                $store_coupon = (self::$functionStatus['is_coupon'] == 1 and in_array(
                        $info_data['order_type'],
                        [2, 3, 4]
                    )) ? [] : $memberCoupon
                    ->where(
                        [
                            ['store_id', '=', $value['store_id']],
                            ['member_id', '=', $param['member_id']],
                            ['full_subtraction_price', '<=', $value['total_price']],
                            ['status', '=', 0],
                            ['end_time', '>=', date('Y-m-d')],
                            ['type', '=', 0],
                        ]
                    )
                    ->field(
                        'member_coupon_id,title,store_id,actual_price,full_subtraction_price,start_time,end_time'
                    )
                    ->order(['actual_price' => 'desc', 'full_subtraction_price' => 'asc'])
                    ->find();
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
                            'flag_id'      => $_val['cart_id'] ?? 0,
                            'goods_id'     => $_val['goods_id'],
                            'goods_attr'   => is_null($_val['goods_attr']) ? '' : $_val['goods_attr'],
                            'store_id'     => $_val['store_id'],
                            'freight_id'   => $_val['freight_id'],
                            'is_freight'   => $_val['is_freight'],
                            'quantity'     => $_val['number'],
                            'sub_price'    => ($_val['price'] - ($_val['discount_price'] ?? $_val['price'] - $_val['price'] * $_discount)) * $_val['number'],
                            'goods_weight' => $_val['goods_weight'],
                        ]
                    );
                }
                // 存在就存放
                if ($store_coupon)
                {
                    $store_coupon['state'] = 'store';
                    $coupon[] = is_object($store_coupon) ? $store_coupon->toArray() : $store_coupon;
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
            $_address_info = $memberAddress->optAddress($address[0] ?? '');
            $freightService = new Freight(
                $frightArgs, $address->toArray() ? [
                'street_id' => $_address_info['street_id'] ?: $_address_info['area_id'],//如果街道地址不存在就取区域id
                'city_name' => $address[0]['city'],
                'lng'       => $address[0]['lng'],
                'lat'       => $address[0]['lat'],
            ] : []
            );
            $freightData = $freightService->calculation();
            //计算运费
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
                            $_item['freight']['express_freight_price'] = number_format(
                                $_item['freight']['express_freight_price'],
                                2,
                                '.',
                                ''
                            );
                            if ($_val['city_freight_sup'])
                            {
                                $_item['freight']['city_freight_sup'] = 1;
                            }
                            $_item['freight']['city_freight_price'] += $_val['city_freight_price'];
                            $_item['freight']['city_freight_price'] = number_format(
                                $_item['freight']['city_freight_price'],
                                2,
                                '.',
                                ''
                            );
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
                // 平台优惠券   开启优惠券功能  拼团砍价商品不支持使用优惠券
                $common_coupon = (self::$functionStatus['is_coupon'] == 1 and in_array(
                        $info_data['order_type'],
                        [2, 3, 4]
                    )) ? [] : $memberCoupon
                    ->alias('mc')
                    ->where(
                        [
                            ['mc.member_id', '=', $param['member_id']],
                            ['mc.status', '=', 0],
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
                    $cc['state'] = 'platform';
                    //平台优惠券id拼接
                    $platform_coupon = ($platform ?? '') . $cc['member_coupon_id'] . ',';
                    $memberPacketPrice += $cc['actual_price'];
                    $coupon[] = $cc;
                }
            }

            // 红包  开启红包功能   如果是拼团和砍价没有红包
            $packet = (self::$functionStatus['is_red_packet'] == 1 and in_array(
                    $info_data['order_type'],
                    [2, 3, 4]
                )) ? [] : $memberPacket
                ->where(
                    [
                        ['status', '=', 0],
                        ['member_id', '=', $param['member_id']],
                        ['start_time', '<=', date('Y-m-d H:i:s')],
                        ['end_time', '>=', date('Y-m-d H:i:s')],
                    ]
                )
                ->field(
                    'member_packet_id,actual_price,title,abs(actual_price - ' . $dealPrice . ') as sort_price,start_time,end_time'
                )
                ->order(['actual_price' => 'desc'])
                ->select();
            if ($packet)
            {
                $packet = $packet->toArray();
                array_multisort(array_column($packet, 'sort_price'), SORT_ASC, $packet);
            }
            //处理店铺数据集合数据
            foreach ($result as $goods_data)
            {
                $info_data['store_set'][$goods_data['store_id']] = [
                    'store_id'           => $goods_data['store_id'],
                    //下单渠道为1 商品规格id 为2传空或不传
                    'products_id'        => $param['products_id'] ?? '',
                    //下单渠道为1时商品规格(校验实时性)
                    'goods_attr'         => $param['pay_channel'] == 1 ? $goods_data['list'][0]['goods_attr'] : '',
                    //下单渠道为1时数量 为2传空或不传
                    'quantity'           => $param['pay_channel'] == 1 ? $goods_data['list'][0]['number'] : '',
                    //设置店铺支持支付方式集合   是否开启货到付款
                    'pay_type_set'       => $goods_data['goods_distribution']['is_pay_delivery'] ? [1, 2] : [1],
                    //店铺是否支持开启增值税发票
                    'is_added_value_tax' => $goods_data['list'][0]['is_added_value_tax'],
                ];
            }
            //地址列表
            $province = $area->where('parent_id', 0)
                ->field('area_id,area_name')
                ->select();
            //发票信息缓存数据
            $invoice_history_data = Cache::store('file')->tag('invoice_history')->get('invoice_rt_' . $param['member_id'] , []);
            //personal   普通个人发票  company  普通发票公司  //tax 增值税发票
            if (!empty($invoice_history_data)) {
                foreach ($invoice_history_data as $_key => &$_value) {
                    $_value = array_map(function ($v) {
                        return json_decode($v, true);
                    }, $_value);
                }
            }
            return $this->fetch(
                'common_confirm_order',
                [
                    'address'            => $address,
                    'result'             => $result,
                    'coupon'             => $coupon,//优惠券列表
                    'platform_coupon'    => trim(($platform_coupon ?? ''), ','),//平台优惠券id字符串
                    'coupon_price'       => number_format($memberPacketPrice, 2, '.', ''),//使用优惠券总金额
                    'packet'             => $packet,//红包列表
                    'packet_price'       => $packet[0]['actual_price'] ?? 0,//使用红包金额
                    'vip_discount_price' => $vipDiscountprice,              //vip折扣价格
                    'info_data'          => $crypt->singleEnc($info_data), //加密订单数据
                    'param_data'         => $param_data,                   //加密请求数据
                    'province'           => $province,//地址列表
                    'sum_total_price'    => $sum_goods_total_price ?? 0,//商品总金额
                    'sum_total_number'   => array_sum(array_column($result, 'total_number')), //商品总数量
                    'invoice_history_data' => $invoice_history_data , // 用户历史输入发票信息缓存
                    'order_type'         => $info_data['order_type'], //下单类型
                ]
            );
        }
    }

    /**
     * 获得当前请求支持的配送方式
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Goods $goods
     * @param CartModel $cartModel
     * @param MemberAddressModel $memberAddress
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_freight_data(Request $request, RSACrypt $crypt, Goods $goods, CartModel $cartModel, MemberAddress $memberAddress)
    {
        $param = $request::post();
        $address_id = $param['address_id'] ?? '';
        $param = $crypt->singleDec($param['param'] ?? exception('网络繁忙'));
        $param['member_id'] = Session::get('member_info')['member_id'];
        //商品信息  pay_channel  下单渠道   1立即购买 2购物车
        $result = $param['pay_channel'] == 1 ? $this->buy_order_data($goods, $param) : $this->cart_order_data(
            $cartModel,
            $param
        );
        // 收货地址
        $address = $memberAddress
            ->where(
                [['member_id', '=', $param['member_id']], ['member_address_id', '=', $address_id]]
            )
            ->field('member_address_id,name,phone,province,city,area,street,address,lat,lng,is_default')
            ->append(['address_info'])
            ->find();
        // 转格式
        $result = $this->cartGroupingLogin($result, 'list', 'store_id', 'store_name', 'logo', 'goods_distribution');
        $frightArgs = [];
        //当前会员折扣  只有是普通商品下单走会员折扣
        $_discount = ($param['order_type'] == 1 ? discount(
                Session::get('member_info')['member_id'] ?? 0
            ) : 100) / 100;
        foreach ($result as $key => &$value)
        {
            foreach ($value['list'] as $_key => $_val)
            {
                array_push(
                    $frightArgs,
                    [
                        'flag_id'      => $_val['cart_id'] ?? 0,
                        'goods_id'     => $_val['goods_id'],
                        'goods_attr'   => is_null($_val['goods_attr']) ? '' : $_val['goods_attr'],
                        'store_id'     => $_val['store_id'],
                        'freight_id'   => $_val['freight_id'],
                        'is_freight'   => $_val['is_freight'],
                        'quantity'     => $_val['number'],
                        'sub_price'    => ($_val['price'] - ($_val['discount_price'] ?? $_val['price'] - $_val['price'] * $_discount)) * $_val['number'],
                        'goods_weight' => $_val['goods_weight'],
                    ]
                );
            }
        }
        if ($frightArgs)
        {
            $freightService = new Freight(
                $frightArgs, [
                               'street_id' => $address['street_id'] ? : $address['address_info']['area_id'],
                               'city_name' => $address['city'],
                               'lng'       => $address['lng'],
                               'lat'       => $address['lat'],
                           ]
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
                'take_freight_sup'      => 0,
                'take_freight_list'     => [],
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
                        $_item['freight']['express_freight_price'] += $_val['express_freight_price'];
                        $_item['freight']['express_freight_price'] = number_format(
                            $_item['freight']['express_freight_price'],
                            2,
                            '.',
                            ''
                        );
                        if ($_val['city_freight_sup'])
                        {
                            $_item['freight']['city_freight_sup'] = 1;
                        }
                        $_item['freight']['city_freight_price'] += $_val['city_freight_price'];
                        $_item['freight']['city_freight_price'] = number_format(
                            $_item['freight']['city_freight_price'],
                            2,
                            '.',
                            ''
                        );
                        if ($_val['take_freight_sup'])
                        {
                            $_item['freight']['take_freight_sup'] = 1;
                        }
                        if (empty($_item['freight']['take_freight_list']) && $_val['take_freight_list'])
                        {
                            $_item['freight']['take_freight_list'] = $_val['take_freight_list'];
                        }
                    }
                }
            }
        }
        return json(['code' => 0, 'data' => $result]);
    }

    /**
     * 购物车商品数据
     * @param $cartModel
     * @param $param
     * @return
     */
    private function cart_order_data($cartModel, $param)
    {
        return $cartModel
            ->alias('c')
            ->join('goods g', 'g.goods_id = c.goods_id')
            ->join('store store', 'g.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where([['cart_id', 'in', $param['cart_id']]], 'g'))
            ->field(
                'c.cart_id,c.store_id,c.goods_id,c.goods_name,c.file,price,c.number,c.products_id,g.default_express_type,
                    c.attr,c.goods_attr,c.goods_weight,g.goods_classify_id,g.freight_id,g.is_freight,g.store_id,g.is_vip,c.discount_price'
            )
            ->order('goods_id', 'desc')
            ->append(['goods_distribution', 'store_name', 'logo', 'is_added_value_tax'])
            ->select()
            ->toArray();
    }

    /**
     * 立即购买商品数据
     * @param $goods
     * @param $param
     * @return
     * @throws \Exception
     */

    private function buy_order_data($goods, $param)
    {
        if(empty($param['number']))exception('数量不可为空');
        $where = [
            ['g.goods_id', '=', $param['goods_id']],
        ];
        $file = 'g.is_vip,g.default_express_type,g.store_id,g.goods_id,g.goods_name,g.goods_classify_id,g.freight_id,g.is_freight,"' . $param['price'] . '" price,"' . $param['number'] . '" number';
        //判断是否有属性
        if (!empty($param['products_id']))
        {
            $where[] = ['p.products_id', '=', $param['products_id']];
            $file .= ',if(p.attr_file="",g.file,p.attr_file) file,p.products_id,p.attr,p.goods_attr,p.attr_goods_weight goods_weight';
        } else
        {
            $file .= ',g.file,(\'\') attr,(\'\') goods_attr,g.goods_weight';
        }
        return $goods
            ->alias('g')
            ->join('store store', 'g.store_id = store.store_id and ' . self::store_auth_sql('store'))
            ->where(self::goods_where($where, 'g'))
            ->join('products p', 'g.goods_id = p.goods_id and p.products_id=' . ($param['products_id'] ?: 0), 'left')
            ->field($file)
            ->group('g.goods_id')
            ->order('g.goods_id', 'desc')
            ->append(['goods_distribution', 'store_name', 'logo', 'is_added_value_tax'])
            ->select()
            ->toArray();
    }

    /**
     * 会员购物车商品数量
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CartModel $cart
     * @return mixed
     */
    public
    function number(Request $request, RSACrypt $crypt, CartModel $cart)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request()->mid ?? '';
                // 列表
                $result = $cart
                    ->where('member_id', $param['member_id'])
                    ->field(
                        'cart_id,store_id,goods_id,goods_name,file,file as cart_file,price,number,products_id,attr,goods_attr'
                    )
                    ->append(['inventory', 'is_putaway', 'store_name', 'store_status', 'collect_status'])
                    ->select()
                    ->toArray();

                foreach ($result as $key => $value)
                {
                    if ($value['is_putaway'] <> 1 || $value['store_status'] <> 1)
                    {
                        $lose[] = $value;
                        unset($result[$key]);
                    }

                }

                return $crypt->response(['code' => 0, 'result' => $this->total_number($result)]);

            } catch
            (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 已经登录
     * 读取两级
     * @param $data
     * @param $list
     * @param $val
     * @param $val2
     * @param string $val3
     * @param string $val4
     * @param string $val5
     * @param string $is_vip
     * @return array
     */
    protected
    function cartGroupingLogin($data, $list, $val, $val2, $val3 = '', $val4 = '', $val5 = '', $is_vip = '')
    {

        if (empty($data))
        {
            return [];
        }
        $result = array_values(
            array_reduce(
                $data,
                function ($value, $key) use ($val, $val2, $val3, $val4, $val5, $list, $is_vip)
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
                    if ($is_vip)
                    {
                        $value[$key[$val]][$is_vip] = $key[$is_vip];
                    }
                    $value[$key[$val]][$list][] = $key;
                    return $value;
                }
            )
        );

        return $result;
    }

    /**
     * 未登录
     * @param $data
     * @param $val
     * @param $title
     * @param $list
     * @param $val2
     * @param $title2
     * @return array
     */
    protected
    function cartGroupingNotLogin($data, $val, $title, $list, $val2, $title2)
    {

        if (empty($data))
        {
            return [];
        }

        $result = array_values(
            array_reduce(
                $data,
                function ($value, $key) use ($val, $val2, $title, $title2, $list)
                {
                    $name = $key[$val];
                    $name2 = $key[$val2];
                    unset($key[$val]);
                    $value[$name][$title] = $name;
                    $value[$name][$title2] = $name2;
                    $value[$name][$list][] = $key;
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
    protected
    function total_price($data)
    {
        $price = 0;
        foreach ($data as $item)
        {
            $price += round($item['price'] * $item['number'], 2);
        }

        return $price;
    }

    /**
     * 求总数
     * @param $data
     * @return integer
     */
    protected
    function total_number($data)
    {
        $number = 0;
        foreach ($data as $item)
        {
            $number += $item['number'];
        }

        return $number;
    }

    /**
     * 收货地址列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberAddressModel $memberAddress
     * @return mixed
     */
    public
    function address(Request $request, RSACrypt $crypt, MemberAddressModel $memberAddress)
    {
        if ($request::isPost())
        {
            try
            {
                // 接参
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;

                // 获取数据
                $result = $memberAddress
                    ->where([['member_id', 'eq', $param['member_id']]])
                    ->field('member_address_id,name,phone,province,city,area,street,address,lat,lng')
                    ->order(['is_default' => 'desc', 'member_address_id' => 'asc'])
                    ->select()->toArray();
                array_shift($result);

                return $crypt->response(['code' => 0, 'result' => $result], TRUE);
            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
            }

        }
    }


}