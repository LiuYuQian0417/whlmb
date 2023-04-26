<?php
declare(strict_types = 1);

namespace app\interfaces\controller\common;


use app\common\model\ShopStyle as ShopStyleModel;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;

class ShopStyle extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    /**
     * 获取全店风格
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function get(RSACrypt $crypt)
    {
        $_currentId = Env::get('shop_style');
        $res = ShopStyleModel::_GetCurrentColor($_currentId);
        $ossConfig = config('oss.');
        if ($res['code'] === 0) {
            // 加入功能显示开关
            $res['result']['show_switch'] = [];
            foreach (Env::get() as $_k => $_e) {
                if (stripos($_k, 'IS_') === 0) {
                    $res['result']['show_switch'][strtolower($_k)] = $_e;
                }
            }
            // 加入版本控制开关
            $user = config('user.');
            $res['result']['version_info'] = [
                'one_more' => $user['one_more'],    // 多店铺1还是单店铺0
                'one_store_id' => $user['one_store_id'],        //单店铺id
            ];
            $res['result']['app_info'] = [
                'logo' => $ossConfig['prefix'] . Env::get('logo', '') . $ossConfig['style'][0],
                'title' => Env::get('title', 'iShop2.0'),
                'business_name' => Env::get('business_name', '资海科技集团'),
                'contact' => Env::get('phone', ''), //平台客服
            ];

            if (INI_CONFIG['SHARE_FRIEND_SWITCH']) {
               $res['result']['share_text'] = ['分享到好友', '朋友圈'];
            } else {
                $res['result']['share_text'] = ['分享到好友'];
            }
        }
        return $crypt->response($res);
    }
}