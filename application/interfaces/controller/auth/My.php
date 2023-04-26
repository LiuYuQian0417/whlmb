<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\DistributionLevel;
use app\common\model\Member;
use app\common\model\MemberCoupon;
use app\common\model\MemberPacket;
use app\common\model\MemberRank;
use app\common\model\MemberTask;
use app\common\model\Message;
use app\common\model\MessageExamine;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\Store;
use app\interfaces\controller\BaseController;
use app\interfaces\controller\advices\Applet;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;
use think\facade\View;

/**
 * 我的 - Joy
 * Class Register
 * @package app\interfaces\controller\auth
 */
class My extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    /**
     * 我的
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Message $message
     * @param MessageExamine $messageExamine
     * @param DistributionLevel $distributionLevel
     * @param OrderGoods $orderGoods
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Member $member,
                          Message $message,
                          MessageExamine $messageExamine,
                          DistributionLevel $distributionLevel,
                          OrderGoods $orderGoods,
                          OrderAttach $orderAttach)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        // 读取金额、积分
        $result['userInfo'] = $member
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->field('phone,nickname,avatar,card_number,distribution_superior')
            ->find();
        // 成长值
        $result['userInfo']['growth_value'] = countGrowth($param['member_id']);
        // 获取等级图片
        $result['userInfo']['rank_img'] = $member->RankImg($result['userInfo']['growth_value']);
        // 功能状态
        $function_status = [
            'is_coupon' => Env::get('is_coupon', 1),
            'is_red_packet' => Env::get('is_red_packet', 1),
            'is_cut' => Env::get('is_cut', 1),
            'is_group' => Env::get('is_group', 1),
            'is_recharge' => Env::get('is_recharge', 1),
            'is_balance' => Env::get('is_balance', 1),
            'is_take' => self::$type,               // 是否包含自提(取决于单店多店)
            'is_underPay' => self::$type,           // 是否包含线下订单(取决于单店多店)
        ];
        $og = $orderGoods->myStatistical($param['member_id'], $function_status);
        $oa = $orderAttach->myStatistical($param['member_id'], $function_status);
        $result['orderStat'] = array_merge($oa, [$og]);
        $result['assistant'] = self::assistant($param['member_id'], $function_status);
        $result['userInfo']['information'] = 0;
        if ($param['member_id']) {
            $regTime = $member
                ->where(
                    [
                        ['member_id', '=', $param['member_id']],
                    ]
                )
                ->value('register_time');
            if (!is_null($regTime)) {
                // 已登录
                foreach (['common', 'express', 'activity'] as $key => $value) {
                    $ct = $messageExamine
                        ->where(
                            [
                                ['member_id', '=', $param['member_id']],
                                ['type', '=', $key],
                            ]
                        )
                        ->value('create_time');
                    $infoWhere = [
                        ['type', '=', $key],
                        ['status', '=', 1],
                        ['member_id', '=', ($value == 'activity') ? 0 : $param['member_id']],
                        ['create_time', '>', !is_null($ct) ? $ct : $regTime],
                    ];
                    $result['userInfo']['information'] += $message
                        ->where($infoWhere)
                        ->count();
                }
            }
        }
        // 分销商数据
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $result['distribution'] = [
            'distribution_status' => Env::get('distribution_status', 0),
            'distribution_id' => 0,
        ];
        // 用户登录和开启分销的前提下
        if ($param['member_id'] && $result['distribution']['distribution_status']) {
            $orderLevelId = $distributionLevel
                ->order(['level_weight' => 'asc'])
                ->column('distribution_level_id');
            $distInfo = $distributionLevel
                ->alias('dl')
                ->where(
                    [
                        ['d.member_id', '=', $param['member_id']],
                        ['audit_status', '=', 1],                       //已审核
                    ]
                )
                ->join('distribution d', 'dl.distribution_level_id = d.distribution_level_id and d.delete_time is null')
                ->field('d.distribution_id,d.distribution_level_id,dl.level_title,dl.mark')
                ->hidden(['mark'])
                ->append(['mark_alias'])
                ->find();
            if ($distInfo && !empty($orderLevelId)) {
                foreach ($orderLevelId as $key => $_orderLevelId) {
                    if ($_orderLevelId == $distInfo['distribution_level_id']) {
                        $result['distribution']['level_pos'] = $key + 1;
                    }
                }
                $result['distribution'] = array_merge($result['distribution'], $distInfo->toArray());
            }
        }
        $result['encrypt'] = [
            'parameter' => urlencode($crypt->singleEnc(['member_id' => $param['member_id']]) ?: ''),
        ];
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $result,
        ], TRUE);
    }
    
    
    /**
     * 获取会员入驻状态
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInState(RSACrypt $crypt)
    {
        $args['member_id'] = request(0)->mid;
        $in_state = Hook::exec(['app\\interfaces\\behavior\\StoreCheck', 'isSettledIn'], $args);
        $msg = [
            0 => '平台正在审核您的入驻申请,请注意查收审核结果的通知短信',
            1 => '恭喜您,您的【%s】店铺申请已通过审核,可以登录商家后台管理您的店铺',
            2 => '很遗憾，您的【%s】店铺入驻申请审核未通过，具体原因请联系管理员',
            3 => '店铺认证信息审核中',
            4 => '店铺认证信息审核已通过',
            5 => '很遗憾，您的【%s】店铺认证信息审核未通过，请登录商家后台进行编辑再次提交审核',
            6 => '您的店铺已注销，如想重新开启，请联系客服：400-2233-1333',
        ];
        $state_msg = isset($msg[$in_state['code']]) ?
            (in_array($in_state['code'], [1, 2, 5]) ? sprintf(
                $msg[$in_state['code']],
                $in_state['store_name']
            ) : $msg[$in_state['code']]) :
            '';
        return $crypt->response(
            [
                'code' => 0,
                'message' => '查询成功',
                'result' => [
                    'in_state' => $in_state['code'],
                    'state_msg' => $state_msg,
                ],
            ],
            TRUE
        );
    }
    
    /**
     * 我的钱包
     * @param RSACrypt $crypt
     * @param Member $member
     * @param MemberCoupon $memberCoupon
     * @param MemberPacket $memberPacket
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myWallet(RSACrypt $crypt,
                             Member $member,
                             MemberCoupon $memberCoupon,
                             MemberPacket $memberPacket)
    {
        $param['member_id'] = request(0)->mid;
        $result = $member
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->field('usable_money,pay_points')
            ->find();
        // 优惠券数量[有效未使用]
        $result['coupon'] = $memberCoupon
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['status', '=', '0'],
                    ['end_time', '>=', date('Y-m-d')],
                ]
            )
            ->count() ?: 0;
        // 红包数量[有效未使用]
        $result['red_packet'] = $memberPacket
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                    ['status', '=', '0'],
                    ['end_time', '>=', date('Y-m-d')],
                ]
            )
            ->count() ?: 0;
        return $crypt->response(
            [
                'code' => 0,
                'message' => '查询成功',
                'data' => $result,
            ],
            TRUE
        );
    }
    
    /**
     * 我的小助手
     * @param $member_id
     * @param $function_status
     * @return array
     */
    protected function assistant($member_id,
                                 $function_status)
    {
        $redisInstance = Cache::handler();
        $prefix = Config::get('cache.default')['prefix'];
        $assistant_a = [
            [
                'key' => 'goodsFoll',
                'title' => '商品关注',
                'count' => ($member_id ? ((int)$redisInstance->zScore($prefix . 'collect_goods', $member_id) ?: 0) : 0),
                'img' => '',
            ],
            [
                'key' => 'contentColl',
                'title' => '内容收藏',
                'count' => ($member_id ? ((int)$redisInstance->zScore(
                    $prefix . 'collect_article',
                    $member_id
                ) ?: 0) : 0),
                'img' => '',
            ],
            [
                'key' => 'browseRec',
                'title' => '浏览记录',
                'count' => ($member_id ? ((int)$redisInstance->zScore($prefix . 'record_goods', $member_id) ?: 0) : 0),
                'img' => '',
            ],
        ];
        $imgPathPrefix = 'mobile/small/image/';
        $assistant_b = [
            ['key' => 'myLuck', 'title' => '我的抽奖', 'count' => 0, 'img' => $imgPathPrefix . 'prize.png'],
            ['key' => 'myEval', 'title' => '我的评价', 'count' => 0, 'img' => $imgPathPrefix . 'wd-wdpj.png'],
            ['key' => 'customer', 'title' => '客户服务', 'count' => 0, 'img' => $imgPathPrefix . 'wd-khfw.png'],
        ];
        if (self::$oneOrMore) {
            // 多店下需加入店铺关注和商家入驻
            array_splice(
                $assistant_a,
                1,
                0,
                [
                    [
                        'key' => 'storeFoll',
                        'title' => '店铺关注',
                        'count' => ($member_id ? ((int)$redisInstance->zScore(
                            $prefix . 'collect_store',
                            $member_id
                        ) ?: 0) : 0),
                        'img' => '',
                    ],
                ]
            );
            array_splice(
                $assistant_b,
                -1,
                0,
                [
                    ['key' => 'storeIn', 'title' => '商家入驻', 'count' => 0, 'img' => $imgPathPrefix . 'wd-sjrz.png'],
                ]
            );
        }
        if ($function_status['is_cut']) {
            array_splice(
                $assistant_b,
                0,
                0,
                [
                    ['key' => 'myCut', 'title' => '我的砍价', 'count' => 0, 'img' => $imgPathPrefix . 'wd-wdkj.png'],
                ]
            );
        }
        if ($function_status['is_group']) {
            array_splice(
                $assistant_b,
                0,
                0,
                [
                    ['key' => 'myGroup', 'title' => '我的拼团', 'count' => 0, 'img' => $imgPathPrefix . 'wd-wdpt.png'],
                ]
            );
        }
        return array_merge($assistant_a, $assistant_b);
    }
    
    /**
     * 会员卡生成码
     * @param RSACrypt $crypt
     * @param Member $member
     * @return array|mixed|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function payment_code(RSACrypt $crypt,
                                 Member $member)
    {
        $member_id = request(0)->mid;
        $usable_money = $member
            ->where(
                [
                    ['member_id', '=', $member_id],
                ]
            )
            ->value('usable_money');
        return $crypt->response(
            [
                'code' => 0,
                'number' => coding($member_id),
                'usable_money' => $usable_money,
            ],
            TRUE
        );
    }
    
    /**
     * 会员卡web页
     * @param Member $member
     * @param MemberRank $memberRank
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index_web(Member $member,
                              MemberRank $memberRank)
    {
        
        $member_id = request()->mid ?? '';
        if ($member_id === '') {
            return '<div style="text-align: center;font-size:20px;padding: 30px 0;">用户不存在</div>';
        }
        $info = $member
            ->where(
                [
                    ['member_id', '=', $member_id],
                ]
            )
            ->field('nickname,card_number,usable_money,pay_password')
            ->find();
        if (is_null($info)) {
            return '<div style="text-align: center;font-size:20px;padding: 30px 0;">用户不存在</div>';
        }
        $info['pay_state'] = $info['pay_password'] ? 1 : 0;
        unset($info['pay_password']);
        $info['growth_value'] = countGrowth($member_id);
        $set = Env::get();
        $info['now'] = $memberRank
            ->where(
                [
                    ['min_points', '<=', $info['growth_value']],
                ]
            )
            ->field('mark,rank_name')
            ->order(['min_points' => 'desc'])
            ->find();
        return View::fetch(
            'auth/my/index_web',
            [
                'member_info' => $info,
                'set' => $set,
            ]
        );
    }
    
    /**
     * 会员卡获取解密会员ID
     * @param RSACrypt $crypt
     * @param Member $member
     * @return array
     */
    public function index_number(RSACrypt $crypt, Member $member)
    {
        $par = Request::post('parameter', '');
        $member_id = $crypt->singleDec($par);
        if ($member_id) {
            $usable_money = $member
                ->where(
                    [
                        ['member_id', '=', $member_id['member_id']],
                    ]
                )
                ->value('usable_money');
            return [
                'code' => 0,
                'number' => coding($member_id['member_id']),
                'usable_money' => $usable_money,
            ];
        }
        return [
            'code' => -1,
        ];
    }
    
    /**
     * 个人资料
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function info(RSACrypt $crypt,
                         Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $result = $member
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->field('avatar,username,nickname,sex')
            ->find();
        $encrypt = [
            'parameter' => urlencode($crypt->singleEnc(['member_id' => $param['member_id']])),
        ];
        return $crypt->response(
            [
                'code' => 0,
                'message' => '查询成功',
                'result' => $result,
                'encrypt' => $encrypt,
            ],
            TRUE
        );
    }
    
    /**
     * 更新头像
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function avatar(RSACrypt $crypt,
                           Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $member->allowField(TRUE)->isUpdate(TRUE)->save($param);
        return $crypt->response(
            [
                'code' => 0,
                'message' => '更新成功',
                'avatar' => $member->avatar,
            ],
            TRUE
        );
    }
    
    /**
     * 更新昵称 OR 性别
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function other(RSACrypt $crypt,
                          Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $member->allowField(TRUE)->isUpdate(TRUE)->save($param);
        return $crypt->response(
            [
                'code' => 0,
                'message' => '更新成功',
            ],
            TRUE
        );
    }
    
    /**
     * 创建店铺
     * @param RSACrypt $crypt
     * @param Store $store
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create_store(RSACrypt $crypt,
                                 Store $store,Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $store->valid($param, 'create_store');
        //店铺审核状态 0 申请中 1 申请通过 2申请不通过 3认证中 4认证通过 5认证不通过
        $store_info = $store
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->field('store_id,status')
            ->find();
        if ($store_info && $store_info['status'] != 2) {
            return $crypt->response([
                'code' => -1,
                'message' => '您已经申请过店铺了,请勿重复申请',
            ], TRUE);
        }
        // 检测店铺名称是否有重复
        $checkWhere = [
            ['store_name', '=', $param['store_name']],
        ];
        if (!is_null($store_info)) {
            array_push($checkWhere, ['store_id', '<>', $store_info['store_id']]);
        }
        $check = $store
            ->where($checkWhere)
            ->find();
        if (!is_null($check)) {
            return $crypt->response([
                'code' => -1,
                'message' => '系统检测到店铺名称已存在,请更换名称再提交',
            ], TRUE);
        }
        Db::startTrans();
        // 创建店铺或编辑店铺
        if (is_null($store_info)) {
            $store->allowField(TRUE)->isUpdate(FALSE)->save($param);
        } else {
            $param['store_id'] = $store_info['store_id'];
            $param['status'] = 0;
            $store->allowField(TRUE)->isUpdate(TRUE)->save($param);
        }

        //  设置密码
        if(!empty($param['password'])){
            $member->where("member_id",$param['member_id'])->update(['password'=>passEnc($param['password'])]);
        }
        // 推送消息[店铺入驻申请]
        $pushServer = app('app\\interfaces\\behavior\\Push');
        $pushServer->send([
            'tplKey' => 'reside_apply_state',
            'openId' => Request::param('web_open_id'),
            'subscribe_time' => Request::param('subscribe_time'),
            'microId' => Request::param('micro_open_id'),
            'phone' => Request::param('phone'),
            'data' => [0, $param['store_name']],
            'inside_data' => [
                'member_id' => $param['member_id'],
                'type' => 0,
                'jump_state' => '-1',
                'file' => 'image/dui.png',
            ],
            'sms_data' => [],
        ]);
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '提交成功',
        ], TRUE);
    }
    
    /**
     * 成长值任务
     * @param RSACrypt $crypt
     * @param MemberTask $memberTask
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function task(RSACrypt $crypt,
                         MemberTask $memberTask)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 用户任务详情
        $task = $memberTask
            ->where(
                [
                    ['member_id', '=', $param['member_id']],
                ]
            )
            ->field('member_id,delete_time', TRUE)
            ->find();
        $result = [
            'info' => [
                'state' => $task['info_state'],     //完善账户状态 0 否 1 是
                'growth' => Env::get('growth_info', 0),     //完善个人信息赠送成长值
            ],
            'phone' => [
                'state' => $task['phone_state'],    //绑定手机号状态 0 否 1 是
                'growth' => Env::get('growth_phone', 0),    //绑定手机号赠送成长值
            ],
            'third_party' => [
                'state' => $task['third_party_state'],  //绑定第三方账号状态 0 否 1 是
                'growth' => Env::get('growth_third_party', 0),  //绑定第三方账号赠送成长值
            ],
            'monthly_shopping' => [
                'growth' => Env::get('growth_monthly_shopping', 0), //每月购物3天赠送成长值
            ],
            'age_limit' => [
                'growth' => Env::get('growth_age_limit', 0),    //购物年限赠送成长值
            ],
            'evaluate_number' => [
                'growth' => Env::get('growth_evaluate', 0),     //评价商品赠送成长值
                'number' => Env::get('growth_evaluate_number', 0),  //评价商品每日限制次数
            ],
            'growth_share' => [
                'growth' => Env::get('growth_share', 0),        //分享商品或活动赠送成长值
                'number' => Env::get('growth_share_number', 0), //分享商品或活动每日限制次数
            ],
            'growth_adv' => [
                'growth' => Env::get('growth_adv', 0),          //浏览广告赠送成长值
            ],
            'growth_balance' => [
                'growth' => Env::get('growth_balance', 0),      //使用余额赠送成长值
                'number' => Env::get('growth_balance_number', 0)        //使用余额每日限制次数
            ],
        ];
        $date = [
            'start' => date("Y-m-31", strtotime("-1 year")),
            'end' => date('Y-m-31'),
        ];
        return $crypt->response(
            [
                'code' => 0,
                'message' => '查询成功',
                'result' => $result,
                'growth_value' => countGrowth($param['member_id']),
                'date' => $date,
            ],
            TRUE
        );
    }

    // 客服电话
    public function customer_phone(RSACrypt $crypt,Store $store)
    {

        $param = $crypt->request();
        #$param['member_id'] = request(0)->mid;

        if(empty($param['store_id'])){
            return json(['code' => -100, 'message' => '参数不可为空' ]);
        }

        $store_model = $store->where(['store_id'=>$param['store_id']])->value('phone');
        #$param['store_id']

        $result['phone'] = $store_model;

        return $crypt->response(
            [
                'code' => 0,
                'message' => '查询成功',
                'result' => $result,
            ],
            TRUE
        );
    }
}