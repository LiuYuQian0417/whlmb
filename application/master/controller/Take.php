<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\Store;
use think\Controller;
use think\exception\ValidateException;
use think\facade\Request;
use app\common\model\Take as TakeModel;

class Take extends Controller
{

    /**
     * 自提点列表
     * @param Request $request
     * @param TakeModel $takeModel
     * @return array|mixed
     */
    public function index(Request $request, TakeModel $takeModel)
    {
        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['a.store_id', '>', 0];

            $_singleShop = config('user.one_more');

            if ($_singleShop != 1) {
                $_oneStoreId = config('user.one_store_id');

                $condition = [
                    ['a.store_id', '=', $_oneStoreId],
                ];
            }

            // 父ID
            if (!empty($param['keyword'])) $condition[] = ['take_name', 'like', '%' . $param['keyword'] . '%'];

            $data = $takeModel
                ->alias('a')
                ->join('store store', 'a.store_id = store.store_id', 'right')
                ->where($condition)
                ->field('a.*,store.store_name')
                ->order(['take_id' => 'desc'])
                ->paginate(15, FALSE, ['query' => $param]);

        } catch (\Exception $e) {
            $this->error(config('message.')[-1]);
            return;
        }

        return $this->fetch('', [
            'data'        => $data,
            'single_shop' => $_singleShop,
        ]);
    }

    /**
     * 自提点新增
     * @param Request $request
     * @param TakeModel $takeModel
     * @return array|mixed
     */
    public function create(Request $request, TakeModel $takeModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $takeModel->valid($param, 'create');
                if ($check['code']) return $check;

                $_workTime = explode(' - ', $param['work_time']);

                if (count($_workTime) < 2) {
                    throw new \Exception('请选择正确的营业时间');
                }

                $param['start_hours'] = $_workTime[0];
                $param['end_hours'] = $_workTime[1];

                if (strtotime($param['start_hours']) >= strtotime($param['end_hours'])) {
                    throw new \Exception('闭店时间不能早于开店时间');
                }

                if (!isset($param['store_id']) || empty($param['store_id'])) {
                    return ['code' => -100, 'message' => '请选择店铺'];
                }

                if (!isset($param['week'])) {
                    return ['code' => -100, 'message' => '请选择每星期中的营业日期'];
                }

                $param['week'] = implode(',', $param['week']);

                $state = $takeModel->allowField(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/take/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $_storeList = Store::all();

        $this->assign('store_list', $_storeList);
        $this->assign('page_type', 'create');
        $this->assign('single_shop', config('user.one_more'));
        $this->assign('one_store_id', config('user.one_store_id'));

        return $this->fetch('');
    }

    /**
     * 自提点编辑
     * @param Request $request
     * @param TakeModel $takeModel
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, TakeModel $takeModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $takeModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $_workTime = explode(' - ', $param['work_time']);

                if (count($_workTime) < 2) {
                    throw new \Exception('请选择正确的营业时间');
                }

                $param['start_hours'] = $_workTime[0];
                $param['end_hours'] = $_workTime[1];

                if (strtotime($param['start_hours']) >= strtotime($param['end_hours'])) {
                    throw new \Exception('闭店时间不能早于开店时间');
                }

                $param['week'] = implode(',', $param['week']);

                $state = $takeModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/take/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $this->assign('page_type', 'edit');
        $this->assign('single_shop', config('user.one_more'));
        $this->assign('one_store_id', config('user.one_store_id'));

        return $this->fetch('create', [
            'item' => $takeModel->with('store')->find($request::get('take_id')),
        ]);
    }

    /**
     * 删除自提点
     * @param Request $request
     * @param TakeModel $takeModel
     * @return array
     */
    public function destroy(Request $request, TakeModel $takeModel)
    {
        if ($request::isPost()) {

            try {

                $takeModel::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 显示状态更新
     * @param Request $request
     * @param TakeModel $takeModel
     * @return array
     */
    public function auditing(Request $request, TakeModel $takeModel)
    {

        if ($request::isPost()) {
            try {
                $takeModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}