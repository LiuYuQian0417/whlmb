<?php
declare(strict_types=1);

namespace app\common\sms;

use app\common\model\SmsTemplate;
use think\Exception;
use think\facade\Cache;
use think\facade\Config;

/**
 * 短信管理
 * Class SmsManage
 * @package app\common\sms
 */
class SmsManage
{
    /**
     * 短信类别
     * @var
     */
    private $smsType;
    /**
     * 使用场景[注册,找回密码..]
     * @var string
     */
    private $type = '1';
    /**
     * 短信实例
     * @var QCloud
     */
    private $instance;
    /**
     * 接收号码
     * @var
     */
    private $phone;
    
    /**
     * SmsManage constructor.
     * @param int $smsType
     * @param string $phone
     * @param array $param
     * @param int $type
     * @throws Exception
     */
    public function __construct($smsType = 1, $phone = '', $param = [], $type = 1)
    {
        $this->smsType = $smsType;
        $this->type = $type;
        $this->phone = $phone;
        //获取模板信息
        $info = self::getTempInfo();

        if (is_null($info)) {
            throw new Exception('短信模板缓存异常,请检查模板类型是否选择正确');
        }
        switch ($smsType) {
            case 1:
                $this->instance = new ALi($phone, $param, $info['temp_id']);
                break;
            case 2:
                $this->instance = new QCloud($phone, $param, $info['temp_id']);
                break;
            default :
                $this->instance = new QCloud($phone, $param, $info['temp_id']);
                break;
        }
    }
    
    /**
     * 返回模板信息(ID,内容)
     * @return mixed|null |null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getTempInfo()
    {
        // 新版获取模板
        $_templateList = Config::get('sms_template.', NULL);

        $_templateList = isset($_templateList[$this->smsType])?$_templateList[$this->smsType]:$_templateList[1];

        return $_templateList ? $_templateList[isset($_templateList[$this->type]) && $_templateList[$this->type]['temp_id'] ? $this->type : 0] : NULL;

        // 旧版获取模板
//        $_temCache = Cache::get('flatMaster_smsTemp_' . $this->smsType);
//        if (!$_temCache) {
//            $_temCache = [];
//            $smsTemp = (new SmsTemplate());
//            $tempCacheArr = $smsTemp
//                ->where(['type' => $this->smsType, 'status' => 1])
//                ->field('scene,content,temp_id')
//                ->select();
//
//            if (!$tempCacheArr->isEmpty()) {
//                foreach ($tempCacheArr as $item) {
//                    $_temCache[$item['scene']] = [
//                        'content' => $item['content'],
//                        'temp_id' => $item['temp_id'],
//                    ];
//                }
//                if ($_temCache) {
//                    Cache::set('flatMaster_smsTemp_' . $this->smsType, $_temCache);
//                }
//            }
//        }
//        return $_temCache ? $_temCache[(isset($_temCache[$this->type]) ? $this->type : 0)] : null;
    }
    
    /**
     * 发送短信
     * @return mixed|string
     */
    public function sendSms()
    {
        $ret = $this->instance->sendSms();
        $sucRes = $errRes = [];
        switch ($this->smsType) {
            case 1:
                if ($ret->Code === 'OK') {
                    $sucRes = ['code' => 0, 'message' => '发送成功'];
                }
                // 分钟级流控
                if ($ret->Code == 'isv.BUSINESS_LIMIT_CONTROL') {
                    $ret->Message = (strstr($ret->Message, '分钟') ? '60秒' : '1小时') . '内请勿重复获取验证码';
                }
                $errRes = ['code' => -1, 'message' => $ret->Message];
                break;
            case 2:
                if ($ret['result'] === 0) $sucRes = ['code' => 0, 'message' => '发送成功'];
                $errRes = ['code' => -1, 'message' => $ret['errmsg']];
                break;
            default:
                if ($ret['result'] === 0) $sucRes = ['code' => 0, 'message' => '发送成功'];
                $errRes = ['code' => -1, 'message' => $ret['errmsg']];
                break;
        }
        return $sucRes ?: $errRes;
    }
}