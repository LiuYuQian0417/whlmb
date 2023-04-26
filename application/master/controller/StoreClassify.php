<?php
declare(strict_types=1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\StoreClassify as StoreClassifyModel;
use app\common\model\Adv;

class StoreClassify extends Controller
{

    /**
     * 商家分类列表
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @return \Exception|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, StoreClassifyModel $StoreClassifyModel)
    {

        // 获取参数
        $param = $request::get();
        // 父ID
        $data = $StoreClassifyModel
            ->field('store_classify_id,title,sort,is_recommend,status')
            ->order(['sort' => 'desc','store_classify_id' => 'asc'])
            ->paginate(15, false, ['query' => $param]);

        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 商品分类新增
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, StoreClassifyModel $storeClassify)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();
                // 验证
                $check = $storeClassify->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $storeClassify->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/store_classify/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('', [
        ]);
    }

    /**
     * 商品分类编辑
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, StoreClassifyModel $storeClassify)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $storeClassify->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $storeClassify->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/store_classify/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('create', [
            'item' => $storeClassify->get($request::get('store_classify_id'))
        ]);
    }

    /**
     * 删除商品分类
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @return array
     */
    public function destroy(Request $request, StoreClassifyModel $storeClassify)
    {
        if ($request::isPost()) {

            try {

                $param = $request::post('id');
                $storeClassify::destroy($param);

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 商品分类显示状态更新
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @return array
     */
    public function auditing(Request $request, StoreClassifyModel $storeClassify)
    {

        if ($request::isPost()) {
            try {
                $storeClassify->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 商品分类排序更新
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @return array
     */
    public function text_update(Request $request, StoreClassifyModel $storeClassify)
    {

        if ($request::isPost()) {
            try {
                $storeClassify->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}