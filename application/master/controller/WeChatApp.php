<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/3 0003
 * Time: 13:19
 */

namespace app\master\controller;


use app\common\model\WechatConf;
use think\Controller;
use think\facade\Env;

class WeChatApp extends Controller
{
    /**
     * 基础配置
     */
    public function baseConf()
    {
        if ($this->request->isPost()) {

            if(!\think\facade\Config::get('user.can_change_primary_config',TRUE)){
                return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
            };

            $_post = $this->request->post();

            $_create = WechatConf::setConf($_post, 'wechat-app');

            if ($_create !== TRUE) {
                return ['code' => -1, 'message' => $_create];
            }
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/we_chat_app/base_conf'];
        }

        $_baseConf = WechatConf::getConf('wechat-app');

        $this->assign('data', $_baseConf);

        return $this->fetch();
    }

    public function payConf()
    {
        if ($this->request->isPost()) {
            if(!\think\facade\Config::get('user.can_change_primary_config',TRUE)){
                return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
            };
            $_post = $this->request->post();

            $_file = $this->request->file();

            $_create = WechatConf::setConf($_post, 'wechat-app-pay');

            if ($_create !== TRUE) {
                return ['code' => -1, 'message' => $_create];
            }
            // 证书文件
            $_apiclientCert = $_file['apiclient_cert'] ?? FALSE;
            $_apiclientKey = $_file['apiclient_key'] ?? FALSE;

            // 支付证书存放文件夹
            $_filePath = Env::get('root_path') . 'cert/pay/wechat-app';

            // 尝试上传证书文件
            try {
                // 如果上传了 cert.pem 文件则保存
                if ($_apiclientCert !== FALSE) {
                    $_apiclientCert->move($_filePath, 'cert.pem');
                }

                // 如果上传了 key.pem 文件则保存
                if ($_apiclientKey !== FALSE) {
                    $_apiclientKey->move($_filePath, 'key.pem');
                }
            } catch (\Exception $e) {
                return ['code' => -1, 'message' => '证书文件上传失败'];
            }
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/we_chat_app/pay_conf'];
        }

        // 获取配置
        $_payConf = WechatConf::getConf('wechat-app-pay');

        // 判断返回信息是否正确
        if (!is_array($_payConf)) {
            $this->error($_payConf);
        }

        // 支付证书存放文件夹
        $_filePath = Env::get('root_path') . 'cert/pay/wechat-app';

        $_payConf['wechat-app-pay_cert_uploaded'] = file_exists($_filePath . '/cert.pem');
        $_payConf['wechat-app-pay_key_uploaded'] = file_exists($_filePath . '/key.pem');

        $this->assign('data', $_payConf);
        return $this->fetch();
    }

}