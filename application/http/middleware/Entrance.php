<?php

namespace app\http\middleware;

use app\common\service\AuthManage;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Response;
use think\facade\Session;
use think\facade\View;
use think\Request;

class Entrance
{
    use \traits\controller\Jump;
    
    /**
     * 中间件处理入口
     * @param Request $request
     * @param \Closure $closure
     * @return mixed
     */
    public function handle(Request $request, \Closure $closure)
    {
        // 清除boom头
//        ob_end_clean();
        $currentUrl = $request->url();
        $request->mid = '';
        $baseUrl = $request->baseUrl();
        if (stripos($currentUrl, '/v2.0/') === 0 || stripos($currentUrl, '/pc2.0/') === 0 || stripos($currentUrl, '/pc') === 0) {
            $newToken = self::interfaces($request, $currentUrl);
            header('token:' . ($newToken ?? ''));
        } elseif (stripos($currentUrl, '/client/') === 0) {
            if ($baseUrl != '/client/store_capital/check_order') {
                self::client($currentUrl, $baseUrl, $request);
            }
        } elseif (stripos($currentUrl, '/m2.0/') === 0) {
            $newToken = self::merchant($request, $currentUrl);
            header('token:' . ($newToken ?? ''));
        } else {
            if ($baseUrl == '/') {
                $html404 = file_get_contents(Env::get('app_path') . 'common/tpl/404.html');
                \think\Response::create($html404)->send();
            }
            self::master($currentUrl, $baseUrl, $request);
        }
        return $closure($request);
    }
    
    /**
     * 商家端区
     * @param Request $request
     * @param $url
     * @return mixed|null
     */
    public function merchant(Request $request, $url)
    {
        $header = $request->header();
        $except = [
            // 手机号登录
            '/merchant/login/index',
        ];
        $prefix = Config::get('cache.default')['prefix'];
        // if ($identification = $request->get('identification', false)) {
        //     if (!Cache::handler()->set($prefix . $url . '_' . $identification, microtime(), ['NX', 'PX' => 500])) {
        //         $response = Response::create(['code' => -202, 'message' => '触发限频,请重试'], 'json');
        //         $response->send();
        //     };
        // }
        $newToken = null;
        if (array_key_exists('token', $header) && $header['token'] && !in_array($url, $except)) {
            
            $jwtService = app('app\\common\\service\\JwtManage', ['param' => $header['token']]);
            try {
                $tokenArr = $jwtService->parseToken();
                
                // token已过期
                if ($tokenArr['code'] == -1) {
                    // 检测是否在文件token黑名单
                    $list = Cache::store('file')->get('tokenBlackList', []);
                    $str = $tokenArr['data']->jti . '_' . $tokenArr['data']->iat;
                    if (in_array($str, $list)) {
                        if (!$newToken = Cache::store('file')->get('black_token:' . $header['token'])) {
                            $response = Response::create(['code' => -200, 'message' => '登录过期,请重新登录'], 'json');
                            $response->send();
                        }
                    } else {
                        // 颁发新token
                        $newToken = app('app\\common\\service\\JwtManage', ['param' => ['mid' => $tokenArr['data']->mid], 'types' => 2], true)->issueToken();
                        // 加入文件黑名单
                        $jwtService->addBlackList($tokenArr['data']);
                        // 列入余地黑名单,30秒有效期
                        Cache::store('file')->set("black_token:" . $header['token'], $newToken, 30);
                    }
                }
                // $request->mid = $tokenArr['data']->mid;
                // if (!Cache::handler()->set($prefix . $url . '_' . $request->mid, microtime(), ['NX', 'PX' => 500])) {
                //     $response = Response::create(['code' => -202, 'message' => '触发限频,请重试'], 'json');
                //     $response->send();
                // };
            } catch (\Exception $e) {
                // token处理失败
                return '500';
            }
        }
        return $newToken;
    }
    
    
    /**
     * 接口区
     * @param Request $request
     * @param $url
     * @return mixed|null
     */
    public function interfaces(Request $request, $url)
    {
        $header = $request->header();
        $except = [
            // app微信注册登录
            '/v2.0/applet_my/app_login',
            // 手机号登录
            '/v2.0/login/index',
            // 手机短信登录
            '/v2.0/login/sms',
            // 小程序登录
            '/v2.0/applet_my/login',
            // 微信支付回调
            '/v2.0/wx/paidNotify',
            // 发送短信
            '/v2.0/sms/send',
            // 验证短信
            '/v2.0/sms/checkCodeInvalid'
        ];
        $xp = [
            '/v2.0/mobile_my/set',      // 手机站配置
        ];
        // $prefix = Config::get('cache.default')['prefix'];
        // if ($identification = $request->get('identification', false)) {
        //     if (!Cache::handler()->set($prefix . $url . '_' . $identification, microtime(), ['NX', 'PX' => 500])) {
        //         $response = Response::create(['code' => -202, 'message' => '触发限频,请重试'], 'json');
        //         $response->send();
        //     };
        // }
        $newToken = null;
        if (array_key_exists('token', $header) && $header['token'] && !in_array($url, $except)) {
            $jwtService = app('app\\common\\service\\JwtManage', ['param' => $header['token']]);
            try {
                $tokenArr = $jwtService->parseToken();
                // token已过期
                if ($tokenArr['code'] == -1) {
                    // 检测是否在文件token黑名单
                    $list = Cache::store('file')->get('tokenBlackList', []);
                    $str = $tokenArr['data']->jti . '_' . $tokenArr['data']->iat;
                    if (in_array($str, $list)) {
                        if (!$newToken = Cache::store('file')->get('black_token:' . $header['token'])) {
                            $response = Response::create(['code' => -200, 'message' => '登录过期,请重新登录'], 'json');
                            $response->send();
                        }
                    } else {
                        // 颁发新token
                        $newToken = app('app\\common\\service\\JwtManage', [
                            'param' => [
                                'mid' => $tokenArr['data']->mid,
                                'dev_type' => $tokenArr['data']->dev_type,
                            ],
                        ], true)->issueToken();
                        // 加入文件黑名单
                        $jwtService->addBlackList($tokenArr['data']);
                        // 列入余地黑名单,30秒有效期
                        Cache::store('file')->set("black_token:" . $header['token'], $newToken, 30);
                    }
                }
                $request->mid = $tokenArr['data']->mid;
                $request->dev_type = $tokenArr['data']->dev_type;
                // if (!in_array($url, $xp) && !array_key_exists('android', $header)) {
                //     if (!Cache::handler()->set($prefix . $url . '_' . $request->mid, microtime(), ['NX', 'PX' => 500])) {
                //         $response = Response::create(['code' => -202, 'message' => '触发限频,请重试'], 'json');
                //         $response->send();
                //     }
                // };
            } catch (\Exception $e) {
                // token处理失败
                return '500';
            }
        }
        return $newToken;
    }
    
    /**
     * 后台区
     * @param $currentUrl string 当前访问链接(带参数)
     * @param $baseUrl string 当前访问链接(不带参数)
     * @param Request $request
     * @return \think\response\Json
     */
    private function master($currentUrl, $baseUrl, Request $request)
    {
        // 获取 link.php 里面的配置 由于此时 TP 并没有初始化 自定义的配置,所以只能通过 require 的方式 获取 配置
        $_linkConfig = require '../config/link.php';
        // 拼接出 登录的后缀
        $_loginUri = '/' . $_linkConfig['master_suffix'];
        $_allowedUri = [
            $_loginUri,        //  根路径 也是登录路径
//            '/login/index',
            '/we_chat/api',
            '/order/dada_callback'
        ];
        if (!in_array($baseUrl, $_allowedUri)) {
            if (!$request->loginId = Session::get('manage_id')) {
                if ($request->isAjax()) {
                    return json(['code' => -1000, 'message' => '访问无效,请重新登录']);
                }
                $this->error('访问无效,请重新登录', $_loginUri);
            }
            $request->authGroupId = Session::get('auth_group_id');
            $init = ['manage_id' => $request->loginId, 'user_group_id' => $request->authGroupId, 'type' => 'master'];
            //XY轴导航
            $AuthNav = app('app\common\service\AuthNav', $init);
            $systemView = app('think\facade\View');
            $navArr = $AuthNav->getNavHtml();
//            halt($navArr);
            $systemView::share(['X_nav' => $navArr['x'], 'Y_nav' => $navArr['y'], 'Y_data_param' => $navArr['data_param']]);
//            检测用户权限
            $AuthManage = app('app\common\service\AuthManage', $init);
            if ($baseUrl != '/index/index') {
                $check = $AuthManage->checkAuth($baseUrl);
                if (!$check) {
                    if ($request->isAjax()) return json(['code' => 403, 'message' => config('message.')[403]]);
                    $this->error(config('message.')[403]);
                }
            }
            //面包屑
            self::breadcrumb($currentUrl, 'flatMaster_breadcrumb');
        }
    }
    
    
    /**
     * 商家后台区
     * @param $currentUrl
     * @param $baseUrl
     * @param Request $request
     * @return \think\response\Json
     */
    private function client($currentUrl, $baseUrl, Request $request)
    {
        $_allowedUri = [
            '/client/login/index',
            '/client/order/dada_callback'
        ];
        if (!in_array(strtolower($currentUrl), $_allowedUri)) {
            if (!$request->loginId = Session::get('client_member_id')) {
                if ($request->isAjax()) {
                    return json(['code' => -1000, 'message' => '访问无效,请重新登录']);
                }
                $this->error("访问无效,请重新登录", '/client/login/index');
            }
            $init = ['manage_id' => $request->loginId, 'user_group_id' => Session::get('client_store_id')];
            $AuthNav = app('app\common\service\AuthNav', $init);
            $systemView = app('think\facade\View');
            $navArr = $AuthNav->getClientNavHtml();
            $systemView::share(['X_nav' => $navArr['x'], 'Y_nav' => $navArr['y'], 'Y_data_param' => $navArr['data_param']]);
            self::breadcrumb($currentUrl, 'flatClient_breadcrumb');
        }
    }
    
    
    /**
     * 面包屑整理
     * @param $currentUrl // 当前路径
     * @param string $type // 类别
     */
    private function breadcrumb($currentUrl, $type = 'flatMaster_breadcrumb')
    {
        $AuthManage = new AuthManage(NULL, NULL, $type);
        $breadcrumb = $AuthManage->breadcrumb($type);
        $breadcrumbArr = $breadcrumbTitleArr = [];
        if ($currentUrl != '/index/index' && $breadcrumb) {
            
            foreach ($breadcrumb as $key => $item) {
                if (empty($breadcrumbArr)) {
                    if (stripos($currentUrl, $item['url']) && $key != '999')
                        $breadcrumbArr[] = $item;
                } else {
                    if ($key == current($breadcrumbArr)['pid']) {
                        $breadcrumbArr[] = $item;
                        next($breadcrumbArr);
                    }
                }
            }
            if (count($breadcrumbArr)) {
                $breadcrumbTitleArr = array_column($breadcrumbArr, 'title');
                $breadcrumbTitleArr = array_reverse($breadcrumbTitleArr);
                Cache::set('flatMaster_breadcrumb_record', implode(' / ', $breadcrumbTitleArr));
            }
        }
        
        View::share(['breadcrumbArr' => $breadcrumbTitleArr]);
    }
    
    /**
     * 解析访问token
     * @param $token
     * @return mixed
     */
    private function jwt($token)
    {
        $JwtManage = app('app\common\service\JwtManage', ['param' => $token]);
        return $JwtManage->parseToken();
    }
    
    /**
     * 生成随机字符串
     *
     * @param int $len 长度
     *
     * @return string 随机字符串
     */
    protected function rand_string($len = 8)
    {
        return substr(str_shuffle('QWERTYUIOPASDFGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm0123456789'), 0, $len);
    }
}