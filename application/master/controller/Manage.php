<?php
declare(strict_types = 1);
namespace app\master\controller;

use app\common\model\AuthGroup;
use think\Controller;
use think\Db;
use think\facade\Hook;
use think\facade\Request;
use app\common\model\Manage as ManageModel;

class Manage extends Controller
{
    /**
     * 管理员列表
     * @param Request $request
     * @param ManageModel $manage
     * @return mixed
     * @throws \Exception
     */
    public function index(Request $request,ManageModel $manage)
    {
        try{
            $param = $request::get();
            //查询管理员数据
            $data = $manage
                ->alias('m')
                ->join('auth_group ag', 'ag.auth_group_id = m.auth_group_id')
                ->field('m.*,ag.title')
                ->order(['m.create_time' => 'desc' , 'manage_id' => 'desc'])
                ->paginate(10, false, ['query' => $param]);
            return $this->fetch('',[
                'data' => $data,
            ]);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 创建管理员
     * @param Request $request
     * @param ManageModel $manage
     * @param AuthGroup $authGroup
     * @return array|mixed
     */
    public function create(Request $request,ManageModel $manage,AuthGroup $authGroup)
    {
        if ($request::isPost()){
            try{
                $param = $request::post();
                $check = $manage->valid($param,'create');
                if ($check['code']) return $check;
                //检测昵称唯一性
                $unique = $manage
                    ->where([['nickname' , '=' , $param['nickname']]])
                    ->value('manage_id');
                if ($unique) return ['code' => -2 , 'message' => '系统已存在相同昵称,请更改'];
                $manage->allowField(true)->save($param);
                return ['code' => 0 , 'message' => config('message.')[0],'url' => '/manage/index'];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
        //查询权限组
        $authGroupData = $authGroup->getAuthGroup();
        return $this->fetch('',[
            'authGroupData' => $authGroupData,
        ]);
    }

    /**
     * 编辑管理员
     * @param Request $request
     * @param ManageModel $manage
     * @param AuthGroup $authGroup
     * @return mixed
     * @throws \Exception
     */
    public function edit(Request $request,ManageModel $manage,AuthGroup $authGroup)
    {
        if ($request::isPost()){
            try{
                $param = $request::post();
                $param = array_filter($param);
                $check = $manage->valid($param,'edit');
                if ($check['code']) return $check;
                //检测昵称唯一性
                $unique = $manage
                    ->where([['nickname' , '=' , $param['nickname']],['manage_id' , '<>' ,$param['manage_id']]])
                    ->value('manage_id');
                if ($unique) return ['code' => -2 , 'message' => '系统已存在相同昵称,请更改'];
                if (empty($param['password'])) unset($param['password']);

                //更改管理员数据
                $manage->allowField(true)->isUpdate(true)->save($param);
                return ['code' => 0 , 'message' => config('message.')[0] ,'url' => '/manage/index'];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
        $id = $request::get('id');
        //查询管理员信息
        $data = $manage
            ->where(['manage_id' => $id])
            ->field('manage_id,nickname,phone,avatar,auth_group_id,password')
            ->find();
        $data['avatar_data'] = $data->getData('avatar');
        //查询权限组
        $authGroupData = $authGroup->getAuthGroup();
        return $this->fetch('manage/create',[
            'data' => $data,
            'authGroupData' => $authGroupData,
        ]);
    }

    /**
     * 删除管理员
     * @param Request $request
     * @param ManageModel $manage
     * @return array
     */
    public function destroy(Request $request,ManageModel $manage)
    {
        if ($request::isPost()){
            try{
                $param = $request::post('id');
                $manage::destroy($param);
                return ['code' => 0,'message' => config('message.')[0]];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 改变管理员状态
     * @param Request $request
     * @param ManageModel $manage
     * @return array
     */
    public function changeStatus(Request $request,ManageModel $manage)
    {
        if ($request::isPost()){
            try{
                $id = $request::post('id');
                $manage->changeStatus($id);
                return ['code' => 0, 'message' => config('message.')[0]];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
    }
}