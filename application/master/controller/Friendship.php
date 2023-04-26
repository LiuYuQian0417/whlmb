<?php
declare(strict_types = 1);

namespace app\master\controller;

use think\Controller;
use app\common\model\FriendshipLink as FriendshipLinkModel;
use think\facade\Request;

class Friendship extends Controller
{
    /**
     * 友情链接列表
     * @param Request $request
     * @param FriendshipLinkModel $friendshipLink
     * @return array|mixed
     */
    public function index(Request $request, FriendshipLinkModel $friendshipLink)
    {

        try {
            // 获取数据
            $param = $request::get();

            // 条件筛选
            $condition[] = ['friendship_link_id', '>', 0];
            if (!empty($param['keyword'])) $condition[] = ['friendship_title','like' ,'%' . $param['keyword'] . '%'];
            // 获取数据
            $data = $friendshipLink->where($condition)
                ->field('update_time,delete_time', true)
                ->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }


        return $this->fetch('', [
            'data' => $data
        ]);
    }


    /**
     * 创建友情链接
     * @param Request $request
     * @param FriendshipLinkModel $friendshipLink
     * @return array|mixed
     */
    public function create(Request $request, FriendshipLinkModel $friendshipLink)
    {
        if ($request::isPost()){

            try {
                // 获取数据
                $param = $request::post();

                // 验证
                $check = $friendshipLink->valid($param,'create');
                if ($check['code']) return $check;

                // 写入
                $operation = $friendshipLink->allowField(true)->save($param);
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/friendship/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('',[

        ]);
    }


    /**
     * 编辑友情链接
     * @param Request $request
     * @param FriendshipLinkModel $friendshipLink
     * @return array|mixed
     */
    public function edit(Request $request,FriendshipLinkModel $friendshipLink)
    {
        if ($request::isPost()){

            try {
               // 获取数据
               $param = $request::post();

                // 验证
                $check = $friendshipLink->valid($param,'edit');
                if ($check['code']) return $check;

                // 写入
                $operation = $friendshipLink->allowField(true)->isUpdate(true)->save($param);
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/friendship/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch('create',[
           'item'=> $friendshipLink::get($request::get('friendship_link_id')),
        ]);
    }


    /**
     * 删除友情链接
     * @param Request $request
     * @param FriendshipLinkModel $friendshipLink
     * @return array
     */
    public function destroy(Request $request,FriendshipLinkModel $friendshipLink)
    {
        if ($request::isPost()){
            try{
                // 删除
                $friendshipLink::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            }catch (\Exception $e){

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}