<?php
// 商品品牌分类
declare(strict_types=1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use think\Db;
use app\common\model\BrandClassify as BrandClassifyModel;
use app\common\model\Store;

class BrandClassify extends Controller
{
    /**
     * 品牌分类列表
     * @param Request $request
     * @param BrandClassifyModel $brandClassify
     * @return array|mixed
     */
    public function index(Request $request, BrandClassifyModel $brandClassify)
    {
        try {
            // 获取数据
            $param = $request::param();

            // 筛选条件
            $condition[] = ['brand_classify_id', '>', 0];
            if (!empty($param['keyword'])) $condition[] = ['brand_classify_name|description', 'like', '%' . $param['keyword'] . '%'];

            // 获取数据
            $data = $brandClassify->where($condition)->field('update_time,delete_time', TRUE)->paginate(10, FALSE, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }


    /**
     * 创建商品分类
     * @param Request $request
     * @param BrandClassifyModel $brandClassify
     * @return array|mixed
     */
    public function create(Request $request, BrandClassifyModel $brandClassify, Store $store)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();

                // 验证
                $check = $brandClassify->valid($param, 'create');
                if ($check['code']) return $check;
                // 写入
                $brandClassify->allowField(TRUE)->save($param);
                if (array_key_exists('store_id', $param) && $param['store_id']) {
                    $store->editBrandClassify($param['store_id'], $brandClassify->brand_classify_id);
                }
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/brand_classify/index'];

            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $_singleStore = config('user.one_more');

        return $this->fetch('', [
            'storeList'    => $store->where([
                ['status', '=', 4],
            ])->where('brand_classify_id', 'null')->field('store_id,brand_classify_id,store_name')->select(),
            'single_store' => $_singleStore,
        ]);
    }


    /**
     * 编辑商品分类
     * @param Request $request
     * @param BrandClassifyModel $brandClassify
     * @return array|mixed
     */
    public function edit(Request $request, BrandClassifyModel $brandClassify, Store $store)
    {
        if ($request::isPost()) {

            try {
                // 获取数据
                $param = $request::post();

                // 单店
                if (config('user.one_more') == 0){
                    $param['store_id'] = config('user.one_store_id');
                }

                // 验证
                $check = $brandClassify->valid($param, 'edit');
                if ($check['code']) return $check;

                // 更新
                $operation = $brandClassify->allowField(TRUE)->isUpdate(TRUE)->save($param);
                $store->editBrandClassify($param['store_id'], $param['brand_classify_id']);
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/brand_classify/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $_singleStore = config('user.one_more');

        return $this->fetch('create', [
            'item'      => $brandClassify::get($request::get('id')),
            'storeList' => $store::where(['status' => 4, 'shop' => 0])->field('store_id,brand_classify_id,store_name')->select(),
            'single_store' => $_singleStore,
        ]);
    }


    /**
     * 删除品牌分类
     * @param Request $request
     * @param BrandClassifyModel $brandClassify
     * @return array
     */
    public function destroy(Request $request, BrandClassifyModel $brandClassify , Store $store)
    {
        if ($request::isPost()) {
            // 启动事务
            Db::startTrans();
            try {
                // 删除
                $brandClassify::destroy($request::post('id'));

                $store_find  =  $store->where([
                    ['brand_classify_id', '=', $request::post('id')],
                ])->find();

                if(!empty($store_find)){
                    $store->where([
                        ['store_id', '=', $store_find['store_id']],
                        ['brand_classify_id', '=', $request::post('id')],
                    ])->update(['brand_classify_id'=>Null]);
                }

                // 提交事务
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 更新显示状态
     * @param Request $request
     * @param BrandClassifyModel $brandClassify
     * @return array
     */
    public function auditing(Request $request, BrandClassifyModel $brandClassify)
    {

        if ($request::isPost()) {
            try {
                $brandClassify->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 更新排序
     * @param Request $request
     * @param BrandClassifyModel $brandClassify
     * @return array
     */
    public function text_update(Request $request, BrandClassifyModel $brandClassify)
    {

        if ($request::isPost()) {
            try {
                $brandClassify->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}