<?php /** @noinspection ALL */
declare(strict_types = 1);
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/26 0026
 * Time: 16:56
 */

namespace app\interfaces\controller\lottery;

use app\common\service\Lock;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Request;
use app\common\model\{LotteryShare,
    LotteryPrize,
    LotteryActivity as LotteryActivityModel,
    LotteryRecord,
    Member,
    LotteryOrder,
    IntegralRecord,
    Coupon,
    MemberAddress,
    MemberCoupon};

/**
 * 抽奖活动
 * Class LotteryActivity
 * @package app\interfaces\controller\lottery
 */
class LotteryActivity extends BaseController
{
    
    /**
     * 抽奖活动商品列表
     * @param LotteryActivityModel $activity
     * @param RSACrypt $crypt
     * @param Member $member
     * @param LotteryRecord $lotteryRecord
     * @param LotteryShare $lotteryShare
     * @return mixed
     */
    public function activity_goods_list(LotteryActivityModel $activity,
                                        RSACrypt $crypt,
                                        Member $member,
                                        LotteryRecord $lotteryRecord,
                                        LotteryShare $lotteryShare,
                                        MemberAddress $address)
    {
        $param = Request::post();
        $param['member_id'] = request()->mid ?? '';
        //查询活动信息
        $activity_data_transfer = $activity
            ->with(['lotteryPrize' => function ($query) {
                $query->field('prize_id,activity_id,prize_title,file,IF(is_open=2||prize_number=0,2,1) is_open');
            }])
            ->field('activity_id,title,action_rule,integral,is_compensation,
            compensation_integral,copy_writer,is_open,update_time')
            ->field('create_time,update_time,delete_time', true);
        $activity_data = $activity_data_transfer
                ->where('is_open', 2)
                ->find() ?? $activity_data_transfer
                ->removeOption('where')
                ->order('update_time', 'desc')
                ->find();
        if (is_null($activity_data)) {
            $activity_data['lottery_prize'] = [];
        }
        //中奖纪录
        $lottery_record = $lotteryRecord
            ->with(['member' => function ($query) {
                $query->field('member_id,phone,nickname')->withAttr('phone', function ($value) {
                    return substr_replace($value, '****', 3, 4);
                });
            }])
            ->where([
                ['is_winning', '=', 1],
            ])
            ->field('member_id,prize_title,lottery_time')
            ->order('lottery_time', 'desc')
            ->limit(30)
            ->select();
        if (empty($param['member_id'])) {
            $activity_data['pay_points'] = 0;
            $lottery_record_type = 0;
            $address = [];
        } else {
            //会员积分
            $activity_data['pay_points'] = $member
                    ->get($param['member_id'])
                    ->pay_points ?? '';//exception('会员不存在');
            //下次抽奖类别
            $_lottery_record_type = $lotteryRecord
                ->field('type')
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['lottery_time', '>=', date('Y-m-d')],
                ])
                ->group('type')
                ->column('type');
            switch (count($_lottery_record_type)) {
                case 0:
                    $lottery_record_type = 0;
                    break;
                case 1:
                    //判断是否分享过
                    $lottery_record_type = $lotteryShare
                        ->where([
                            ['member_id', '=', $param['member_id']],
                            ['time', '=', date('Y-m-d')],
                        ])->count() > 0 ? 1 : 2;
                    break;
                case 2:
                    //如果是两个判断当前最后一次类别
                    switch ($_lottery_record_type[1]) {
                        //分享抽奖
                        case 2:
                            $lottery_record_type = 2;
                            break;
                        //积分抽奖，未分享
                        case 3:
                            $lottery_record_type = $lotteryShare
                                ->where([
                                    ['member_id', '=', $param['member_id']],
                                    ['time', '=', date('Y-m-d')],
                                ])->count() > 0 ? 1 : 2;
                            break;
                    }
                    break;
                default:
                    $lottery_record_type = 2;
                    break;
            }
            $address_transfer = $address
                ->field('location_address,is_default,lat,lng,
                create_time,update_time,delete_time', true);
            $address = $address_transfer
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['is_default', '=', 1],
                    ])
                    ->find() ?? $address_transfer
                    ->removeOption('where')
                    ->where([
                        ['member_id', '=', $param['member_id']],
                    ])
                    ->find();
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $activity_data,
            'lottery_record' => $lottery_record,
            'draw_type' => $lottery_record_type,
            'address' => $address ?: json([]),
        ], true);
    }
    
    /**
     * 抽奖
     * @param LotteryActivityModel $activity
     * @param RSACrypt $crypt
     * @param LotteryRecord $lotteryRecord
     * @param Member $member
     * @param LotteryOrder $lotteryOrder
     * @param IntegralRecord $integralRecord
     * @param Coupon $coupon
     * @param MemberCoupon $memberCoupon
     * @param Lock $lock
     * @param MemberAddress $address
     * @param LotteryPrize $lotteryPrize
     * @param LotteryShare $lotteryShare
     * @return mixed
     */
    public function draw(LotteryActivityModel $activity,
                         RSACrypt $crypt,
                         LotteryRecord $lotteryRecord,
                         Member $member,
                         LotteryOrder $lotteryOrder,
                         IntegralRecord $integralRecord,
                         Coupon $coupon,
                         MemberCoupon $memberCoupon,
                         Lock $lock,
                         MemberAddress $address,
                         LotteryPrize $lotteryPrize,
                         LotteryShare $lotteryShare)
    {
        try {
            $param = Request::post();
            $param['member_id'] = request(0)->mid;
            $rul = ['activity_id' => '活动id不能为空'];
            $verify = array_values(array_diff_key($rul, $param));
            if ($verify) {
                exception($verify[0]);
            }
            Db::startTrans();
            // redis锁设置
            $getLock = $lock->lock(['activity_' . $param['activity_id']], 5000);
            if (!$getLock) {
                exception('网络繁忙,请稍后再试', 100);
            }
            //获取当前活动信息
            $activity_data = $activity
                    ->with(['lotteryPrize' => function ($query) {
                        $query->field('prize_id,activity_id,prize_number,prize_whole_title,prize_info,probability,prize_title,file,is_open,if(is_open=2,4,goods_type) goods_type');
                    }])
                    ->field('activity_id,integral,is_compensation,compensation_integral,
                    copy_writer,update_time')
                    ->where([
                        ['is_open', '=', 2],
                        ['activity_id', '=', $param['activity_id']],
                    ])->find() ?? exception('活动已经结束');
            //判断当前抽奖类型   每日首次   分享  消耗积分
            $_lottery_record_type = $lotteryRecord
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['lottery_time', '>=', date('Y-m-d')],
                ])
                ->group('type')
                ->column('type');
            $lottery_share_count = $lotteryShare
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['time', '=', date('Y-m-d')],
                ])->count();
            //下次抽奖类别  1 分享抽奖  2 积分抽奖
            $next_draw_type = 2;
            switch (count($_lottery_record_type)) {
                case 0:
                    $lottery_record_type = 0;
                    //下次抽奖类别
                    $next_draw_type = $lottery_share_count > 0 ? 1 : 2;
                    break;
                case 1:
                    //判断是否分享过
                    $lottery_record_type = $lottery_share_count > 0 ? 1 : 2;
                    $next_draw_type = 2;
                    break;
                case 2:
                    //如果是两个判断当前最后一次类别
                    switch ($_lottery_record_type[1]) {
                        //分享抽奖
                        case 2:
                            $lottery_record_type = 2;
                            break;
                        //积分抽奖，未分享
                        case 3:
                            $lottery_record_type = $lottery_share_count > 0 ? 1 : 2;
                            break;
                    }
                    break;
                default:
                    $lottery_record_type = 2;
                    break;
            }
            //积分记录
            $integral_record_log = [];
            //判断积分抽奖类别
            switch ($lottery_record_type) {
                case 0:
                case 1:
                    $_integral = 0;
                    $member_integral = $member
                            ->get($param['member_id'])
                            ->pay_points ?? exception('会员不存在');
                    $_integral > $member_integral ? exception('积分不足') : '';
                    break;
                default:
                    $_integral = $activity_data['integral'];
                    $integral_record_log[] = [
                        'member_id' => $param['member_id'],
                        'type' => 1,
                        'integral' => $activity_data['integral'],
                        'describe' => '抽奖消耗积分',
                    ];
                    $member_integral = $member
                            ->get($param['member_id'])
                            ->pay_points ?? exception('会员不存在');
                    $_integral > $member_integral ? exception('积分不足') : '';
                    //会员减积分
                    $member
                        ->where([
                            ['member_id', '=', $param['member_id']],
                        ])
                        ->setDec('pay_points', $activity_data['integral']);
            }
            //将抽奖概率扩大100倍
            foreach ($activity_data['lottery_prize'] as $v) {
                $v['probability'] *= 100;
            }
            $lottery_prize = $activity_data['lottery_prize']->toarray();
            $prize_id = self::getRand($lottery_prize, $param['activity_id']);
            $prize_info = $activity_data['lottery_prize'][$prize_id];
            if ((int)$prize_info['goods_type'] != 4 && $activity_data['update_time'] != $param['update_time']) {
                // 中奖
                Db::rollback();
                return $crypt->response([
                    'code' => -1,
                    'message' => '活动信息修改,请重新抽奖',
                    'data' => [
                        'prize_type' => 0,
                    ]
                ], true);
            }
            //抽奖记录
            $lottery_record = [
                'member_id' => $param['member_id'],
                'activity_id' => $prize_info['activity_id'],
                'lottery_time' => date('Y-m-d H:i:s'),
                'is_winning' => 1,
                'type' => $lottery_record_type + 1,
                'prize_id' => $prize_info['prize_id'],
                'prize_title' => $prize_info['prize_title'],
            ];
            //抽奖订单
            $lottery_order = [
                'order_number' => get_order_sn(),
                'member_id' => $param['member_id'],
                'prize_id' => $prize_info['prize_id'],
                'prize_info' => $prize_info['prize_info'],
                'prize_title' => $prize_info['prize_whole_title'],
                'goods_type' => $prize_info['goods_type'],
                'file' => $prize_info['File_extra'],
            ];
            $data = [
                'lottery_record' => $member_integral,
                'draw_type' => $next_draw_type,
                'prize_type' => $prize_info['goods_type'],
                'prize_id' => $prize_info['prize_id'],
                'prize_index' => $prize_id,
            ];
            $address_transfer = $address
                ->field('location_address,is_default,lat,lng,create_time,
                update_time,delete_time', true);
            $data['address'] = $address_transfer
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['is_default', '=', 1],
                    ])->find() ?? $address_transfer
                    ->removeOption('where')
                    ->where([
                        ['member_id', '=', $param['member_id']],
                    ])->find() ?? [];
            $message = '恭喜你获得' . $prize_info['prize_title'];
            switch ((int)$prize_info['goods_type']) {
                case 1:
                    break;
                case 2:
                    //改变订单状态为已完成
                    $lottery_order['status'] = 3;
                    //增加积分
                    $member
                        ->where([
                            ['member_id', '=', $param['member_id']],
                        ])->setInc('pay_points', $prize_info['prize_info']);
                    //添加积分记录
                    $integral_record_log[] = [
                        'member_id' => $param['member_id'],
                        'type' => 0,
                        'origin_point' => 9,
                        'integral' => $prize_info['prize_info'],
                        'describe' => '抽奖获得',
                    ];
                    break;
                case 3:
                    //改变订单状态为已完成
                    $lottery_order['status'] = 3;
                    //优惠券奖品
                    $getInfo = $coupon
                        ->where([
                            ['coupon_id', '=', $prize_info['prize_info']],
                        ])
                        ->field('coupon_id,title,type,actual_price,full_subtraction_price,classify_str,start_time,end_time')
                        ->find();
                    $result = [];
                    if (!is_null($getInfo)) {
                        $result = array_merge($getInfo->toArray(), ['member_id' => $param['member_id']]);
                    }
                    $result['type'] == 1 ? $result['goods_classify_id'] = $result['classify_str'] : $result['store_id'] = $result['classify_str'];
                    unset($result['classify_str']);
                    $coupon
                        ->where([
                            ['coupon_id', '=', $prize_info['prize_info']],
                        ])->setInc('exchange_num');
                    $memberCoupon
                        ->allowField(true)
                        ->save($result);
                    break;
                case 4:
                    $message = $activity_data['copy_writer'];
                    //如果返积分
                    if ($activity_data['is_compensation'] == 1) {
                        //增加积分
                        $member
                            ->where([
                                ['member_id', '=', $param['member_id']],
                            ])->setInc('pay_points', $activity_data['compensation_integral']);
                        //积分记录
                        $integral_record_log[] = [
                            'member_id' => $param['member_id'],
                            'type' => 0,
                            'origin_point' => 9,
                            'integral' => $activity_data['compensation_integral'],
                            'describe' => '未中奖补偿积分',
                        ];
                        $data['lottery_record'] += $activity_data['compensation_integral'];
                    }
                    $lottery_record['is_winning'] = 2;
                    unset($lottery_order);
                    break;
            }
            //减奖品库存
            if ($prize_info['goods_type'] != 4) {
                $lotteryPrize
                    ->where([
                        ['prize_id', '=', $prize_info['prize_id']],
                    ])
                    ->setDec('prize_number');
            }
            //增加积分记录
            $integralRecord->saveAll($integral_record_log);
            //如果中奖保存中奖订单
            if (isset($lottery_order)) {
                $lotteryOrder->save($lottery_order);
            }
            //抽奖记录
            $lotteryRecord->save($lottery_record);
            // 解锁
            $lock->unlock($getLock);
            Db::commit();
            return $crypt->response([
                'code' => 0,
                'data' => $data,
                'message' => $message,
                'order_id' => $lotteryOrder->lottery_order_id ?? '',
            ], true);
        } catch (\Exception $e) {
            Db::rollback();
            // 如果不是锁失败抛异常就解锁
            if ($e->getCode() != 100 && isset($getLock)) {
                $lock->unlock($getLock);
            }
            //如果是全部商品未中奖则改变活动状态
            if ($e->getCode() == 404) {
                (new LotteryActivityModel)->save(['is_open' => 1], ['activity_id' => $param['activity_id']]);
                $lotteryPrize->where('activity_id', $param['activity_id'])->setField('is_activity', 2);
            }
            return $crypt->response([
                'code' => -100,
                'message' => $e->getMessage(),
            ], true);
        }
    }
    
    
    /**
     * 订单列表
     * @param LotteryOrder $lotteryOrder
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function order_list(LotteryOrder $lotteryOrder,
                               RSACrypt $crypt)
    {
        $member_id = request(0)->mid ?? exception('会员id不能为空');
        $where = [['member_id', '=', $member_id]];
        $status = Request::post('status', 'all');
        if ($status != 'all') {
            $where[] = ['status', '=', $status];
        }
        $data = $lotteryOrder
            ->field('lottery_order_id order_id,prize_title,file,express_number,
            express_value,status,create_time,goods_type')
            ->order('create_time', 'desc')
            ->where($where)
            ->paginate(10, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
        
    }
    
    /**
     * 订单详情
     * @param LotteryOrder $lotteryOrder
     */
    public function order_info(LotteryOrder $lotteryOrder,
                               RSACrypt $crypt)
    {
        $order_id = Request::post('order_id', '');
        $order_data = $lotteryOrder
                ->with(['member' => function ($query) {
                    $query->field('member_id,nickname');
                }])
                ->where([
                    ['lottery_order_id', '=', $order_id],
                    ['goods_type', '=', 1],     // 实物
                ])
                ->field('prize_title,phone,order_number,member_id,file,goods_type,
                province,city,area,street,address,status,create_time,name,express_number,express_value')
                ->withAttr('phone', function ($value) {
                    return substr_replace($value, '****', 3, 4);
                })
                ->find() ?? [];
        if ($order_data) {
            $order_data['quantity'] = 1;
            $order_data['attr'] = '';       // 商品规格
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $order_data,
        ], true);
    }
    
    /**
     * 设置抽奖地址
     * @param RSACrypt $crypt
     * @param MemberAddress $address
     * @param LotteryOrder $lotteryOrder
     * @return mixed
     */
    public function set_addres(RSACrypt $crypt,
                               MemberAddress $address,
                               LotteryOrder $lotteryOrder)
    {
        $param = Request::post();
        $param['member_id'] = request(0)->mid;
        $_rul = ['activity_order_id' => '活动订单id不能为空', 'member_address_id' => '收货地址不能为空'];
        try {
            $verify = array_values(array_diff_key($_rul, $param));
            if ($verify) {
                exception($verify[0]);
            }
            $address = $address
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['member_address_id', '=', $param['member_address_id']],
                    ])
                    ->field('location_address,is_default,lat,lng,create_time,
                    update_time,delete_time', true)
                    ->find() ?? exception('收货地址不存在');
            $lotteryOrder
                ->allowField(true)
                ->save(array_merge($address->toArray(), ['status' => 1]),
                    ['lottery_order_id' => $param['activity_order_id']]);
            return $crypt->response([
                'code' => 0,
                'message' => '领取成功',
            ], true);
        } catch (\Exception $e) {
            return $crypt->response([
                'code' => -100,
                'message' => $e->getMessage(),
            ], true);
        }
    }
    
    
    /**
     * 确认收货
     * @param RSACrypt $crypt
     * @param LotteryOrder $lotteryOrder
     * @return mixed
     */
    public function confirm_take(RSACrypt $crypt,
                                 LotteryOrder $lotteryOrder)
    {
        try {
            $order_id = Request::post('order_id') ?? exception('订单为空');
            $lotteryOrder
                ->where([
                    ['lottery_order_id', '=', $order_id],
                ])
                ->update([
                    'status' => '3',
                    'deal_time' => date('Y-m-d H:i:s'),
                ]);
            return $crypt->response([
                'code' => 0,
                'message' => '收货成功',
            ]);
        } catch (\Exception $e) {
            return $crypt->response([
                'code' => -100,
                'message' => $e->getMessage(),
            ], true);
        }
    }
    
    /**
     * 获取中奖概率
     * @param $activity_prize_data
     * @param $activity_id
     * @param int $i
     * @return int|string
     * @throws \Exception
     */
    private static function getRand($activity_prize_data, $activity_id, $i = 0)
    {
        static $open = [];   //记录开启的活动信息
        static $close = [];  //记录未开启的活动信息
        static $index = 0;  //记录当前中奖奖品在将品中属于第几个
        static $proSum = 0;
        //如果是第一次获取计算抽奖信息
        //概率数组的总概率精度
        $proSum = array_sum(array_column($activity_prize_data, 'probability')); //计算数组中元素的和
        if ($i === 0) {
            foreach ($activity_prize_data as $index => &$activity_prize_datum) {
                if ($activity_prize_datum['is_open'] == 1) {
                    $open[] = $activity_prize_datum;
                } else {
                    $close[] = $activity_prize_datum;
                }
            }
            //如果开启商品不足8个并且中奖率不足100，随机未开启商品位置补全概率
            if (count($open) != 8 && $proSum != 100 * 100) {
                shuffle($close);
                $_rand_losing_lottery = $close[0];
                foreach ($activity_prize_data as &$activity_prize_v) {
                    if ($activity_prize_v['prize_id'] == $_rand_losing_lottery['prize_id']) {
                        $activity_prize_v['probability'] = 100 * 100 - $proSum;
                    }
                }
                $proSum = 100 * 100;
            }
        }
        //将所有组合组合进入数组打乱后随即取出
        $indexArr = array();
        foreach ($activity_prize_data as $i => $v) {
            for ($j = 0; $j < $v['probability']; $j++) {
                //index 追加到数组
                array_push($indexArr, $i);
            }
        }
        //如果所有商品轮训后还未中奖，活动结束
        if (empty($indexArr)) {
            exception('活动已经结束', 404);
        };
        $result = $indexArr[mt_rand(0, count($indexArr) - 1)];
        //概率数组循环
//        foreach ($activity_prize_data as $key => $proCur) {
//            $randNum = mt_rand(1, (int)$proSum);
//            if ($randNum <= $proCur['probability']) { //如果这个随机数小于等于数组中的一个元素，则返回数组的下标
//                $result = $key;
//                break;
//            } else {
//                $proSum -= $proCur['probability'];
//            }
//        }
        if (isset($activity_prize_data[$result])) {
            //如果商品库存不足就从新抽奖;
            if (($activity_prize_data[$result]['goods_type'] != 4 && (new LotteryPrize())->where('prize_id', $activity_prize_data[$result]['prize_id'])->value('prize_number') <= 0)
                || ($activity_prize_data[$result]['goods_type'] == 3 && (new Coupon())->where([['coupon_id', '=', $activity_prize_data[$result]['prize_info']]])->count() == 0)) {
                unset($activity_prize_data[$result]);
                return self::getRand($activity_prize_data, $activity_id, $i = 1);
            }
            unset($proSum);
            unset($open);
            unset($close);
            unset ($activity_prize_data);
            return $result;
        } else {
            return self::getRand($activity_prize_data, $activity_id, $i = 1);
        }
    }
    
    /**
     * 分享获取抽奖机会
     * @param LotteryShare $lotteryShare
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function share_activity(LotteryShare $lotteryShare,
                                   RSACrypt $crypt)
    {
        $self_time = date('Y-m-d');
        $member_id = request(0)->mid ?? exception('用户id不能为空');
        $lotteryShare
            ->where([
                ['member_id', '=', $member_id],
                ['time', '=', $self_time],
            ])
            ->count() > 0 ? exception('今天已经分享过了') : '';
        $lotteryShare
            ->save(['member_id' => $member_id, 'time' => $self_time]);
        return $crypt
            ->response(['code' => 0, 'message' => '分享成功']);
    }
}