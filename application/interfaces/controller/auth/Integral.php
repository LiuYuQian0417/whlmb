<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\Adv;
use app\common\model\Article;
use app\common\model\Consumption;
use app\common\model\Integral as IntegralModel;
use app\common\model\IntegralRecord;
use app\common\model\IntegralTask;
use app\common\model\SignTask;
use app\common\model\ShareTask;
use app\common\model\Member;
use app\common\model\MemberAddress;
use app\common\model\IntegralClassify;
use app\common\model\IntegralOrder;
use app\common\service\Lock;
use app\common\service\Inform;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\Db;

/**
 * 积分商城 - Joy
 * Class Register
 * @package app\interfaces\controller\auth
 */
class Integral extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    /**
     * 首页
     * @param RSACrypt $crypt
     * @param SignTask $signTask
     * @param Member $member
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          SignTask $signTask,
                          Member $member,
                          Adv $adv)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $member_info = $member
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->field('avatar,nickname,pay_points')
            ->find();
        if (!is_null($member_info)) {
            // 读取签到状态
            $sign = $signTask
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['create_time', '=', date('Y-m-d')],
                ])
                ->field('integral,continuous_days')
                ->find();
            // 是否签到状态
            $member_info['sign_state'] = is_null($sign) ? 0 : 1;
            // 连续签到天数
            $member_info['continuous_days'] = (int)$sign['continuous_days'];
            // 连续增加积分
            $member_info['integral'] = ($sign['continuous_days'] < 7) ?
                $sign['integral'] + Env::get('integral_sign') : 7 * Env::get('integral_sign');
        }
        // 积分首页顶部广告
        $adv_info = $adv
            ->where([
                ['adv_position_id', '=', 4],            // 积分商城
                ['status', '=', 1],
                ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
            ])
            ->field('adv_id,type,content,file')
            ->order(['sort' => 'desc'])
            ->find() ?: json([]);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'is_sign_in' => env('is_sign_in', 1),
            'member_info' => $member_info ?: json([]),
            'adv' => $adv_info,
        ], true);
    }
    
    /**
     * 分类列表
     * @param RSACrypt $crypt
     * @param IntegralClassify $integralClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function classify(RSACrypt $crypt,
                             IntegralClassify $integralClassify)
    {
        $result = $integralClassify
            ->where([
                ['parent_id', '=', 0],      // 一级分类
                ['status', '=', 1],
            ])
            ->field('integral_classify_id,title')
            ->order(['sort' => 'asc'])
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 积分商品列表
     * @param RSACrypt $crypt
     * @param IntegralModel $integral
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function goods(RSACrypt $crypt,
                          IntegralModel $integral)
    {
        $param = $crypt->request();
        $integral->valid($param, 'goods');
        $condition = [
            ['type', '=', $param['type']],  // 0 积分兑换 1 积分换购
            ['integral_number', '>', 0],
        ];
        if ($param['integral_classify_id']) {
            $condition[] = ['integral_classify_id', '=', $param['integral_classify_id']];
        }
        // 读取积分商品列表
        $result = $integral
            ->where($condition)
            ->field('integral_id,file,integral_name,integral,price')
            ->paginate(10, false);
        // 积分转换率
        $ratio = Env::get('integral_conversion', 0) / 100;
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'integral_ratio' => $ratio,
            'result' => $result,
        ], true);
    }
    
    /**
     * 详情
     * @param RSACrypt $crypt
     * @param IntegralModel $integral
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function view(RSACrypt $crypt,
                         IntegralModel $integral)
    {
        $param = $crypt->request();
        $pay_points = request(true)->pay_points ?? 0;
        $integral->valid($param, 'view');
        $result = $integral
            ->where([
                ['integral_id', '=', $param['integral_id']],
            ])
            ->field('video,multiple_file,integral_name,integral,price,
                add_number,web_content')
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -1,
                'message' => '积分商品不存在',
            ], true);
        }
        $result['pay_points'] = $pay_points;
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 兑换展示
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @param IntegralModel $integral
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function conversion(RSACrypt $crypt,
                               MemberAddress $memberAddress,
                               IntegralModel $integral)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $integral->valid($param, 'conversion');
        $address = $memberAddress
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->field('member_address_id,name,phone,province,city,area,
                street,address,lat,lng')
            ->order(['is_default' => 'desc'])
            ->find();
        $result = $integral
            ->where([
                ['integral_id', '=', $param['integral_id']],
            ])
            ->field('file,integral_name,integral,price')
            ->find();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'address' => $address,
            'result' => $result,
        ], true);
    }
    
    /**
     * 积分兑换商品
     * @param RSACrypt $crypt
     * @param Member $member
     * @param IntegralOrder $integralOrder
     * @param IntegralModel $integral
     * @param Lock $lock
     * @param IntegralRecord $integralRecord
     * @param Inform $inform
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function redemption(RSACrypt $crypt,
                               Member $member,
                               IntegralOrder $integralOrder,
                               IntegralModel $integral,
                               Lock $lock,
                               IntegralRecord $integralRecord,
                               Inform $inform)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $integralOrder->valid($param, 'redemption');
        $getLock = $lock->lock(['integral_' . $param['integral_id']], 10000);
        if ($getLock) {
            // 读取商品信息
            $integral_info = $integral
                ->where('integral_id', $param['integral_id'])
                ->field('integral_name,file,integral,price,integral_number')
                ->find();
            // 检查用户积分
            $info = $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->field('member_id,pay_points,web_open_id,subscribe_time,micro_open_id,phone')
                ->find();
            // 判断用户积分是否够用
            if ($info['pay_points'] < $integral_info['integral']) {
                $lock->unlock($getLock);
                return $crypt->response(['code' => -1, 'message' => config('message.')[-4][-1]], true);
            }
            // 判断库存成功执行
            if ($integral_info['integral_number'] > 0) {
                $param['integral_name'] = $integral_info['integral_name'];
                $param['file'] = $integral_info->getData('file');
                $param['integral'] = $integral_info['integral'];
                $param['price'] = $integral_info['price'];
                $param['order_number'] = get_order_sn();
                Db::startTrans();
                // 积分记录
                $integralRecord
                    ->allowField(true)
                    ->isUpdate(false)
                    ->save([
                        'member_id' => $param['member_id'],
                        'type' => 1,
                        'integral' => $integral_info['integral'],
                        'describe' => '兑换' . $integral_info['integral_name'] . '商品'
                    ]);
                // 会员减少积分
                $member
                    ->where([
                        ['member_id', '=', $param['member_id']],
                    ])
                    ->setDec('pay_points', $integral_info['integral']);
                // 减少库存 增加销量
                $integral
                    ->where([
                        ['integral_id', '=', $param['integral_id']],
                    ])
                    ->inc('add_number', $param['number'])
                    ->dec('integral_number', $param['number'])
                    ->update();
                // 订单生成
                $param['status'] = 0;
                $integralOrder->allowField(true)->save($param);
                // 积分推送
                if ($integral_info['integral'] > 0) {
                    $inform->integral_inform(1, '11', $integral_info['integral'], [
                        'member_id' => $info['member_id'],
                        'web_open_id' => $info['web_open_id'],
                        'subscribe_time' => $info['subscribe_time'],
                        'micro_open_id' => $info['micro_open_id'],
                        'phone' => $info['phone'],
                    ], 1, $integralOrder->integral_order_id);
                }
                Db::commit();
            }
            $lock->unlock($getLock);
            return $crypt->response([
                'code' => 0,
                'message' => '兑换成功',
            ], true);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '网络繁忙,请重试',
        ], true);
    }
    
    /**
     * 积分+余额兑换商品
     * @param RSACrypt $crypt
     * @param Member $member
     * @param IntegralOrder $integralOrder
     * @param IntegralModel $integral
     * @param Lock $lock
     * @param IntegralRecord $integralRecord
     * @param Inform $inform
     * @param Consumption $consumption
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function redemption_money(RSACrypt $crypt,
                                     Member $member,
                                     IntegralOrder $integralOrder,
                                     IntegralModel $integral,
                                     Lock $lock,
                                     IntegralRecord $integralRecord,
                                     Inform $inform,
                                     Consumption $consumption)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $integralOrder->valid($param, 'redemption_money');
        $param['pay_channel'] = 3;
        $ret = self::redemption_money_common($integral,
            $member, $integralRecord, $consumption,
            $integralOrder, $inform, $lock, $param);
        return $crypt->response($ret === true ?
            [
                'code' => 0,
                'message' => '兑换成功',
            ] :
            [
                'code' => -1,
                'message' => $ret,
            ], true);
    }
    
    /**
     * 积分预下单
     * @param RSACrypt $crypt
     * @param IntegralOrder $integralOrder
     * @param IntegralModel $integral
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function preOrder(RSACrypt $crypt,
                             IntegralOrder $integralOrder,
                             IntegralModel $integral)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        // 读取商品信息
        $integral_info = $integral
            ->where('integral_id', $args['integral_id'])
            ->field('integral_name,file,integral,price,integral_number')
            ->find();
        if (is_null($integral_info)) {
            return $crypt->response([
                'code' => -1,
                'message' => '积分商品不存在',
            ], true);
        }
        if ($integral_info['integral_number'] <= 0) {
            return $crypt->response([
                'code' => -1,
                'message' => '商品库存不足',
            ], true);
        }
        $args = array_merge($args, $integral_info->toArray());
        if ($integral_info['file']) {
            $args['file'] = $integral_info->getData('file');
        }
        // 订单生成
        $args['status'] = 3;    //未支付
        $integralOrder
            ->allowField(true)
            ->isUpdate(false)
            ->save($args);
        return $crypt->response([
            'code' => 0,
            'message' => '下单成功',
            'data' => [
                'order_number' => $integralOrder->order_number,
            ],
        ], true);
    }
    
    /**
     * 积分混合兑换公共逻辑区
     * @param IntegralModel $integral
     * @param Member $member
     * @param IntegralRecord $integralRecord
     * @param Consumption $consumption
     * @param IntegralOrder $integralOrder
     * @param Inform $inform
     * @param Lock $lock
     * @param $args
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function redemption_money_common(IntegralModel $integral,
                                            Member $member,
                                            IntegralRecord $integralRecord,
                                            Consumption $consumption,
                                            IntegralOrder $integralOrder,
                                            Inform $inform,
                                            Lock $lock,
                                            $args)
    {
        // 查询订单信息
        $order_info = $integralOrder
            ->alias('io')
            ->join('integral i', 'i.integral_id = io.integral_id')
            ->join('member m', 'm.member_id = io.member_id')
            ->where([
                ['io.order_number', '=', $args['order_number']],
                ['io.status', '=', 3],            //未支付
            ])
            ->field('io.integral_order_id,io.member_id,io.integral,io.price,io.number,io.status,
                io.integral_name,io.member_id,io.order_number,io.number,io.from,io.integral_id,
                i.integral_number,m.web_open_id,m.subscribe_time,m.micro_open_id,m.phone')
            ->find();
        if (is_null($order_info)) {
            return '积分商品或订单不存在';
        }
        // 检查用户积分
        $member_info = $member
            ->where('member_id', $args['member_id'])
            ->field('pay_points,usable_money,pay_password')
            ->find();
        // 支付方式为余额
        if ($args['pay_channel'] == 3) {
            // 设置支付密码
            if (!$member_info['pay_password']) {
                return '未设置支付密码';
            }
            // 匹配密码
            if (passEnc($args['pay_pass']) <> $member_info['pay_password']) {
                return '支付密码不正确';
            }
            if ($order_info['price'] && $member_info['usable_money'] < $order_info['price']) {
                return '余额不足';
            }
        }
        // 判断用户积分或余额是否够用
        if ($order_info['integral'] > 0 && $member_info['pay_points'] < ($order_info['integral'] * $order_info['number'])) {
            return '积分不足';
        }
        $getLock = $lock->lock(['integral_' . $order_info['integral_id']], 10000);
        if ($getLock === false) {
            return '网络繁忙,请重试';
        }
        // 会员减少积分
        $decMem = [
            'pay_points' => Db::raw('pay_points-' . $order_info['integral']),
        ];
        // 支付方式为余额
        if ($args['pay_channel'] == 3) {
            // 会员同时减去余额
            $decMem['usable_money'] = Db::raw('usable_money - ' . $order_info['price']);
        } else {
            if ($args['total_fee'] != $order_info['price']) {
                return '支付金额不符合';
            }
        }
        // 判断库存成功执行
        if ($order_info['integral_number'] > 0) {
            try {
                Db::startTrans();
                // 积分记录
                $integralRecord
                    ->allowField(true)
                    ->isUpdate(false)
                    ->save([
                        'member_id' => $args['member_id'],
                        'type' => 1,
                        'integral' => $order_info['integral'],
                        'describe' => '兑换' . $order_info['integral_name'] . '商品'
                    ]);
                // 消费记录
                $consumption
                    ->allowField(true)
                    ->isUpdate(false)
                    ->save([
                        'member_id' => $args['member_id'],
                        'type' => 2,
                        'order_number' => $order_info['order_number'],
                        'price' => $order_info['price'],
                        'way' => $order_info['from'],
                        'balance' => $member_info['usable_money'] - $order_info['price'],
                        'status' => 1,
                    ]);
                $member
                    ->where([
                        ['member_id', '=', $order_info['member_id']],
                    ])
                    ->update($decMem);
                // 减少库存 增加销量
                $integral
                    ->where([
                        ['integral_id', '=', $order_info['integral_id']],
                    ])
                    ->inc('add_number', $order_info['number'])
                    ->dec('integral_number', $order_info['number'])
                    ->update();
                // 更改订单状态
                $order_info->status = 0;        //已支付(待发货)
                if (array_key_exists('trade_no', $args) && $args['trade_no']) {
                    $order_info->trade_no = $args['trade_no'];
                }
                $order_info->pay_channel = $args['pay_channel'];
                $order_info->save();
                // 积分推送
                if ($order_info['integral'] > 0) {
                    $inform->integral_inform(1, '11', $order_info['integral'], [
                        'member_id' => $order_info['member_id'],
                        'web_open_id' => $order_info['web_open_id'],
                        'subscribe_time' => $order_info['subscribe_time'],
                        'micro_open_id' => $order_info['micro_open_id'],
                        'phone' => $order_info['phone'],
                    ], 1, $order_info['integral_order_id']);
                }
                Db::commit();
                // 兑换成功
                return true;
            } catch (\Exception $e) {
                Db::rollback();
                $lock->unlock($getLock);
                return $e->getMessage();
            }
        }
        // 库存不足
        return '商品库存不足';
    }
    
    /**
     * 兑换记录
     * @param RSACrypt $crypt
     * @param IntegralOrder $integralOrder
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function conversion_record(RSACrypt $crypt,
                                      IntegralOrder $integralOrder)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $result = $integralOrder
            ->where([
                ['member_id', '=', $param['member_id']],
                ['status', '<>', 3],                        //排除未支付
                ['is_delete', '=', 0],
            ])
            ->field('integral_order_id,integral_id,integral_name,file,integral,
                    price,express_name,express_value,express_number,status')
            ->order('create_time', 'desc')
            ->paginate(10, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 兑换记录删除
     * @param RSACrypt $crypt
     * @param IntegralOrder $integralOrder
     * @return mixed
     */
    public function conversion_record_delete(RSACrypt $crypt,
                                             IntegralOrder $integralOrder)
    {
        $param = $crypt->request();
        $integralOrder->valid($param, 'conversion_record_delete');
        // 全端进行标记删除
        $integralOrder
            ->allowField(true)
            ->isUpdate(true)
            ->save([
                'integral_order_id' => $param['integral_order_id'],
                'is_delete' => 1,
            ]);
        return $crypt->response([
            'code' => 0,
            'message' => '删除成功',
        ], true);
    }
    
    /**
     * 兑换记录详情
     * @param RSACrypt $crypt
     * @param IntegralOrder $integralOrder
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function conversion_view(RSACrypt $crypt,
                                    IntegralOrder $integralOrder)
    {
        $param = $crypt->request();
        $integralOrder->valid($param, 'conversion_view');
        $result = $integralOrder
            ->where('integral_order_id', $param['integral_order_id'])
            ->field('order_number,integral_name,file,integral,price,
                number,name,phone,province,city,area,street,address,express_name,
                express_value,express_number,status,create_time')
            ->find();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 确认收货
     * @param RSACrypt $crypt
     * @param IntegralOrder $integralOrder
     * @return mixed
     */
    public function confirm_receipt(RSACrypt $crypt,
                                    IntegralOrder $integralOrder)
    {
        $param = $crypt->request();
        $integralOrder->valid($param, 'confirm_receipt');
        $integralOrder
            ->allowField(true)
            ->isUpdate(true)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '收货成功',
        ], true);
    }
    
    /**
     * 签到
     * @param RSACrypt $crypt
     * @param SignTask $signTask
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function sign(RSACrypt $crypt,
                         SignTask $signTask,
                         Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 读取今日和上一天签到状态
        $signArr = $signTask
            ->where([
                ['member_id', '=', $param['member_id']],
                ['create_time', ['=', date('Y-m-d', strtotime("-1 day"))], ['=', date('Y-m-d')], 'or'],
            ])
            ->field('integral,continuous_days,date_format(create_time,\'%Y-%m-%d\') as time')
            ->order(['sign_task_id' => 'desc'])
            ->select();
        $sign = null;
        if (count($signArr) == 2 || (count($signArr) == 1 && $signArr[0]['time'] == date('Y-m-d'))) {
            return $crypt->response([
                'code' => -1,
                'message' => '今天签到已完成,请明天再来!',
            ], true);
        } else if (!$signArr->isEmpty()) {
            $sign = end($signArr)[0];
        }
        // 签到获得积分
        if (is_null($sign)) {
            $param['integral'] = Env::get('integral_sign', 0);
        } else {
            $param['integral'] = ($sign['continuous_days'] < 7) ?
                $sign['integral'] + Env::get('integral_sign', 0) :
                7 * Env::get('integral_sign', 0);
        }
        // 签到连续天数
        $param['continuous_days'] = is_null($sign) ? 1 : $sign['continuous_days'] + 1;
        Db::startTrans();
        // 签到记录写入
        $signTask->integral_record = [
            'member_id' => $param['member_id'],
            'type' => 0,
            'integral' => $param['integral'],
            'describe' => '签到'
        ];
        // 签到任务写入
        $signTask
            ->allowField(true)
            ->together('integral_record,member')
            ->save($param);
        Db::commit();
        $point = $member
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->value('pay_points') ?: 0;
        return $crypt->response([
            'code' => 0,
            'message' => '签到成功',
            'pay_points' => $point,
        ], true);
    }
    
    /**
     * 积分明细
     * @param RSACrypt $crypt
     * @param IntegralRecord $integralRecord
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function detail(RSACrypt $crypt,
                           IntegralRecord $integralRecord)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $condition[] = ['member_id', '=', $param['member_id']];
        if ($param['type'] <> Null) {
            $condition[] = ['type', '=', $param['type']];
        }
        $result = $integralRecord
            ->where($condition)
            ->field('integral_record_id,member_id,delete_time', true)
            ->order('create_time', 'desc')
            ->paginate(20, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 积分任务
     * @param RSACrypt $crypt
     * @param IntegralTask $integralTask
     * @param SignTask $signTask
     * @param ShareTask $shareTask
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function task(RSACrypt $crypt,
                         IntegralTask $integralTask,
                         SignTask $signTask,
                         ShareTask $shareTask,
                         Adv $adv)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 积分任务详情
        $task = $integralTask
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->field('member_id,delete_time', true)
            ->find();
        // 今日是否签到
        $todaySign = $signTask
            ->where([
                ['member_id', '=', $param['member_id']],
                ['create_time', '=', date('Y-m-d')],
            ])
            ->value('sign_task_id');
        // 今日是否分享
        $todayShare = $shareTask
            ->where([
                ['member_id', '=', $param['member_id']],
                ['create_time', '=', date('Y-m-d')],
            ])
            ->value('share_task_id');
        $result = [
            'info' => [
                'state' => $task['info_state'],     //完善账户状态 0 否 1 是
                'integral' => Env::get('integral_info', 0),      // 完善个人信息赠送积分数
            ],
            'phone' => [
                'state' => $task['phone_state'],    //绑定手机号状态 0 否 1 是
                'integral' => Env::get('integral_phone', 0),               //绑定手机号赠送积分数
            ],
            'third_party' => [
                'state' => $task['third_party_state'],  //绑定第三方账号状态 0 否 1 是
                'integral' => Env::get('integral_third_party', 0),           //绑定第三方赠送积分数
            ],
            'sign' => [
                'state' => is_null($todaySign) ? 0 : 1,
                'integral' => Env::get('integral_sign', 0),                   //每日赠送积分数
            ],
            'evaluate' => [
                'state' => $task['evaluate_state'],     //评价商品状态  0 否 1 是
                'integral' => Env::get('integral_evaluate', 0),              //评价商品赠送积分数
                'number' => Env::get('integral_evaluate_number', 0),         //评价商品每日限制次数
            ],
            'share' => [
                'state' => is_null($todayShare) ? 0 : 1,
                'integral' => Env::get('integral_share', 0),                 //分享商品或活动赠送
                'number' => Env::get('integral_share_number', 0),           //分享商品或活动每日限制次数
            ],
            'adv' => [
                'integral' => Env::get('integral_adv', 0),                  //浏览广告赠送积分数
            ],
        ];
        // 读取[积分任务页面头部banner]广告
        $adv_info = $adv
            ->where([
                ['adv_position_id', '=', 9],
                ['status', '=', 1],
                ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
            ])
            ->field('adv_id,type,content,file')
            ->find();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'adv_info' => !is_null($adv_info) ? $adv_info : json([]),
        ], true);
    }
    
    /**
     * 积分说明 - web页面
     * @param Article $article
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function help(Article $article)
    {
        $info = $article
            ->where('article_classify_id', 8)
            ->field('title,web_content')
            ->find();
        if (!$info) {
            return "<div style='text-align: center;padding: 30px 0;'>文章不存在</div>";
        }
        return web_page($info['title'], $info['web_content']);
    }
}