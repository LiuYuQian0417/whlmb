<?php
declare(strict_types=1);

namespace app\computer\controller\common;

use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 公用文件 - 图片、视频
 * Class Search
 * @package app\computer\controller\goods
 */
class Image extends BaseController
{


    /**
     * 公用上传图片（APP） - Joy
     * @param Request $request
     * @param RSACrypt $crypt
     * @return mixed
     */
    function app_upload(Request $request, RSACrypt $crypt)
    {
        if ($request::isPost()) {
            try {
                $param = $crypt->request();
                $file = $request::file();
                if (!empty($file)) {
                    $ret = [];
                    $view = [];
                    foreach ($file as $_file) {
                        $ossFilePath = '';
                        if (!is_null($_file)) {
                            $info = $_file->getInfo();
                            $ossFileName = $param['type'] ??'default' . '/file/' . date('Ymd') . '/' . md5(microtime()) . '.jpg';
                            $ossManage = app('app\\common\\service\\OSS');
                            $res = $ossManage->fileUpload($ossFileName, $info['tmp_name']);
                            if (!$res['code']) $ossFilePath = $ossFileName;
                            $_view = $ossManage->getSignUrlForGet($ossFilePath . '!upload-back');
                            $ret[] = $ossFilePath;
                            $view[] = $_view['url'];
                        }
                    }
                    return $crypt->response(['code' => 0, 'url' => $ret, 'view_url' => $view]);
                }
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }


    /**
     * 公用上传视频 - Joy
     * @param Request $request
     * @param RSACrypt $crypt
     * @return array
     */
    public function upload_video(Request $request, RSACrypt $crypt)
    {

        if ($request::isPost()) {
            try {

                $file = $request::file('video');

                if (!empty($file)) {
                    $ossFilePath = '';
                    $view = [];
                    if (!is_null($file)) {
                        $info = $file->getInfo();
                        $ossFileName = 'video/file/' . date('Ymd') . '/' . md5(microtime()) . substr($info['name'], -4);
                        $ossManage = app('app\\common\\service\\OSS');
                        $res = $ossManage->fileUpload($ossFileName, $info['tmp_name']);
                        if (!$res['code']) $ossFilePath = $ossFileName;
                        $view = $ossManage->getSignUrlForGet($ossFilePath);
                    }

                    return $crypt->response(['code' => 0, 'url' => $ossFilePath, 'view_url' => $view['url']]);

                }

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }



    /********废弃********/

//    /**
//     * 公用上传图片（小程序） - Joy
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @return array
//     */
//    public function upload(Request $request, RSACrypt $crypt)
//    {
//
//        if ($request::isPost()) {
//            try {
//
//                $param = $crypt->request();
//
//                $file = $request::file('image');
//
//                if (!empty($file)) {
//                    $ossFilePath = '';
//                    $view = [];
//                    if (!is_null($file)) {
//                        $info = $file->getInfo();
//                        $ossFileName = $param['type'] . '/file/' . date('Ymd') . '/' . md5(microtime()) . '.jpg';
//                        $ossManage = app('app\\common\\service\\OSS');
//                        $res = $ossManage->fileUpload($ossFileName, $info['tmp_name']);
//                        if (!$res['code']) $ossFilePath = $ossFileName;
//                        $view = $ossManage->getSignUrlForGet($ossFilePath . '!upload-back');
//                    }
//
//                    return $crypt->response(['code' => 0, 'url' => $ossFilePath, 'view_url' => $view['url']]);
//
//                }
//
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//            }
//        }
//    }


}
