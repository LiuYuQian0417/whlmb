<?php
declare(strict_types = 1);

namespace app\common\push;


use Aliyun\Core\DefaultAcsClient;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\Regions\EndpointConfig;
use Push\Request\V20160801\BindTagRequest;
use Push\Request\V20160801\PushRequest;
use think\Exception;

class AliPush
{
    /**
     * 阿里推送配置
     * @var mixed
     */
    protected static $set;
    protected $type;
    protected static $instance = null;
    protected static $req = null;
    // DEVICE：是设备0，ACCOUNT：是账号1，ALIAS：是别名2
    const KT = 0;
    
    public function __construct($type = 0)
    {
        if (!self::$set = config('push.ali')) {
            throw new Exception('阿里推送配置失败');
        };
        $this->type = $type;
        self::createNew();
    }
    
    /**
     * 创建请求实例
     */
    public function createNew()
    {
        if (is_null(self::$instance)) {
            EndpointConfig::load();
            $iClientProfile = DefaultProfile::getProfile(self::$set['regionId'], self::$set['accessKeyId'], self::$set['accessKeySecret']);
            self::$instance = new DefaultAcsClient($iClientProfile);
        }
        // $type 0推送消息实例 1绑定标签实例 2解绑标签实例
        switch ($this->type) {
            case 1:
                self::$req = new BindTagRequest();
                // DEVICE：是设备，ACCOUNT：是账号，ALIAS：是别名
                self::$req->setKeyType(['DEVICE', 'ACCOUNT', 'ALIAS'][self::KT]);
                break;
            case 2:
                self::$req = new UnbindTagRequest();
                // DEVICE：是设备，ACCOUNT：是账号，ALIAS：是别名
                self::$req->setKeyType(['DEVICE', 'ACCOUNT', 'ALIAS'][self::KT]);
                break;
            default:
                self::$req = new PushRequest();
                break;
        }
    }
    
    /**
     * 绑定[解绑]标签(组)
     * @param $deviceId
     * @param $tag
     */
    public function switchBindTag($deviceId, $tag)
    {
        if ($deviceId != '' && $tag != '') {
            self::$req->setAppKey(self::$set['androidAppKey']);
            self::$req->setAppKey(self::$set['iosAppKey']);
            self::$req->setClientKey($deviceId);
            self::$req->setTagName($tag);
        }
    }
    
    /**
     * 设置接收者
     * @param $msg
     */
    public function setAudience($msg)
    {
        // 推送目标: DEVICE:推送给设备; ACCOUNT:推送给指定帐号,TAG:推送给自定义标签; ALL: 推送给全部
        self::$req->setTarget("ACCOUNT");
        // 根据Target来设定，如Target=device, 则对应的值为 设备id1,设备id2. 多个值使用逗号分隔.(帐号与设备有一次最多100个的限制)
        self::$req->setTargetValue($msg['account']);
        // 设备类型 ANDROID iOS ALL.
        self::$req->setDeviceType("ALL");
        // 消息类型 MESSAGE NOTICE
        self::$req->setPushType("NOTICE");
        self::$req->setTitle($msg['title']); // 消息的标题
        self::$req->setBody($msg['body']); // 消息的内容
        // 设置该参数后启动小米托管弹窗功能, 此处指定通知点击后跳转的Activity
        //（托管弹窗的前提条件：1. 集成小米辅助通道；2. StoreOffline参数设为true
        self::$req->setAndroidXiaoMiActivity("com.ali.demo.MiActivity");
        self::$req->setAndroidXiaoMiNotifyTitle($msg['title']);
        self::$req->setAndroidXiaoMiNotifyBody($msg['body']);
    }
    
    /**
     * 发送消息
     * @param $msg
     * @param bool $type 发送消息 true , 其他行为 false
     * @return mixed
     */
    public function toSend($msg, $type = true)
    {
        $ret = [];
        if ($msg && $type) {
            self::setAudience($msg);
            // 配置安卓
            self::$req->setAppKey(self::$set['androidAppKey']);
            // 通知的提醒方式 "VIBRATE" : 震动 "SOUND" : 声音 "BOTH" : 声音和震动 NONE : 静音
            self::$req->setAndroidNotifyType("BOTH");
            // 通知栏自定义样式0-100
            self::$req->setAndroidNotificationBarType(1);
            // 点击通知后动作 "APPLICATION" : 打开应用 "ACTIVITY" : 打开AndroidActivity "URL" : 打开URL "NONE" : 无跳转
            self::$req->setAndroidOpenType("ACTIVITY");
            // Android收到推送后打开对应的url,仅当AndroidOpenType="URL"有效
            self::$req->setAndroidOpenUrl("NONE");
            // 设定通知打开的activity，仅当AndroidOpenType="Activity"有效
            self::$req->setAndroidActivity("com.lc.distribution.activity.MessageActivity");
            // Android通知音乐
            self::$req->setAndroidMusic("default");
            // Android渠道id,前后端协议值
            self::$req->setAndroidNotificationChannel("1");
            // 设定android类型设备通知的扩展属性
            self::$req->setAndroidExtParameters(json_encode($msg['extra']));
            // $pushTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+3 second'));//延迟3秒发送
            // self::$req->setPushTime($pushTime);
            $expireTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 day'));//设置失效时间为1天
            self::$req->setExpireTime($expireTime);
            self::$req->setStoreOffline("true"); // 离线消息是否保存,若保存, 在推送时候，用户即使不在线，下一次上线则会收到
            $ret[] = self::$instance->getAcsResponse(self::$req);
            // 配置ios
            self::$req->setAppKey(self::$set['iosAppKey']);
            // iOS应用图标右上角角标
            self::$req->setiOSBadge(1);
            // 是否开启静默通知
            self::$req->setiOSSilentNotification("false");
            // iOS通知声音
            self::$req->setiOSMusic("default");
            // iOS的通知是通过APNs中心来发送的，需要填写对应的环境信息。
            self::$req->setiOSApnsEnv(self::$set['iosEnv']);
            // 推送时设备不在线（既与移动推送的服务端的长连接通道不通），
            // 则这条推送会做为通知，通过苹果的APNs通道送达一次(发送通知时,Summary为通知的内容,Message不起作用)。
            // 注意：离线消息转通知仅适用于生产环境
            self::$req->setiOSRemind(self::$set['iosEnv'] == "PRODUCT" ? "true" : "false");
            // iOS消息转通知时使用的iOS通知内容，仅当iOSApnsEnv=PRODUCT && iOSRemind为true时有效
            self::$req->setiOSRemindBody("iOSRemindBody");
            // 自定义的kv结构,开发者扩展用 针对iOS设备
            self::$req->setiOSExtParameters(json_encode($msg['extra']));
            $ret[] = self::$instance->getAcsResponse(self::$req);
        }
        return $ret;
    }
}