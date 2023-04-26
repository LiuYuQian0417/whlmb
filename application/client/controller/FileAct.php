<?php
declare(strict_types=1);
namespace app\client\controller;

use app\common\model\BaseModel;
use think\Controller;
use think\facade\Request;

/**
 * 文件处理
 * Class FileAct
 * @package app\client\controller
 */
class FileAct extends Controller 
{
    /**
     * 图片上传
     * @param Request $request
     * @param BaseModel $baseModel
     * @return array 返回图片上传信息和oss加密/未加密路径
     */
    public function upload(Request $request,BaseModel $baseModel)
    {
        if ($request::isPost()){
            try{
                $param = $request::post();
                $filename = $baseModel::upload($param['name'], $param['dir'] . '/' . date('Ymd') . '/');
                $config = config('oss.');
                $ossRet = [
                    'domain' => $config['prefix'],
                    'url' => $filename,
                ];
                return json(['code' => 0, 'message' => config('message.')[0],'data' => $ossRet]);
            }catch (\Exception $e){
                return json(['code' => -100 , 'message' => $e->getMessage()]);
            }
        }
    }
}