<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\Goods;
use app\common\model\GoodsClassify;
use app\common\model\Coupon as CouponModel;
use app\common\model\Member;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;

/**
 * 优惠券 - Joy
 * Class Coupon
 * @package app\interfaces\controller\auth
 */
class Coupon extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('app_path') . 'common/ini/.config');
    }
    
    /**
     * 领券中心 - 领券 - 精选
     * @param RSACrypt $crypt
     * @param Goods $goods
     * @param CouponModel $coupon
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get(RSACrypt $crypt,
                        Goods $goods,
                        CouponModel $coupon,
                        GoodsClassify $goodsClassify)
    {
        $param = $crypt->request();
        $result = [
            'current_page' => $param['page'],
            'per_page' => 10,
            'data' => [],
            'total' => 0,
        ];
        if (env('is_coupon', 1)) {
            $condition = [
                ['status', '=', 1],
                ['is_gift', '=', 0],
                ['modality', '=', 0],
                ['receive_end_time', '>=', date('Y-m-d')],
                ['receive_start_time', '<=', date('Y-m-d', strtotime("+1 day"))],
                ['is_integral_exchage', '=', 0],            // 是否参与积分兑换
            ];
            $singleShopCond = [];
            // 单店铺模式
//            if (!self::$oneOrMore) {
//                array_push($condition, ['type', '=', 1]);   // 只查询平台优惠券
//                array_push($singleShopCond, ['s.store_id', '=', self::$oneStoreId]);
//            }
            $arr = $coupon
                ->where($condition)
                ->field('coupon_id,type,file,title,actual_price,full_subtraction_price,
                    total_num,exchange_num,receive_start_time,classify_str,limit_num,
                    if(!type,(select store_id from `ishop_store` s where s.store_id = classify_str and
                     ' . self::$storeAuthSql . '),0) as cond')
                ->group('coupon_id')
                ->append(['member_state', 'distance_start_time'])
                ->having('cond is not null')
                ->order(['create_time' => 'desc'])
                ->select();
            if (!$arr->isEmpty()) {
                $result['total'] = $arr->count();
                $result['data'] = array_slice($arr->toArray(), 10 * ($param['page'] - 1), 10);
                if (!empty($result['data'])) {
                    foreach ($result['data'] as &$value) {
                        if ($value['type'] == 0) {
                            // 店铺优惠券
                            $value['goods_list'] = $goods
                                ->alias('g')
                                ->whereRaw(self::$goodsAuthSql . ' and g.store_id = ' . $value['classify_str'])
                                ->field('g.goods_id,g.file')
                                ->order(['g.create_time' => 'desc'])
                                ->limit(3)
                                ->select();
                        } else {
                            // 平台优惠券
                            $goods_classify_str = implode(',',
                                array_column(getParCate($value['classify_str'], $goodsClassify, 0), 'goods_classify_id'));
                            $value['goods_list'] = $goods
                                ->alias('g')
                                ->whereRaw(self::$goodsAuthSql . ' and g.goods_classify_id in (' . $goods_classify_str . ')')
                                ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
                                ->where($singleShopCond)
                                ->field('g.goods_id,g.file')
                                ->order(['g.create_time' => 'desc'])
                                ->limit(3)
                                ->select();
                        }
                    }
                }
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 领券中心 - 换券
     * @param RSACrypt $crypt
     * @param CouponModel $coupon
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function exchange(RSACrypt $crypt,
                             CouponModel $coupon)
    {
        $args = $crypt->request();
        $result = [
            'current_page' => $args['page'],
            'per_page' => 10,
            'data' => [],
            'total' => 0,
        ];
        if (env('is_coupon', 1)) {
            $where = [
                ['status', '=', 1],
                ['is_gift', '=', 0],
                ['modality', '=', 0],           //优惠券形式： 0 普通 1 大转盘活动
                ['receive_end_time', '>=', date('Y-m-d')],
                ['receive_start_time', '<=', date('Y-m-d', strtotime("+1 day"))],
                ['is_integral_exchage', '=', 1],
            ];
            // 单店铺模式,只查询平台优惠券
//            if (!self::$oneOrMore) {
//                array_push($where, ['type', '=', 1]);
//            }
            $arr = $coupon
                ->where($where)
                ->field('coupon_id,file,title,actual_price,integral,full_subtraction_price,exchange_num,
                    if(!type,(select store_id from `ishop_store` s where s.store_id = classify_str and
                     ' . self::$storeAuthSql . '),0) as cond')
                ->having('cond is not null')
                ->select();
            if (!$arr->isEmpty()) {
                $result['total'] = $arr->count();
                $result['data'] = array_slice($arr->toArray(), 10 * ($args['page'] - 1), 10);
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 领券中心 - 换券 - 立即兑换详情
     * @param RSACrypt $crypt
     * @param CouponModel $coupon
     * @param Goods $goods
     * @param GoodsClassify $goodsClassify
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exchange_view(RSACrypt $crypt,
                                  CouponModel $coupon,
                                  Goods $goods,
                                  GoodsClassify $goodsClassify,
                                  Member $member)
    {
        if (env('is_coupon', 1)) {
            $param = $crypt->request();
            $param['member_id'] = request(0)->mid;
            $coupon->valid($param, 'exchange_view');
            $where = [
                ['coupon_id', '=', $param['coupon_id']],
            ];
            $singleShopCond = [];
            // 单店铺模式
//            if (!self::$oneOrMore) {
//                // 只保留平台优惠券
//                array_push($where, ['type', '=', 1]);
//                array_push($singleShopCond, ['s.store_id', '=', self::$oneStoreId]);
//            }
            $result = $coupon
                ->where($where)
                ->field('coupon_id,type,title,actual_price,full_subtraction_price,
                exchange_num,integral,classify_str')
                ->find();
            if (is_null($result)) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '优惠券不存在',
                ], true);
            }
            // 会员积分
            $result['member_integral'] = $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->value('pay_points') ?: 0;
            // 适用商品
            if ($result['type'] == 0) {
                // 店铺优惠券
                $result['goods_list'] = $goods
                    ->alias('g')
                    ->whereRaw(self::$goodsAuthSql . ' and g.store_id = ' . $result['classify_str'])
                    ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
                    ->field('goods_id,file')
                    ->order(['g.create_time' => 'desc'])
                    ->limit(3)
                    ->select();
            } else {
                // 平台优惠券
                $goods_classify_str = implode(',',
                    array_column(getParCate($result['classify_str'], $goodsClassify, 0), 'goods_classify_id'));
                $result['goods_list'] = $goods
                    ->alias('g')
                    ->whereRaw(self::$goodsAuthSql . ' and g.goods_classify_id in (' . $goods_classify_str . ')')
                    ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
                    ->where($singleShopCond)
                    ->field('g.goods_id,g.file')
                    ->order(['g.create_time' => 'desc'])
                    ->limit(3)
                    ->select();
            }
            return $crypt->response([
                'code' => 0,
                'message' => '查询成功',
                'result' => $result
            ], true);
        }
        return $crypt->response([
            'code' => -1,
            'message' => '该模式不支持优惠券',
        ], true);
    }
}