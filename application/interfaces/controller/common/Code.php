<?php
declare(strict_types=1);

namespace app\interfaces\controller\common;

use app\interfaces\controller\BaseController;
use common\lib\phpcode\QrCode;
use think\facade\Env;
use think\facade\Request;

/**
 * 扫码生成
 * Class Area
 * @package app\interfaces\controller\common
 */
class Code extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
    }

    /**
     * 条形码生成
     * @throws \BCGArgumentException
     * @throws \BCGDrawException
     */
    public function bar_code()
    {
        $args = Request::get('parameter');
        QrCode::getBarCode($args);
    }

    /**
     * 二维码生成
     * @throws \common\lib\phpcode\BadRequestHttpException
     */
    public function qr_code()
    {
        $args = Request::get('parameter');
        QrCode::getQrCode($args, $args);
    }
}