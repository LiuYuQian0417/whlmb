<?php
declare(strict_types = 1);
namespace app\master\controller;

use app\common\model\Manage;
use think\Controller;
use think\facade\Cache;
use think\facade\Request;
use app\common\model\AuthGroup as AuthGroupModel;
use think\facade\Session;

class AuthGroup extends Controller
{
    /**
     * 权限组列表
     * @param Request $request
     * @param AuthGroupModel $authGroup
     * @return mixed
     * @throws \Exception
     */
    public function index(Request $request,AuthGroupModel $authGroup)
    {
        try{
            $param = $request::param();
            $data = $authGroup
                ->field('delete_time',true)
                ->order(['create_time' => 'desc','auth_group_id' => 'desc'])
                ->paginate($authGroup->pageLimits,false,['query' => $param]);
            return $this->fetch('',[
                'data' => $data,
            ]);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 创建权限组
     * @param Request $request
     * @param AuthGroupModel $authGroup
     * @return array|mixed
     */
    public function create(Request $request,AuthGroupModel $authGroup)
    {
        if ($request::isPost()){
            try{
                $param = $request::post();
                $check = $authGroup->valid($param,'create');
                if ($check['code']) return $check;
                //检测标题唯一性
                $unique = $authGroup
                    ->where([['title' , '=' , $param['title']]])
                    ->value('auth_group_id');
                if ($unique) return ['code' => -2 , 'message' => '系统已存在相同标题,请更改'];
                $authGroup->allowField(true)->save($param);
                return ['code' => 0 , 'message' => config('message.')[0],'url' => '/auth_group/index'];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('');
    }

    /**
     * 编辑权限组
     * @param Request $request
     * @param AuthGroupModel $authGroup
     * @return mixed
     * @throws \Exception
     */
    public function edit(Request $request,AuthGroupModel $authGroup)
    {
        if ($request::isPost()){
            try{
                $param = $request::post();
                $param = array_filter($param);
                $check = $authGroup->valid($param,'edit');
                if ($check['code']) return $check;
                //检测标题唯一性
                $unique = $authGroup
                    ->where([['title' , '=' , $param['title']],['auth_group_id' , '<>' ,$param['auth_group_id']]])
                    ->value('auth_group_id');
                if ($unique) return ['code' => -2 , 'message' => '系统已存在相同昵称,请更改'];
                //更改权限组数据
                $authGroup->allowField(true)->isUpdate(true)->save($param);
                return ['code' => 0 , 'message' => config('message.')[0] ,'url' => '/auth_group/index'];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
        $id = $request::get('id');
        //查询管理员信息
        $data = $authGroup
            ->where(['auth_group_id' => $id])
            ->field('delete_time',true)
            ->find();
        return $this->fetch('auth_group/create',[
            'data' => $data,
        ]);
    }

    /**
     * 改变权限组状态
     * @param Request $request
     * @param AuthGroupModel $authGroup
     * @return array
     */
    public function changeStatus(Request $request,AuthGroupModel $authGroup)
    {
        if ($request::isPost()){
            try{
                $id = $request::post('id');
                $authGroup->changeStatus($id);
                return ['code' => 0 ,'message' => config('message.')[0]];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 删除权限组
     * @param Request $request
     * @param AuthGroupModel $authGroup
     * @return array
     */
    public function destroy(Request $request, AuthGroupModel $authGroup)
    {
        if ($request::isPost()){
            try{
                $param = $request::post('id');
                $authGroup::destroy($param);
                return ['code' => 0,'message' => config('message.')[0]];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 编辑权限组权限展示页
     * @return mixed
     */
    public function authEdit()
    {
        return $this->fetch('');
    }

    /**
     * 获取权限数据
     * @param Request $request
     * @param AuthGroupModel $authGroup
     * @return array
     */
    public function getAuthData(Request $request,AuthGroupModel $authGroup)
    {
        if ($request::isPost()){
            try{
                $groupId = $request::post('group');
                //查询权限组权限
                $groupAuth = $authGroup->groupRules($groupId);
                $authManage = app('app\\common\\service\\AuthManage');
                $authArr = array_values($authManage->allAuthData(1));
                //不允许选择的节点路径
                $disabledArr = ['auth_group/getAuthData'];
                $disabledStr = '';
                if ($authArr){
                    foreach ($authArr as $key => &$item){
                        if ((isset($item['url']) && in_array($item['url'],$disabledArr)) || (stripos($item['id'],'999') === 0)){
                            // 禁止勾选
                            $item['chkDisabled'] = true;
                            $item['title'] .= ' (系统内置)';
                            $disabledStr .= ',' . $item['id'];
                        }
                        if ($groupAuth === '0' || in_array($item['id'],$groupAuth)){
                            // 全部权限或在权限组权限内为勾选状态
                            $item['checked'] = true;
                        }
                        unset($item['url']);
                        if ((isset($item['flg']) && $item['flg'] == config('user.one_more'))){
                            unset($authArr[$key]);
                        }

                        $level = substr_count($item['id'],'.')+1;
                        if ($level == 1) $item['open'] = true;
                    }
                }
                return ['code' => 0 , 'message' => config('message.')[0] , 'data' => array_values($authArr) ,'disabledStr' => $disabledStr];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 保存权限组权限
     * @param Request $request
     * @param AuthGroupModel $authGroup
     * @param Manage $manage
     * @return array
     */
    public function saveAuthData(Request $request,AuthGroupModel $authGroup,Manage $manage)
    {
        if ($request::isPost()){
            try{
                $param = $request::post();
                //权限组更改新规则
                $authGroup->where(['auth_group_id' => $param['group']])->update(['rules' => trim($param['data'],',')]);
                //查询权限组包含的管理员
                $manageId = $manage::withTrashed()->where(['auth_group_id' => $param['group']])->column('manage_id');
                //查询此分类下的缓存标识
                $redisInstance = Cache::handler();
                if ($manageId){
                    $cls = [];
                    foreach ($manageId as $item){
                        //获取当前redis前缀
                        $_prefix = config('cache.')['default']['prefix'];
                        $clo = $redisInstance->keys($_prefix.'flatMaster_*_'. $item);
                        if ($clo) $cls[] = $clo;
                    }
                    if ($cls){
                        foreach ($cls as $val){
                            foreach ($val as $_val){
                                Cache::rm($_val);
                            }
                        }
                    }
                }
                return ['code' => 0 , 'message' => config('message.')[0]];
            }catch (\Exception $e){
                return ['code' => -100 , 'message' => $e->getMessage()];
            }
        }
    }
}