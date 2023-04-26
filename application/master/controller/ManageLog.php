<?php
declare(strict_types = 1);
namespace app\master\controller;

use app\common\model\Manage;
use think\Controller;
use app\common\model\ManageLog as ManageLogModel;
use think\facade\Request;

class ManageLog extends Controller
{
    /**
     * 日志列表
     * @param Request $request
     * @param ManageLogModel $log
     * @return mixed
     * @throws \Exception
     */
    public function index(Request $request , ManageLogModel $log)
    {
        try{
            $param = $request::get();
            $condition[] = ['manage_log_id','>',0];
            if (isset($param['keyword']) && $param['keyword'])
                $condition[] = ['operate_ip|breadcrumb|m.nickname','like','%'.$param['keyword'].'%'];
            if (isset($param['type']) && $param['type'])
                $condition[] = ['type','=',$param['type']];
            if (isset($param['manage']) && $param['manage'])
                $condition[] = ['ml.manage_id','=',$param['manage']];
            if (isset($param['date']) && $param['date']){
                list($begin,$end) = explode(' - ',$param['date']);
                $end = $end . ' 23:59:59';
                $condition[] = ['ml.create_time','between',[$begin,$end]];
            }
            $data = $log
                ->alias('ml')
                ->join(['ishop_manage' => 'm'],'m.manage_id = ml.manage_id')
                ->field('ml.*,m.nickname')
                ->where($condition)
                ->order(['create_time' => 'desc' , 'manage_log_id' => 'desc'])
                ->paginate($log->pageLimits,false,['query' => $param]);
            //日志类型
            $type = ['登录','新增','编辑','删除'];
            //全部操作员
            $manage = Manage::withTrashed()
                ->field('manage_id,nickname')
                ->select();
            return $this->fetch('',[
                'data'  =>  $data,
                'type'  =>  $type,
                'manage'=>  $manage,
            ]);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 删除日志
     * @param Request $request
     * @param ManageLogModel $log
     * @return array
     */
    public function destroy(Request $request,ManageLogModel $log)
    {
        try{
            $param = $request::post('id');
            $log::destroy($param);
            return ['code' => 0 , 'message' => config('message.')[0]];
        }catch (\Exception $e){
            return ['code' => -100 , 'message' => $e->getMessage()];
        }
    }
}