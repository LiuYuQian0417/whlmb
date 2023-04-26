<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-02-19
 * Time: 16:22
 */

namespace app\interfaces\controller\test;


use EasyWeChat\Factory;
use think\Controller;
use think\Exception;
use think\facade\Cache;

class Index extends Controller
{

    /**
     * app实例
     * @var \EasyWeChat\OfficialAccount\Application
     */
    public $app = NULL;

    function initialize()
    {

        $_config = [
            'app_id' => 'wxa6da69186270b51f',
            'secret' => '8de63f1ad88d7649efedc82ff577a900',

            'oauth'         => [
                'scopes'   => ['snsapi_userinfo'],
                'callback' => '/v2.0/test/auth_req',
            ],
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',
        ];

        $this->app = Factory::officialAccount($_config);
    }

    function auth()
    {
        $_auth = $this->app->oauth->redirect();

        $_targetUrl = $_auth->getTargetUrl();

        $_referer = parse_url($this->request->header("Referer"));
        parse_str(parse_url($_targetUrl)['query'], $query);
        Cache::set(
            "mobile_auth_share_link_{$query['state']}",
            "{$_referer['scheme']}://{$_referer['host']}{$this->request->get('callback')}"
        );

        header("location: {$_targetUrl}");
    }

    function authReq()
    {
        $_get = $this->request->get();

        $_targetUrl = Cache::pull("mobile_auth_share_link_{$_get['state']}");

        header("location: {$_targetUrl}?code={$_get['code']}");
    }

}