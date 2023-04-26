<?php
declare(strict_types = 1);

namespace app\interfaces\controller\recharge;

use app\common\model\Member;
use EasyWeChat\Factory;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\facade\Request;

/**
 * 手机站 - 支付 - Joy
 * Class Pay
 * @package app\interfaces\controller\recharge
 */
class Mobile extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    /**
     * 获取open_id
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @return mixed
     */
    public function open_id(RSACrypt $crypt,
                            Member $memberModel)
    {
        $param = Request::get();
        if (!$param['code']) {
            return $crypt->response([
                'code' => 0,
                'message' => '查询成功',
                'openid' => '',
            ], true);
        }
        $memberModel->valid($param, 'mobile_login');
        // 读取配置
        $app = Factory::officialAccount(config('wechat.')['mobile']);
        // 获取 OAuth 授权结果用户信息
        $result = $app->oauth->user();
        if (empty($result['original']['openid'])) {
            return $crypt->response([
                'code' => -1,
                'message' => '微信服务器返回失败',
            ], true);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'openid' => $result['original']['openid'],
            'unionId' => isset($result['original']['unionId']) ? $result['original']['unionId'] : $result['original']['openid'],
        ], true);
    }
}