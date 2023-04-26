<?php
declare(strict_types = 1);
namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\MemberAddress as MemberAddressModel;
use app\common\model\Area as AreaModel;

class MemberAddress extends Controller
{

    /**
     * 城市三级联动列表
     * @param Request $request
     * @param AreaModel $areaModel
     * @return array|mixed
     */
    public function area(Request $request, AreaModel $areaModel)
    {


        try {

            // 获取参数
            $param = $request::get();

            // 父ID
            $condition[] = !empty($param['id']) ? ['parent_id', '=', $param['id']] : ['deep', '=', 1];

            $data = $areaModel
                ->where($condition)
                ->select();
        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $data;
    }

    /**
     * 会员收货地址列表
     * @param Request $request
     * @param MemberAddressModel $memberAddressModel
     * @return array|mixed
     */
    public function index(Request $request, MemberAddressModel $memberAddressModel)
    {


        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['member_address_id', '>', 0];

            // 父ID
            if (!empty($param['keyword'])) $condition[] = ['name|phone|address', 'like', '%' . $param['keyword'] . '%'];

            $data = $memberAddressModel
                ->where($condition)
                ->field('update_time', true)
                ->order(['member_address_id' => 'desc'])
                ->paginate(15, false, ['query' => $param]);
        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 会员等级编辑
     * @param Request $request
     * @param MemberAddressModel $memberAddressModel
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, MemberAddressModel $memberAddressModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $memberAddressModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $param['province'] = (new AreaModel())->where('area_id', $param['province'])->value('area_name');
                $param['city'] = (new AreaModel())->where('area_id', $param['city'])->value('area_name');
                $param['area'] = (new AreaModel())->where('area_id', $param['area'])->value('area_name');
                $param['street'] = (new AreaModel())->where('area_id', $param['street'])->value('area_name');

                $state = $memberAddressModel->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/member_address/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('', [
            'item' => $memberAddressModel->get($request::get('member_address_id'))
        ]);
    }

    /**
     * 删除会员收货地址
     * @param Request $request
     * @param MemberAddressModel $memberAddressModel
     * @return array
     */
    public function destroy(Request $request, MemberAddressModel $memberAddressModel)
    {
        if ($request::isPost()) {

            try {

                $memberAddressModel::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

}