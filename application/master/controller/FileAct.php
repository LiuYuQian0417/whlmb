<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\BaseModel;
use think\Controller;
use think\facade\Env;
use think\facade\Request;

/**
 * 文件处理
 * Class FileAct
 * @package app\master\controller
 */
class FileAct extends Controller
{

    /**
     * 系统设置文件路径
     * @var
     */
    private $filename;

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.config';
    }

    /**
     * 图片上传
     * @param Request $request
     * @param BaseModel $baseModel
     * @return array 返回图片上传信息和oss加密/未加密路径
     */
    public function upload(Request $request, BaseModel $baseModel)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $filename = $baseModel::upload($param['name'], $param['dir'] . '/' . date('Ymd') . '/');
                if ($param['dir'] == 'logo') {
                    ini_file(null, $param['name'], $filename, $this->filename);
                }
                $config = config('oss.');
                $ossRet = [
                    'domain' => $config['prefix'],
                    'url' => $filename,
                ];
                return json(['code' => 0, 'message' => config('message.')[0], 'data' => $ossRet]);
            } catch (\Exception $e) {
                return json(['code' => -100, 'message' => $e->getMessage()]);
            }
        }
    }
}