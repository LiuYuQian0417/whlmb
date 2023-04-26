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
use app\computer\model\Member as MemberModel;
use think\facade\Cache;


class Forget extends BaseController
{
    /**
     * 是否开启验证码验证 0开 1关
     */
    const OPEN = 0;

    protected $beforeActionList = [
        //检查是否登录
        'check_login'=>['except' => '']
    ] ;

    public function one()
    {
        return  $this->fetch();
    }

    public function two()
    {
        return  $this->fetch();
    }


    /**
     * 忘记密码
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @return mixed
     */
    public function three(Request $request, RSACrypt $crypt, MemberModel $member)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();
                // 验证
                $check = $member->valid($param, 'ForgetPassword');
                if ($check['code']) return $crypt->response($check);

                // 读取个人信息
                $member_id = $member->where('phone', $param['phone'])->value('member_id');

                // 如果不存在
                if (!$member_id) return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-2]], true);

                $old_password = $member->where('phone', $param['phone'])->value('password');
                if ($old_password  == passEnc($param['password'])){
                    return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-4]], true);
                }

                $member->allowField(true)->isUpdate(true)->save($param, ['phone' => $param['phone']]);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
        return $this->fetch();
    }



    public function check(Request $request, RSACrypt $crypt, MemberModel $member)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();

                // 验证
                $check = $member->valid($param, 'check');
                if ($check['code']) return $crypt->response($check);

                // 读取个人信息
                $member_id = $member->where('phone', $param['phone'])->value('member_id');

                // 如果不存在
                if (!$member_id) return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-2]], true);


                return $crypt->response(['code' => 0, 'message' => config('message.')[0][3]], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }

    /**
     * 验证验证码是否合法(单接口)
     * @param Request $request
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function checkCodeInvalid(Request $request, RSACrypt $crypt)
    {
        if ($request::isPost()) {
            try {
                $param = $crypt->request();
                // 获取验证码并验证合法性
                $bool = in_array($param['type'], [1]) ? 0 : 1;
                $checkRet = self::getCache($param['phone'], $param['type'], $param['code'], $bool);
                return $crypt->response($checkRet ?
                                            ['code' => 0, 'message' => config('message.')[0][3],]
                                            : ['code' => -1, 'message' => config('message.')[-1][-3]], true);
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }

    /**
     * 读取短信缓存并验证
     * @param $phone string 接收号码
     * @param $type string 使用场景 1注册 2忘记密码 3找回密码 4修改密码 5付款 6下单 7发货 8降价提醒 9短信登录 10绑定手机号 11商家入驻通知
     * @param $code string 需要验证的验证码
     * @param $bool integer 是否需要删除验证码 1删 0留
     * @return bool
     */
    public function getCache($phone, $type, $code, $bool = 1)
    {
        if (self::OPEN) return true;
        $get = $phone && is_string($phone) ? Cache::get('SMS_' . $phone . '_' . $type) : null;
        return $get == $code ? ($bool ? Cache::rm('SMS_' . $phone . '_' . $type) : true) : false;
    }


}