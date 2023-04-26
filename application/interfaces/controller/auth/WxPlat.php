<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\WechatConf;
use app\common\push\PubNum;
use app\interfaces\controller\BaseController;
use think\facade\Request;

/**
 * 公众号自动回复
 * Class WxPlat
 * @package app\interfaces\controller\auth
 */
class WxPlat extends BaseController
{
    
    const LOCATION = 1;  //是否过滤地理位置消息 0否 1是
    
    /**
     * 交互微信公众号服务器
     * @return array|bool|string
     */
    public function receive()
    {
        $get = Request::get();
        if ($get && array_key_exists('echostr', $get)) {
            return (new PubNum($get))->checkToken() ? $get['echostr'] : '';
        }
        try {
            if (Request::isPost()) {
                $post = file_get_contents('php://input');
                if (!$post) {
                    return 'success';
                }
                //禁止引用外部xml实体
                libxml_disable_entity_loader(true);
                $values = json_decode(json_encode(simplexml_load_string($post, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
                if (!$values && !is_array($values)) return 'success';
                //是否过滤地理信息消息
                if (array_key_exists('Event', $values) && $values['Event'] == 'LOCATION' && self::LOCATION) {
                    return 'success';
                }
                //调用消息回复xml
                $ret = (new PubNum($values))->reply();
                return $ret;
            }
        } catch (\Exception $e) {
            return 'success';
        }
    }
}