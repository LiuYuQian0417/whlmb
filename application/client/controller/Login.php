<?php
// 登录
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\Member as MemberModel;
use mrmiao\encryption\RSACrypt;
use think\Controller;
use think\facade\Cookie;
use think\facade\Request;
use think\facade\Session;

class Login extends Controller
{
    /**
     * 店铺登录
     *
     * @param Request     $request
     * @param MemberModel $member
     * @param RSACrypt    $crypt
     *
     * @return mixed
     */
    public function index(Request $request, MemberModel $member, RSACrypt $crypt)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取数据
                $param = $request::post();

                // 验证是否滑动验证码
                if (!$param['verification'])
                {
                    return ['code' => -1, 'message' => '请滑动验证码'];
                }

                // 验证
                $check = $member->valid($param, 'client');
                if ($check['code'])
                {
                    return $check;
                }

                // 调用后台管理员登录方法
                return $member->clientLogin($param);
            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'msg' => $e->getMessage()], TRUE);
            }

        }

        return $this->fetch(
            '', [

        ]
        );
    }


    /**
     * 注销登录
     *
     * @param Request  $request
     * @param RSACrypt $crypt
     *
     * @return array|mixed
     */
    public function outLogin(Request $request, RSACrypt $crypt)
    {
        if ($request::isPost())
        {
            try
            {
                //清除cookie缓存
                Cookie::clear('client_');
                //清除session缓存
                Session::clear('client_');
                // 清除token
                Cookie::clear('token');
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/login/index'];
            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'msg' => $e->getMessage()], TRUE);
            }
        }
    }
}