<?php
declare(strict_types=1);

namespace app\computer\controller\auth;


use app\computer\controller\BaseController;
use app\computer\model\Member;
use app\common\service\Inform;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;
use app\computer\model\Article;
use app\computer\model\Coupon;
use app\computer\model\MemberCoupon;
use think\facade\Session;


class Register extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }


//    protected $beforeActionList = [
//        //检查是否登录
//        'check_login'=>['except' => 'three']
//    ];

    public function one()
    {

        return  $this->fetch('one');
        
    }

    /**
     * 注册 -- 手机号
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Inform $inform
     * @return mixed
     */
    public function tel(Request $request, RSACrypt $crypt, Member $member, Inform $inform)
    {
        if ($request::isPost()) {
            // try {
                $param = $crypt->request();
                Db::startTrans();
//                Confirm
                // $check = $member->valid($param, 'Confirm');
                // // dump($check);
                // if ($check['code']) {
                //     return $crypt->response($check, true);
                // }

                if(!preg_match('/^(?![^a-zA-Z]+$)(?!\D+$)/', $param['password'])){
                    return $crypt->response(['code' => -1, 'message' =>'必须为数字字母组合'], true);
                }

                if($param['password'] != $param['confirm_password']){
                     return $crypt->response(['code' => -1, 'message' =>'两次密码不一致'], true);
                }
//                 验证码二次验证
                $smsApi = app('app\\interfaces\\controller\\auth\\Sms');
                $codeCheck = $smsApi->getCache($param['phone'], 1, $param['code']);
                if (!$codeCheck) {
                    return $crypt->response(['code' => -1, 'message' => config('message.')[-1][-3]], true);
                }
                // 积分、成长值设置
//                $param['pay_points'] = Env::get('integral_phone');
                $param['distribution_superior'] = array_key_exists('superior', $param) && $param['superior'] ? $param['superior'] : 0;
                // 进行注册
                // halt($param);
                $find = self::common($member, $param, $inform, 0);
                // halt( $find);
                // 注册即成为分销商检测
                $regToBe = [
                    'member_id' => $find['member_id'],
                    'nickname' => $find['nickname'],
                    'phone' => $find['phone'],
                    'sex' => 0,     //默认女
                    'web_open_id' => $find['web_open_id'],
                    'subscribe_time' => $find['subscribe_time'],
                    'micro_open_id' => $find['micro_open_id'],
                    'distribution_superior' => $param['distribution_superior'],
                    'bType' => 2,   //成为分销商途径注册自动成为分销商
                    'text' => 2,    //注册即成为分销商
                ];
                $find['distribution_id'] = '';
                $rb = Hook::exec(['app\\interfaces\\behavior\\Distribution', 'regToBe'], $regToBe);
                if ($rb) {
                    $find['distribution_id'] = $rb['distribution_id'];
                }
                Db::commit();
                Session::set('member_info',$find);
                //返回token
                (new My())->get_token();
                // 注册成功返回用户信息
                return $crypt->response(['code' => 0, 'message' => config('message.')[0][7], 'url' => 'three', 'data' => $find], true);
            // } catch (\Exception $e) {
            //     Db::rollback();
            //     return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            // }
        }
        return $this->fetch();
    }


    public function three()
    {

        return $this->fetch();
    }


    /**
     * 赠送优惠券
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Coupon $coupon
     * @param Article $article
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function coupon_list(Request $request, RSACrypt $crypt, Coupon $coupon, Article $article)
    {
        $result = $coupon
            ->where([
//                                ['member_id', '=',  $param['member_id']],
                        ['is_gift', '=', 1],
                        ['status', '=', 1],
                        ['receive_start_time', '<=', date('Y-m-d')],
                        ['receive_end_time', '>=', date('Y-m-d')]
                    ])
            ->field('actual_price,full_subtraction_price,total_num,exchange_num,file')
            ->order('actual_price', 'asc')
            ->select();

        $content = $article->where('article_id', 18)->value('content');

        return $this->fetch('',['code' => 0, 'result' => $result, 'content' => str_replace('class="tools"', 'class="tools" hidden', $content)]);

    }

    /**
     * 领取优惠券
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Coupon $coupon
     * @param Member $member
     * @param MemberCoupon $memberCoupon
     * @param Inform $inform
     * @return mixed
     */
    public function get_coupon(Request $request, RSACrypt $crypt, Coupon $coupon, Member $member, MemberCoupon $memberCoupon, Inform $inform)
    {
        if ($request::isPost()) {
            try {

                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;

                $is_gift = $member->where('member_id', $param['member_id'])->value('is_gift');

                if ($is_gift == 1) return $crypt->response(['code' => -100, 'message' => config('message.')[-13][-1]]);

                $result = $coupon
                    ->where([
                                ['is_gift', '=', 1],
                                ['status', '=', 1],
                                ['receive_start_time', '<=', date('Y-m-d')],
                                ['receive_end_time', '>=', date('Y-m-d')]
                            ])
                    ->field('coupon_id,actual_price,full_subtraction_price,title,type,end_time,classify_str')
                    ->order('actual_price', 'asc')
                    ->select()
                    ->toArray();

                // 新数组
                $array = [];

                foreach ($result as $key => $value) {

                    $array[$key] = $value;

                    $array[$key]['member_id'] = $param['member_id'];

                    $array[$key]['goods_classify_id'] = $value['classify_str'];

                    $array[$key]['start_time'] = date('Y-m-d');

                    $array[$key]['store_id'] = $value['classify_str'];
//                    $array[$key]['web_open_id'] = '';
                    // 推送
//                    dump($array[$key]);
                    $inform->coupon_inform(0, 'coupon', $param, 0,  $value['coupon_id']);

                }
//                halt($result);
                // 批量插入
                $memberCoupon->allowField(true)->saveAll($array);

                // 改变状态
                $member->save(['is_gift' => 1], ['member_id' => $param['member_id']]);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 公共注册方法
     * @param Member $memberModel
     * @param $data
     * @param Inform $inform
     * @param int $type
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common(Member $memberModel, $data, Inform $inform, $type = 0)
    {

        // 注册用户默认生成表
        $member = default_generated($memberModel, $data, isset($data['token']['member_id']) ? $data['token']['member_id'] : '', $type);

        // 积分推送
        $inform->integral_inform(0, 'integral', Env::get('integral_phone'), $member, 0);

        return $member;
    }


}