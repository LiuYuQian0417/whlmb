<?php
/**
 * Created by PhpStorm.
 * User: Kassy
 * Date: 2019-09-24
 * Time: 9:21
 * Description: 融云token类
 */

namespace app\common\service;

use think\facade\Env;

class IntegratingCloud
{
    private static $key;           // 融云app_key
    private static $secret;        // 融云app_secret

    public function __construct()
    {
        self::$key = config('rongyun.app_key');
        self::$secret = config('rongyun.app_secret');
        require Env::get('extend_path') . 'rongyun/API/RongCloud.php';
    }

    /**
     * 获取融云token
     * @param $uid
     * @param $username
     * @param $avatar
     * @return mixed
     */
    public function getToken($uid, $username, $avatar = '')
    {
        $appKey = self::$key;
        $appSecret = self::$secret;
        $RongCloud = new \RongCloud($appKey, $appSecret);
        $result = $RongCloud->user()->getToken($uid, $username, $avatar);
        $arr_result = json_decode($result, true);
        return $arr_result;
    }
}