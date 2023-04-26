<?php
declare(strict_types = 1);

namespace app\computer\controller\auth;

use app\computer\model\Goods;
use app\computer\model\GoodsClassify;
use app\computer\model\Coupon as CouponModel;
use app\computer\model\Member;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Request;
use app\computer\model\Adv;
use think\facade\Session;
use think\facade\Config;

/**
 * 优惠券
 * Class Coupon
 * @package app\computer\controller\auth
 */
class Coupon extends BaseController
{
    /**
     * 获取banner和theme
     * @param Adv $adv
     * @param int $type
     * @param bool $all
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getAd(Adv $adv, $type, $all = TRUE)
    {
        $Base = $adv
            ->where(
                [
                    ['adv_position_id', '=', $type],
                    ['status', '=', 1],
                    ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ['client', '=', 1],
                ]
            )
            ->field('adv_id,title,type,content,file')
            ->order('sort', 'desc');
        $data = $all ? $Base->select() : $Base->find();
        return $data;
    }

    /**
     * 领券中心 - 领券 - 精选
     * @param Request $request
     * @param Goods $goods
     * @param CouponModel $coupon
     * @param GoodsClassify $goodsClassify
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get(Request $request,Goods $goods, CouponModel $coupon, GoodsClassify $goodsClassify,Adv $adv)
    {
        // 获取参数
        $param = $request::instance()->param();

        $result['left_banner'] = self::getAd($adv, Config::get('pc_config.get_coupon_left_id'), TRUE);

        $result['right_banner'] = self::getAd($adv, Config::get('pc_config.get_coupon_right_id'), '');
        $result['top_data'] = $coupon
            ->where([
                        ['status', '=', 1],
                        ['modality', '=', 0],
                        ['receive_end_time', '>=', date('Y-m-d')],
                        ['receive_start_time', '<=', date('Y-m-d')],
                        ['is_integral_exchage', '=', 1]
                    ])
            ->field('coupon_id,file,title,actual_price,full_subtraction_price,exchange_num,integral')
            ->limit(3)->select();
        // 条件
        $condition = [];

        // 默认
        $condition[] = ['status', '=', 1];
        $condition[] = ['type', '=', 0]; //店铺优惠券
        $condition[] = ['is_gift', '=', 0];
        $condition[] = ['modality', '=', 0];
        $condition[] = ['receive_end_time', '>=', date('Y-m-d')];
        $condition[] = ['receive_start_time', '<=', date('Y-m-d', strtotime("+1 day"))];
        $condition[] = ['is_integral_exchage', '=', 0];

        // 分类
        if (!empty($param['category'])) {
            $condition[] = ['classify_str', 'in', $param['category'] . ',' . implode(',', array_column(getParCate($param['category'], $goodsClassify, 0), 'goods_classify_id'))];
        } else {
            $param['category'] = 0;
        }

        $result['result'] = $coupon
            ->where($condition)
            ->field('coupon_id,type,file,title,actual_price,full_subtraction_price,total_num,exchange_num,receive_start_time,classify_str,limit_num')
            ->group('coupon_id')
            ->append(['member_state', 'distance_start_time'])
            ->order('create_time', 'desc')
            ->paginate(18,FALSE,['query' => $param]);

//        foreach ($result['result'] as &$value) {
//            if ($value['type'] == 0) {
//                $value['goods_list'] = $goods->alias('g')->join('store s','g.store_id = s.store_id and '.self::store_auth_sql('s'))
//                    ->where(self::goods_where([
//                                                  ['g.store_id', '=', $value['classify_str']],
//                                                  ['g.is_putaway', '=', 1]
//                                              ],'g'))
//                    ->field('g.file')
//                    ->order('g.create_time', 'desc')
//                    ->limit(3)
//                    ->select();
//            } else {
//                $value['goods_list'] = $goods
//                    ->where([
//                        ['goods_classify_id', 'in', implode(',', array_column(getParCate($value['classify_str'], $goodsClassify, 0), 'goods_classify_id'))],
//                        ['is_putaway', '=', 1]
//                    ])
//                    ->field('file')
//                    ->order('create_time', 'desc')
//                    ->limit(3)
//                    ->select();
//            }
//        }

        $result['goods_classify']  = $goodsClassify
            ->where(['parent_id' => 0, 'status' => 1])
            ->field('classify_adv_id,goods_classify_id,title,web_file')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
            ->select()->toArray();

        $array = array_column($result['goods_classify'],'goods_classify_id');

        $num = array_search($param['category'],$array);

        return $this->fetch('',['code' => 0, 'data' => $result,'header_title' =>'领券中心','num'=> $num,'array'=> json_encode($array)]);


    }

    /**
     * 领券中心 - 换券
     * @param CouponModel $coupon
     * @param Adv $adv
     * @param Goods $goods
     * @param GoodsClassify $goodsClassify
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exchange(CouponModel $coupon,Adv $adv,Goods $goods,GoodsClassify $goodsClassify,Member $member)
    {
        $member_id = Session::get('member_info')['member_id'] ?? '';
        $pay_points = $member->where([['member_id','=',$member_id]])->value('pay_points');

        $result['banner'] = $adv
            ->where([
                        ['adv_position_id', '=', config('pc_config.exchange_coupon_id')],
                        ['status', '=', 1],
                        ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                        ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ])
            ->field('adv_id,title,type,file,content')
            ->order('sort', 'desc')
            ->find();

        $result['data'] = $coupon
            ->where([
                ['status', '=', 1],
                ['modality', '=', 0],
                ['receive_end_time', '>=', date('Y-m-d')],
                ['receive_start_time', '<=', date('Y-m-d')],
                ['is_integral_exchage', '=', 1]
            ])
            ->field('coupon_id,file,type,title,actual_price,full_subtraction_price,exchange_num,integral,classify_str')
            ->paginate(21);

        foreach ($result['data'] as &$value) {
            // dump($value['title'].' - '.$value['classify']);
            if ($value['type'] == 0) {
                $value['goods_list'] = $goods
                    ->where([
                                ['store_id', '=', $value['classify_str']],
                                ['is_putaway', '=', 1]
                            ])
                    ->field('goods_id,file')
                    ->order('create_time', 'desc')
                    ->limit(3)
                    ->select();
            } else {
                $value['goods_list'] = $goods
                    ->where([
                                ['goods_classify_id', 'in', implode(',', array_column(getParCate($value['classify_str'], $goodsClassify, 0), 'goods_classify_id'))],
                                ['is_putaway', '=', 1]
                            ])
                    ->field('goods_id,file')
                    ->order('create_time', 'desc')
                    ->limit(3)
                    ->select();
            }
        }

//        halt($result);

        return $this->fetch('',['code' => 0, 'result' => $result,'pay_points' => $pay_points,'header_title' =>'换券中心']);
    }

    /************废弃***************/

//    /**
//     * 领券中心 - 换券 - 立即兑换详情
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param CouponModel $coupon
//     * @param Goods $goods
//     * @param GoodsClassify $goodsClassify
//     * @param Member $member
//     * @return mixed
//     */
//    public function exchange_view(Request $request, RSACrypt $crypt, CouponModel $coupon, Goods $goods, GoodsClassify $goodsClassify, Member $member)
//    {
//        if ($request::isPost()) {
//            try {
//
//                // 获取参数
//                $param = $crypt->request();
//                $param['member_id'] = request(0)->mid;
//                // 验证
//                $check = $coupon->valid($param, 'exchange_view');
//                if ($check['code']) return $crypt->response($check);
//
//                $result = $coupon
//                    ->where('coupon_id', $param['coupon_id'])
//                    ->field('coupon_id,type,title,actual_price,full_subtraction_price,exchange_num,integral,classify_str')
//                    ->find();
//
//                // 会员积分
//                $result['member_integral'] = $member->where('member_id', $param['member_id'])->value('pay_points');
//
//                // 适用商品
//                if ($result['type'] == 0) {
//                    $result['goods_list'] = $goods
//                        ->where([
//                            ['store_id', '=', $result['classify_str']],
//                            ['is_putaway', '=', 1]
//                        ])
//                        ->field('file')
//                        ->order('create_time', 'desc')
//                        ->limit(3)
//                        ->select();
//                } else {
//                    $result['goods_list'] = $goods
//                        ->where([
//                            ['goods_classify_id', 'in', implode(',', array_column(getParCate($result['classify_str'], $goodsClassify, 0), 'goods_classify_id'))],
//                            ['is_putaway', '=', 1]
//                        ])
//                        ->field('file')
//                        ->order('create_time', 'desc')
//                        ->limit(3)
//                        ->select();
//                }
//
//                return $crypt->response(['code' => 0, 'result' => $result]);
//
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//            }
//        }
//    }
//
//    /**
//     * 领券中心 - 换券 - 促销列表
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param CouponModel $coupon
//     * @param Goods $goods
//     * @param GoodsClassify $goodsClassify
//     * @return mixed
//     */
//    public function goods_list(Request $request, RSACrypt $crypt, CouponModel $coupon, Goods $goods, GoodsClassify $goodsClassify)
//    {
//        if ($request::isPost()) {
//            try {
//
//                // 获取参数
//                $param = $crypt->request();
//                $param['member_id'] = request(0)->mid;
//                // 验证
//                $check = $coupon->valid($param, 'goods_list');
//                if ($check['code']) return $crypt->response($check);
//
//                $result = $coupon
//                    ->where('coupon_id', $param['coupon_id'])
//                    ->field('coupon_id,type,actual_price,full_subtraction_price,end_time,classify_str')
//                    ->append(['distance_end_time'])
//                    ->find();
//
//                // 商品条件
//                $condition = [];
//
//                // 默认商品条件
//                $condition[] = ['is_putaway', '=', 1];
//                $condition[] = ['is_group', '=', 0];
//                $condition[] = ['is_bargain', '=', 0];
//                $condition[] = ['is_limit', '=', 0];
//
//                // 店铺 or 商品
//                if ($result['type'] == 0) {
//                    $condition[] = ['store_id', '=', $result['classify_str']];
//                } else {
//                    $condition[] = ['goods_classify_id', 'in', implode(',', array_column(getParCate($result['classify_str'], $goodsClassify, 0), 'goods_classify_id'))];
//                }
//
//                // 分类状态
//                if ($param['goods_classify_id']) $condition[] = ['goods_classify_id', 'in', implode(',', array_column(getParCate($param['goods_classify_id'], $goodsClassify, 0), 'goods_classify_id'))];
//                // 物流状态
//                if ($param['freight_status'] <> Null) $condition[] = ['freight_status', 'in', $param['freight_status']];
//                // 是否有货
//                if ($param['goods_number'] <> Null) $condition[] = ['goods_number', '>', $param['goods_number']];
//                // 个人、企业类型
//                if ($param['shop'] <> Null) $condition[] = ['shop', 'in', $param['shop']];
//                // 关键词查询
//                if ($param['keyword'] <> Null) $condition[] = ['goods_name|keyword', 'like', '%' . $param['keyword'] . '%'];
//                // 是否免运费
//                if ($param['is_freight']) $condition[] = ['freight_status', '=', $param['is_freight']];
//                // 最低价查询
//                if ($param['minimum_price']) $condition[] = ['shop_price', '>=', $param['minimum_price']];
//                // 最高价间查询
//                if ($param['top_price']) $condition[] = ['shop_price', '<=', $param['top_price']];
//                // 价格区间查询
//                if ($param['minimum_price'] && $param['top_price']) $condition[] = ['shop_price', 'between', [$param['minimum_price'], $param['top_price']]];
//
//                // 排序
//                $parameter = !empty($param['parameter']) ? 'goods.' . $param['parameter'] : 'goods.sort';
//                $rank = !empty($param['rank']) ? $param['rank'] : 'asc';
//
//                // 适用商品
//                $goods_list = $goods->alias('goods')
//                    ->join('store store', 'store.store_id = goods.store_id')
//                    ->where($condition)
//                    ->field('goods.goods_id,goods.store_id,goods_name,shop_price,goods.sales_volume,freight_status,shop,store_name,goods.file,is_vip')
//                    ->order($parameter, $rank)
//                    ->paginate(10, false, $param);
//
//                // 分类条件
//                $classCondition = [];
//
//                // 分类默认条件
//                $classCondition[] = ['parent_id', '=', 0];
//
//                // 如果有分类
//                if ($result['type'] == 1) $classCondition[] = ['goods_classify_id', 'in', $result['classify_str']];
//
//                // 适用分类
//                $class_list = $goodsClassify
//                    ->where($classCondition)
//                    ->field('goods_classify_id,title')
//                    ->select();
//
//                // 折扣
//                $discount = discount($param['member_id']);
//
//                return $crypt->response(['code' => 0, 'result' => $result, 'goods_list' => $goods_list, 'class_list' => $class_list, 'discount' => $discount]);
//
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//            }
//        }
//    }
}