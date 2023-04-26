<?php
declare(strict_types = 1);

namespace app\interfaces\behavior;

use app\common\model\Message;
use app\common\push\AliPush;
use app\common\push\PubNum;
use GuzzleHttp\Client;
use think\facade\Cache;
use think\facade\Config;

class Push
{
    // 小程序formId过期时间
    const FORM_ID_EXPIRE = 86400 * 7 - 3600;
    
    /**
     * 发送消息
     * @param $args
     * @param int $ext 0 含所有 1 只含短信 2 不含短信 3 只含站内信
     */
    public function send($args, $ext = 0)
    {
        $tpl = app('app\\common\\push\\Tpl');
        // 获取模板配置
        $tplConfig = $tpl->getSet($args);
        $message = new Message();
        $message
            ->allowField(true)
            ->isUpdate(false)
            ->save(array_merge($args['inside_data'], [
                'title' => $tplConfig['data']['first']['value'],
                'describe' => $tplConfig['data']['keyword2']['value'],
            ]));
        if ($ext == 3) {
            return;
        }
        /**** pc端 ****/
        try {
            if (config('user.pc.is_push') && $ext != 1) {
                $_httpConfig = Config::pull('daemon')['notify'];
                $msg = [
                    'type' => 'PC_MEMBER_INFO',
                    'base' => [
                        'title' => $tplConfig['data']['first']['value'],              // 消息标题
                        'tag' => ['jumpKey' => $tplConfig['PcAppKey']],               // 跳转标签
                    ],
                    'data' => [
                        [
                            'dist' => $message->member_id,    // 接收消息会员id
                            'icon' => '',                                   // 消息图片
                            'body' => $tplConfig['data']['keyword2']['value'],// 消息内容
                            'message_id' => $message->message_id,                  // 消息id
                        ]
                    ],
                ];
                (new Client())->post('127.0.0.1:' . $_httpConfig['port'] . '/NOTIFY', ['form_params' => $msg]);
            }
        } catch (\Exception $e) {
            self::recordLog('用户:' . $message->member_id . ';msg:' . $e->getMessage());
        }
        /**** app端 ****/
        try {
            if (config('user.app.is_push') && $ext != 1) {
                (new AliPush())->toSend([
                    'account' => array_key_exists('phone', $args) ?
                        $args['phone'] : request()->phone,
                    'title' => $tplConfig['data']['first']['value'],
                    'body' => $tplConfig['data']['keyword2']['value'],
                    'extra' => ['jumpKey' => $tplConfig['PcAppKey']],
                ]);
            }
        } catch (\Exception $e) {
            self::recordLog('用户:' . $message->member_id . ';msg:' . $e->getMessage());
        }
        /**** 手机站公众号 ****/
        try {
            $subscribe_time = array_key_exists('subscribe_time', $args) ?
                $args['subscribe_time'] : request()->subscribe_time;
            if (config('user.mobile.is_push') && $subscribe_time
                && $ext != 1
                && $tplConfig['openId'] = $args['openId']) {
                (new PubNum($tplConfig))->toSendTpl();
            }
        } catch (\Exception $e) {
            self::recordLog('用户:' . $message->member_id . ';msg:' . $e->getMessage());
        }
        /**** 小程序服务通知 ****/
        try {
            if (config('user.applet.is_push') && $ext != 1 && $args['microId']) {
                $tplConfig = $tpl->getSet($args, 1);
                $tplConfig['form_id'] = false;
                if ($args['tplKey'] == 'order_state' && $args['data'][0] == 1) {
                    // 若使用微信付款则尝试获取prepay_id,没有则用form_id
                    $tplConfig['form_id'] = Cache::tag('micro-recharge')->get('micro-recharge-' . $args['order_number']);
                }
                if (!$tplConfig['form_id']) {
                    $tplConfig['form_id'] = self::getFormId($args['microId']);
                }
                if (($tplConfig['openId'] = $args['microId']) && $tplConfig['form_id']) {
                    (new PubNum($tplConfig))->toSendTpl(1);
                }
            }
        } catch (\Exception $e) {
            self::recordLog('用户:' . $message->member_id . ';msg:' . $e->getMessage());
        }
        /**** 短信 ****/
        try {
            if (!empty($args['sms_data']) && $ext != 2) {
                app('app\\common\\sms\\SmsManage', $args['sms_data'])->sendSms();
            }
        } catch (\Exception $e) {
            self::recordLog('用户:' . $message->member_id . ';msg:' . $e->getMessage());
        }
    }
    
    /**
     * 记录错误
     * @param $msg
     */
    protected static function recordLog($msg)
    {
        if (!is_dir($push_log_path = env('app_path') . 'public/push_log/')) {
            mkdir($push_log_path, 0755, true);
        }
        $path = $push_log_path . date('Y-m-d') . '.log';
        $msg = date('Y-m-d H:i:s') . $msg . PHP_EOL;
        error_log($msg, 3, $path);
    }
    
    /**
     * 获取小程序formId
     * @param $micro_open_id
     * @return string
     */
    public function getFormId($micro_open_id)
    {
        $arr = Cache::store('file')->get('form_id_' . $micro_open_id, []);
        $form_id = '';
        if (!empty($arr)) {
            foreach ($arr as $key => $_arr) {
                if ($_arr['expire_time'] > time()) {
                    $form_id = $_arr['form_id'];
                }
                unset($arr[$key]);
                if ($form_id) break;
            }
            Cache::store('file')->set('form_id_' . $micro_open_id, $arr);
        }
        return $form_id;
    }
    
    /**
     * 设置小程序formId
     * @param $micro_open_id
     * @param $form_id
     */
    public function setFormId($micro_open_id, $form_id)
    {
        $arr = Cache::store('file')->get('form_id_' . $micro_open_id, []);
        foreach ($form_id as $_form_id) {
            array_push($arr, ['form_id' => $_form_id, 'expire_time' => time() + self::FORM_ID_EXPIRE]);
        }
        Cache::store('file')->set('form_id_' . $micro_open_id, $arr);
    }
}