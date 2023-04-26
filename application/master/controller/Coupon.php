<?php
// 优惠券管理
declare(strict_types = 1);

namespace app\master\controller;

use app\common\model\LotteryPrize;
use app\common\service\Beanstalk;
use think\Controller;
use think\facade\Request;
use app\common\model\Coupon as CouponModel;
use app\common\model\Store as StoreModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;

class Coupon extends Controller
{
    /**
     * 优惠券列表
     * @param Request $request
     * @param CouponModel $coupon
     * @return array|mixed
     */
    public function index(Request $request, CouponModel $coupon)
    {
        try {
            // 获取参数
            $param = $request::get();
            
            $_singleStore = config('user.one_more');
            
            
            // 条件定义
            $condition = [['coupon_id', 'neq', 0], ['modality', 'eq', 0]];
            
            if ($_singleStore != 1) {
                $condition[] = ['classify_str', '=', config('user.one_store_id')];
            }
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];
            if (isset($param['type']) && $param['type'] != -1) $condition[] = ['type', '=', $param['type']];
            if (isset($param['is_gift']) && $param['is_gift'] != -1) $condition[] = ['is_gift', '=', $param['is_gift']];
            if (isset($param['is_recommend']) && $param['is_recommend'] != -1) $condition[] = ['is_recommend', '=', $param['is_recommend']];
            if (isset($param['is_integral_exchage']) && $param['is_integral_exchage'] != -1) $condition[] = ['is_integral_exchage', '=', $param['is_integral_exchage']];
            
            $data = $coupon
                ->where($condition)
                ->field('update_time,delete_time,integral,is_integral_exchage,description', true)
                ->order('create_time', 'desc')
                ->paginate(15, false, ['query' => $param]);
            
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'data' => $data,
            'single_store' => $_singleStore,
        ]);
    }
    
    /**
     * 新增优惠券
     * @param Request $request
     * @param CouponModel $coupon
     * @param GoodsClassifyModel $goodsClassify
     * @param StoreModel $store
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, CouponModel $coupon, GoodsClassifyModel $goodsClassify, StoreModel $store)
    {
        $_singleStore = config('user.one_more');
        $_oneStoreId = config('user.one_store_id');
        
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();
                // 验证
                
                if ($_singleStore != 1) {
                    $param['classify_str'] = $_oneStoreId;
                } else {
                    $param['classify_str'] = $param['type'] ? $param['goods_classify_id'] : $param['member_id'];
                }
                
                $check = $coupon->valid($param, 'create');
                
                if ($check['code']) return $check;
                $param['exchange_num'] = intval($param['exchange_num']);
                if (!$param['exchange_num'] || $param['exchange_num'] < 0 || $param['exchange_num'] > $param['total_num']) {
                    $param['exchange_num'] = $param['total_num'];
                }
                // 写入
                $operation = $coupon->allowField(true)->save($param);
                if ($operation) {
                    // 生成消息到优惠券使用到期下架队列
                    (new Beanstalk())->put(json_encode(['queue' => 'couponGetExpireChangeStatus',
                        'id' => $coupon->coupon_id, 'time' => date('Y-m-d H:i:s')]), (strtotime($param['receive_end_time']) - time()));
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/coupon/index'];
                }
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        return $this->fetch('', [
            'categoryOne' => $goodsClassify->where([['parent_id', '=', 0], ['status', '=', 1]])->field('goods_classify_id,parent_id,title,count,title')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select(),
            'shops' => $store->where('shop', 0)->field('store_name,store_id')->select(),
            'single_store' => $_singleStore,
        ]);
    }
    
    /**
     * 编辑优惠券
     * @param Request $request
     * @param CouponModel $coupon
     * @param GoodsClassifyModel $goodsClassify
     * @param StoreModel $store
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, CouponModel $coupon, GoodsClassifyModel $goodsClassify, StoreModel $store)
    {
        $_singleStore = config('user.one_more');
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();
                $exchange_num = $coupon->where(['coupon_id' => $param['coupon_id']])->value('exchange_num');
                
                if ($_singleStore != 1) {
                    $param['classify_str'] = config('user.one_store_id');
                } else {
                    $param['classify_str'] = $param['type'] ? $param['goods_classify_id'] : $param['member_id'];
                }
                
                // 验证器
                $check = $coupon->valid($param, 'edit');
                if ($check['code']) return $check;
                
                $param['exchange_num'] = intval($param['exchange_num']);
                if ($exchange_num > $param['total_num']) {
                    $exchange_num = $param['total_num'];
                }
                if (!$param['exchange_num'] || $param['exchange_num'] < 0 || $param['exchange_num'] > $param['total_num']) {
                    $param['exchange_num'] = $exchange_num;
                }
                //编辑
                $operation = $coupon->allowField(true)->isUpdate(true)->save($param);
                if ($operation) {
                    // 生成消息到优惠券使用到期下架队列
                    (new Beanstalk())->put(json_encode(['queue' => 'couponGetExpireChangeStatus',
                        'id' => $param['coupon_id'], 'time' => date('Y-m-d H:i:s')]), (strtotime($param['receive_end_time']) - time()));
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/coupon/index'];
                }
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        $data = $coupon->where('coupon_id', $request::get('coupon_id'))->find();
        $data['file_data'] = $data->getData('file');
        
        return $this->fetch('create', [
            'categoryOne' => $goodsClassify->where([['parent_id', '=', 0], ['status', '=', 1]])->field('goods_classify_id,parent_id,title,count,title')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select(),
            'item' => $data,
            'shops' => $store->where('shop', 0)->field('store_name,store_id')->select(),
            'single_store' => $_singleStore,
        ]);
    }
    
    /**
     * 删除优惠券
     * @param Request $request
     * @param CouponModel $coupon
     * @param LotteryPrize $lotteryPrize
     * @return array
     */
    public function destroy(Request $request, CouponModel $coupon, LotteryPrize $lotteryPrize)
    {
        if ($request::isPost()) {
            try {
                
                //查看当前优惠券是否是积分抽奖优惠券
                $Coupon_data = $coupon->field('coupon_id')->get(['coupon_id' => $request::post('id'), 'modality' => 1], ['CouponActivityPrize' => function ($query) {
                    $query->field('prize_id,is_activity,prize_info');
                }]);
                
                //判断优惠券是否处于抽奖活动中
                if (!empty($Coupon_data['coupon_activity_prize'])) {
                    $activity_prize_id = [];
                    foreach ($Coupon_data['coupon_activity_prize'] as $activity_prize_v) {
                        if ($activity_prize_v['is_activity'] == 1) {
                            exception('积分优惠券正处于活动中，不可删除');
                        }
                        $activity_prize_id[] = $activity_prize_v['prize_id'];
                    }
                    //更新对应活动商品信息
                    $lotteryPrize->save([
                        'prize_title' => '',
                        'prize_whole_title' => '',
                        'prize_number' => 0,
                        'early_warning_number' => 0,
                        'prize_info' => 0,
                        'probability' => 0,
                        'file' => 0,
                        'goods_type' => 0,
                        'is_open' => 2,
                        'is_activity' => 2,
                    ], [['prize_id', 'in', $activity_prize_id]]);
                }
                // 删除
                $coupon::destroy($request::post('id'));
                
                return ['code' => 0, 'message' => config('message.')[0]];
                
            } catch (\Exception $e) {
                
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 新人礼包推荐状态改变
     * @param Request $request
     * @param CouponModel $coupon
     * @return array
     */
    public function auditing(Request $request, CouponModel $coupon)
    {
        if ($request::isPost()) {
            try {
                $coupon->changeIsGit($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
}