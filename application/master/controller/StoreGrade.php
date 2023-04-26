<?php
declare(strict_types = 1);
namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\StoreGrade as StoreGradeModel;
use app\common\service\OSS as OssModel;

class StoreGrade extends Controller
{

    /**
     * 店铺等级列表
     * @param Request $request
     * @param StoreGradeModel $StoreGradeModel
     * @return array|mixed
     */
    public function index(Request $request, StoreGradeModel $StoreGradeModel)
    {


        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['store_grade_id', '>', 0];

            //
            if (!empty($param['keyword'])) $condition[] = ['name', 'like', '%' . $param['keyword'] . '%'];

            $data = $StoreGradeModel
                ->where($condition)
                ->field('update_time', true)
                ->order(['store_grade_id' => 'asc'])
                ->paginate(15, false, ['query' => $param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 店铺等级新增
     * @param Request $request
     * @param StoreGradeModel $StoreGradeModel
     * @return array|mixed
     */
    public function create(Request $request, StoreGradeModel $StoreGradeModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $StoreGradeModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $StoreGradeModel->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/store_grade/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('');
    }

    /**
     * 店铺等级分类编辑
     * @param Request $request
     * @param StoreGradeModel $StoreGradeModel
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, StoreGradeModel $StoreGradeModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $StoreGradeModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $StoreGradeModel->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/store_grade/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('create', [
            'item' => $StoreGradeModel->get($request::get('store_grade_id'))
        ]);
    }

    /**
     * 删除店铺等级分类
     * @param Request $request
     * @param StoreGradeModel $StoreGradeModel
     * @return array
     */
    public function destroy(Request $request, StoreGradeModel $StoreGradeModel)
    {
        if ($request::isPost()) {

            try {

                $StoreGradeModel::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 店铺等级分类显示状态更新
     * @param Request $request
     * @param StoreGradeModel $StoreGradeModel
     * @return array
     */
    public function auditing(Request $request, StoreGradeModel $StoreGradeModel)
    {

        if ($request::isPost()) {
            try {
                $StoreGradeModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 店铺等级分类排序更新
     * @param Request $request
     * @param StoreGradeModel $StoreGradeModel
     * @return array
     */
    public function text_update(Request $request, StoreGradeModel $StoreGradeModel)
    {

        if ($request::isPost()) {
            try {
                $StoreGradeModel->clickEdit($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    public function jurisdiction()
    {
        return $this->fetch('', [

        ]);
    }

}