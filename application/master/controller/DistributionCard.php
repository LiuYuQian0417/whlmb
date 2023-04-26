<?php
declare(strict_types=1);

namespace app\master\controller;

use common\lib\phpcode\QrCode;
use EasyWeChat\Factory;
use think\Controller;
use think\facade\Env;
use think\facade\Request;

class DistributionCard extends Controller
{

    /**
     * 分销系统设置文件路径
     * @var
     */
    private $filename;

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.distribution');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.distribution';
    }

    /**
     * 推广名片
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     */
    public function index()
    {

//        $generalize_code_file = 'qr_code/generalize/generalize_0.png';
//
//        if (!is_file(Env::get('root_path') . 'public/' . $generalize_code_file)) {
//
//            $app = Factory::miniProgram(config('wechat.')['applet']);
//
//            $response = $app->app_code->getUnlimit('generalize,0', [
//                'width' => 600,
//                'page'  => 'my/fx_invitation/fx_invitation'
//            ]);
//            if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
//                $response->saveAs('./qr_code/generalize', 'generalize_0.png');
//            }
//        }
        return $this->fetch('', [
            'generalize_code_file' => $generalize_code_file??'',
            'single_store' => config('user.one_more'),
        ]);
    }


    /**
     * 保存分销系统设置
     * @param Request $request
     * @return array
     */
    public function editVal(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                ini_file(null, $param['parameter'], $param['data'], $this->filename);
                if ($param['parameter'] == 'distribution_card') {
                    // 删除已合成的分销图片
                    $dir = Env::get('ROOT_PATH') . 'public/static/img/interfaces/qr_code/distribution/combination/mobile/';
                    $files = scandir($dir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            if (file_exists($dir.$file)) {
                                @unlink($dir.$file);
                            }
                        }
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 二维码生成
     * @param Request $request
     * @throws \common\lib\phpcode\BadRequestHttpException
     */
    public function qr_code(Request $request)
    {
        require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
        QrCode::getQrCode($request::get('parameter'), $request::domain() . '/advocacy/' . $request::get('parameter') . '.html');
    }
}