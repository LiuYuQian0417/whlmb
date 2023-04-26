<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/23 0023
 * Time: 14:07
 */

namespace app\computer\controller\auth;


use app\computer\controller\BaseController;
use app\computer\model\MemberRank;
use think\Db;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use mrmiao\encryption\RSACrypt;
use app\computer\model\Goods;
use app\computer\model\Store;
use app\common\model\MemberCard;
use app\computer\model\
{Area,
    CollectArticle,
    CollectGoods,
    CollectStore,
    Member,
    MessageExamine,
    DistributionLevel,
    OrderAttach,
    RecordGoods,
    MemberCoupon,
    MemberPacket,
    Message,
    MemberTask,
    MemberGrowthRecord,
    StoreClassify};
use think\facade\Session;

class My extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => ''],
    ];

    /**
     * 我的 - 猜你喜欢
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Goods $goodsModel
     * @return mixed
     */
    public function recommend_list(Request $request, RSACrypt $crypt, Goods $goodsModel)
    {
        if ($request::isPost())
        {
            try
            {
                $param = $crypt->request();
                $param['member_id'] = Session::get('member_info')['member_id'];
                $result = recommend_list($goodsModel, $param['limit'], $param['member_id'], $param['type'] ?? 1);
                // 折扣
                $discount = discount($param['member_id']);
                return $crypt->response(['code' => 0, 'result' => $result, 'discount' => $discount]);
            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 我的
     * @param RSACrypt $crypt
     * @param Member $member
     * @param MemberCoupon $memberCoupon
     * @param MemberPacket $memberPacket
     * @param DistributionLevel $distributionLevel
     * @param RecordGoods $recordGoods
     * @param OrderAttach $orderAttach
     * @param Goods $goods
     * @return mixed
     */
    public function index(
        RSACrypt $crypt,
        Member $member,
        MemberCoupon $memberCoupon,
        MemberPacket $memberPacket,
        DistributionLevel $distributionLevel,
        RecordGoods $recordGoods,
        OrderAttach $orderAttach,
        MemberCard $memberCard,
        Goods $goods)
    {
        try
        {
            $member_id = Session::get('member_info')['member_id'];
            // 成长值
            $result['growth_value'] = countGrowth($member_id);
            // // 获取等级信息
            $result['rank_name'] = $member->get($member_id)->rank_name;
            // redis 初始化
            $redisInstance = Cache::handler();
            $prefix = Config::get('cache.default')['prefix'];
            // 优惠券数量
            $result['coupon'] = $memberCoupon
                ->where(
                    [
                        ['member_id', '=', $member_id],
                        ['status', '=', '0'],
                        ['end_time', '>=', date('Y-m-d')],
                    ]
                )
                ->count() ?: 0;
            //  银行卡数量
            $result['card'] = $memberCard->where([
                ['member_id', '=', $member_id],

            ])->count()?:0;

            // 红包数量
            $result['red_packet'] = $memberPacket
                ->where(
                    [
                        ['member_id', '=', $member_id],
                        ['status', '=', '0'],
                        ['end_time', '>=', date('Y-m-d')],
                    ]
                )
                ->count() ?: 0;


            // 商品关注数
            $result['collect_goods'] = CollectGoods::where([['member_id','=',$member_id]])->count();
//                (int)$redisInstance->zScore(
//                $prefix . 'collect_goods',
//                $member_id
//            ) ?: 0;

            // 店铺关注数
            $result['collect_store'] = CollectStore::where([['member_id','=',$member_id]])->count();
//                (int)$redisInstance->zScore(
//                $prefix . 'collect_store',
//                $member_id
//            ) ?: 0;

            // 文章关注数
            $result['collect_article'] = CollectArticle::where([['member_id','=',$member_id]])->count();

//                (int)$redisInstance->zScore(
//                $prefix . 'collect_article',
//                $member_id
//            ) ?: 0;

            // 浏览记录
            $result['record_goods'] = $recordGoods
                ->alias('r_g')
                ->join('goods goods', 'goods.goods_id = r_g.goods_id')
                ->where(
                    [
                        ['r_g.member_id', '=', $member_id],
                        ['review_status', '=', 1],
                        ['is_putaway', '=', 1],
                    ]
                )
                ->field('goods.goods_id,file')
                ->order(['r_g.create_time' => 'desc'])
                ->limit(12)->select();
            // 我的最新订单
            $result['my_order'] = $orderAttach::with(
                [
                    'orderGoods' => function ($query)
                    {
                        $query->field('order_attach_id,order_goods_id,goods_id,file,single_price,status');
                    },
                ]
            )->alias('oa')
                ->join('order o', 'o.order_id = oa.order_id')
                ->where([['o.member_id', '=', $member_id], ['o.order_type', '<>', 5]])
                ->field('order_attach_id,pay_type,distribution_type,status,express_value,express_number,order_type')
                ->order('oa.create_time', 'desc')
                ->limit(2)
                ->select();
            //分销
            Env::load(Env::get('app_path') . 'common/ini/.distribution');
            $result['distribution_status'] = Env::get('distribution_status', 0);
            $distribution_status = 2;//成为分销 是否有规则 1没有->弹窗 2 有规则->规则页面

            // 用户登录和开启分销的前提下
            if ($member_id && $result['distribution_status'])
            {
                //开启注册后用户自动成为分销商自动会员成为分销商
                if (Env::get('distribution_register', 0) == 1)
                {
                    $distribution_status = 1; //注册成为分销商
                }
                $orderLevelId = $distributionLevel
                    ->order(['level_weight' => 'asc'])
                    ->column('distribution_level_id');
                $result['distribution'] = $distributionLevel
                    ->alias('dl')
                    ->where(
                        [
                            ['d.member_id', '=', $member_id],
                            ['audit_status', '=', 1],                       //已审核
                        ]
                    )
                    ->join(
                        'distribution d',
                        'dl.distribution_level_id = d.distribution_level_id and d.delete_time is null'
                    )
                    ->field('d.distribution_id,d.distribution_level_id,dl.level_title,dl.mark')
                    ->hidden(['mark'])
                    ->append(['mark_alias'])
                    ->find();
                if ($result['distribution'] && !empty($orderLevelId))
                {
                    foreach ($orderLevelId as $key => $_orderLevelId)
                    {
                        if ($_orderLevelId == $result['distribution']['distribution_level_id'])
                        {
                            $result['distribution']['level_pos'] = $key + 1;
                        }
                    }
                } else
                {
                    $result['distribution'] = NULL;
                }
            }
            //猜你喜欢
            $recommend_list = recommend_list($goods, 8, $member_id, 1);
        } catch (\Exception $e)
        {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
        }
        return $this->fetch(
            '',
            [
                'result'         => $result,
                'recommend_list' => $recommend_list,
                'distribution_status' => $distribution_status,
            ]
        );
    }


    /**
     * 退出登录
     */
    public function login_out()
    {
        header("token:''");
        Session::clear();
        $this->redirect('/pc2.0/login/index');
    }


    /**
     * 任务
     * @param MemberTask $memberTask
     * @param MemberRank $memberRank
     * @return mixed
     * @throws ModelNotFoundException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\exception\DbException
     */
    public function task(MemberTask $memberTask, MemberRank $memberRank)
    {

        $member_id = Session::get('member_info')['member_id'];
        // 其他状态
        $task = $memberTask
            ->where('member_id', $member_id)
            ->field('member_id,delete_time', TRUE)
            ->find();
        $result = [];
        $result['growth_value'] = countGrowth($member_id);
        // 现在的等级
        $result['now'] = $memberRank->where('min_points', '<=', $result['growth_value'])
            ->field('member_rank_id,rank_name,mark,max_points,min_points')
            ->order('min_points','desc')
            ->find();
        // 下一个等级
        $result['next'] = $memberRank->where('member_rank_id', '>', $result['now']['member_rank_id'])
            ->field('rank_name,mark,min_points')
            ->order('member_rank_id', 'asc')
            ->find();
//        halt($result);

        // 完善个人中心
        $result['info']['state'] = $task['info_state'];
        $result['info']['growth'] = Env::get('growth_info');

        // 绑定手机号
        $result['phone']['state'] = $task['phone_state'];
        $result['phone']['growth'] = Env::get('growth_phone');

        // 绑定第三方社交账号
        $result['third_party']['state'] = $task['third_party_state'];
        $result['third_party']['growth'] = Env::get('growth_third_party');

        // 每月购物3天
        $result['monthly_shopping']['growth'] = Env::get('growth_monthly_shopping');

        // 购物年限
        $result['age_limit']['growth'] = Env::get('growth_age_limit');

        // 评价商品
        $result['evaluate_number']['growth'] = Env::get('growth_evaluate');
        $result['evaluate_number']['number'] = Env::get('growth_evaluate_number');

        // 分享商品或活动
        $result['growth_share']['growth'] = Env::get('growth_share');
        $result['growth_share']['number'] = Env::get('growth_share_number');

        // 浏览广告
        $result['growth_adv']['growth'] = Env::get('growth_adv');

        // 使用余额支付
        $result['growth_balance']['growth'] = Env::get('growth_balance');
        $result['growth_balance']['number'] = Env::get('growth_balance_number');

        $date['start'] = date("Y-m-31", strtotime("-1 year"));
        $date['end'] = date('Y-m-31');

        // 等级列表
        $result['rank_list'] = $memberRank
//            ->where('member_rank_id', '<>', $result['now']['member_rank_id'])
            ->field('member_rank_id,rank_name,mark,min_points,max_points,file2,discount')
            ->order('member_rank_id', 'asc')
            ->select();
        return $this->fetch(
            '',
            ['code' => 0, 'result' => $result, 'date' => $date]
        );

    }

    /**
     * 创建店铺
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Store $store
     * @param StoreClassify $storeClassify
     * @param Area $area
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create_store(Request $request, RSACrypt $crypt, Store $store, StoreClassify $storeClassify, Area $area)
    {
        if ($request::isPost())
        {
            try
            {
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                Db::startTrans();
                // 验证
                $check = $store->valid($param, 'create_store');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                $store_id = $store->where('member_id', $param['member_id'])->value('store_id');
                if ($store_id)
                {
                    return $crypt->response(['code' => -100, 'message' => config('message.')[-7][-3]]);
                }
                //  设置密码
                if(!empty($param['password'])){
                    Db::name('member')->where("member_id",$param['member_id'])->update(['password'=>passEnc($param['password'])]);
                }
                // 创建店铺
                $store->allowField(TRUE)->save($param);
                Db::commit();
                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);
            } catch (\Exception $e)
            {
                Db::rollback();
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
        //店铺分类
        $data['store_classify'] = $storeClassify
            ->field('store_classify_id,title')
            ->where('status', 1)
            ->order(['sort' => 'desc', 'create_time' => 'desc'])
            ->select();
        //一级地址
        $data['province'] = $area->where('parent_id', 0)
            ->field('area_id,area_name')
            ->select();
        $store_info = $store::withTrashed()
            ->where([
                        ['member_id', '=', Session::get('member_info')['member_id']],
                    ])
            ->field('store_id,status,delete_time')
            ->find();

        //  是否设置过密码
        $is_password = Db::name('member')->where([
                        ['member_id', '=', Session::get('member_info')['member_id']],
                    ])->value('password');
        $tel = Db::name('member')->where([
            ['member_id', '=', Session::get('member_info')['member_id']],
        ])->value('phone');
        //店铺审核状态 0 申请中 1 申请通过 2申请不通过 3认证中 4认证通过 5认证不通过
        Env::load(Env::get('app_path') . 'common/ini/.config');
        return $this->fetch(
            '',
            [
                'data'       => $data,
                'store_info' => $store_info,
                'phone'      => Env::get('phone'),
                'is_password'=> $is_password,
                'tel'        => $tel,
            ]
        );
    }

    public function get_token()
    {
        $newToken = '';
        //如果会员登录
        if (!empty(Session::get('member_info')['member_id']))
        {
            $newToken = app(
                'app\\common\\service\\JwtManage',
                ['param' => ['mid' => Session::get('member_info')['member_id'], 'dev_type' => 5]],
                TRUE
            )->issueToken();
        }
        header("token:" . $newToken);
    }

}