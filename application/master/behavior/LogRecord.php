<?php
declare(strict_types = 1);
namespace app\master\behavior;

use think\facade\Cache;
use think\facade\Session;
use think\Request;

/**
 * 管理员操作日志记录
 * Class LogRecord
 * @package app\master\behavior
 */
class LogRecord
{
    /**
     * 执行记录
     * @param Request $request
     */
    public function run(Request $request)
    {
        if ($request->isPost() && ($request->loginId || Session::get('manage_id'))){
            $currentUrl = $request->url();
            $currentBaseUrl = $request->baseUrl();
            $currentBaseUrlArr = explode('/',ltrim($currentBaseUrl,'/'));
            $save = [
                'manage_id' =>  $request->loginId?:Session::get('manage_id'),
                'operate_ip' =>  $request->ip(),
                'breadcrumb'   =>  Cache::get('flatMaster_breadcrumb_record',$currentUrl),
                'content'   =>  json_encode($request->param()),
            ];
            $save['type'] = $currentUrl;
            if ($currentBaseUrl == '/login/index'){
                $save['type'] = '登录';
                $save['breadcrumb'] = '登录成功';
                if (is_null($save['manage_id'])){
                    $save['manage_id'] = 0;
                    $save['breadcrumb'] = '登录异常';
                }
            }
            //匹配新增
            if (preg_match('/create/i',end($currentBaseUrlArr))){
                $save['type'] = '新增';
            }
            //匹配编辑
            if (preg_match('/edit/i',end($currentBaseUrlArr))){
                $save['type'] = '编辑';
            }
            //匹配删除
            if (preg_match('/destroy/i',end($currentBaseUrlArr))){
                $save['type'] = '删除';
            }
            //保存数据
            $manageLogModel = app('app\\common\\model\\ManageLog');
            $manageLogModel->allowField(true)->save($save);
        }
    }
}