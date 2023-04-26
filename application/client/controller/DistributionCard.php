<?php
declare(strict_types=1);

namespace app\client\controller;

use common\lib\phpcode\QrCode;
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

    public function index()
    {
        return $this->fetch('', [

        ]);
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