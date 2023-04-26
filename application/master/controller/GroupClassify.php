<?php
// 团购商品分类
declare(strict_types = 1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use app\common\model\GroupClassify as GroupClassifyModel;
use app\common\model\GroupGoods;

class GroupClassify extends Controller
{
    /**
     * 团购商品分类
     * @param Request $request
     * @param GroupClassifyModel $groupClassify
     * @return array|mixed
     */
    public function index(Request $request, GroupClassifyModel $groupClassify)
    {
        try {
            // 获取参数
            $param = $request::get();

            // 筛选条件
            $condition[] = ['parent_id', 'eq', 0];
            if (!empty($param['classify_id'])) $condition = [['parent_id', '=', $param['classify_id']]];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            // 获取数据
            $data = $groupClassify
                ->where($condition)
                ->field('group_classify_id,title,create_time')
                ->order('create_time', 'desc')
                ->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
            'classify_id'   =>  Request::get('classify_id', 0)
        ]);
    }


    /**
     * 创建团购商品分类
     * @param Request $request
     * @param GroupClassifyModel $groupClassify
     * @return array|mixed
     */
    public function create(Request $request, GroupClassifyModel $groupClassify)
    {
        if ($request::isPost()) {
            try {
                // 获取数据
                $param = $request::post();

                // 验证
                $check = $groupClassify->valid($param, 'create');
                if ($check['code']) return $check;

                // 写入
                $operation = $groupClassify->allowField(true)->save($param);

                // 跳转
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/group_classify/index?classify_id='.Request::get('classify_id','')];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }
        $condition[] = ['parent_id','=',0];
        return $this->fetch('',[
            'classify_list' => $groupClassify->where($condition)
                ->field('group_classify_id,title,parent_id')->select()->toArray(),
        ]);
    }


    /**
     * 编辑团购分类
     * @param Request $request
     * @param GroupClassifyModel $groupClassify
     * @return array|mixed
     */
    public function edit(Request $request, GroupClassifyModel $groupClassify)
    {
        if ($request::isPost()) {

            try {
                // 获取参数
                $param = $request::post();

                // 验证
                $check = $groupClassify->valid($param, 'edit');
                if ($check['code']) return $check;

                // 写入
                $operation = $groupClassify->allowField(true)->isUpdate(true)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/group_classify/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $condition[] = ['parent_id','=',0];
        return $this->fetch('create', [
            'item' => $groupClassify::get($request::get('group_classify_id')),
            'classify_list' => $groupClassify->where($condition)
                ->field('group_classify_id,title,parent_id')->select()->toArray(),
        ]);
    }

    /**
     * 删除团购分类
     * @param Request $request
     * @param GroupClassifyModel $groupClassify
     * @return array
     */
    public function destroy(Request $request, GroupClassifyModel $groupClassify, GroupGoods $groupGoods)
    {
        if ($request::isPost()) {
            try {

                $param = $request::post('id');

                $group_classify_id = $groupClassify->where('parent_id', $param)->value('group_classify_id');

                if (!empty($group_classify_id)) return ['code' => -100, 'message' => '该分类下还有子分类，请先删除其子分类'];

                $result = $groupGoods->where([
                    ['group_classify_id', '=', $param],
                    ['status', '=', 1],
                ])
                    ->append(['ActivityText'])
                    ->select();
                foreach ($result as $k => $v) {
                    if ($v['ActivityText'] == '进行中') return ['code' => -100, 'message' => '该分类下有活动进行中的商品，删除失败'];
                }

                $groupClassify::destroy($param);

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


}