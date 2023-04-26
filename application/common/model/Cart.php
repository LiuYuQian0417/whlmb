<?php
declare(strict_types = 1);

namespace app\common\model;

use think\facade\Request;

/**
 * 购物车表
 * Class Cart
 * @package app\common\model
 */
class Cart extends BaseModel
{
    
    protected $pk = 'cart_id';
    
    /**
     * 获取用户购物车数量
     * @param $member_id
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cartNum($member_id)
    {
        $val = null;
        if ($member_id) {
            $val = $this
                ->alias('c')
                ->where([
                    ['c.member_id', '=', $member_id],
                    ['g.is_putaway', '=', 1],
                    ['g.review_status', '=', 1],
                    ['s.status', '=', 4],               //认证通过
                ])
                ->join('goods g', 'g.goods_id = c.goods_id')
                ->join('store s', 's.store_id = g.store_id')
                ->field('sum(c.number) as num')
                ->find();
        }
        return is_null($val) ? 0 : intval($val['num']);
    }
    
    /**
     * 获取库存
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getInventoryAttr($value, $data)
    {
        // 检查库存
        if ($data['goods_attr']) {
            // 查看商品库存
            $number = (new Products())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                    ['goods_attr', '=', $data['goods_attr']],
                ])
                ->value('attr_goods_number');
        } else {
            // 查看商品库存
            $number = (new Goods())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                ])->value('goods_number');
        }
        return $number ?: 0;
    }
    
    /**
     * 获取商品上架状态
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getIsPutawayAttr($value, $data)
    {
        return (new Goods())
            ->where([
                ['goods_id', '=', $data['goods_id']],
            ])
            ->value('is_putaway');
    }
    
    public function getGoodsClassifyIdAttr($value, $data)
    {
        return (new Goods())
            ->where([
                ['goods_id', '=', $data['goods_id']],
            ])
            ->value('goods_classify_id');
    }
    
    /**
     * 获取店铺名称
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getStoreNameAttr($value, $data)
    {
        return (new Store())
            ->where([
                ['store_id', '=', $data['store_id']],
            ])
            ->value('store_name');
    }
    
    /**
     * 获取店铺状态
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getStoreStatusAttr($value, $data)
    {
        return (new Store())
            ->where([
                ['store_id', '=', $data['store_id']],
            ])
            ->value('status');
    }
    
    /**
     * 获取收藏状态
     * @param $value
     * @param $data
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCollectStatusAttr($value, $data)
    {
        return (new CollectGoods())
            ->where([
                ['member_id', '=', request(0)->mid],
                ['goods_id', '=', $data['goods_id']],
            ])
            ->value('collect_goods_id') ?: 0;
    }
    
    /**
     * 获取优惠券状态
     * @param $value
     * @param $data
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCouponStatusAttr($value, $data)
    {
        $where = [
            ['modality', '=', 0],           // 非大转盘
            ['status', '=', 1],
            ['receive_end_time', '>=', date('Y-m-d')],
            ['receive_start_time', '<=', date('Y-m-d')],
            ['exchange_num', '>', 0],
            ['is_integral_exchage', '=', 0],
        ];
        $cateArr = getParCate($data['goods_classify_id'], (new GoodsClassify()));
        // type 0店铺1平台
        $whereRaw = "((type = 0 and classify_str = " . $data['store_id'] . ") 
        or (type = 1 and find_in_set('" . reset($cateArr)['goods_classify_id'] . "',classify_str)))";
        $get = (new Coupon())
            ->where($where)
            ->whereRaw($whereRaw)
            ->value('coupon_id');
        return $get ? 1 : 0;
    }
    
    /**
     * 获取配送方式
     * @param $value
     * @param $data
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getDistributionAttr($value, $data)
    {
        return (new Store())
            ->where([
                ['store_id', '=', $data['store_id']],
            ])
            ->field('is_city,is_shop,is_express,is_pay_delivery')
            ->find();
    }
    
    /**
     * 获取配送方式
     * @param $value
     * @param $data
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getFreightAttr($value, $data)
    {
        $member_address_id = Request::param('member_address_id');
        $condition[] = ['member_id', '=', Request::param('member_id')];
        if ($member_address_id) $condition[] = ['member_address_id', '=', $member_address_id];
        // 读取地址
        $address = (new MemberAddress())
            ->where($condition)
            ->field('street,lat,lng,city')
            ->append(['street_id'])
            ->order('is_default', 'desc')
            ->find();
        if (!$address) return null;
        $goods = (new Goods())
            ->where('goods_id', $data['goods_id'])
            ->field('freight_id,goods_weight,is_freight')
            ->find();
        $freightService = app('app\\common\\service\\Freight',
            [
                'args' => [
                    [
                        'goods_id' => $data['goods_id'],
                        'store_id' => $data['store_id'],
                        'goods_attr' => is_null($data['goods_attr']) ? '' : $data['goods_attr'],
                        'is_freight' => $goods['is_freight'],
                        'freight_id' => $goods['freight_id'],
                        'quantity' => $data['number'],
                        'single_price' => $data['price'],
                        'goods_weight' => $goods['goods_weight']
                    ]
                ],
                'address' => [
                    'street_id' => $address['street_id'],
                    'city_name' => $address['city'],
                    'lat' => $address['lat'],
                    'lng' => $address['lng'],
                ]
            ], true);
        return $freightService->calculation();
    }
}