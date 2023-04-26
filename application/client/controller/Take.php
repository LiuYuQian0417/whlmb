<?php
// 自提点
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\Area as AreaModel;
use app\common\model\Store;
use app\common\model\Take as TakeModel;
use think\Controller;
use think\facade\Request;
use think\facade\Session;

class Take extends Controller
{

    private $_week = [
        '周一',
        '周二',
        '周三',
        '周四',
        '周五',
        '周六',
        '周日',
    ];

    /**
     * 店铺自提列表
     *
     * @param Request   $request
     * @param TakeModel $takeModel
     *
     * @param Store     $store
     *
     * @return array|mixed
     */
    public function index(Request $request, TakeModel $takeModel, Store $store)
    {

        try
        {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['store_id', '=', Session::get('client_store_id')];

            // 父ID
            if (!empty($param['keyword']))
            {
                $condition[] = ['take_name', 'like', '%' . $param['keyword'] . '%'];
            }

            $data = $takeModel->where($condition)->field(
                'province,city,area,member_id,lat,lng,create_time,update_time', TRUE
            )->order(['take_id' => 'desc'])->paginate(15, FALSE, ['query' => $param]);
            $coordinate = $store->field(
                [
                    'is_shop',
                    'is_delivery',
                    'is_city',
                ]
            )->find(Session::get('client_store_id'));

        } catch (\Exception $e)
        {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch(
            '', [
            'data'       => $data,
            'render'     => $data->render(),
            'coordinate' => $coordinate,
        ]
        );
    }


    /**
     * 创建自提点（门店）
     *
     * @param Request   $request
     * @param TakeModel $takeModel
     * @param AreaModel $area
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, TakeModel $takeModel, AreaModel $area)
    {
        if ($request::isPost())
        {

            try
            {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $takeModel->valid($param, 'create');
                if ($check['code'])
                {
                    return $check;
                }

                $_workTime = explode(' - ', $param['work_time']);

                if (count($_workTime) < 2)
                {
                    throw new \Exception('请选择正确的营业时间');
                }

                $param['start_hours'] = $_workTime[0];
                $param['end_hours'] = $_workTime[1];

                if (strtotime($param['start_hours']) >= strtotime($param['end_hours']))
                {
                    throw new \Exception('闭店时间不能早于开店时间');
                }

                if (!isset($param['week']) || count($param['week']) < 1)
                {
                    throw new \Exception('请选择每周营业日期');
                }

                $param['week'] = join(',', $param['week']);
                $param['store_id'] = Session::get('client_store_id');
                $state = $takeModel->allowField(TRUE)->save($param);

                if ($state)
                {
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/take/index'];
                }

            } catch (\Exception $e)
            {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch(
            '', [
            'areas'        => $area->where(['parent_id' => 0])->field('area_id,area_name')->select(),
            'week'         => $this->_week,
            'selectedWeek' => [],
        ]
        );
    }


    /**
     * 编辑自提点（门店）
     *
     * @param Request   $request
     * @param TakeModel $takeModel
     * @param AreaModel $area
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, TakeModel $takeModel, AreaModel $area)
    {
        if ($request::isPost())
        {

            try
            {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $takeModel->valid($param, 'edit');
                if ($check['code'])
                {
                    return $check;
                }

                $_workTime = explode(' - ', $param['work_time']);

                if (count($_workTime) < 2)
                {
                    throw new \Exception('请选择正确的营业时间');
                }

                $param['start_hours'] = $_workTime[0];
                $param['end_hours'] = $_workTime[1];

                if (strtotime($param['start_hours']) >= strtotime($param['end_hours']))
                {
                    throw new \Exception('闭店时间不能早于开店时间');
                }

                if (!isset($param['week']) || count($param['week']) < 1)
                {
                    throw new \Exception('请选择每周营业日期');
                }

                $param['week'] = join(',', $param['week']);

                $param['store_id'] = Session::get('client_store_id');


                $state = $takeModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state)
                {
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/take/index'];
                }

            } catch (\Exception $e)
            {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $_item = $takeModel->get($request::get('id'));

        $_selectedWeek = explode(',', $_item['week']);

        return $this->fetch(
            'create', [
            'item'         => $_item,
            'areas'        => $area->where(['parent_id' => 0])->field('area_id,area_name')->select(),
            'week'         => $this->_week,
            'selectedWeek' => $_selectedWeek,
        ]
        );
    }


    /**
     * 删除自提点
     *
     * @param Request   $request
     * @param TakeModel $takeModel
     *
     * @return array
     */
    public function destroy(Request $request, TakeModel $takeModel)
    {
        try
        {
            $takeModel::destroy($request::post('id'));

            return ['code' => 0, 'message' => config('message.')[0]];

        } catch (\Exception $e)
        {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }
}
