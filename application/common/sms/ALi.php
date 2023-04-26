<?php
declare(strict_types = 1);
namespace app\common\sms;

use Aliyun\Api\Sms\Request\V20170525\SendBatchSmsRequest;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\Regions\EndpointConfig;
use think\facade\Env;

/**
 * 阿里云短信
 * Class ALi
 * @package app\common\sms
 */
class ALi
{
    /**
     * 应用ID
     * @var
     */
    private $appId;
    /**
     * 应用Key
     * @var
     */
    private $appKey;
    /**
     * 发送短信的号码
     * @var
     */
    private $phone;
    /**
     * 短信签名
     * @var
     */
    private $sign = '';
    /**
     * 模板ID
     * @var
     */
    private $tempId;
    /**
     * 多元参数
     * @var
     */
    private $param;
    /**
     * 实例
     * @var
     */
    private static $acsClient = null;

    public function __construct($phone = '',$param = '',$tempId = 'SMS_137825175')
    {
        // 加载sms配置
        Env::load(Env::get('APP_PATH') . 'common/ini/.sms');
        $this->phone = $phone;
        $this->appId = Env::get('ALI_APPID');
        $this->appKey = Env::get('ALI_APPKEY');
        $this->sign = Env::get('ALI_SIGN');
        $this->tempId = $tempId;
        $this->param = $param;
    }

    /**
     * 请求单例
     * @return mixed
     */
    protected function getAcsClient()
    {
        // 产品名称:云通信流量服务API产品,开发者无需替换
        $product = 'Dysmsapi';
        // 产品域名,开发者无需替换
        $domain = "dysmsapi.aliyuncs.com";
        // 暂时不支持多Region
        $region = "cn-hangzhou";
        // 服务结点
        $endPointName = "cn-hangzhou";
        if(static::$acsClient == null) {
            //初始化acsClient,暂不支持region化
            $profile = DefaultProfile::getProfile($region, $this->appId, $this->appKey);
            //手动加载endpoint
            EndpointConfig::load();
            // 增加服务结点
            DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);

            // 初始化AcsClient用于发起请求
            static::$acsClient = new DefaultAcsClient($profile);
        }
        return static::$acsClient;
    }

    /**
     * 发送短信(单/群发)
     * 单次请求批量上限为100
     * @return mixed
     */
    public function sendSms()
    {
        if (is_array($this->phone)){
            $req = new SendBatchSmsRequest();
            // 设置群发接收号码
            $req->setPhoneNumberJson(json_encode($this->phone,JSON_UNESCAPED_UNICODE));
            // 批量设置签名
            $signArr = [];
            for ($x = 0;$x < count($this->phone); $x++){
                array_push($signArr,$this->sign);
            }
            $req->setSignNameJson(json_encode($signArr,JSON_UNESCAPED_UNICODE));
            // 批量设置模板参数
            $req->setTemplateParamJson(json_encode($this->param,JSON_UNESCAPED_UNICODE));
        }else{
            $req = new SendSmsRequest();
            // 设置单发接收号码
            $req->setPhoneNumbers($this->phone);
            // 设置签名
            $req->setSignName($this->sign);
            // 设置模板参数
            $req->setTemplateParam(json_encode($this->param,JSON_UNESCAPED_UNICODE));
        }
        // 设置模板ID
        $req->setTemplateCode($this->tempId);
        // 发起请求

        $aceRes = static::getAcsClient()->getAcsResponse($req);
        return $aceRes;
    }


}