<?php
declare(strict_types=1);
/**
 * 控制器基类.
 * User: Heng
 * Date: 2019/2/21
 * Time: 13:59
 */

namespace app\computer\controller;

use app\computer\model\
{ArticleClassify, Cart, Member, Search, Store};
use app\computer\model\DistributionLevel;
use think\Controller;
use think\facade\Env;
use think\facade\Session;
use think\facade\View;

class BaseController extends Controller
{
    /**
     * 系统定义的错误信息
     * @var string
     */
    public static $errMsg;
    public static $type;
    public static $oneOrMore;
    public static $functionStatus;
    public static $memberId;

    public function initialize()
    {

        $errMsg = config('message.')[-100];
        self::$errMsg = $errMsg ? $errMsg : '';
        // 当前平台类型
        self::$type = config('user.pattern');
        // 当前单店铺还是多店铺   单店是单店铺id   多店是true 判断的时候判断全等
        self::$oneOrMore = config('user.one_more') === 0 ? config('user.one_store_id') : TRUE;
        //当前平台开关配置
        self::$functionStatus = self::function_status();
        //公共模板全局赋值
        $this->template_assig();
    }

    /**
     * 检查是否登录
     */
    protected function is_login()
    {
        if (empty(Session::get('member_info')['member_id']) || Member::where(
                [['member_id', '=', Session::get('member_info')['member_id'] ?? 0]]
            )->count() <= 0)
        {
            Session::clear();
            if ($this->request->isAjax())
            {
                return json(['code' => -201]);
            }
            header("Location: /pc2.0/login/index");
            die;
        }
        return TRUE;
    }

    protected function check_login()
    {
        if (!empty(Session::get('member_info')['member_id']))
        {
            header("Location: /pc2.0/my/index");
            die;
        }
        return TRUE;
    }

    /**
     * 公共模板赋值
     */
    private function template_assig()
    {
        //帮助信息
        $article_Classify = ArticleClassify::with('article')
            ->field('article_classify_id,title')
            ->where(
                [
                    ['parent_id', '=', 16],
                    ['status', '=', 1],
                ]
            )
            ->order('sort', 'desc')
            ->select();
        //会员信息
        if (isset(Session::get('member_info')['member_id']))
        {
            $member_info = Member::where([['member_id', '=', Session::get('member_info')['member_id']]])
                ->field('member_id,phone,username,nickname,avatar,sex,usable_money,pay_points,cumulative_order_sum,distribution_superior,rank_points,is_gift')
                ->find();
            //如果当前登录会员未查到
            if (empty($member_info))
            {
                Session::delete('member_info');
            } else
            {
                //店铺审核状态 -1 未申请 0 不通过 1 通过 2待审核 3未认证
                $member_info['store_status'] = (new Store)->where('member_id', $member_info['member_id'])->value(
                    'status',
                    -1
                );
                // 用户登录和开启分销的前提下
                if (INI_DISTRIBUTION['DISTRIBUTION_STATUS'])
                {
                    $orderLevelId = (new DistributionLevel)
                        ->order(['level_weight' => 'asc'])
                        ->column('distribution_level_id');
                    $member_info['distribution'] = (new DistributionLevel)
                        ->alias('dl')
                        ->where(
                            [
                                ['d.member_id', '=', $member_info->member_id],
                                ['audit_status', '=', 1],                       //已审核
                            ]
                        )
                        ->join(
                            'distribution d',
                            'dl.distribution_level_id = d.distribution_level_id and d.delete_time is null'
                        )
                        ->field('d.distribution_id,d.distribution_level_id,dl.level_title')
                        ->find();
                    if ($member_info['distribution'] && !empty($orderLevelId))
                    {
                        foreach ($orderLevelId as $key => $_orderLevelId)
                        {
                            if ($_orderLevelId == $member_info['distribution']['distribution_level_id'])
                            {
                                $member_info['distribution']['level_pos'] = $key + 1;
                            }
                        }
                    } else
                    {
                        $member_info['distribution'] = NULL;
                    }
                }
                //会员购物车数量
                $member_info['cart_count'] = Cart::where([['member_id', '=', $member_info['member_id']]])->sum(
                    'number'
                );
                //会员折扣状态
                $member_info['discount']=discount($member_info['member_id'] ?? 0);
                Session::set('member_info', $member_info);
            }
        }
        //热门搜索
        $Search = Search::field('keyword,type')->order('number', 'desc')->limit(10)->select();
        //公共数据赋值
        View::share(
            [
                'article_classify' => $article_Classify,
                'member_info'      => $member_info ?? '',
                'search'           => $Search ?? '',
                'discount'         => $member_info['discount'] ?? 100,
                'function_status'  => self::$functionStatus,
            ]
        );
    }


    /**
     * 当前平台功能状态
     */
    public static function function_status()
    {
        //将数组下标转化为小写
        $env = array_change_key_case(array_merge(INI_CONFIG,INI_DISTRIBUTION), CASE_LOWER);
        //功能开关默认值为开
        $function_status = array_merge(
            [
                'is_coupon'             => 1,//优惠券
                'is_red_packet'         => 1,//红包
                'is_group'              => 1,//拼团
                'is_cut'                => 1,//砍价
                'is_limit'              => 1,//限时
                'is_sign_in'            => 1,//签到
                'is_recharge'           => 1,//充值
                'is_ranking'            => 1,//排行榜
                'is_brand'              => 1,//品牌甄选
                'is_goods_recommend'    => 1,//好物推荐
                'is_classify_recommend' => 1,//推荐分类
                'is_balance'            => 1,//余额
                'is_master_customer'    => 1,//平台客服
                'is_client_customer'    => 1,//商家客服
                'distribution_status'   => 1,//分销
                'is_wx_login'            => config('user.')['pc']['is_wx_login'],//是否开启微信登录
                'one_or_more'           => self::$oneOrMore,//单店多店模式
            ],$env
        );
        return $function_status;
    }


    /**
     * 店铺有效状态查询sql
     * @param string $alias 店铺表别名
     * @return string
     */
    protected static function store_auth_sql($alias = '')
    {
        $alias = $alias === '' ? '' : $alias . '.';
        return $storeAuthSql = "{$alias}delete_time is null and {$alias}status = 4 and if({$alias}end_time,unix_timestamp({$alias}end_time) > unix_timestamp(),1)";
    }


    /**
     * 根据当前开关追加商品查询条件
     * @param array $where 当前查询条件
     * @param string $alias 商品数据别名
     * @return array 当前查询条件
     */
    protected static function goods_where($where, $alias = '')
    {
        $alias = $alias === '' ? '' : $alias . '.';

        $delete_where = [];
        //判断是单店多店
        if (self::$oneOrMore !== TRUE)
        {
            $_where[] = ["{$alias}store_id", '=', self::$oneOrMore];
            $delete_where[] = "{$alias}store_id";
            $delete_where[] = 'store_id';
        }
        //如果未开启拼团
        if (self::$functionStatus['is_group'] == 0)
        {
            $_where[] = ["{$alias}is_group", '=', 0];
            $delete_where[] = "{$alias}is_group";
            $delete_where[] = 'is_group';
        }
        //如果未开启砍价
        if (self::$functionStatus['is_cut'] == 0)
        {
            $_where[] = ["{$alias}is_bargain", '=', 0];
            $delete_where[] = "{$alias}is_bargain";
            $delete_where[] = 'is_bargain';
        }
        //如果未开启限时抢购
        if (self::$functionStatus['is_limit'] == 0)
        {
            $_where[] = ["{$alias}is_limit", '=', 0];
            $delete_where[] = "{$alias}is_limit";
            $delete_where[] = 'is_limit';
        }
        if (!empty($delete_where))
        {
            //判断当前数组中是否有预先条件
            foreach ($where as &$vv)
            {
                foreach ($delete_where as $key => $v)
                {
                    if (in_array($v, $vv))
                    {
                        unset($value);
                        unset($delete_where[$key]);
                    }
                }
            };
        }
        return array_merge($where, $_where ?? []);
    }
}