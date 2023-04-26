<?php
declare(strict_types = 1);
namespace app\master\controller;

use app\common\model\Manage;
use mrmiao\encryption\RSACrypt;
use think\Controller;
use think\facade\Cookie;
use think\facade\Request;
use think\facade\Session;

/**
 * 管理后台登录
 * Class Login
 * @package app\master\controller
 */
class Login extends Controller
{

    /**
     * 后台登录展示与验证
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Manage $manage
     * @return mixed
     */
    public function index(Request $request, RSACrypt $crypt, Manage $manage)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $manage->valid($param, 'login');
                $res = $manage->login($param);

                return $res;
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => $e->getMessage()], true);
            }
        }
        return $this->fetch('');
    }

    /**
     * 退出登录
     * @param Request $request
     * @param RSACrypt $crypt
     * @return array|mixed
     */
    public function outLogin(Request $request, RSACrypt $crypt)
    {
        if ($request::isPost()) {
            try {
                if (Session::delete('manage_id')) {
                    Session::delete('manageName');
                    Session::delete('manageAvatar');
                    Session::delete('auth_group_id');
                    Session::delete('manageAuthGroupTitle');
                }
                return ['code' => 0, 'message' => '退出成功', 'url' => '/login/index'];
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'msg' => $e->getMessage()], true);
            }
        }
    }

    /**
     * 管理后台MISS路由地址
     * @param Request $request
     */
    public function miss(Request $request)
    {
        //当前访问的链接
        $url = $request::url(true);
        $this->error('您查看的页面不存在');
    }
}