<?php
declare(strict_types=1);

namespace app\client\controller;

use app\client\validate\StoreGoodsClassify as StoreGoodsClassifyValid;
use app\common\model\Adv;
use app\common\model\Goods;
use app\common\model\StoreGoodsClassify as StoreGoodsClassifyModel;
use think\Controller;
use think\exception\ValidateException;
use think\facade\Request;
use think\facade\Session;

class StoreGoodsClassify extends Controller
{

    /**
     * 商家商品分类列表
     *
     * @param Request $request
     * @param StoreGoodsClassifyModel $storeGoodsClassify
     *
     * @return \Exception|mixed
     * @throws \think\exception\DbException
     */
    public function index(Request $request, StoreGoodsClassifyModel $storeGoodsClassify)
    {
        // 获取参数
        $param = $request::get();

        // 如果未设置 分类ID 或分类ID 是第一级
        if (!isset($param['classify_id']) || $param['classify_id'] == 0) {
            $condition = [
                ['parent_id', '=', 0],
                ['store_id','=', Session::get('client_store_id')]
            ];
        } else {
            $condition = [
                ['parent_id', '=', $param['classify_id']],
                ['store_id','=', Session::get('client_store_id')]
            ];
        }

        // 分类层级
        if (!isset($param['level'])) {
            $_level = 1;
            $condition = [
                ['parent_id', '=', 0],
                ['store_id','=', Session::get('client_store_id')]
            ];
        } else {
            $_level = $param['level'];
        }

        // 父ID
        $data = $storeGoodsClassify
            ->where($condition)
            ->field('keyword,update_time', true)
            ->order(['store_goods_classify_id' => 'desc'])
            ->paginate(15, false, ['query' => $param]);

        // 上级分类名称
        if ($_level != 1) {
            $_parentClassify = $storeGoodsClassify->where([
                ['store_goods_classify_id', '=', $param['classify_id']],
            ])->find();
        }

//        } catch (\Exception $e) {
//            return ['code' => -100, 'message' => $e->getMessage()];
//        }

        return $this->fetch('', [
            'data' => $data,
            'classify_list' => find_level($storeGoodsClassify->field('store_goods_classify_id,title,parent_id')->select()->toArray(), 'store_goods_classify_id'),
            'level' => $_level,
            'parent_classify' => $_parentClassify ?? [],
        ]);
    }

    /**
     * 商品分类新增
     *
     * @param Request $request
     * @param StoreGoodsClassifyModel $StoreGoodsClassifyModel
     * @param Adv $adv
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, StoreGoodsClassifyModel $StoreGoodsClassifyModel)
    {

        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();

                // 验证
                $param['store_id'] = Session::get('client_store_id');
                $check = $StoreGoodsClassifyModel->valid($param, 'create');
                if ($check['code']) {
                    return $check;
                }
                $hotCount = $StoreGoodsClassifyModel->where([['store_id', 'eq', Session::get('client_store_id')],['is_hot', 'eq', 1]])->count('store_goods_classify_id');
                if ($param['is_hot'] == 1 && $hotCount >= 4) return ['code' => -100, 'message' => '热推最多开启4个'];
                $state = $StoreGoodsClassifyModel->allowField(TRUE)->save($param);

                if ($state) {
                    return [
                        'code' => 0,
                        'message' => config('message.')[0],
                        'url' => '/client/store_goods_classify/index?classify_id=' . $param['parent_id'] . '&level=' . $param['level'],
                    ];
                }

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
        $parentData = $StoreGoodsClassifyModel->where([
            ['store_goods_classify_id', 'eq', $request::get('classify_id')]
        ])->field('title,store_goods_classify_id')->find();

        return $this->fetch(
            '', [
                'classify_list' => $StoreGoodsClassifyModel->where(
                    ['count' => 1, 'store_id' => Session::get('client_store_id')]
                )->field('store_goods_classify_id,title,parent_id')->select()->toArray(),
                'parent_data' => $parentData,
            ]
        );
    }

    /**
     * 商品分类编辑
     *
     * @param Request $request
     * @param StoreGoodsClassifyModel $StoreGoodsClassifyModel
     * @param Adv $adv
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, StoreGoodsClassifyModel $StoreGoodsClassifyModel)
    {

        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();
                $param['store_id'] = Session::get('client_store_id');

                // 验证
                $check = $StoreGoodsClassifyModel->valid($param, 'edit');
                if ($check['code']) {
                    return $check;
                }
                $hotCount = $StoreGoodsClassifyModel->where([['store_id', 'eq', Session::get('client_store_id')],['is_hot', 'eq', 1]])->count('store_goods_classify_id');
                if ($param['is_hot'] == 1 && $hotCount >= 4) return ['code' => -100, 'message' => '热推最多开启4个'];
                $state = $StoreGoodsClassifyModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state) {
                    return [
                        'code' => 0,
                        'message' => config('message.')[0],
                        'url' => '/client/store_goods_classify/index?classify_id=' . $param['parent_id'] . '&level=' . $param['level'],
                    ];
                }

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }

        return $this->fetch(
            'create', [
                'item' => $StoreGoodsClassifyModel->get($request::get('id')),
                'classify_id' => $request::get('classify_id'),
                'classify_list' => $StoreGoodsClassifyModel->where(
                    ['count' => 1, 'store_id' => Session::get('client_store_id')]
                )->field('store_goods_classify_id,title,parent_id')->select()->toArray(),

            ]
        );
    }

    /**
     * 删除商品分类
     *
     * @param Request $request
     * @param StoreGoodsClassifyModel $StoreGoodsClassifyModel
     * @param Goods $goods
     *
     * @return array
     */
    public function destroy(Request $request, StoreGoodsClassifyModel $StoreGoodsClassifyModel, Goods $goods)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post('id');
                // 获取店铺下是否还有子分类
                if (!empty($StoreGoodsClassifyModel->where('parent_id', $param)->value('store_goods_classify_id'))) {
                    return ['code' => -100, 'message' => '该分类下还有子分类，请先删除其子分类'];
                }
                // 检测分类下是否含有商品
                if (!empty($goods->where([['store_goods_classify_id', '=', $param]])->column('goods_id'))) {
                    return ['code' => -100, 'message' => '该分类下含有商品,请先更改对应商品的分类关联'];
                }
                // 删除分类
                $StoreGoodsClassifyModel::destroy($param);

                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }

    /**
     * 商品分类显示状态更新
     *
     * @param Request $request
     * @param StoreGoodsClassifyModel $StoreGoodsClassifyModel
     *
     * @optimization Malson 2019-03-13 15:20
     *
     * @return array
     */
    public function auditing(Request $request, StoreGoodsClassifyModel $StoreGoodsClassifyModel)
    {
        if ($request::isPost()) {
            try {

                // 修改热推状态
                if ($request::post('type') == 'is_hot') {
                    $count = $StoreGoodsClassifyModel->where(
                        [
                            ['status', 'eq', 1],
                            ['is_hot', 'eq', 1],
                            ['parent_id', 'eq', 0],
                            ['store_id', 'eq', Session::get('client_store_id')],
                            ['store_goods_classify_id', 'neq', $request::post('id')],
                        ]
                    )->count();

                    if ($count == 0) {
                        return [
                            'code' => -100,
                            'message' => '热门分类至少一个',
                            'url' => '/client//store_goods_classify/index',
                        ];
                    }
                }
                // 修改数据库状态
                $StoreGoodsClassifyModel->changeStatus($request::post('id'), $request::post('type'));

                // 修改显示状态
                if ($request::post('type') == 'status') {//如果修改是否显示
                    //一级分类变成不显示
                    $status = $StoreGoodsClassifyModel->where('store_goods_classify_id', $request::post('id'))->value(
                        'status'
                    );
                    if ($status == 0) {
                        //下面所有得子分类都变成不显示
                        $StoreGoodsClassifyModel->where('parent_id', $request::post('id'))->update(['status' => 0]);
                    } else {
                        //一级分类不显示，下面的分类变成显示得话，一级分类要恢复显示
                        //找出它的上级
                        $parent = $StoreGoodsClassifyModel->where(
                            'store_goods_classify_id', $request::post('id')
                        )->value('parent_id');
                        if ($parent != '') {
                            $StoreGoodsClassifyModel->where('store_goods_classify_id', $parent)->update(
                                ['status' => 1]
                            );
                        }
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }
    }

    /**
     * 商品分类排序更新
     *
     * @param Request $request
     * @param StoreGoodsClassifyModel $StoreGoodsClassifyModel
     *
     * @optimization Malson 2019-03-13 16:12
     * @return array
     */
    public function text_update(Request $request, StoreGoodsClassifyModel $StoreGoodsClassifyModel)
    {

        if ($request::isPost()) {
            try {
                $StoreGoodsClassifyModel->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }
    }


    public function fastCreate(StoreGoodsClassifyModel $StoreGoodsClassifyModel)
    {

        if ($this->request->isPost()) {
            $_param = $this->request->post();

            $_data = [];

            $_parentId = $this->request->get('parent_id', 0);

            try {
                // 将发送过来的数组分组
                foreach ($_param['title'] as $key => $row) {
                    $_data[] = [
                        'title' => $row,
                        'sort' => $_param['sort'][$key],
                        'status' => $_param['status'][$key],
                        'parent_id' => $_parentId,
                        'store_id' => Session::get('client_store_id'),
                        'count' => $this->request->get('level', 1)
                    ];
                }

                $_valid = new StoreGoodsClassifyValid();
                // 循环验证 如果有错误则返回
                foreach ($_data as $row) {
                    if (!$_valid->scene('fast_create')->check($row)) {
                        throw new ValidateException($_valid->getError());
                    }
                }

                $StoreGoodsClassifyModel->saveAll($_data);

                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }

        $this->assign($this->request->get('level', '1'));

        return $this->fetch();
    }

    public function fastCreateGoods(StoreGoodsClassifyModel $StoreGoodsClassifyModel)
    {

        if ($this->request->isPost()) {
            $_param = $this->request->post();

            $_data = [];

            $_parentId = $this->request->get('parent_id', 0);
            $_level = $this->request->get('level', '1');
            try {
                // 将发送过来的数组分组
                foreach ($_param['title'] as $key => $row) {
                    $_data[] = [
                        'title' => $row,
                        'sort' => $_param['sort'][$key],
                        'status' => $_param['status'][$key],
                        'parent_id' => $_parentId,
                        'store_id' => Session::get('client_store_id'),
                        'count' => $this->request->get('level', 1)
                    ];
                }

                $_valid = new StoreGoodsClassifyValid();
                // 循环验证 如果有错误则返回
                foreach ($_data as $row) {
                    if (!$_valid->scene('fast_create')->check($row)) {
                        throw new ValidateException($_valid->getError());
                    }
                }

                $StoreGoodsClassifyModel->saveAll($_data);

                return ['code' => 0,'level'=>$_level,'parentId'=>$_parentId, 'message' => config('message.')[0]];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }

        $this->assign($this->request->get('level', '1'));

        return $this->fetch();
    }

}