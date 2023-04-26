<?php
declare(strict_types = 1);

namespace app\common\model;

use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\facade\Request;

/**
 * 商品模型
 * Class Goods
 * @package app\common\model
 */
class Goods extends BaseModel
{
    protected $pk = 'goods_id';
    protected $snPrefix = 'iShop';
    protected $goods_sn = '';
    
    /**
     * 省份
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getProvinceAttr($value, $data)
    {
        return is_numeric($data['province']) ?
            (new Area())
                ->where([
                    ['area_id', '=', $data['province']],
                ])
                ->value('area_name') : $data['province'];
    }
    
    /**
     * 城市
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getCityAttr($value, $data)
    {
        return is_numeric($data['city']) ?
            (new Area())
                ->where([
                    ['area_id', '=', $data['city']],
                ])->value('area_name') : $data['city'];
    }
    
    /**
     * 限时抢购状态
     * @param $value
     * @param $data
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getLimitStateAttr($value, $data)
    {
        if (array_key_exists('is_limit', $data) && $data['is_limit']) {
            // 查询数据
            $limit_find = (new Limit())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                    ['status', '=', 1],
                    ['up_shelf_time', '<=', date('Y-m-d')],
                    ['down_shelf_time', '>=', date('Y-m-d')],
                    ['exchange_num', '>', 0],
                ])
                ->field('available_sale,exchange_num,interval_id')
                ->order(['create_time' => 'desc'])
                ->find();
            if (!is_null($limit_find)) {
                // 查询时间段
                $now_limit_interval = (new LimitInterval())
                    ->where([
                        ['start_time', '<=', date('H')],
                        ['end_time', '>', date('H')],
                    ])
                    ->value('limit_interval_id');
                if (!is_null($now_limit_interval)) {
                    $has = in_array($now_limit_interval, explode(',', $limit_find['interval_id']));
                    return $has ? 1 : 0;
                }
                return 0;
            }
        }
        return 0;
    }
    
    /**
     * 降价通知
     * @param $goods_id
     * @param $price
     * @param $file
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function getReduction($goods_id, $price, $file)
    {
        //找出符合条件的降价提醒
        $data = (new GoodsReductionNotic())
            ->alias('grn')
            ->where([
                ['grn.goods_id', '=', $goods_id],
                ['grn.status', '=', 0],
                ['grn.expected_price', '>=', $price],
            ])
            ->join('member m', 'm.member_id = grn.member_id')
            ->join('goods g', 'g.goods_id = grn.goods_id')
            ->join('store s', 's.store_id = g.store_id')
            ->field('grn.goods_reduction_notic_id,grn.member_id,m.web_open_id,m.subscribe_time,
            m.micro_open_id,m.nickname,g.goods_name,s.store_name,m.phone')
            ->select();
        $idArr = [];
        // 推送消息[降价通知]
        $pushServer = app('app\\interfaces\\behavior\\Push');
        foreach ($data as $v) {
            array_push($idArr, $v['goods_reduction_notic_id']);
            $pushServer->send([
                'tplKey' => 'goods_state',
                'openId' => $v['web_open_id'],
                'subscribe_time' => $v['subscribe_time'],
                'phone' => $v['phone'],
                'microId' => $v['micro_open_id'],
                'data' => [0, $v['store_name'], $v['nickname'], $v['goods_name']],
                'inside_data' => [
                    'member_id' => $v['member_id'],
                    'type' => 0,
                    'jump_state' => '4',
                    'attach_id' => $goods_id,
                    'file' => $file,
                ],
                'sms_data' => [],
            ]);
        }
        if (!empty($idArr)) {
            (new GoodsReductionNotic())
                ->where([
                    ['goods_reduction_notic_id', 'in', implode(',', $idArr)],
                ])
                ->update(['status' => 1]);
        }
    }
    
    /**
     * 图片源路径
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getFileExtraAttr($value, $data)
    {
        return $data['file'];
    }
    
    /**
     * 图片源路径
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getRecommeFileExtraAttr($value, $data)
    {
        return $data['recomme_file'];
    }
    
    /**
     * pc图片源路径
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getPcRecommeFileExtraAttr($value, $data)
    {
        return $data['pc_recomme_file'];
    }
    
    /**
     * 多图源路径
     * @param $value
     * @param $data
     * @return array
     */
    public function getMultipleFileExtraAttr($value, $data)
    {
        return $data['multiple_file'] ? explode(',', $data['multiple_file']) : [];
    }
    
    /**
     * 倒计时
     * @param $value
     * @param $data
     * @return string
     */
    public function getContinueTimeAttr($value, $data)
    {
        if ($data['is_limit']) {
            $lii = (new Limit())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                ])
                ->value('interval_id');
            if (!is_null($lii)) {
                $lit = (new LimitInterval())
                    ->where([
                        ['limit_interval_id', 'in', $lii],
                        ['start_time', '<=', date('H')],
                        ['end_time', '>', date('H')],
                    ])
                    ->value('end_time');
                if (!is_null($lit)) {
                    $last_time = strtotime("$lit:00:00") - time();
                    return $last_time > 0 ? $last_time : -1;
                }
            }
            return -1;
        } elseif ($data['is_bargain']) {
            $cut = (new CutGoods())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                    ['status', '=', 1],
                ])
                ->value('down_shelf_time');
            if (!is_null($cut)) {
                $last_time = strtotime("$cut 00:00:00") - time();
                return $last_time > 0 ? $last_time : -1;
            }
            return -1;
        } elseif ($data['is_group']) {
            $ggt = (new GroupGoods())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                    ['status', '=', 1],
                ])
                ->value('down_shelf_time');
            if (!is_null($ggt)) {
                $last_time = strtotime("$ggt 00:00:00") - time();
                return $last_time > 0 ? $last_time : -1;
            }
            return -1;
        } else {
            return (($data['active_begin_time'] <= date('Y-m-d H:i:s') &&
                    $data['active_end_time'] >= date('Y-m-d H:i:s')) &&
                !empty($data['active_begin_time'])) ? strtotime($data['active_end_time']) - time() : -1;
        }
    }
    
    /**
     * 商品分类
     * @param $value
     * @param $data
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsClassifyAttr($value, $data)
    {
        $goodsClassify = app('app\\common\\model\\GoodsClassify');
        $goodsClassifyArr = getParCate($data['goods_classify_id'], $goodsClassify);
        if ($goodsClassifyArr) {
            return implode(' > ', array_column($goodsClassifyArr, 'title'));
        }
        return '暂无分类';
    }
    
    /**
     * 属性列表
     * @param $value
     * @param $data
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAttributeListAttr($value, $data)
    {
        $attrCollect = [];
        $attrArr = (new GoodsAttr())
            ->alias('ga')
            ->where([
                ['ga.goods_id', '=', $data['goods_id']],
            ])
            ->join('attr a', 'a.attr_id = ga.attr_id')
            ->field('ga.goods_attr_id,ga.attr_value,a.attr_id,a.attr_name')
            ->order(['ga.attr_id' => 'asc'])
            ->select();
        if (!$attrArr->isEmpty()) {
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
        return $attrCollect ? array_values($attrCollect) : [];
    }
    
    /**
     * 判断购物车是否存在并显示购物车数量
     * @param $value
     * @param $data
     * @return int|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCartIdAttr($value, $data)
    {
        $param = (new RSACrypt())->request();
        $member_id = request()->mid ?? '';
        if ($member_id) {
            return (new Cart())
                ->where([
                    ['member_id', '=', $member_id],
                    ['goods_id', '=', $data['goods_id']],
                ])
                ->value('cart_id') ?: '';
        } else if (array_key_exists('identification', $param) && $param['identification']) {
            return (new LoginCart())
                ->where([
                    ['identification', '=', $param['identification']],
                    ['goods_id', '=', $data['goods_id']],
                ])
                ->value('login_cart_id') ?: '';
        }
        return '';
    }
    
    /**
     * 判断购物车是否存在并显示购物车数量
     * @param $value
     * @param $data
     * @return int|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCartNumberAttr($value, $data)
    {
        $param = (new RSACrypt())->request();
        if ($mid = request(true)->mid) {
            return (new Cart())
                ->where([
                    ['member_id', '=', $mid],
                    ['goods_id', '=', $data['goods_id']],
                ])
                ->sum('number');
        } else if (array_key_exists('identification', $param) && $param['identification']) {
            return (new LoginCart())
                ->where([
                    ['identification', '=', $param['identification']],
                    ['goods_id', '=', $data['goods_id']],
                ])
                ->sum('number');
        }
        return 0;
    }
    
    /**
     * 统计
     * @return mixed
     */
    public function count()
    {
        //自营店铺商品总量
        $ret['Self'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([['s.shop', '=', 0]])
            ->count();
        //个人店铺商品总量
        $ret['OtherSelf'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([['s.shop', '=', 1]])
            ->count();
        //个人店铺商品总量
        $ret['OtherCompany'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([['s.shop', '=', 2]])
            ->count();
        //全部商品
        $ret['all'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([
                ['s.shop', '=', Request::get('type') ?: 0]
            ])
            ->count();
        //待审核
        $ret['review'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([
                ['review_status', '=', '2'],
                ['s.shop', '=', Request::get('type') ?: 0]
            ])
            ->count();
        //上架
        $ret['putaway'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([
                ['is_putaway', '=', '1'],
                ['review_status', '=', '1'],
                ['s.shop', '=', Request::get('type') ?: 0]
            ])->count();
        //下架
        $ret['unPutaway'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([
                ['is_putaway', '=', '0'],
                ['s.shop', '=', Request::get('type') ?: 0]
            ])->count();
        //回收站(软删除)  [只自营]
        $ret['softDelete'] = self::onlyTrashed()
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([
                ['forever_del_status', '=', 0],
                ['s.shop', '=', 0],
            ])->count();
        
        return $ret;
    }
    
    /**
     * 商家列表统计
     * @param $id
     * @return mixed
     */
    public function storeCount($id)
    {
        //自营店铺商品总量
        $ret['Self'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([['s.shop', '=', 0], ['s.store_id', '=', $id]])
            ->count();
        //个人店铺商品总量
        $ret['OtherSelf'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([['s.shop', '=', 1], ['s.store_id', '=', $id]])
            ->count();
        //个人店铺商品总量
        $ret['OtherCompany'] = $this
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id')
            ->where([['s.shop', '=', 2], ['s.store_id', '=', $id]])
            ->count();
        $ret['all'] = $this
            ->join('brand b', 'b.brand_id = ishop_goods.brand_id', 'left')
            ->where([
                ['store_id', '=', $id]
            ])
            ->count();
        // 商品审核 待审核和未通过
        $ret['review'] = $this
            ->join('brand b', 'b.brand_id = ishop_goods.brand_id', 'left')
            ->where([
                ['review_status', 'in', '0,2'],
                ['store_id', '=', $id]
            ])
            ->count();
        //上架
        $ret['putaway'] = $this
            ->join('brand b', 'b.brand_id = ishop_goods.brand_id', 'left')
            ->where([
                ['is_putaway', '=', '1'],
                ['review_status', '=', '1'],
                ['store_id', '=', $id]
            ])->count();
        //下架
        $ret['unPutaway'] = $this
            ->join('brand b', 'b.brand_id = ishop_goods.brand_id', 'left')
            ->where([
                ['is_putaway', '=', '0'],
                ['review_status', '=', '1'],
                ['store_id', '=', $id]
            ])->count();
        //回收站(软删除)
        $ret['softDelete'] = self::onlyTrashed()->where([['store_id', 'in', $id], ['forever_del_status', '=', 0]])->count();
        
        return $ret;
    }
    
    
    /**
     * 检测货号是否唯一
     * @param $param
     * @return mixed
     */
    public function checkGoodsSnUnique($param)
    {
        $where = [['goods_sn', '=', $param['goods_sn']]];
        if (array_key_exists('goods_id', $param) && $param['goods_id']) {
            array_push($where, ['goods_id', '<>', $param['goods_id']]);
        }
        return $this->where($where)->value('goods_id');
    }
    
    /**
     * 设置商品货号
     * @param $id int 刚插入的id
     * @return string
     */
    public function setGoodsSn($id)
    {
        $this->goods_sn = $this->snPrefix . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this
            ->allowField(true)
            ->isUpdate(true)
            ->save([
                'goods_id' => $id,
                'goods_sn' => $this->goods_sn,
            ]);
        return $this->goods_sn;
    }
    
    /**
     * 关联阶梯价格
     * @return \think\model\relation\HasMany
     */
    public function ladder()
    {
        return $this->hasMany('GoodsLadder', 'goods_id', 'goods_id');
    }
    
    /**
     * 关联满减对照
     * @return \think\model\relation\HasMany
     */
    public function sub()
    {
        return $this->hasMany('GoodsSub', 'goods_id', 'goods_id');
    }
    
    /**
     * 关联订单商品
     * @return \think\model\relation\HasMany
     */
    public function ordergoods()
    {
        return $this->hasMany('OrderGoods', 'goods_id', 'goods_id');
    }
    
    /**
     * 关联商品属性
     * @return \think\model\relation\HasMany
     */
    public function spec()
    {
        return $this->hasMany('Products', 'goods_id', 'goods_id');
    }
    
    /**
     * 关联商品属性
     * @return \think\model\relation\HasMany
     */
    public function arrivalSpec()
    {
        return $this->spec()->field('products_id,goods_id,attr_goods_number,attr_warn_number');
    }
    
    /**
     * 关联商品参数
     * @return \think\model\relation\HasMany
     */
    public function parameter()
    {
        return $this->hasMany('GoodsParameter', 'goods_id', 'goods_id');
    }
    
    /**
     * 关联店铺参数
     * @return \think\model\relation\HasOne
     */
    public function store()
    {
        return $this->hasOne('Store', 'store_id', 'store_id')->field('store_id,store_name');
    }
    
    /**
     * 关联店铺类型
     * @return \think\model\relation\HasOne
     */
    public function storeStatus()
    {
        return $this->hasOne('Store', 'store_id', 'store_id')->field('store_id,shop');
    }
    
    /**
     * 关联拼团商品
     *
     * @return \think\model\relation\HasOne
     */
    public function groupHO()
    {
        return $this->hasOne('GroupGoods', 'goods_id', 'goods_id');
    }
    
    /**
     * 限时抢购
     * @return \think\model\relation\HasOne
     */
    public function limitHO()
    {
        return $this->hasOne('Limit', 'goods_id', 'goods_id');
    }
    
    /**
     * 砍价
     * @return \think\model\relation\HasOne
     */
    public function cutHO()
    {
        return $this->hasOne('CutGoods', 'goods_id', 'goods_id');
    }
    
    
    /**
     * 同城配送
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsCityAttr($value, $data)
    {
        if ($value && array_key_exists('express_one_city', $data) && $data['express_one_city'] == 1) {
            return 1;
        }
        return 0;
    }
    
    public function GoodsAllPrice()
    {
        return $this->hasMany('OrderGoods', 'goods_id', 'goods_id');
    }
    
    /**
     * 全国快递
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsDeliveryAttr($value, $data)
    {
        if ($value && array_key_exists('express', $data) && $data['express'] == 1) {
            return 1;
        }
        return 0;
    }
    
    /**
     * 门店自提
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsShopAttr($value, $data)
    {
        if ($value && array_key_exists('express_self_lifting', $data) && $data['express_self_lifting'] == 1) {
            return 1;
        }
        return 0;
    }
    
    /**
     * 设置关联商品参数
     * @param $param
     */
    public function setParameter($param)
    {
        //整理商品参数
        $parameter = [];
        foreach ($param['parameter_name'] as $key => $value) {
            if ($value !== '')
                array_push($parameter, ['parameter_name' => $value, 'parameter_val' => $param['parameter_val'][$key]]);
        }
        //删除原有参数
        if (array_key_exists('goods_id', $param) && $param['goods_id'])
            GoodsParameter::where(['goods_id' => $param['goods_id']])->delete();
        // 关联新增商品参数
        if ($parameter) $this->parameter()->saveAll($parameter);
    }
    
    /**
     * 设置商品属性
     * @param $param
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function setSpec($param)
    {
        $productModel = new Products();
        $origin = $productModel
            ->where([['goods_id', '=', $param['goods_id']]])
            ->field('products_id,goods_attr')
            ->select();
        $originId = array_column($origin->toArray(), 'products_id');
        if (array_key_exists('spec', $param) && $param['spec']) {
            array_walk($param['spec'], function (&$v, $k) use ($param) {
                $v['goods_id'] = $param['goods_id'];
                if (!$v['attr_goods_weight']) {
                    $v['attr_goods_weight'] = $this['goods_weight'];
                }
                unset($param['spec'][$k]['products_id']);
            });
            $specEditArr = $productIdArr = [];
            if ($origin->count()) {
                foreach ($origin as $item) {
                    foreach ($param['spec'] as $key => $val) {
                        if ($item['goods_attr'] == $key) {
                            array_push($productIdArr, $val['products_id'] = $item['products_id']);
                            array_push($specEditArr, $val);
                            unset($param['spec'][$key]);
                        }
                    }
                }
            }
            if ($origin = $origin->toArray()) {
                $originId = array_diff(array_column($origin, 'products_id'), $productIdArr);
            }
            if ($specEditArr) {
                $productModel->allowField(true)->isUpdate(true)->saveAll($specEditArr);
            }
            if ($param['spec']) {
                $res = $productModel->allowField(true)->isUpdate(false)->saveAll($param['spec']);
                if ($res->count()) {
                    $productIdArr = array_merge($productIdArr, array_column($res->toArray(), 'products_id'));
                }
            }
            // 补充商品规格货号
            $specSn = [];
            foreach ($productIdArr as $item) {
                array_push($specSn, ['products_id' => $item, 'attr_goods_sn' => $this['goods_sn'] . '_' . $item]);
            }
            if ($specSn) {
                $productModel->allowField(true)->isUpdate(true)->saveAll($specSn);
            }
        }
        if ($originId) {
            $productModel->where([['products_id', 'in', implode(',', $originId)]])->delete();
        }
    }
}