<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\Member;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Hook;
use think\facade\Request;

/**
 * 会员登录
 * Class Login
 * @package app\interfaces\controller\auth
 */
class Login extends BaseController
{
    /**
     * 登录
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Member $member)
    {
        $param = $crypt->request();
        Db::startTrans();
        $ret = $member->login([
            'phone' => $param['phone'],
            'password' => $param['password'],
            'dev_type' => $param['dev_type'],
        ]);
        Db::commit();
        return $crypt->response($ret, true);
    }
    
    /**
     * 短信验证码登录
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function sms(RSACrypt $crypt, Member $member)
    {
        $param = $crypt->request();
        //检测短信
        $sms = app('app\\interfaces\\controller\\auth\\Sms');
        $checkCode = $sms->getCache($param['phone'], 9, $param['code']);
        // 验证码不正确
        if (!$checkCode) {
            return $crypt->response([
                'code' => -1,
                'message' => '验证码不正确',
            ], true);
        }
        Db::startTrans();
        $distribution_superior = array_key_exists('superior', $param) && $param['superior'] ? $param['superior'] : 0;
        $ret = $member->login([
            'phone' => $param['phone'],
            'distribution_superior' => $distribution_superior,
            'dev_type' => $param['dev_type'],
            'subscribe_time' => null,
            'micro_open_id' => null,
            'web_open_id' => null,
        ], 2);
        Db::commit();
        return $crypt->response($ret, true);
    }
    
}