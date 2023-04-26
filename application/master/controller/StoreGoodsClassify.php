<?php
// 店铺商品分类
declare(strict_types = 1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\StoreGoodsClassify as StoreGoodsClassifyModel;

class StoreGoodsClassify extends Controller
{
    /**
     * 店铺分类列表
     * @param Request $request
     * @param StoreGoodsClassifyModel $storeGoodsClassify
     * @return array|mixed
     */
    public function index(Request $request,StoreGoodsClassifyModel $storeGoodsClassify)
    {
        try {
            // 获取参数
            $param = $request::get();

            // 条件定义
            isset($param['classify_id'])?$condition[] = ['parent_id', '=', $param['classify_id']]:$condition[] = ['parent_id', '=', 0];
            if (!empty($param['store_id']) ) $condition[] = ['store_id','=',$param['store_id']];

            // 获取数据
            $data = $storeGoodsClassify->where($condition)->field('update_time', true)
                ->order(['store_goods_classify_id' => 'asc'])->paginate(10,false,['query'=>$param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data'          => $data,
            'classify_list' => find_level($storeGoodsClassify
                ->where($condition)->field('store_goods_classify_id,title,parent_id')->select()->toArray(), 'store_goods_classify_id'),
            'classify_id'   =>  Request::get('classify_id', 0)
        ]);
    }


    /**
     * 创建店铺商品分类
     * @param Request $request
     * @param StoreGoodsClassifyModel $storeGoodsClassify
     * @return array|mixed
     */
    public function create(Request $request,StoreGoodsClassifyModel $storeGoodsClassify)
    {
        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $storeGoodsClassify->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $storeGoodsClassify->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/store_goods_classify/index?store_id=' . $param['store_id']];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
        $where = $request::get();
        $condition[] = ['parent_id','=',0];
        if (!empty($where['store_id']) ) $condition[] = ['store_id','=',$where['store_id']];


        return $this->fetch('', [
            'classify_list' => $storeGoodsClassify->where($condition)
                ->field('store_goods_classify_id,title,parent_id')->select()->toArray(),

        ]);
    }

    /**
     * 店铺商品分类编辑
     * @param Request $request
     * @param StoreGoodsClassifyModel $storeGoodsClassify
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, StoreGoodsClassifyModel $storeGoodsClassify)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();

                // 验证器
                $check = $storeGoodsClassify->valid($param, 'edit');
                if ($check['code']) return $check;

                //编辑
                $operation = $storeGoodsClassify->allowField(true)->isUpdate(true)->save($param);


                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/store_goods_classify/index?store_id=' . $param['store_id']];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }


        }
        $condition[] = ['parent_id','=',0];

        return $this->fetch('create', [
            'classify_list' => $storeGoodsClassify->where($condition)
                ->field('store_goods_classify_id,title,parent_id')->select()->toArray(),
            'item' => $storeGoodsClassify->get($request::get('store_goods_classify_id')),
        ]);
    }

    /**
     * 店铺商品分类删除
     * @param Request $request
     * @param StoreGoodsClassifyModel $storeGoodsClassify
     * @return array
     */
    public function destroy(Request $request,StoreGoodsClassifyModel $storeGoodsClassify)
    {
        if ($request::isPost()){
            try{
                $param = $request::post('id');
                $atore_goods_classify_id = $storeGoodsClassify->where('parent_id',$param)->value('store_goods_classify_id');
                if (!empty($atore_goods_classify_id)) return ['code' => -100, 'message' => '该分类下还有子分类，请先删除其子分类'];
                // 删除
                $storeGoodsClassify::destroy($param);
                return ['code' => 0, 'message' => config('message.')[0]];

            }catch (\Exception $e){

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    public function auditing(Request $request,StoreGoodsClassifyModel $storeGoodsClassify)
    {
        if ($request::isPost()) {
            try {
                $storeGoodsClassify->changeStatus($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}