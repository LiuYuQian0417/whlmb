<?php
/**
 *
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/21 0021
 * Time: 14:39
 */

namespace app\master\controller;


use app\common\model\LotteryOrder;
use app\common\model\Coupon;
use app\common\model\GoodsClassify;
use app\common\model\LotteryPrize;
use app\common\model\Store;
use app\common\model\Express;
use app\common\model\Message;
use app\common\model\LotteryActivity;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Request;

class Lottery extends Controller
{
    /**
     * 抽奖活动列表
     * @param Request $request
     * @param LotteryActivity $activity
     * @param LotteryPrize $lotteryPrize
     * @return array|mixed
     */
    public function activity_list(Request $request, LotteryActivity $activity, LotteryPrize $lotteryPrize)
    {
        try {
            // 获取数据
            $param = $request::get();
            // 条件筛选
            $condition = !empty($param['keyword']) ? [['title', 'like', '%' . $param['keyword'] . '%']] : [];
            // 获取数据
            $_inventory_status_sql = $lotteryPrize->alias('b')->field('count(*)')->where('b.activity_id=a.activity_id and b.early_warning_number>=b.prize_number and b.is_open = 1')->buildSql();
            $field = [
                'a.activity_id',
                'a.title',
                'a.is_open',
                'a.create_time',
                'if(' . $_inventory_status_sql . '>0,"不足","充足")' => 'inventory_status',
            ];
            $data = $activity->where($condition)->alias('a')->field($field)->order('a.create_time', 'desc')->paginate(10, FALSE, ['query' => $param]);
        } catch (\Exception $e) {
            $this->error('网络繁忙');
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 创建抽奖活动
     * @param Request $request
     * @param LotteryActivity $activity
     * @param LotteryPrize $lotteryPrize
     * @return mixed
     */
    public function activity_create(Request $request, LotteryActivity $activity, LotteryPrize $lotteryPrize)
    {
        if ($request::isPost()) {
            try {
                Db::startTrans();
                // 获取参数
                $param = $request::post();
                // 验证
                $activity->valid($param, 'create');
                
                // 开启补偿积分时 补偿积分不可大于或等于消耗积分
                if ($param['is_compensation'] == 1 && $param['compensation_integral'] >= $param['integral']) {
                    throw new Exception('补偿积分必须小于消耗积分');
                }
                
                // 写入
                if (array_sum($param['probability']) > 100) exception('中奖率总和不能大于100');
                if (array_sum($param['is_open']) == 0) exception('活动商品最少有一个开启');
                $_saveData = $param;
                $_saveData['is_open'] = $_saveData['open'];
                
                // 如果 活动设置为开启
                if ($_saveData['is_open'] == 2) {
                    // 活动 都是开启
                    if ($activity->where([
                            ['is_open', '=', '2'],
                        ])->count() > 0) {
                        // 将所有活动设为关闭
                        $activity::update([
                            'is_open' => '1',
                        ], [
                            ['is_open', '=', '2'],
                        ]);
                    }
                }

                $status[] = $activity->allowField(TRUE)->save($_saveData);
                $lotteryPrizeData = [];
                $prize_info = ['', '', 'prize_info_integral', 'prize_info_coupon'];
                foreach ($param['goods_type'] as $k => $v) {
                    $v = empty($v) ? 0 : $v;
                    $lotteryPrizeData[] = [
                        'activity_id'          => $activity->activity_id,
                        'prize_title'          => $param['prize_title'][$k],
                        'prize_whole_title'    => $param['prize_whole_title'][$k],
                        'prize_number'         => $param['prize_number'][$k],
                        'early_warning_number' => $param['early_warning_number'][$k],
                        'prize_info'           => isset($param[$prize_info[$v]][$k]) ? $param[$prize_info[$v]][$k] : '',
                        'goods_type'           => $v,
                        'is_open'              => $param['is_open'][$k] == 0 ? 2 : $param['is_open'][$k],
                        'file'                 => $param['file'][$k],
                        'probability'          => $param['probability'][$k],
                    ];
                }
                $status[] = $lotteryPrize->saveAll($lotteryPrizeData);
                if (!array_diff($status, [TRUE])) {
                    exception('错误');
                }
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/lottery/activity_list'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('', [
            'start' => 2,
        ]);
    }

    /**
     * 编辑抽奖活动
     * @param Request $request
     * @param LotteryActivity $activity
     * @param LotteryPrize $lotteryPrize
     * @return mixed
     * @throws \think\Exception\DbException
     */
    public function activity_edit(Request $request, LotteryActivity $activity, LotteryPrize $lotteryPrize)
    {
        if ($request::isPost()) {
            try {
                Db::startTrans();
                // 获取参数
                $param = $request::post();
                // 验证
                $activity->valid($param, 'create');

                // 开启补偿积分时 补偿积分不可大于或等于消耗积分
                if($param['is_compensation'] == 1){
                    if (!isset($param['compensation_integral'])){
                        throw new Exception('请设置补偿积分');
                    }
                    if ($param['compensation_integral'] > $param['integral']){
                        throw new Exception('补偿积分必须小于消耗积分');
                    }
                }

                $param['copy_writer'] = str_replace('，', ',', $param['copy_writer']);
                if (array_sum($param['probability']) > 100) exception('中奖率总和不能大于100');
                if (array_sum($param['is_open']) == 16) exception('活动商品最少有一个开启');
                $_saveData = $param;
                $_saveData['is_open'] = $_saveData['open'];

                // 如果 活动设置为开启
                if ($_saveData['is_open'] == 2) {
                    // 活动 都是开启
                    if ($activity->where([
                            ['is_open', '=', '2'],
                        ])->count() > 0) {
                        // 将所有活动设为关闭
                       $activity::update([
                            'is_open' => '1',
                        ], [
                            ['is_open', '=', '2'],
                            ['activity_id','<>',$_saveData['activity_id']]
                        ]);
                    }
                }

                $status[] = $activity->allowField(TRUE)->save($_saveData, ['activity_id' => $param['activity_id']]);
                $prize_info = ['', '', 'prize_info_integral', 'prize_info_coupon'];
                foreach ($param['goods_type'] as $k => $v) {
                    $v = empty($v) ? 0 : $v;
                    $lotteryPrizeData = [
                        'prize_title'          => $param['prize_title'][$k],
                        'prize_whole_title'    => $param['prize_whole_title'][$k],
                        'prize_number'         => $param['prize_number'][$k],
                        'early_warning_number' => $param['early_warning_number'][$k],
                        'prize_info'           => isset($param[$prize_info[$v]][$k]) ? $param[$prize_info[$v]][$k] : '',
                        'goods_type'           => $v,
                        'is_open'              => $param['is_open'][$k] == 0 ? 2 : $param['is_open'][$k],
                        'file'                 => $param['file'][$k],
                        'probability'          => $param['probability'][$k],
                    ];
                    $status[] = $lotteryPrize->force()->save($lotteryPrizeData, ['prize_id' => $param['activity_prize_id'][$k]]);
                }
                if (array_diff($status, [TRUE])) {
                    exception('网络异常');
                }


                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/lottery/activity_list'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $activity_data = $lotteryPrize->where('activity_id', $request::get('id'))->select();
        $img_file = array_map(function ($val) {
            if ($val['is_open'] == 1) {
                return $val['file'];
            }
        }, $activity_data->toArray());
        return $this->fetch('activity_create', [
            'item'     => $activity::get($request::get('id')),
            'activity' => $activity_data,
            'img_file' => $img_file,
        ]);
    }

    /**
     * 删除抽奖活动
     * @param Request $request
     * @param LotteryActivity $activity
     * @param LotteryPrize $lotteryPrize
     * @return array
     */
    public function activity_destroy(Request $request, LotteryActivity $activity, LotteryPrize $lotteryPrize)
    {
        try {
            Db::startTrans();
            $activity_id = explode(',', $request::post('id', ''));
            $activity_id_data = $activity->lock(TRUE)->where([['activity_id', 'in', $activity_id], ['is_open', '=', 1]])->column('activity_id');
            if (array_diff($activity_id, $activity_id_data)) exception('进行中的活动不可删除');
            foreach ($activity_id_data as $v) {
                $activity->destroy(function ($query) use ($v) {
                    $query->where([['activity_id', '=', $v]]);
                });
                $lotteryPrize->where([['activity_id', '=', $v]])->update(['delete_time' => date('Y-m-d H:i:s')]);
//                $lotteryPrize->destroy(function ($query) use ($v) {
//                    $query->where([['activity_id', '=', $v]]);
//                });
            }
            Db::commit();
            return ['code' => 0, 'message' => '成功'];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 启用抽奖活动
     * @param Request $request
     * @param LotteryActivity $activity
     * @param LotteryPrize $lotteryPrize
     * @return array
     */
    public function activity_open(Request $request, LotteryActivity $activity, LotteryPrize $lotteryPrize)
    {
        try {
            $activity_id = $request::post('id');
            empty($activity_id) ? exception('活动不存在', 401) : '';
            $is_open = $activity->where('activity_id', $activity_id)->value('is_open');
            switch ($is_open) {
                case 1:
                    $activity->where([['is_open', '=', 2]])->count() > 0 ? exception('同时开启活动只能有一个', 401) : $is_open = 1;
                    empty($lotteryPrize->where([['activity_id', '=', $activity_id], ['prize_number', '=', 0], ['is_open', '=', '1']])->count()) ? $is_open = 2 : exception('活动中商品库存不足,无法开启', 401);
                    break;
                case 2:
                    $is_open = 1;
                    break;
            }
            $activity->save(['is_open' => $is_open], ['activity_id' => $activity_id]);
            $lotteryPrize->save(['is_activity' => $is_open == 1 ? 2 : 1], ['activity_id' => $activity_id]);
            return ['code' => 200, 'message' => '成功', 'is_open' => $is_open];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage(), 'is_open' => $is_open];
        }
    }
    
    /**
     * 抽奖活动订单列表
     * @param Request $request
     * @param LotteryOrder $lotteryOrder
     * @return array|mixed
     */
    public function order_list(Request $request, LotteryOrder $lotteryOrder)
    {
        try {
            // 获取数据
            $param = $request::post();
//            dump($param);
            // 条件筛选
            $param['start_date'] = !empty($param['start_date']) ? $param['start_date'] : '1968-01-01 00:00:00'; // 开始时间
            $param['end_date'] = !empty($param['end_date']) ? $param['end_date'] : date('Y-m-d H:i:s', time()); // 结束时间
            $condition = [['create_time', 'between time', [$param['start_date'], $param['end_date']]]];
            if (!empty($param['keywords'])) {
                $condition[] = ['prize_title|order_number', 'like', '%' . $param['keywords'] . '%'];
            }
            if (!empty($param['goods_type'])) $condition[] = ['goods_type', 'in', $param['goods_type']];
            if (!empty($param['status'])) $condition[] = ['status', 'in', $param['status']];
            // 获取数据
            $data = $lotteryOrder->with(['member' => function ($query) {
                $query->field('member_id,phone,nickname');
            }])->field('lottery_order_id,member_id,status,create_time,prize_title')->where($condition)->order('create_time', 'desc')->paginate(20, FALSE, ['query' => $param]);
        } catch (\Exception $e) {
            $this->error('网络繁忙');
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'data' => $data,
        ]);
    }
    
    
    /**
     * 订单详情
     * @param Request $request
     * @param Express $express
     * @param Message $message
     * @param LotteryOrder $lotteryOrder
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order_examine(Request $request, Express $express, Message $message, LotteryOrder $lotteryOrder)
    {
        if ($request::isPost()) {
            try {
                Db::startTrans();
                $param = $request::post();
                $check = $lotteryOrder->valid($param, 'examine');
                if ($check['code']) return $check;
                $lotteryOrderData = $lotteryOrder
                    ->alias('lo')
                    ->where([
                        ['lottery_order_id', '=', $param['id']],
                    ])
                    ->join('member m', 'm.member_id = lo.member_id')
                    ->field('lottery_order_id,m.member_id,lo.prize_title,lo.file,
                    m.web_open_id,m.subscribe_time,m.micro_open_id,m.phone,m.nickname,
                    lo.express_value,lo.express_number,lo.deliver_time,lo.status')
                    ->find();
                // 推送消息[抽奖订单已发货]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey' => 'order_state',
                    'openId' => $lotteryOrderData['web_open_id'],
                    'subscribe_time' => $lotteryOrderData['subscribe_time'],
                    'microId' => $lotteryOrderData['micro_open_id'],
                    'phone' => $lotteryOrderData['phone'],
                    'data' => [8, '平台', $lotteryOrderData['nickname'], $lotteryOrderData['prize_title']],
                    'inside_data' => [
                        'member_id' => $lotteryOrderData['member_id'],
                        'type' => 1,
                        'attach_id' => $param['id'],
                        'jump_state' => '10',
                        'file' => $lotteryOrderData->getData('file'),
                    ],
                ]);
                $lotteryOrderData->express_value = $param['time_type'];
                $lotteryOrderData->express_number = $param['express_number'];
                $lotteryOrderData->deliver_time = date('Y-m-d H:i:s');
                $lotteryOrderData->status = 2;
                $lotteryOrderData->save();
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/lottery/order_list'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $item = $lotteryOrder
            ->with(['member' => function ($query) {
                $query->field('member_id,phone,nickname,username');
            }])
            ->where('lottery_order_id', $request::get('id'))->find();
        // join express数据类型不一致
        $expressName = Db::name('express')->where(['code' => $item['express_value']])->value('name');
        // 快递100
        $data['customer'] = config('user.common.express.customer');
        $data['param'] = json_encode(['com' => $item['express_value'], 'num' => $item['express_number']]);
        $data["sign"] = strtoupper(md5($data['param'] . config('user.common.express.sign') . $data['customer']));
        $logistics = express_post($data, 'http://poll.kuaidi100.com/poll/query.do');
        $logistics['returnCode'] = isset($logistics['returnCode']) ? $logistics['returnCode'] : '0';
        if ($logistics['returnCode'] == 400 || $logistics['returnCode'] == 500 || $logistics['returnCode'] == 504) $logistics['data'] = [];
        if (empty($logistics['com'])) $logistics['com'] = '暂无记录';
        return $this->fetch('', [
            'item' => $item,
            'express' => $express::all(),
            'logistics' => $logistics,
            'express_name' => $expressName,
        ]);
    }
    
    /**
     * 抽奖活动商品优惠券列表
     * @param Request $request
     * @param Coupon $coupon
     * @return array|mixed
     */
    public function coupon_list(Request $request, Coupon $coupon)
    {
        try {
            // 获取参数
            $param = $request::get();
            // 条件定义
            $condition = [['coupon_id', 'neq', 0], ['modality', 'eq', 1]];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];
            if (isset($param['type']) && $param['type'] != -1) $condition[] = ['type', '=', $param['type']];
            $data = $coupon
                ->where($condition)
                ->field('update_time,delete_time,integral,is_integral_exchage,description', TRUE)
                ->order('create_time', 'desc');
            if ($request::isAjax()) {
                return ['data' => $data->select()];
            } else {
                $data = $data->paginate(15, FALSE, ['query' => $param]);
            }
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'data' => $data,
        ]);
    }
    
    /**
     * 新增优惠券
     * @param Request $request
     * @param Coupon $coupon
     * @param GoodsClassify $goodsClassify
     * @param Store $store
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function coupon_create(Request $request, Coupon $coupon, GoodsClassify $goodsClassify, Store $store)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();
                $param['receive_start_time'] = $param['start_time'];
                $param['receive_end_time'] = $param['end_time'];
                $param['limit_num'] = 1;
                $param['total_num'] = 0;
                $param['modality'] = 1;
                // 验证
                $param['classify_str'] = $param['type'] ? $param['goods_classify_id'] : $param['member_id'];
                $check = $coupon->valid($param, 'create');
                if ($check['code']) return $check;
                // 写入
                $coupon->allowField(TRUE)->save($param);
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/lottery/coupon_list'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('', [
            'categoryOne' => $goodsClassify->where([['parent_id', '=', 0], ['status', '=', 1]])->field('goods_classify_id,parent_id,title,count,title')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select(),
            'shops' => $store->where('shop', 0)->field('store_name,store_id')->select(),
        ]);
    }
    
    
    /**
     * 编辑优惠券
     * @param Request $request
     * @param Coupon $coupon
     * @param GoodsClassify $goodsClassify
     * @param Store $store
     * @param LotteryActivity $activity
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function coupon_edit(Request $request, Coupon $coupon, GoodsClassify $goodsClassify, Store $store, LotteryActivity $activity)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();
                //查询优惠券是否处于活动中
                $lottery_prize_count = $activity->field('activity_id')->withCount(['lotteryPrize' => function ($query) use ($param) {
                    $query->where([['prize_info', '=', $param['coupon_id']], ['goods_type', '=', '3']]);
                }])->where('is_open', 2)->select()->toArray();
                array_sum(array_column($lottery_prize_count, 'lottery_prize_count')) > 0 ? \exception('处于开启活动中的优惠券不可编辑') : '';
                $param['receive_start_time'] = $param['start_time'];
                $param['receive_end_time'] = $param['end_time'];
                $param['limit_num'] = 1;
                $param['total_num'] = 0;
                $param['modality'] = 1;
                $param['classify_str'] = $param['type'] ? $param['goods_classify_id'] : $param['member_id'];
                // 验证器
                $check = $coupon->valid($param, 'edit');
                if ($check['code']) return $check;
                //编辑
                $coupon->allowField(TRUE)->isUpdate(TRUE)->save($param);
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/lottery/coupon_list'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('coupon_create', [
            'categoryOne' => $goodsClassify->where([['parent_id', '=', 0], ['status', '=', 1]])->field('goods_classify_id,parent_id,title,count,title')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select(),
            'item' => $coupon->get($request::get('coupon_id')),
            'shops' => $store->where('shop', 0)->field('store_name,store_id')->select(),
        ]);
    }
    
    
}