<?php
declare(strict_types = 1);

namespace app\common\push;

use app\common\model\WechatConf;
use think\Db;
use think\facade\Cache;

/**
 * 公众号&小程序
 * Class PubNum
 * @package app\common\push
 */
class PubNum
{
    protected $data;
    // 获取accessToken api地址GET
    const AccessTokenURL = 'https://api.weixin.qq.com/cgi-bin/token';
    // 发送公众号模板消息的api地址POST
    const MOBILE_URL = 'https://api.weixin.qq.com/cgi-bin/message/template/send?';
    // 发送小程序模板消息的api地址POST
    const APPLET_URL = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?';
    // 获取用户信息
    const USER_URL = 'https://api.weixin.qq.com/cgi-bin/user/info';

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * 获取accessToken
     * @param $type 0 公众号 1 小程序
     * @return mixed|string
     */
    public function getAccessToken($type = 0)
    {
        $token = Cache::store('file')->get('plat_token' . $type, '');
        if (!$token) {
            $param = [
                'appid' => $type ? config('wechat.')['applet']['app_id'] : config('wechat.')['mobile']['app_id'],
                'secret' => $type ? config('wechat.')['applet']['secret'] : config('wechat.')['mobile']['secret'],
                'grant_type' => 'client_credential',
            ];
            $ret = curl(1, self::AccessTokenURL, $param);
            if ($ret && is_array($ret)) {
                Cache::store('file')->set('plat_token' . $type, $token = $ret['access_token'], $ret['expires_in'] - 60 * 5);
            }
        }
        return $token;
    }

    /**
     * 获取用户信息
     */
    public function getInfo()
    {
        $param = [
            'access_token' => self::getAccessToken(),
            'openid' => $this->data['open_id'],
            'lang' => 'zh_CN',
        ];
        $ret = curl(1, self::USER_URL, $param);
        return $ret;
    }

    /**
     * 微信公众号服务器验证token
     * @return bool
     */
    public function checkToken()
    {
        $arr = [$this->data['timestamp'], $this->data['nonce'], WechatConf::getConf('wechat')['wechat_token']['value']];
        sort($arr, SORT_STRING);
        $str = sha1(implode($arr));
        return $str == $this->data['signature'] ? true : false;
    }

    /**
     * 发送模板消息
     * @param int $type 公众号 0 小程序 1
     * @return bool|mixed|string
     */
    public function toSendTpl($type = 0)
    {
        $data = [
            'touser' => $this->data['openId'],
            'template_id' => $this->data[['mobile_id', 'applet_id'][$type]],
            'data' => $this->data['data'],
        ];
        if ($type) {
            $data['form_id'] = $this->data['form_id'];
            $data['page'] = $this->data['applet_url'];
        } else {
            $data['url'] = config('user.mobile.mobile_domain') . $this->data['mobile_url'];
        }
        $ret = curl(2, ($type ? self::APPLET_URL : self::MOBILE_URL) . 'access_token=' . self::getAccessToken($type), $data, 1);
        return $ret;
    }

    /**
     * 回复消息
     * @return string
     */
    public function reply()
    {
        try {
            $action = '';
            //检测是否是正常微信消息
            if (array_key_exists('MsgType', $this->data) && $this->data['MsgType'] == 'text') {
                $action = $this->data['MsgType'];
            }
            //检测是否是事件事件消息
            if (array_key_exists('Event', $this->data) && $this->data['Event']) {
                $action = $this->data['Event'];
            }
            //检测是否是子菜单[点击等]事件消息
            if (array_key_exists('EventKey', $this->data) && $this->data['EventKey']) {
                $action = $this->data['EventKey'];
            }
            // 调用当前对象内对应方法
            if (!$action && !method_exists($this, $action)) {
                // 忽略[image,voice,video,shortvideo,link]事件
                return 'success';
            }
            $extra = self::$action();
            $ret = is_array($extra) && $extra ? array_merge([
                'ToUserName' => $this->data['FromUserName'],
                'FromUserName' => $this->data['ToUserName'],
                'CreateTime' => time(),
            ], $extra) : '';
            if ($ret && is_array($ret)) {
                $switch = ($ret['MsgType'] == 'transfer_customer_service') ? 0 : 1;
                return self::arrayToXml($ret, $switch);
            }
            return 'success';
        } catch (\Exception $e) {
            return 'success';
        }
    }

    /**
     * 普通文本消息
     * @return array
     */
    protected function text()
    {
        $ret = [
            'MsgType' => 'text',
            'Content' => $this->data['Content'],
        ];
        return $ret;
    }

    /**
     * 关注事件
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function subscribe()
    {
        $ret = [
            'MsgType' => 'text',
            // 关注消息...平台->设置->公众号设置->关注回复
            'Content' => WechatConf::getConf('diy-replay')['diy-replay_content']['value'] ?? '感谢关注资海e店+',
        ];
        Db::name('member')
            ->where([['wechat_open_id', '=', $this->data['FromUserName']]])
            ->update(['subscribe_time' => date('Y-m-d H:i:s')]);
        return $ret;
    }

    /**
     * 取消关注事件
     * @return string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function unsubscribe()
    {
        Db::name('member')
            ->where([['wechat_open_id', '=', $this->data['FromUserName']]])
            ->update(['subscribe_time' => null]);
        return '';
    }

    /**
     * 数组转xml
     * @param $arr
     * @param $switch [0关 1开] 客服需关闭
     * @return string
     */
    protected static function arrayToXml($arr, $switch = 0)
    {
        $xml = "<xml>";
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                if ($switch) $xml .= "<Articles>";
                foreach ($v as $_v) {
                    if ($switch) $xml .= "<item>";
                    foreach ($_v as $_sk => $item) {
                        if (is_numeric($item)) {
                            $xml .= "<" . $_sk . ">" . $item . "</" . $_sk . ">";
                        } else {
                            $xml .= "<" . $_sk . "><![CDATA[" . $item . "]]></" . $_sk . ">";
                        }
                    }
                    if ($switch) $xml .= "</item>";
                }
                if ($switch) $xml .= "</Articles>";
            } else {
                if (is_numeric($v)) {
                    $xml .= "<" . $k . ">" . $v . "</" . $k . ">";
                } else {
                    $xml .= "<" . $k . "><![CDATA[" . $v . "]]></" . $k . ">";
                }
            }
        }
        $xml .= "</xml>";
        return $xml;
    }
}