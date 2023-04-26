<?php

namespace app\merchant\controller\auth;

use think\Controller;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;


class Sms extends Controller
{
    /**
     * 短信类型
     * @var int
     */
    private $smsType = 1;
    /**
     * 短信过期时间
     * @var int
     */
    private $expire = 600;
    /**
     * 是否开启验证码验证 0开 1关
     */
    const OPEN = 0;
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.sms');
        $this->smsType = Env::get('base.type');
    }
    
    /**
     * 发送短信
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function send(RSACrypt $crypt)
    {
        $param = $crypt->request();
//        halt(Cache::get('SMS_m_' . $param['phone'] . '_' . $param['type'], ''));
        // 检测验证码60秒内是否重复发送
        if (Cache::get('SMS_m_' . $param['phone'] . '_' . $param['type'], '')) {
            return $crypt->response([
                'code' => -1,
                'message' => '60秒钟内请勿重新获取验证码',
            ], true);
        }
        // 根据不同场景,验证发送短信验证码手机号的合法性
        $validRet = self::checkPhoneInvalid($param);
        if ($validRet['code'] !== 0) {
            return $crypt->response($validRet, true);
        }
        $code = self::getCode();
        $init = [
            'smsType' => $this->smsType,
            'param' => ['code' => $code],
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
        //短信计数
        $redis = Cache::handler();
        $redis->select(2);
        $prefix = Config::get('cache.default')['prefix'];
        $redis->zIncrby($prefix . 'sms_count_' . $this->smsType . '_' . $param['type'], 1, date('Y-m-d'));
        return $crypt->response([
            'code' => 0,
            'message' => '发送成功',
            'sms_code' => $code,
        ], true);
    }
    
    /**
     * 验证验证码是否合法(单接口)
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function checkCodeInvalid(RSACrypt $crypt)
    {
        $param = $crypt->request();
        // 获取验证码并验证合法性
        $checkRet = self::getCache($param['phone'], $param['type'], $param['code']);
        return $crypt->response($checkRet ?
            [
                'code' => 0,
                'message' => '验证成功',
            ]
            : [
                'code' => -1,
                'message' => '验证码不正确',
            ], true);
    }
    
    /**
     * 验证发送验证码手机号是否合法性
     * @param $param
     * @return array
     */
    protected function checkPhoneInvalid($param)
    {
        $memberModel = app('app\\common\\model\\Member');
        $where = [['phone', '=', $param['phone']]];
        $phoneWhere = [];
        $phone = '';
        if (array_key_exists('wechat_open_id', $param) && $param['wechat_open_id'] !== '') {
            array_push($phoneWhere, ['wechat_open_id', '=', $param['wechat_open_id']]);
        }
//        if (array_key_exists('qq_open_id', $param) && $param['qq_open_id'] !== '') {
//            array_push($phoneWhere, ['qq_open_id', '=', $param['qq_open_id']]);
//        }
        if (!empty($phoneWhere)) {
            $phone = $memberModel
                ->where($phoneWhere)
                ->value('phone');
        }
        $memberId = $memberModel
            ->where($where)
            ->value('member_id');
        $ret = ['code' => 0];
        if (in_array($param['type'], [1])) {
            //  注册(验证手机号唯一)
            if ($memberId) {
                $ret = ['code' => -2, 'message' => '手机号已注册,请更换号码再试'];
            }
        } else if (in_array($param['type'], [2, 3, 4, 5, 6, 7, 8])) {
            //  忘记密码,找回密码,修改密码,付款,下单,发货,降价提醒,短信登录(都可发短信)
            if (!$memberId) {
                $ret = ['code' => -2, 'message' => '手机号不存在'];
            }
        } else if (in_array($param['type'], [10])) {
            // 微信或QQ绑定手机号
            $flag = array_key_exists('qq_open_id', $param) && $param['qq_open_id'] === '' ? 0 : 1;
            if ($param['phone'] == $phone) {
                $ret = [
                    'code' => -3,
                    'message' => '该手机号已绑定此' . ['QQ', '微信'][$flag],
                ];
            }
            if ($phone) {
                $ret = [
                    'code' => -3,
                    'message' => '该微信号已绑定手机,无需绑定',
                ];
            }
        }
        return $ret;
    }
    
    /**
     * 获取短信验证码
     * @return int
     */
    private static function getCode()
    {
        return mt_rand('100000', '999999');
    }
    
    /**
     * 设置缓存(只限单条发送)
     * @param $phone string 接收号码
     * @param $type string 使用场景
     * @param $code string 验证码
     * @return mixed|null
     */
    private function setCache($phone, $type, $code)
    {
        return $phone && is_string($phone) ?
            Cache::set('SMS_m_' . $phone . '_' . $type, $code, $this->expire)
            : null;
    }
    
    /**
     * 读取短信缓存并验证
     * @param $phone string 接收号码
     * @param $type string 使用场景 1注册 2忘记密码 3找回密码 4修改密码 5付款 6下单 7发货 8降价提醒 9短信登录 10绑定手机号 11商家入驻通知
     * @param $code string 需要验证的验证码
     * @return bool
     */
    public function getCache($phone, $type, $code)
    {
        $get = $phone ? Cache::get('SMS_m_' . $phone . '_' . $type) : null;
//         halt(Cache::get('SMS_m_' . $phone . '_' . $type));
        if (!config('user.sms_verify')) {
            Cache::rm('SMS_m_' . $phone . '_' . $type);
            return true;
        }
        return $get == $code ? Cache::rm('SMS_m_' . $phone . '_' . $type) : false;
    }
    
}