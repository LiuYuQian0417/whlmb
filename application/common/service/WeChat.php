<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Cache;

class WeChat
{

    private $appId;
    private $appSecret;

    public function __construct($appId, $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
    }

    public function getSignPackage()
    {
        $jsapiTicket = $this->getJsApiTicket();
        $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = 'jsapi_ticket=' . $jsapiTicket . '&noncestr=' . $nonceStr . '&timestamp=' . $timestamp . '&url=' . $url;
        $signature = sha1($string);

        $signPackage = [
            "appId"     => $this->appId,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        ];
        return $signPackage;
    }

    private function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getJsApiTicket()
    {

        if (empty(Cache::get('wx_ticket'))) {
            $url2 = sprintf("https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=%s&type=jsapi", $this->getAccessToken());
            $res = $this->httpGet($url2);
            $res = json_decode($res, true);
            $ticket = $res['ticket'];
            if ($ticket) {
                Cache::set('wx_ticket', $ticket, 7000);
            }
        } else {
            $ticket = Cache::get('wx_ticket');
        }

        return $ticket;
    }

    private function getAccessToken()
    {
        if (empty(Cache::get('access_token'))) {
            $res = json_decode($this->httpGet('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appId . '&secret=' . $this->appSecret), true);
            $access_token = $res['access_token'];
            if ($access_token) {
                Cache::set('access_token', $access_token, 3600);
            }
        } else {
            $access_token = Cache::get('access_token');
        }
        return $access_token;
    }

    private function httpGet($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }
}