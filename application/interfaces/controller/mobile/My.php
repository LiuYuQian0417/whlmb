<?php
declare(strict_types=1);

namespace app\interfaces\controller\mobile;

use app\common\model\Member;
use app\interfaces\controller\BaseController;
use EasyWeChat\Factory;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;

/**
 * 手机端 - Joy
 * Class My
 * @package app\interfaces\controller\mobile
 */
class My extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    /**
     * 配置
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function set(RSACrypt $crypt,
                        Member $memberModel)
    {
        $param = $crypt->request();
//        if (array_key_exists('url', $param) && $param['url']) {
//            $param['url'] = urldecode($param['url']);
//        }
        $memberModel->valid($param, 'set');
        $app = Factory::officialAccount(config('wechat.')['mobile']);
        $app->jssdk->setUrl($param['url']);
        return $crypt->response([
            'code' => 0,
            'result' => json_decode($app->jssdk->buildConfig([explode(',', $param['parameter'])])),
        ], true);
    }
}