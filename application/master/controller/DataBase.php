<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\DbBackup as DbBackupModel;
use think\Controller;
use think\Env;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Request;
use app\common\service\Backup as BackupModel;
use app\common\service\OSS as OssModel;
use think\facade\Session;

class DataBase extends Controller
{

    /**
     * 数据库列表
     * @param Request $request 获取参数类库
     * @param BackupModel $backupModel 数据备份类库
     * @return mixed
     * @throws \Exception
     */
    public function index(Request $request, BackupModel $backupModel)
    {
        try {
            $data = $backupModel->dataList();
            return $this->fetch('', [
                'data' => $data,
                'total' => count($data),
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 数据备份 - 执行
     * @param Request $request 获取参数类库
     * @param Env $env 获取环境变量类库
     * @param BackupModel $backupModel 数据备份类库
     * @param OssModel $OSSModel OSS类库
     * @return array|\Exception|mixed
     */
    public function backup(Request $request, Env $env, BackupModel $backupModel, OssModel $OSSModel)
    {
        // 判断POST请求
        if ($request::isPost()) {
            try {
                // 备份数据库
                foreach (array_map('array_shift', $backupModel->dataList()) as $value) {
                    $backupModel->backup($value, 0);
                }

                // 上传 oss
                $find = $OSSModel->fileUpload('backup/' . substr($backupModel->getFile('pathname'), 9), $env->get('root_path') . 'public/' . substr($backupModel->getFile('pathname'), 2));

                if ($find['code'] == 0) {


                    // 删除本地文件
                    unlink($env->get('root_path') . 'public/' . substr($backupModel->getFile('pathname'), 2));
                    return ['code' => 0, 'message' => '备份数据库成功,正在跳转...', 'url' => '/data_base/backup_index'];

                } else {
                    return ['code' => 400, 'message' => $find['message']];
                }
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 数据备份列表
     * @param Request $request 获取参数类库
     * @param OssModel $OSSModel 数据备份类库
     * @return array|\Exception|mixed
     */
    public function backup_index(Request $request, OssModel $OSSModel, DbBackupModel $dbBackup)
    {
        // 云列表
//        if ($request::isPost()) {
//
//            try {
//
//                return ['code' => 0, 'message' => '查询成功', 'count' => count($OSSModel->listObject('backup/', '200', '')['data']), 'data' => $OSSModel->listObject('backup/', '200', '')['data']];
//
//            } catch (\Exception $e) {
//
//                return $e;
//
//            }
//        }

        $data = array_reverse($OSSModel->listObject('backup/', '200', '')['data']);

        return $this->fetch('', [
            // 数据库列表
//            'total' => count($dbBackup->select()),
//            'data' => $dbBackup->order('create_time', 'desc')->paginate(10, false),

            // 云列表
            'total' => count($OSSModel->listObject('backup/', '200', '')['data']),
            'data' => $data // 数组倒叙排序，让最新备份的在上
        ]);
    }

    /**
     * 数据备份下载
     * @param Request $request 获取参数类库
     * @param OssModel $OSSModel 数据备份类库
     * @return array|\Exception|mixed
     */
    public function download(Request $request, OssModel $OSSModel, DbBackupModel $dbBackup)
    {
        if ($request::isPost()) {

            try {

                // 读取远程文件
                $find = $OSSModel->getSignUrlForGet($request::post('fileName'));

                if ($find['code'] == 0) {
                    header($find['url']);
                    return ['code' => 0, 'message' => '获取成功,等待下载...', 'url' => $find['url']];
                }

            } catch (\Exception $e) {

                return ['code' => $e->getCode(), 'message' => $e->getMessage()];

            }
        }
    }


    /**
     * 数据备份删除
     * @param Request $request 获取参数类库
     * @param OssModel $OSSModel 数据备份类库
     * @return array|\Exception|mixed
     */
    public function destroy(Request $request, OssModel $OSSModel)
    {
        if ($request::isPost()) {
//halt($request::post());
            try {

                // 删除远程文件
                $find = $OSSModel->deleteFile($request::post('id'));

                if ($find['code'] == 0) {
                    return ['code' => 0, 'message' => '删除成功,正在跳转...', 'url' => '/data_base/backup_index'];
                }

            } catch (\Exception $e) {

                return ['code' => 0, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 清除数据缓存
     * @param Request $request 获取参数类库
     * @return array|\Exception|mixed
     */
    public function clear_cache(Request $request)
    {
        if ($request::isPost()) {
            try {
                \think\facade\Env::load(\think\facade\Env::get('app_path') . 'common/ini/.config');
                /**
                 * 判断是否支持各种配送方式
                 */
                // 店铺更新数据
                $_storeUpdateData = [];
                // 关闭门店自提
                if (\think\facade\Env::get('is_shop') != 1) {
                    // 关闭所有店铺的门店自提
                    $_storeUpdateData['is_shop'] = 0;
                } else {
                    $_storeUpdateData['is_shop'] = 1;
                }

                // 同城配送被关闭
                if (\think\facade\Env::get('is_city') != 1) {
                    // 关闭所有店铺的同城配送
                    $_storeUpdateData['is_city'] = 0;
                } else {
                    $_storeUpdateData['is_city'] = 1;
                }
                // 如果店铺更新数据不为空的话 把所有的店铺更新为 不支持同城配送或者 门店自提
                if (!empty($_storeUpdateData)) {
                    @\app\common\model\Store::withTrashed()->where([
                        ['store_id', '>', '0'],
                    ])->update($_storeUpdateData);
                }

                $param = $request::post('selected/a');
                if ($param) {
                    foreach ($param as $item) {
                        switch ($item) {
                            case 'Session' :
                                Session::clear();
                                break;
                            case 'Cookie' :
                                Cookie::clear('master_');
                                break;
                            default :
                                //查询此分类下的缓存标识
                                $prefix = Config::get('cache.default')['prefix'];
                                $redisInstance = Cache::handler();
                                $cls = $redisInstance->keys($prefix . $item . '_*');
                                if ($cls) {
                                    foreach ($cls as $val) {
                                        Cache::handler()->del($val);
                                    }
                                }
                                break;
                        }
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        //查询缓存分类
        $classify = config('cache.')['classify'];
        return $this->fetch('', [
            'classify' => $classify,
        ]);
    }
}