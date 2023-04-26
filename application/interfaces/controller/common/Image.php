<?php
declare(strict_types = 1);

namespace app\interfaces\controller\common;

use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 公用文件 - 图片、视频
 * Class Search
 * @package app\interfaces\controller\goods
 */
class Image extends BaseController
{
    
    /**
     * 公用上传图片（小程序） - Joy
     * @param RSACrypt $crypt
     * @return array
     */
    public function upload(RSACrypt $crypt)
    {
        $param = $crypt->request();
        $file = Request::file('image');
        if (!empty($file)) {
            $view = $ossFileName = '';
            if (!is_null($file)) {
                $info = $file->getInfo();
                $config = config('oss.');
                $ossFileName = $param['type'] . '/file/' . date('Ymd') . '/' . md5(microtime()) . '.png';
                $ossManage = app('app\\common\\service\\OSS');
                $ossManage->fileUpload($ossFileName, $info['tmp_name']);
                // $view = $config['prefix'] . $ossFileName . $config['style'][1];
                $view = $config['prefix'] . $ossFileName;
            }
            return $crypt->response([
                'code' => 0,
                'message' => '上传成功',
                'url' => $ossFileName,
                'view_url' => $view,
            ], true);
        }
        return $crypt->response([
            'code' => -1,
            'message' => '未上传图片',
        ], true);
    }
    
    
    /**
     * 公用上传图片（APP） - Joy
     * @param RSACrypt $crypt
     * @return mixed
     */
    function app_upload(RSACrypt $crypt)
    {
        $param = $crypt->request();
        $file = Request::file();
        if (!empty($file)) {
            $view = $ret = [];
            $config = config('oss.');
            foreach ($file as $_file) {
                if (!is_null($_file)) {
                    $info = $_file->getInfo();
                    $ossFileName = $param['type'] . '/file/' . date('Ymd') . '/' . md5(microtime()) . '.png';
                    $ossManage = app('app\\common\\service\\OSS');
                    $ossManage->fileUpload($ossFileName, $info['tmp_name']);
                    $ret[] = $ossFileName;
                    $view[] = $config['prefix'] . $ossFileName . $config['style'][1];
                }
            }
            return $crypt->response([
                'code' => 0,
                'message' => '上传成功',
                'url' => empty($ret) ? '' : implode(',', $ret),
                'view_url' => empty($view) ? '' : implode(',', $view),
            ], true);
        }
        return $crypt->response([
            'code' => -1,
            'message' => '未上传图片',
        ], true);
    }
    
    
    /**
     * 公用上传视频 - Joy
     * @param RSACrypt $crypt
     * @return array
     */
    public function upload_video(RSACrypt $crypt)
    {
        $file = Request::file('video');
        if (!empty($file)) {
            $view = $ossFileName = '';
            if (!is_null($file)) {
                $info = $file->getInfo();
                $ossFileName = 'video/file/' . date('Ymd') . '/' . md5(microtime()) . substr($info['name'], -4);
                $ossManage = app('app\\common\\service\\OSS');
                $ossManage->fileUpload($ossFileName, $info['tmp_name']);
                $config = config('oss.');
                $view = $config['prefix'] . $ossFileName;;
            }
            return $crypt->response([
                'code' => 0,
                'message' => '上传成功',
                'url' => $ossFileName,
                'view_url' => $view,
            ], true);
        }
    }
}