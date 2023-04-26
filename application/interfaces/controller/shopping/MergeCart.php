<?php
declare(strict_types=1);

namespace app\interfaces\controller\shopping;

use app\common\model\Cart as CartModel;
use app\common\model\LoginCart;
use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\Products;
use app\common\model\Store;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Hook;

/**
 * 购物车 - Joy
 * Class Cart
 * @package app\interfaces\controller\goods
 */
class MergeCart extends BaseController
{

    /**
     * 合并购物车
     * @param RSACrypt $crypt
     * @param CartModel $cartModel
     * @param LoginCart $loginCart
     * @param Goods $goodsModel
     * @param Products $products
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function combine(RSACrypt $crypt,
                            CartModel $cartModel,
                            LoginCart $loginCart,
                            Goods $goodsModel,
                            Products $products)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $cartModel->valid($param, 'identification');
        $discount = discount($param['member_id']);
        $data = $loginCart
            ->alias('lc')
            ->where([
                ['identification', '=', $param['identification']],
                ['s.member_id', '<>', $param['member_id']],                 //排除自己的商品
            ])
            ->join('goods g', 'g.goods_id = lc.goods_id')
            ->join('store s', 's.store_id = lc.store_id')
            ->field('lc.login_cart_id,lc.goods_weight,lc.products_id,lc.store_id,g.is_vip,
            lc.goods_id,lc.goods_name,lc.file as cart_file,lc.price,lc.number,lc.attr,lc.goods_attr')
            ->select();
        $hasAttr = $noHasAttr = $allAttr = $allData = [];
        if (!$data->isEmpty()) {
            Db::startTrans();
            foreach ($data as $value) {
                $_a = '(member_id = ' . $param['member_id'] .
                    ' and goods_id = ' . $value['goods_id'];
                $allAttr[] = $_a . ($value['goods_attr'] ? ' and goods_attr = "' . $value['goods_attr'] . '")' : ')');
                if ($value['goods_attr']) {
                    $hasAttr[] = $value['products_id'];
                } else {
                    $noHasAttr[] = $value['goods_id'];
                }
                $allData[] = [
                    'store_id' => $value['store_id'],
                    'member_id' => $param['member_id'],
                    'goods_id' => $value['goods_id'],
                    'goods_name' => $value['goods_name'],
                    'file' => $value['cart_file'],
                    'price' => $value['price'],
                    'discount' => $discount,
                    'discount_price' => $value['is_vip'] ? $value['price'] * (100 - $discount) / 100 : 0,
                    'number' => $value['number'],
                    'attr' => $value['attr'],
                    'goods_attr' => $value['goods_attr'],
                    'goods_weight' => $value['goods_weight'],
                    'products_id' => $value['products_id']
                ];
                // 删除此条记录
                $value->delete(true);
            }
            $prodKV = $goodsKV = [];
            if (!empty($hasAttr)) {
                $prod = $products
                    ->where([
                        ['products_id', 'in', implode(',', $hasAttr)],
                    ])
                    ->field('products_id,attr_goods_number')
                    ->select();
                if (!$prod->isEmpty()) {
                    foreach ($prod as $_prod) {
                        $prodKV[$_prod['products_id']] = $_prod['attr_goods_number'];
                    }
                }
            }
            if (!empty($noHasAttr)) {
                $goods = $goodsModel
                    ->where([
                        ['goods_id', 'in', implode(',', $noHasAttr)]
                    ])
                    ->field('goods_id,goods_number')
                    ->select();
                if (!$goods->isEmpty()) {
                    foreach ($goods as $_goods) {
                        $goodsKV[$_goods['goods_id']] = $_goods['goods_number'];
                    }
                }
            }
            if (!empty($allAttr)) {
                $cart = $cartModel
                    ->whereRaw(implode(' or ', $allAttr))
                    ->field('cart_id,goods_id,goods_attr,number')
                    ->select();
                $updateArr = [];
                if (!$cart->isEmpty()) {
                    // 排除购物车内含有的
                    foreach ($data as $_data) {
                        foreach ($cart as $_cart) {
                            if ($_data['goods_id'] == $_cart['goods_id']
                                && $_data['goods_attr'] == $_cart['goods_attr']) {
                                if ($_cart['goods_attr']) {
                                    $on = isset($prodKV[$_data['products_id']]) ? $prodKV[$_data['products_id']] : 0;
                                } else {
                                    $on = isset($goodsKV[$_data['goods_id']]) ? $goodsKV[$_data['goods_id']] : 0;
                                }
                                $n = ($_cart['number'] + $_data['number']) > $on ?
                                    $on - $_cart['number'] :
                                    $_data['number'];
                                array_push($updateArr, [
                                    'cart_id' => $_cart['cart_id'],
                                    'number' => Db::raw('number + ' . $n),
                                ]);
                            }
                        }
                    }
                    if (!empty($allData)) {
                        $cartHasGoods = array_column($cart->toArray(), 'goods_id');
                        $newAllData = [];
                        foreach ($allData as $_allData) {
                            if (!in_array($_allData['goods_id'], $cartHasGoods)) {
                                $newAllData[] = $_allData;
                            }
                        }
                        $allData = $newAllData;
                    }
                }
                if (!empty($allData)) {
                    $cartModel
                        ->allowField(true)
                        ->isUpdate(false)
                        ->saveAll($allData);
                }
                if (!empty($updateArr)) {
                    $cartModel
                        ->allowField(true)
                        ->isUpdate(true)
                        ->saveAll($updateArr);
                }
            }
            Db::commit();
        }
        return $crypt->response([
            'code' => 0,
            'message' => '合并成功',
        ], true);
    }

}