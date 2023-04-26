<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;

class Sms extends BaseController
{
    /**
     * 短信类型 1阿里 2腾讯 3华为
     * @var int
     */
    private $smsType = 1;
    /**
     * 短信过期时间
     * @var int
     */
    private $expire = 10 * 60;
    /**
     * 是否开启验证码验证 0开 1关
     */
    const OPEN = 0;
    
    /**
     * 发送短信
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \Exception
     */
    public function send(RSACrypt $crypt)
    {
        $param = $crypt->request();
        // 检测验证码60秒内是否重复发送
        $code = Cache::get("SMS_{$param['phone']}_{$param['type']}", "");
        if ($code) {
            $codeArr = explode("_", $code);
            if (time() - $codeArr[1] < 60) {
                exception('60秒钟内请勿重新获取验证码');
            }
        }
        // 根据不同场景,验证发送短信验证码手机号的合法性
        self::checkPhoneInvalid($param);
        $code = mt_rand(100000, 999999);
        $init = [
            'smsType' => $this->smsType,
            'param' => [['code' => $code], ['code' => $code], json_encode([$code])][$this->smsType - 1],
            'phone' => $param['phone'],
            'type' => $param['type'],
        ];
        $smsManage = app('app\\common\\sms\\SmsManage', $init);
        $ret = $smsManage->sendSms();
        if ($ret['code'] !== 0) {
            return $crypt->response($ret, true);
        }
        //设置缓存标识
        self::setCache($param['phone'], $param['type'], $code);
        return $crypt->response([
            'code' => 0,
            'message' => '发送成功',
        ], true);
    }
    
    /**
     * 验证验证码是否合法(单接口)
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \Exception
     */
    public function checkCodeInvalid(RSACrypt $crypt)
    {
        $param = $crypt->request();
        // 获取验证码并验证合法性
        self::getCache($param['phone'], $param['type'], $param['code']);
        return $crypt->response([
            'code' => 0,
            'message' => '验证成功',
        ], true);
    }
    
    /**
     * 验证发送验证码手机号是否合法性
     * @param $param
     * @throws \Exception
     */
    protected function checkPhoneInvalid($param)
    {
        $memberModel = app('app\\common\\model\\Member');
        $where = [['phone', '=', $param['phone']]];
        if (array_key_exists('union_id', $param) && $param['union_id']) {
            $where = [['wechat_open_id', '=', $param['union_id']]];
        }
        if (array_key_exists('qq_open_id', $param) && $param['qq_open_id']) {
            $where = [['qq_open_id', '=', $param['qq_open_id']]];
        }
        $info = $memberModel
            ->where($where)
            ->field('phone,qq_open_id,wechat_open_id')
            ->find();
        if (in_array($param['type'], [1])) {
            if (!is_null($info)) {
                exception('手机号已注册,请更换号码再试');
            }
        } elseif (in_array($param['type'], [2, 3, 4, 5, 6, 7, 8, 11])) {
            //  忘记密码,找回密码,修改密码,付款,下单,发货,降价提醒,短信登录(都可发短信)
            if (is_null($info)) {
                exception('手机号不存在');
            }
        } elseif (in_array($param['type'], [10]) && !is_null($info) && $param['phone'] == $info->phone) {
            // 微信或QQ绑定手机号
            if (array_key_exists('qq_open_id', $param) && $param['qq_open_id']) {
                exception('该手机号已绑定此QQ');
            }
            if (array_key_exists('union_id', $param) && $param['union_id']) {
                exception('该手机号已绑定此微信');
            }
        }
    }
    
    /**
     * 设置缓存(只限单条发送)
     * @param $phone string 接收号码
     * @param $type  string 使用场景
     * @param $code  string 验证码
     * @return mixed|null
     */
    private function setCache($phone, $type, $code)
    {
        $cur_time = time();
        return $phone && is_string($phone) ?
            Cache::set("SMS_{$phone}_{$type}", "{$code}_{$cur_time}", $this->expire)
            : false;
    }
    
    /**
     * 读取短信缓存并验证
     * @param $phone string 接收号码
     * @param $type  string 使用场景 1注册 2忘记密码 3找回密码 4修改密码 5付款 6下单 7发货 8降价提醒 9短信登录 10绑定手机号 11商家入驻通知
     * @param $code  string 需要验证的验证码
     * @return bool
     * @throws \Exception
     */
    public static function getCache($phone, $type, $code)
    {
        $get = $phone ? Cache::get("SMS_{$phone}_{$type}", '') : '';
        if (self::OPEN || !config('user.sms_verify')) {
            Cache::rm("SMS_{$phone}_{$type}");
            return true;
        }
        if (!$get) {
            exception('验证码不正确');
        }
        $arr = explode('_', $get);
        if ($arr[0] != $code) {
            exception('验证码不正确');
        }
        Cache::rm("SMS_{$phone}_{$type}");
        return true;
    }
    
}