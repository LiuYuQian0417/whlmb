<?php
declare(strict_types=1);

namespace app\computer\controller\shopping;

use app\computer\model\Cart as CartModel;
use app\computer\model\LoginCart;
use app\computer\model\Goods;
use app\computer\model\Member;
use app\computer\model\MemberRank;
use app\computer\model\Products;
use app\computer\model\Store;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 购物车
 * Class Cart
 * @package app\computer\controller\goods
 */
class MergeCart extends BaseController
{

    /**
     * 合并购物车
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param LoginCart $loginCart
     * @param Store $store
     * @param Goods $goodsModel
     * @param Products $products
     * @return mixed
     */
    public function combine(Request $request, RSACrypt $crypt, CartModel $cartModel, LoginCart $loginCart, Store $store, Goods $goodsModel, Products $products)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;

                // 验证
                $check = $cartModel->valid($param, 'identification');
                if ($check['code']) return $crypt->response($check);

                // 会员等级
                $discount = discount($param['member_id']);

                // 未登录信息
                $data = $loginCart
                    ->where('identification', $param['identification'])
                    ->field('login_cart_id,goods_weight,products_id,store_id,goods_id,goods_name,file as cart_file,price,number,attr,goods_attr')
                    ->select();

                foreach ($data as $value) {

                    // 会员ID
                    $member_id = $store->where('store_id', $value['store_id'])->value('member_id');

                    // 判断是否是自己的商品
                    if ($member_id <> $param['member_id']) {

                        // 检查库存
                        if ($value['goods_attr']) {

                            // 查看商品库存
                            $number = $products->where([['goods_id', '=', $value['goods_id']], ['goods_attr', '=', $value['goods_attr']]])->value('attr_goods_number');

                        } else {

                            // 查看商品库存
                            $number = $goodsModel->where('goods_id', $value['goods_id'])->value('goods_number');

                        }

                        // 读取本地库是否存在
                        $cart = $cartModel
                            ->where([
                                ['member_id', '=', $param['member_id']],
                                ['goods_id', '=', $value['goods_id']],
                                ['goods_attr', '=', $value['goods_attr']]
                            ])
                            ->field('cart_id,number')
                            ->find();

                        // 计算库存
                        $num = ($cart['number'] + $value['number']) > $number ? $number - $cart['number'] : $value['number'];

                        // 判断是否存在
                        if ($cart) {

                            // 增加库存
                            $cartModel->where('cart_id', $cart['cart_id'])->setInc('number', $num);

                        } else {

                            // 插入记录
                            $cartModel->save([
                                'store_id'     => $value['store_id'],
                                'member_id'    => $param['member_id'],
                                'goods_id'     => $value['goods_id'],
                                'goods_name'   => $value['goods_name'],
                                'file'         => $value['cart_file'],
                                'price'        => $value['price'] * ($discount / 100),
                                'discount'     => $discount,
                                'number'       => $value['number'],
                                'attr'         => $value['attr'],
                                'goods_attr'   => $value['goods_attr'],
                                'goods_weight' => $value['goods_weight'],
                                'products_id'  => $value['products_id']
                            ]);

                        }

                    }

                    // 删除此条
                    $loginCart::destroy($value['login_cart_id'], true);

                }

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

}