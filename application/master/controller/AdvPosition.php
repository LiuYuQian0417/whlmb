<?php
declare(strict_types = 1);
namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\AdvPosition as AdPositionModel;
use app\common\service\OSS as OssModel;

class AdvPosition extends Controller
{

    /**
     * 广告投放端列表
     * @param AdPositionModel $adPositionModel
     * @return \Exception|mixed
     */
    public function index(AdPositionModel $adPositionModel)
    {


        try {

            $data = $adPositionModel
                ->where('parent_id', '=', 0)
                ->field('update_time', true)
                ->order(['adv_position_id' => 'asc'])
                ->select();

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 广告位置列表
     * @param Request $request
     * @param AdPositionModel $adPositionModel
     * @return \Exception|mixed
     */
    public function ad_list(Request $request, AdPositionModel $adPositionModel)
    {


        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['parent_id', '=', $param['classify_id']];
            // 搜索
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            $data = $adPositionModel
                ->where($condition)
                ->field('update_time', true)
                ->order(['adv_position_id' => 'asc'])
                ->paginate(15, false, ['query' => $param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 广告位置新增
     * @param Request $request
     * @param AdPositionModel $adPositionModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, AdPositionModel $adPositionModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $adPositionModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $adPositionModel->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/adv_position/ad_list?classify_id=' . $param['parent_id']];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('', [
            'classify_list' => $adPositionModel->field('adv_position_id,title')->where('parent_id', 0)->select()
        ]);
    }

    /**
     * 广告位置编辑
     * @param Request $request
     * @param AdPositionModel $adPositionModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, AdPositionModel $adPositionModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $adPositionModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $adPositionModel->allowField(true)->isUpdate(true)->save($param);



                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/adv_position/ad_list?classify_id='.$param['parent_id']];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('create', [
            'classify_list' => $adPositionModel->field('adv_position_id,title')->where('parent_id', 0)->select(),
            'item'          => $adPositionModel->get($request::get('adv_position_id'))
        ]);
    }

}