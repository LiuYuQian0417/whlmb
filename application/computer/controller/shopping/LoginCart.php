<?php
declare(strict_types=1);

namespace app\computer\controller\shopping;

use app\computer\model\Attr;
use app\computer\model\GoodsAttr;
use app\computer\model\LoginCart as LoginCartModel;
use app\computer\model\CollectGoods;
use app\computer\model\Coupon;
use app\computer\model\Goods;
use app\computer\model\Member;
use app\computer\model\MemberRank;
use app\computer\model\Products;
use app\computer\model\Store;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 未登录购物车
 * Class LoginCart
 * @package app\computer\controller\goods
 */
class LoginCart extends BaseController
{

    /**
     * 加入购物车
     * @param Request $request
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @param Goods $goodsModel
     * @param Products $products
     * @return mixed
     */
    public function create(Request $request, RSACrypt $crypt, LoginCartModel $loginCartModel, Goods $goodsModel, Products $products)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $loginCartModel->valid($param, 'login_create');
                if ($check['code']) return $crypt->response($check);

                // 检查购物车
                $cart = $loginCartModel
                    ->where([
                        ['identification', '=', $param['identification']],
                        ['goods_id', '=', $param['goods_id']],
                        ['goods_attr', '=', $param['goods_attr']]
                    ])
                    ->field('login_cart_id,number')
                    ->find();

                // 判断是否库是否够
                if ($param['goods_attr']) {

                    $goods = $products->where([['goods_id', '=', $param['goods_id']], ['goods_attr', '=', $param['goods_attr']]])->field('attr_goods_number,attr_shop_price,attr_goods_weight as goods_weight')->find();

                    if (($goods['attr_goods_number'] - $cart['number']) < $param['number']) return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-3]], true);

                } else {

                    // 查看商品价格和库存
                    $goods = $goodsModel->where('goods_id', $param['goods_id'])->field('shop_price,goods_number,goods_weight')->find();

                    // 判断库存是否够
                    if (($goods['goods_number'] - $cart['number']) < $param['number']) return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-3]], true);

                }
                $param['goods_weight'] = $goods['goods_weight'];
                // 判断是更新数量还是新增
                if ($cart['login_cart_id'])
                    $loginCartModel
                        ->where([
                            ['identification', '=', $param['identification']],
                            ['goods_id', '=', $param['goods_id']],
                            ['goods_attr', '=', $param['goods_attr']]
                        ])
                        ->setInc('number', $param['number']);
                else
                    $loginCartModel->allowField(true)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }

    /**
     * 购物车列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @param Goods $goods
     * @param Coupon $coupon
     * @return mixed
     */
    public function index(Request $request, RSACrypt $crypt, LoginCartModel $loginCartModel, Goods $goods, Coupon $coupon)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 失效的
                $lose = [];

                // 列表
                $result = $loginCartModel
                    ->where('identification', $param['identification'])
                    ->field('login_cart_id,store_id,goods_id,goods_name,file,file as cart_file,price,number,attr,goods_attr')
                    ->select()
                    ->append(['inventory', 'is_putaway', 'store_name', 'store_status','coupon_status'])
                    ->toArray();

                foreach ($result as $key => $value) {
                    if ($value['is_putaway'] <> 1 || $value['store_status'] <> 1) {
                        $lose[] = $value;
                        unset($result[$key]);
                    }
                }

                // 折扣
                $discount = discount(0);

                // 优惠券数量
                $coupon_status = $coupon->where([
                    ['type', '=', 0],
                    ['modality', '=', 0],
                    ['classify_str', '=', $param['store_id']],
                    ['status', '=', 1],
                    ['receive_end_time', '>=', date('Y-m-d')],
                    ['receive_start_time', '<=', date('Y-m-d')]
                ])->value('coupon_id') ? 1 : 0;

                return $crypt->response([
                    'code' => 0,
                    'result' => $this->cartGrouping(array_values($result), 'store_id', 'store_id', 'list', 'store_name', 'store_name'),
                    'lost' => $lose,
                    'lost_count' => count($lose),
                    'recommend_list' => recommend_list($goods, 4),
                    'discount' => $discount,
                    'coupon_status' => $coupon_status
                                        ]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 购物车控制增加数量
     * @param Request $request
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @param Products $products
     * @param Goods $goodsModel
     * @return mixed
     */
    public function add_number(Request $request, RSACrypt $crypt, LoginCartModel $loginCartModel, Products $products, Goods $goodsModel)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $loginCartModel->valid($param, 'login_add_number');
                if ($check['code']) return $crypt->response($check);

                // 检查购物车
                $cart = $loginCartModel
                    ->where([
                        ['login_cart_id', '=', $param['login_cart_id']],
                        ['identification', '=', $param['identification']]
                    ])
                    ->field('login_cart_id,number,goods_id,goods_attr')
                    ->find();

                // 检查库存
                if ($cart['goods_attr']) {

                    // 查看商品库存
                    $number = $products->where([['goods_id', '=', $cart['goods_id']], ['goods_attr', '=', $cart['goods_attr']]])->value('attr_goods_number');

                } else {

                    // 查看商品库存
                    $number = $goodsModel->where('goods_id', $cart['goods_id'])->value('goods_number');

                }

                // 判断库存是否够
                if (($number - $cart['number']) < $param['number']) return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-3]], true);


                // 新增
                $loginCartModel
                    ->where([
                        ['login_cart_id', '=', $param['login_cart_id']],
                        ['identification', '=', $param['identification']]
                    ])
                    ->setInc('number', $param['number']);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch
            (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 购物车控制减少数量
     * @param Request $request
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @return mixed
     */
    public function reduce_number(Request $request, RSACrypt $crypt, LoginCartModel $loginCartModel)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $loginCartModel->valid($param, 'login_reduce_number');
                if ($check['code']) return $crypt->response($check);

                // 新增
                $loginCartModel
                    ->where([
                        ['login_cart_id', '=', $param['login_cart_id']],
                        ['identification', '=', $param['identification']]
                    ])
                    ->setDec('number', $param['number']);

                // 查询数量
                $number = $loginCartModel
                    ->where([
                        ['login_cart_id', '=', $param['login_cart_id']],
                        ['identification', '=', $param['identification']]
                    ])
                    ->value('number');

                if ($number == 0) $loginCartModel::destroy($param['cart_id'], true);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch
            (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 购物车删除
     * @param Request $request
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @return mixed
     */
    public function delete(Request $request, RSACrypt $crypt, LoginCartModel $loginCartModel)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $loginCartModel->valid($param, 'login_delete');
                if ($check['code']) return $crypt->response($check);

                // 删除
                $loginCartModel::destroy($param['login_cart_id'], true);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch
            (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 购物车编辑
     * @param Request $request
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @param Products $products
     * @param Goods $goodsModel
     * @return mixed
     */
    public function update(Request $request, RSACrypt $crypt, LoginCartModel $loginCartModel, Products $products, Goods $goodsModel)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $loginCartModel->valid($param, 'login_update');
                if ($check['code']) return $crypt->response($check);

                // 检查库存
                if ($param['goods_attr']) {

                    // 查看商品库存
                    $number = $products->where([['goods_id', '=', $param['goods_id']], ['goods_attr', '=', $param['goods_attr']]])->value('attr_goods_number');

                } else {

                    // 查看商品库存
                    $number = $goodsModel->where('goods_id', $param['goods_id'])->value('goods_number');

                }

                // 判断库存是否够
                if ($number < $param['number']) return $crypt->response(['code' => -100, 'message' => config('message.')[-9][-3]], true);

                // 更新
                $loginCartModel->allowField(true)->isUpdate(true)->save($param);


                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch
            (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
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
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $coupon->valid($param, 'login_coupon_list');
                if ($check['code']) return $crypt->response($check);

                $result = $coupon
                    ->where([
                        ['status', '=', 1],
                        ['receive_end_time', '>=', date('Y-m-d')],
                        ['receive_start_time', '<=', date('Y-m-d')],
                        ['is_integral_exchage', '=', 0],
                        ['exchange_num', '>', 0],
                        ['classify_str', '=', $param['store_id']]
                    ])
                    ->field('coupon_id,title,actual_price,full_subtraction_price,total_num,exchange_num,start_time,end_time,limit_num')
                    ->group('coupon_id')
                    ->append(['member_state'])
                    ->select();

                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch
            (\Exception $e) {
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
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $collectGoods->valid($param, 'login_collect_cart');
                if ($check['code']) return $crypt->response($check);

                // 取出已收藏的数据
                $goods_id = $collectGoods->where('identification', $param['identification'])->column('goods_id');

                // 新数组
                $arr = [];

                foreach (explode(',', $param['goods_id']) as $value) {
                    if (!in_array($value, $goods_id)) {
                        $arr[] = ['identification' => $param['identification'], 'goods_id' => $value];
                    }
                }

                // 批量新增
                $collectGoods->allowField(true)->saveAll($arr);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch
            (\Exception $e) {
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
    public function attr(Request $request, RSACrypt $crypt, Goods $goods, Attr $attr, GoodsAttr $goodsAttr)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                // 验证
                $check = $goods->valid($param, 'attr');
                if ($check['code']) return $crypt->response($check);

                $goodsData = $goods
                    ->where('goods_id', $param['goods_id'])
                    ->field('attr_type_id,goods_number')
                    ->find();
                // 商品规格
                $attrCollect = [];
                $attrArr = $goodsAttr
                    ->alias('ga')
                    ->where([['ga.goods_id', '=', $param['goods_id']]])
                    ->join('attr a', 'a.attr_id = ga.attr_id')
                    ->field('ga.goods_attr_id,ga.attr_value,a.attr_id,a.attr_name')
                    ->order(['ga.attr_id' => 'asc'])
                    ->select();
                if ($attrArr) {
                    foreach ($attrArr as $item) {
                        if (!array_key_exists($item['attr_id'], $attrCollect)) {
                            $attrCollect[$item['attr_id']] = [
                                'attr_id' => $item['attr_id'],
                                'attr_name' => $item['attr_name'],
                                'goods_attr' => [],
                            ];
                        }
                        array_push($attrCollect[$item['attr_id']]['goods_attr'], [
                            'attr_id' => $item['attr_id'],
                            'attr_value' => $item['attr_value'],
                            'goods_attr_id' => $item['goods_attr_id'],
                        ]);
                    }
                }
                $result['attr'] = $attrCollect ? array_values($attrCollect) : [];
                $result['goods_number'] = $goodsData['goods_number'];

                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch
            (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 会员购物车商品数量
     * @param Request $request
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @return mixed
     */
    public function number(Request $request, RSACrypt $crypt, LoginCartModel $loginCartModel)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();

                return $crypt->response(['code' => 0, 'result' => $loginCartModel->where('identification', $param['identification'])->count()]);

            } catch
            (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * @param $data
     * @param $val
     * @param $title
     * @param $list
     * @param $val2
     * @param $title2
     * @return array
     */
    protected function cartGrouping($data, $val, $title, $list, $val2, $title2)
    {

        if (empty($data)) return [];

        $result = array_values(array_reduce($data, function ($value, $key) use ($val, $val2, $title, $title2, $list) {
            $name = $key[$val];
            $name2 = $key[$val2];
            unset($key[$val]);
            $value[$name][$title] = $name;
            $value[$name][$title2] = $name2;
            $value[$name][$list][] = $key;
            return $value;
        }));

        return $result;
    }


}