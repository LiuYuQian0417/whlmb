<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/13 0013
 * Time: 10:49
 */

namespace app\interfaces\controller\wechat;


use app\common\model\WechatConf;
use think\Controller;

class Applet extends Controller
{

    /**
     * 接收微信的验证消息
     */
    public function api()
    {

        /**
         * 获取微信配置
         */
        $_wechatConf = WechatConf::getConf('wechat');


        $_easyWechatConf = [
            'app_id'        => $this->$_wechatConf['wechat_app_id'],
            'secret'        => $this->$_wechatConf['wechat_app_secret'],
            'token'         => $this->$_wechatConf['wechat_token'],
            'aes_key'       => $this->$_wechatConf['wechat_encoding_aes_key'],
            'response_type' => 'array',
        ];



    }
}