<?php
declare(strict_types=1);

namespace app\computer\controller\auth;

use app\computer\model\Adv;
use app\computer\model\Article;
use app\computer\model\Consumption;
use app\computer\model\Integral as IntegralModel;
use app\computer\model\IntegralRecord;
use app\computer\model\IntegralTask;
use app\computer\model\SignTask;
use app\computer\model\ShareTask;
use app\computer\model\Member;
use app\computer\model\MemberAddress;
use app\computer\model\IntegralClassify;
use app\computer\model\IntegralOrder;
use app\common\service\Lock;
use app\common\service\Inform;
use app\computer\controller\BaseController;
use EasyWeChat\Factory;
use mrmiao\encryption\RSACrypt;
use think\Config;
use think\Exception;
use think\facade\Request;
use think\facade\Env;
use think\Db;
use think\facade\Session;
use app\computer\model\Area;

/**
 * 积分商城
 * Class Register
 * @package app\computer\controller\auth
 */
class Integral extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['only' => 'index,my,sign,detail,conversion,redemption,redemption_money'],
    ];

    /**
     * 首页
     * @param Request $request
     * @param IntegralModel $integral
     * @param IntegralClassify $integralClassify
     * @param SignTask $signTask
     * @param Member $member
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, IntegralModel $integral, IntegralClassify $integralClassify, SignTask $signTask, Member $member, Adv $adv)
    {
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? 0;

        $param['type'] = empty($param['type']) ? 0 : $param['type'];

        // 读取签到状态
        $sign = $signTask
            ->where(['member_id' => $param['member_id'], 'create_time' => date('Y-m-d')])
            ->field('integral,continuous_days')
            ->find();

        // 我的
        $member_info = $member
            ->where('member_id', $param['member_id'])
            ->field('avatar,nickname,pay_points')
            ->find();

        // 是否签到状态
        $member_info['sign_state'] = empty($sign) ? 0 : 1;
        // 连续签到天数
        $member_info['continuous_days'] = (int)$sign['continuous_days'];
        // 连续增加积分
        $member_info['integral'] = ($sign['continuous_days'] < 7) ? $sign['integral'] + Env::get(
                'integral_sign'
            ) : 7 * Env::get('integral_sign');

        // 读取广告
        $adv_info = $adv->where(
            [
                ['adv_position_id', '=', config('pc_config.integral_index_id')],
                ['status', '=', 1],
                ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
            ]
        )->field('type,content,file')
            ->find();


        $classify = $integralClassify
            ->where(
                [
                    ['parent_id', '=', 0],
                    ['status', '=', 1],
                ]
            )
            ->field('integral_classify_id,title')
            ->order('sort', 'asc')
            ->select();


        $condition[] = ['type', '=', $param['type']];
        $condition[] = ['integral_number', '>', 0];

        if (!empty($param['integral_classify_id']))
        {
            $condition[] = ['integral_classify_id', '=', $param['integral_classify_id']];
        }


        // 读取积分列表
        $data = $integral->where($condition)
            ->field('integral_id,file,integral_name,integral,price')
            ->paginate(20);

        return $this->fetch(
            '',
            [
                'code'        => 0,
                'member_info' => $member_info,
                'adv'         => $adv_info,
                'data'        => $data,
                'classify'    => $classify,
                'integral_ratio' => Env::get('integral_conversion') / 100,
            ]
        );

    }

    /**
     * 签到
     * @param RSACrypt $crypt
     * @param SignTask $signTask
     * @param Member $member
     * @return mixed
     */
    public function sign(RSACrypt $crypt,
                         SignTask $signTask,
                         Member $member)
    {
        try
        {
            $param = $crypt->request();
            $param['member_id'] = Session::get('member_info')['member_id'];
            $check = $signTask->valid($param, 'sign');
            if ($check['code'])
            {
                return $crypt->response($check, TRUE);
            }
            // 读取上一天签到状态
            $sign = $signTask
                ->where(
                    [
                        ['member_id', '=', $param['member_id']],
                        ['create_time', '=', date('Y-m-d', strtotime("-1 day"))],
                    ]
                )
                ->field('integral,continuous_days')
                ->find();
            // 签到获得积分
            if (is_null($sign))
            {
                $param['integral'] = Env::get('integral_sign', 0);
            } else
            {
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
                'type'      => 0,
                'integral'  => $param['integral'],
                'describe'  => '签到',
            ];
            // 签到任务写入
            $signTask
                ->allowField(TRUE)
                ->together('integral_record,member')
                ->save($param);
            Db::commit();
            $point = $member
                ->where(
                    [
                        ['member_id', '=', $param['member_id']],
                    ]
                )
                ->value('pay_points') ?: 0;
            return $crypt->response(
                [
                    'code'       => 0,
                    'message'    => '签到成功',
                    'pay_points' => $point,
                ],
                TRUE
            );
        } catch (\Exception $e)
        {
            Db::rollback();
            return $crypt->response(
                [
                    'code'    => -100,
                    'message' => self::$errMsg ?: $e->getMessage(),
                ],
                TRUE
            );
        }
    }

    /**
     * 我的积分-个人信息
     * @param Request $request
     * @param RSACrypt $crypt
     * @param IntegralModel $integral
     * @param IntegralRecord $integralRecord
     * @param Member $member
     * @param IntegralTask $integralTask
     * @param SignTask $signTask
     * @param ShareTask $shareTask
     * @return mixed
     */
    public function my(Request $request, RSACrypt $crypt, IntegralModel $integral, IntegralRecord $integralRecord, Member $member, IntegralTask $integralTask, SignTask $signTask, ShareTask $shareTask)
    {
        try
        {

            $param = $request::instance()->param();
            $param['member_id'] = Session::get('member_info')['member_id'];
            // 我的
            $member_info = $member
                ->where('member_id', $param['member_id'])
                ->field('avatar,nickname,pay_points')
                ->find();
            $result['shopping_integral'] = $integralRecord
                ->where([['member_id', '=', $param['member_id']], ['origin_point', '=', 2], ['type', '=', 0]])
                ->sum('integral');
            
            $result['evaluate_integral'] = $integralRecord
                ->where([['member_id', '=', $param['member_id']], ['origin_point', '=', 3], ['type', '=', 0]])
                ->sum('integral');

            $result['other_integral'] = $integralRecord
                ->where([['member_id', '=', $param['member_id']], ['origin_point', 'notin', '2,3'], ['type', '=', 0]])
                ->sum('integral');


            $record = $integralRecord
                ->where('member_id', $param['member_id'])
                ->field('integral_record_id,member_id,delete_time', TRUE)
                ->order('create_time', 'desc')
                ->find();
            //------------------------------------------积分任务-----------------------------------------
            // 其他状态
            $integral_task = $integralTask
                ->where('member_id', $param['member_id'])
                ->field('if((info_state+phone_state+third_party_state)=3,1,0) account,evaluate_state')
                ->find();
            //完善账户
            $task['account'] = $integral_task['account'];
            // 每日签到
            $task['sign']['state'] = ($signTask->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['create_time', '=', date('Y-m-d')],
                ]
            )->value('sign_task_id')) ? 1 : 0;
            $task['sign']['integral'] = Env::get('integral_sign');

            // 评价商品
            $task['evaluate']['state'] = $integral_task['evaluate_state'];
            $task['evaluate']['integral'] = Env::get('integral_evaluate');
            $task['evaluate']['number'] = Env::get('integral_evaluate_number');

            // 分享活动或商品
            $task['share']['state'] = ($shareTask->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['create_time', '=', date('Y-m-d')],
                ]
            )->value('share_task_id')) ? 1 : 0;
            $task['share']['integral'] = Env::get('integral_share');
            $task['share']['number'] = Env::get('integral_share_number');

            // 浏览广告
            $task['adv']['integral'] = Env::get('integral_adv');

            //------------------------------------------花积分-----------------------------------------

            $goods['exchange'] = $integral
                ->where([['integral_number', '>', 0], ['type', '=', 0]])
                ->field('integral_id,file,integral_name,integral,price')
                ->limit(10)
                ->select();

            $goods['redemption'] = $integral
                ->where([['integral_number', '>', 0], ['type', '=', 1]])
                ->field('integral_id,file,integral_name,integral,price')
                ->limit(10)
                ->select();


        } catch (\Exception $e)
        {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
        }
        return $this->fetch(
            '',
            [
                'code'           => 0,
                'member_info'    => $member_info,
                'integral_ratio' => Env::get('integral_conversion') / 100,
                'result'         => $result,
                'goods'          => $goods,
                'record'         => empty($record) ? NULL : $record,
                'task'           => $task,
            ]
        );


    }


    /**
     * 明细
     * @param Request $request
     * @param RSACrypt $crypt
     * @param IntegralRecord $integralRecord
     * @return mixed
     */
    public function detail(Request $request, RSACrypt $crypt, IntegralRecord $integralRecord)
    {
        try
        {
            $param = $request::instance()->param();
            $param['member_id'] = Session::get('member_info')['member_id'];
            // 事务处理
            Db::startTrans();

            $condition[] = ['member_id', '=', $param['member_id']];

            $result = $integralRecord
                ->where($condition)
                ->field('integral_record_id,member_id,delete_time', TRUE)
                ->order('create_time', 'desc')
                ->paginate(20);

        } catch (\Exception $e)
        {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
        }

        return $this->fetch('', ['code' => 0, 'result' => $result]);


    }


    /**
     * 详情
     * @param Request $request
     * @param IntegralModel $integral
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function view(Request $request,
                         IntegralModel $integral)
    {
        $param = $request::instance()->param();


        $result = $integral
            ->where(
                [
                    ['integral_id', '=', $param['integral_id']],
                ]
            )
            ->field(
                'integral_id,integral_classify_id,video,multiple_file,integral_name,integral,price,file,integral_number,
            add_number,content,type'
            )
            ->find();
        $member_id =Session::get('member_info')['member_id']??"";
        $pay_points = Member::where([['member_id','=',$member_id]])->value('pay_points');

        return $this->fetch(
            '',
            [
                'code'    => 0,
                'message' => '查询成功',
                'result'  => $result,
                'pay_points'  => $pay_points,
            ]
        );

    }


    /**
     * 兑换展示
     * @param Request $request
     * @param MemberAddress $memberAddress
     * @param IntegralModel $integral
     * @param Area $area
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function conversion(Request $request,
                               MemberAddress $memberAddress,
                               IntegralModel $integral, Area $area)
    {
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'];

        $address = $memberAddress
            ->where([['member_id', '=', $param['member_id']],])
            ->field('member_address_id,name,phone,province,city,area,street,is_default,address,lat,lng')
            ->order(['is_default' => 'desc', 'member_address_id' => 'asc'])
            ->select();
        $result = $integral
            ->where([['integral_id', '=', $param['integral_id']],])
            ->field('file,integral_name,integral,price,type')
            ->find();

        //地址列表
        $province = $area->where('parent_id', 0)
            ->field('area_id,area_name')
            ->select();
        return $this->fetch(
            '',
            [
                'address'  => $address,
                'result'   => $result,
                'province' => $province,
            ]
        );
    }


    /**
     * 兑换记录
     * @param Request $request
     * @param IntegralOrder $integralOrder
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function conversion_record(Request $request, IntegralOrder $integralOrder)
    {
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? 0;
        if (!empty($param['status']))
        {
            $condition = [['status', '=', $param['status']]];
        } else
        {
            $condition = [['status', '<>', 3]];
        }

//        halt($condition);
        $result = $integralOrder
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['is_delete', '=', 0],
                ]
            )
            ->where($condition)
            ->field(
                'integral_order_id,integral_id,integral_name,file,integral,order_number,create_time,
                price,express_name,express_value,express_number,status,name,phone,province,city,area,street,address'
            )
            ->order('create_time', 'desc')
            ->paginate(10, FALSE, ['query' => $param]);

        return $this->fetch(
            '',
            [
                'code'    => 0,
                'message' => '查询成功',
                'result'  => $result,
            ]
        );

    }



    /**
     * 兑换记录详情
     * @param Request $request
     * @param IntegralOrder $integralOrder
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function conversion_view(Request $request,
                                    IntegralOrder $integralOrder)
    {
        $param = $request::get();
        $integralOrder->valid($param, 'conversion_view');
        $result = $integralOrder
            ->where('integral_order_id', $param['integral_order_id'])
            ->field(
                'integral_order_id,order_number,integral_name,file,integral,price,
                number,name,phone,province,city,area,street,address,express_name,
                express_value,express_number,status,create_time,integral_id'
            )
            ->find();
        //如果订单已经发货
        $express_info = NULL;
        if (in_array($result->status, [1, 2]) && !empty($result['express_value']))
        {
            $config = config('user.')['common']['express'];
            // 快递100
            $data['customer'] = $config['customer'];
            $data['param'] = json_encode(['com' => $result['express_value'], 'num' => $result['express_number']]);
            $data["sign"] = strtoupper(md5($data['param'] . $config['sign'] . $data['customer']));
            $express_info = express_post($data, 'http://poll.kuaidi100.com/poll/query.do');
        }
        return $this->fetch('', ['result' => $result, 'express_info' => $express_info]);

    }

    /**
     * 确认收货
     * @param Request $request
     * @param IntegralOrder $integralOrder
     * @return mixed
     */
    public function confirm_receipt(Request $request,
                                    IntegralOrder $integralOrder)
    {
        $integral_order_id = $request::post('integral_order_id');
        $integralOrder->save(['status'=>2],[['integral_order_id','=',$integral_order_id]]);
        return ['code'    => 0, 'message' => '收货成功',];
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
     * @param MemberAddress $memberAddress
     * @return mixed
     */
    public function redemption(RSACrypt $crypt,
                               Member $member,
                               IntegralOrder $integralOrder,
                               IntegralModel $integral,
                               Lock $lock,
                               IntegralRecord $integralRecord,
                               Inform $inform, MemberAddress $memberAddress)
    {
        try
        {
            $param = $crypt->request();
            $param['number'] = 1;
            //地址信息合并
            $param['member_id'] = Session::get('member_info')['member_id'];
            $member_address = $memberAddress->where([['member_address_id', '=', $param['member_address_id']]])->field(
                'area,city,lat,lng,name,phone,province,street,address'
            )->find();
            if (is_null($member_address)){
                return ['code'=> -1,'message' => '请添加收货地址'];
            }
            $param['from'] = 1;
            $param = array_merge($param, $member_address->toArray());
            $integralOrder->valid($param, 'redemption');
            $getLock = $lock->lock(['integral_' . $param['integral_id']], 10000);
            if ($getLock)
            {
                // 读取商品信息
                $integral_info = $integral
                        ->where('integral_id', $param['integral_id'])
                        ->field('integral_name,type,file,integral,price,integral_number')
                        ->find() ?? exception('积分商品不存在');
                switch ($integral_info['type'])
                {
                    //积分兑换
                    case '0':
                        // 检查用户积分
                        $pay_points = $member
                            ->where(
                                [
                                    ['member_id', '=', $param['member_id']],
                                ]
                            )
                            ->value('pay_points');
                        // 判断用户积分是否够用
                        if ($pay_points < $integral_info['integral'])
                        {
                            $lock->unlock($getLock);
                            exception(config('message.')[-4][-1]);
                        }
                        // 判断库存成功执行
                        if ($integral_info['integral_number'] > 0)
                        {
                            $param['integral_name'] = $integral_info['integral_name'];
                            $param['file'] = $integral_info->getData('file');
                            $param['integral'] = $integral_info['integral'];
                            $param['price'] = $integral_info['price'];
                            $param['order_number'] = get_order_sn();
                            try
                            {
                                Db::startTrans();
                                // 积分记录
                                $integralRecord
                                    ->allowField(TRUE)
                                    ->isUpdate(FALSE)
                                    ->save(
                                        [
                                            'member_id' => $param['member_id'],
                                            'type'      => 1,
                                            'integral'  => $integral_info['integral'],
                                            'describe'  => '兑换' . $integral_info['integral_name'] . '商品',
                                        ]
                                    );

                                // 会员减少积分
                                $member
                                    ->where(
                                        [
                                            ['member_id', '=', $param['member_id']],
                                        ]
                                    )
                                    ->setDec('pay_points', $integral_info['integral']);
                                // 减少库存 增加销量
                                $integral
                                    ->where(
                                        [
                                            ['integral_id', '=', $param['integral_id']],
                                        ]
                                    )
                                    ->inc('add_number', $param['number'])
                                    ->dec('integral_number', $param['number'])
                                    ->update();
                                // 订单生成
                                $param['status'] = 0;
                                $integralOrder->allowField(TRUE)->save($param);

                                // 积分推送
                                $inform->integral_inform(
                                    0,
                                    'integral',
                                    $integral_info['integral'],
                                    $param['member_id'],
                                    1
                                );
                                // halt(1);
                                Db::commit();
                                $lock->unlock($getLock);
                                return ['code' => 0, 'message' => '兑换成功', 'type' => $integral_info['type']];
                            } catch (\Exception $e)
                            {
                                Db::rollback();
                                return
                                    [
                                        'code'    => -100,
                                        'message' => self::$errMsg ?: $e->getFile() . $e->getLine() .$e->getMessage(),
                                    ];
                            }
                        }
                        $lock->unlock($getLock);
                        break;
                    //积分换购
                    case '1':
                        $param = array_merge($param, $integral_info->toArray());
                        if ($integral_info['file'])
                        {
                            $param['file'] = $integral_info->getData('file');
                        }
                        // 订单生成
                        $param['status'] = 3;    //未支付
                        $integralOrder
                            ->allowField(TRUE)
                            ->isUpdate(FALSE)
                            ->save($param);
                        $lock->unlock($getLock);
                        return [
                            'code'       => 0,
                            'message'    => '下单成功',
                            'type'       => $integral_info['type'],
                            'order_data' => urlsafe_b64encode(
                                $crypt->singleEnc(
                                    [
                                        'order_number' => $integralOrder['order_number'],
                                        'order_id'     => $integralOrder['integral_order_id'],
                                        'integral'     => $integralOrder['integral'],
                                        'total_price'  => $integralOrder['price'],
                                        'attach'       => "exchange|3|{$param['member_id']}",
                                    ]
                                )
                            ),

                        ];
                        break;

                }
            }
            exception('网络繁忙');
        } catch (\Exception $e)
        {
            try{
                $lock->unlock($getLock);
            }catch (Exception $exception){

            }
            return [
                'code'    => -100,
                'message' => self::$errMsg ?: $e->getMessage(),
            ];
        }
    }


    /*****************废弃*******************/

//    /**
//     * 分类列表
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param IntegralClassify $integralClassify
//     * @return mixed
//     */
//    public function classify(Request $request, RSACrypt $crypt, IntegralClassify $integralClassify)
//    {
//        if ($request::isPost())
//        {
//            try
//            {
//
//                $result = $integralClassify
//                    ->where(
//                        [
//                            ['parent_id', '=', 0],
//                            ['status', '=', 1],
//                        ]
//                    )
//                    ->field('integral_classify_id,title')
//                    ->order('sort', 'asc')
//                    ->select();
//
//                return $crypt->response(['code' => 0, 'result' => $result]);
//
//            } catch (\Exception $e)
//            {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
//            }
//        }
//    }
//
//    /**
//     * 商品列表
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param IntegralModel $integral
//     * @return mixed
//     */
//    public function goods(Request $request, RSACrypt $crypt, IntegralModel $integral)
//    {
//        if ($request::isPost())
//        {
//            try
//            {
//
//                $param = $crypt->request();
//
//                // 验证
//                $check = $integral->valid($param, 'goods');
//                if ($check['code'])
//                {
//                    return $crypt->response($check);
//                }
//
//                $condition[] = ['type', '=', $param['type']];
//                $condition[] = ['integral_number', '>', 0];
//
//                if (!empty($param['integral_classify_id']))
//                {
//                    $condition[] = ['integral_classify_id', '=', $param['integral_classify_id']];
//                }
//
//                // 读取积分列表
//                $result = $integral->where($condition)
//                    ->field('integral_id,file,integral_name,integral,price')
//                    ->paginate(20);
//
//                return $crypt->response(
//                    ['code' => 0, 'integral_ratio' => Env::get('integral_conversion') / 100, 'result' => $result]
//                );
//
//            } catch (\Exception $e)
//            {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
//            }
//        }
//    }
//

//
//    /**
//     * 积分+余额兑换商品
//     * @param RSACrypt $crypt
//     * @param Member $member
//     * @param IntegralOrder $integralOrder
//     * @param IntegralModel $integral
//     * @param Lock $lock
//     * @param IntegralRecord $integralRecord
//     * @param Inform $inform
//     * @param Consumption $consumption
//     * @return mixed
//     */
//    public function redemption_money(RSACrypt $crypt,
//                                     Member $member,
//                                     IntegralOrder $integralOrder,
//                                     IntegralModel $integral,
//                                     Lock $lock,
//                                     IntegralRecord $integralRecord,
//                                     Inform $inform,
//                                     Consumption $consumption)
//    {
//        try
//        {
//            $param = $crypt->request();
//            $param['member_id'] = request(0)->mid;
//            $check = $integralOrder->valid($param, 'redemption_money');
//            if ($check['code'])
//            {
//                return $crypt->response($check, TRUE);
//            }
//            $param['pay_channel'] = 3;
//            $ret = self::redemption_money_common(
//                $integral,
//                $member,
//                $integralRecord,
//                $consumption,
//                $integralOrder,
//                $inform,
//                $lock,
//                $param
//            );
//            return $crypt->response(
//                $ret === TRUE ?
//                    [
//                        'code'    => 0,
//                        'message' => '兑换成功',
//                    ] :
//                    [
//                        'code'    => -1,
//                        'message' => $ret,
//                    ],
//                TRUE
//            );
//        } catch (\Exception $e)
//        {
//            return $crypt->response(
//                [
//                    'code'    => -100,
//                    'message' => self::$errMsg ?: $e->getMessage(),
//                ],
//                TRUE
//            );
//        }
//    }
//
//    /**
//     * 兑换记录删除
//     * @param RSACrypt $crypt
//     * @param IntegralOrder $integralOrder
//     * @return mixed
//     */
//    public function conversion_record_delete(RSACrypt $crypt,
//                                             IntegralOrder $integralOrder)
//    {
//        try
//        {
//            $param = $crypt->request();
//            $check = $integralOrder->valid($param, 'conversion_record_delete');
//            if ($check['code'])
//            {
//                return $crypt->response($check, TRUE);
//            }
//            // 全端进行标记删除
//            $integralOrder
//                ->allowField(TRUE)
//                ->isUpdate(TRUE)
//                ->save(
//                    [
//                        'integral_order_id' => $param['integral_order_id'],
//                        'is_delete'         => 1,
//                    ]
//                );
//            return $crypt->response(
//                [
//                    'code'    => 0,
//                    'message' => '删除成功',
//                ],
//                TRUE
//            );
//        } catch (\Exception $e)
//        {
//            return $crypt->response(
//                [
//                    'code'    => -100,
//                    'message' => self::$errMsg ?: $e->getMessage(),
//                ],
//                TRUE
//            );
//        }
//    }


}
