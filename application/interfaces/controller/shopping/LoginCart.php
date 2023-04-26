<?php
declare(strict_types = 1);

namespace app\interfaces\controller\shopping;

use app\common\model\GoodsAttr;
use app\common\model\LoginCart as LoginCartModel;
use app\common\model\Coupon;
use app\common\model\Goods;
use app\common\model\Products;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 未登录购物车（app,小程序） - Joy
 * Class LoginCart
 * @package app\interfaces\controller\goods
 */
class LoginCart extends BaseController
{
    
    /**
     * 加入购物车
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @param Goods $goodsModel
     * @param Products $products
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(RSACrypt $crypt,
                           LoginCartModel $loginCartModel,
                           Goods $goodsModel,
                           Products $products)
    {
        $param = $crypt->request();
        $loginCartModel->valid($param, 'login_create');
        $cart = $loginCartModel
            ->where([
                ['identification', '=', $param['identification']],
                ['goods_id', '=', $param['goods_id']],
                ['goods_attr', '=', $param['goods_attr']]
            ])
            ->field('login_cart_id,number')
            ->find();
        if ($param['goods_attr']) {
            $goods = $products
                ->alias('p')
                ->where([
                    ['p.goods_id', '=', $param['goods_id']],
                    ['p.goods_attr', '=', $param['goods_attr']],
                ])
                ->join('goods g', 'g.goods_id = p.goods_id')
                ->field('p.attr_goods_number,p.attr_shop_price as shop_price,
                p.attr_goods_weight as goods_weight,g.is_vip')
                ->find();
            // 判断库存是否够
            if (($goods['attr_goods_number'] - $cart['number']) < $param['number']) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '商品库存不够,请改变数量再添加',
                ], true);
            }
        } else {
            // 查看商品价格和库存
            $goods = $goodsModel
                ->where([
                    ['goods_id', '=', $param['goods_id']],
                ])
                ->field('shop_price,goods_number,goods_weight,is_vip')
                ->find();
            // 判断库存是否够
            if (($goods['goods_number'] - $cart['number']) < $param['number']) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '商品库存不够,请改变数量再添加',
                ], true);
            }
        }
        $param['price'] = $goods['shop_price'];
        $param['goods_weight'] = $goods['goods_weight'];
        // 判断是更新数量还是新增
        if ($cart['login_cart_id']) {
            $loginCartModel
                ->where([
                    ['identification', '=', $param['identification']],
                    ['goods_id', '=', $param['goods_id']],
                    ['goods_attr', '=', $param['goods_attr']]
                ])
                ->setInc('number', $param['number']);
        } else {
            $loginCartModel
                ->allowField(true)
                ->save($param);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '添加成功',
        ], true);
    }
    
    /**
     * 购物车列表
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @param Goods $goods
     * @param Coupon $coupon
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          LoginCartModel $loginCartModel,
                          Goods $goods,
                          Coupon $coupon)
    {
        $param = $crypt->request();
        $lose = [];
        $where = [
            ['identification', '=', $param['identification']],
        ];
        if ($param['store_id']) {
            array_push($where, ['store_id', '=', $param['store_id']]);
        }
        // 列表
        $result = $loginCartModel
            ->where($where)
            ->field('login_cart_id as cart_id,store_id,goods_id,goods_name,file,
            file as cart_file,price,number,attr,goods_attr')
            ->append(['inventory', 'is_putaway', 'store_name', 'store_status', 'coupon_status'])
            ->order(['create_time' => 'desc'])
            ->select()
            ->toArray();
        foreach ($result as $key => $value) {
            if ($value['is_putaway'] <> 1 || $value['store_status'] <> 4) {
                $lose[] = $value;
                unset($result[$key]);
            }
        }
        // 折扣
        $discount = discount(0);
        // 优惠券数量
        $coupon_status = $coupon
            ->where([
                ['type', '=', 0],
                ['modality', '=', 0],
                ['classify_str', '=', $param['store_id']],
                ['status', '=', 1],
                ['receive_end_time', '>=', date('Y-m-d')],
                ['receive_start_time', '<=', date('Y-m-d')]
            ])
            ->value('coupon_id') ? 1 : 0;
        $result = $this->cartGrouping(array_values($result),
            'store_id', 'store_id', 'list',
            'store_name', 'store_name');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'lost' => $lose,
            'lost_count' => count($lose),
            'recommend_list' => recommend_list($goods, 4),
            'discount' => $discount,
            'coupon_status' => $coupon_status,
        ]);
    }
    
    /**
     * 购物车控制增加数量
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @param Products $products
     * @param Goods $goodsModel
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add_number(RSACrypt $crypt,
                               LoginCartModel $loginCartModel,
                               Products $products,
                               Goods $goodsModel)
    {
        $param = $crypt->request();
        $loginCartModel->valid($param, 'login_add_number');
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
            $number = $products
                ->where([
                    ['goods_id', '=', $cart['goods_id']],
                    ['goods_attr', '=', $cart['goods_attr']],
                ])
                ->value('attr_goods_number');
        } else {
            // 查看商品库存
            $number = $goodsModel
                ->where([
                    ['goods_id', '=', $cart['goods_id']],
                ])
                ->value('goods_number');
        }
        // 判断库存是否够
        if (($number - $cart['number']) < $param['number']) {
            return $crypt->response([
                'code' => -1,
                'message' => '商品库存不够,请改变数量再添加',
            ], true);
        }
        // 新增
        $loginCartModel
            ->where([
                ['login_cart_id', '=', $param['login_cart_id']],
                ['identification', '=', $param['identification']]
            ])
            ->setInc('number', $param['number']);
        return $crypt->response([
            'code' => 0,
            'message' => '添加成功',
        ], true);
        
    }
    
    
    /**
     * 购物车控制减少数量
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @return mixed
     * @throws \think\Exception
     */
    public function reduce_number(RSACrypt $crypt,
                                  LoginCartModel $loginCartModel)
    {
        $param = $crypt->request();
        $loginCartModel->valid($param, 'login_reduce_number');
        // 减少数量
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
        if ($number == 0) {
            $loginCartModel::destroy($param['login_cart_id'], true);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '减少成功',
        ], true);
    }
    
    /**
     * 购物车删除
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @return mixed
     */
    public function delete(RSACrypt $crypt,
                           LoginCartModel $loginCartModel)
    {
        $param = $crypt->request();
        $loginCartModel->valid($param, 'login_delete');
        $loginCartModel::destroy($param['login_cart_id'], true);
        return $crypt->response([
            'code' => 0,
            'message' => '清空成功',
        ], true);
    }
    
    /**
     * 购物车编辑
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @param Products $products
     * @param Goods $goodsModel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update(RSACrypt $crypt,
                           LoginCartModel $loginCartModel,
                           Products $products,
                           Goods $goodsModel)
    {
        $param = $crypt->request();
        $loginCartModel->valid($param, 'login_update');
        // 检查库存
        if ($param['goods_attr']) {
            // 查看商品库存
            $number = $products
                ->where([
                    ['goods_id', '=', $param['goods_id']],
                    ['goods_attr', '=', $param['goods_attr']],
                ])
                ->value('attr_goods_number');
        } else {
            // 查看商品库存
            $number = $goodsModel
                ->where([
                    ['goods_id', $param['goods_id']],
                ])
                ->value('goods_number');
        }
        // 判断库存是否够
        if ($number < $param['number']) {
            return $crypt->response([
                'code' => -1,
                'message' => '商品库存不够,请改变数量再添加',
            ], true);
        }
        // 查询是否有相同属性的购物车商品
        $same = $loginCartModel
            ->where([
                ['goods_id', '=', $param['goods_id']],
                ['goods_attr', '=', $param['goods_attr']],
                ['login_cart_id', '<>', $param['login_cart_id']],
            ])
            ->field('login_cart_id,number')
            ->find();
        if (!is_null($same)) {
            // $param['number'] += $same['number'];
            $same->number += $param['number'];
            $same->save();
        } else {
            // 更新
            $loginCartModel
                ->allowField(true)
                ->isUpdate(true)
                ->save($param);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '编辑成功',
        ]);
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
            ->where([
                ['goods_id', '=', $param['goods_id']],
            ])
            ->field('attr_type_id,goods_number')
            ->find();
        $attrCollect = [];
        $attrArr = $goodsAttr
            ->alias('ga')
            ->where([
                ['ga.goods_id', '=', $param['goods_id']],
            ])
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
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 会员购物车商品数量
     * @param RSACrypt $crypt
     * @param LoginCartModel $loginCartModel
     * @return mixed
     */
    public function number(RSACrypt $crypt,
                           LoginCartModel $loginCartModel)
    {
        $param = $crypt->request();
        $count = $loginCartModel
            ->where([
                ['identification', '=', $param['identification']],
            ])
            ->sum('number');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $count,
        ], true);
        
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
    protected function cartGrouping($data,
                                    $val,
                                    $title,
                                    $list,
                                    $val2,
                                    $title2)
    {
        if (empty($data)) {
            return [];
        }
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