<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/24 0024
 * Time: 10:11
 */

namespace app\client\controller;


use app\common\model\StoreAddress;
use think\Controller;
use app\common\model\Area as AreaModel;
use think\Db;
use think\facade\Session;

/**
 * 地址库管理
 *
 * Class DeliverySettings
 * @package app\client\controller
 */
class DeliverySettings extends Controller
{
    /**
     * 首页
     */
    public function index()
    {
        $_data = StoreAddress::_GetListForConsole();

        $this->assign('data', $_data);
        $this->assign('render', $_data->render());
        return $this->fetch();
    }

    /**
     * 创建
     * @param StoreAddress $storeAddress
     * @param AreaModel $area
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(StoreAddress $storeAddress, AreaModel $area)
    {
        if ($this->request->isPost()) {
            try {

                $param = $this->request->post();

                $check = $storeAddress->valid($param, 'client_create');
                if ($check['code']) return $check;

                if (empty($param['phone_number']) && (empty($param['telephone'][1]) && empty($param['telephone'][2]))) {
                    return ['code' => -2, 'message' => '联系电话与座机至少填写一项'];
                }

                // 保存到数据库的地址

                $_result = StoreAddress::_CreateForConsole($param);

                if ($_result !== true) {
                    throw new \Exception($_result);
                }

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/delivery_settings/index'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $this->assign('areas', $area->where(['parent_id' => 0])->field('area_id,area_name')->select());
        return $this->fetch();
    }

    /**
     *
     * 编辑
     * @param StoreAddress $storeAddress
     * @param AreaModel $area
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(StoreAddress $storeAddress, AreaModel $area)
    {
        if ($this->request->isPost()) {
            try {

                $param = $this->request->post();

                $check = $storeAddress->valid($param, 'client_edit');
                if ($check['code']) return $check;

                if (empty($param['phone_number']) && (empty($param['telephone'][1]) && empty($param['telephone'][2]))) {
                    return ['code' => -2, 'message' => '联系电话与座机至少填写一项'];
                }

                // 保存到数据库的地址

                $_result = StoreAddress::_UpdateForConsole($param);

                if ($_result !== true) {
                    throw new \Exception($_result);
                }

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/delivery_settings/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $this->assign('item', StoreAddress::_GetForEditForConsole($this->request->get('id')));
        $this->assign('areas', $area->where(['parent_id' => 0])->field('area_id,area_name')->select());

        return $this->fetch('create');
    }


    /**
     * 删除
     *
     * @return array
     */
    public function destroy()
    {
        try {
            Db::startTrans();
            $_id = $this->request->post('id');

            $_row = StoreAddress::get($_id);

            // 如果是默认地址
            if ($_row['default_return_address'] == 1) {
                // 构建语句
                $_where = [
                    ['store_address_id', '<>', $_id],
                    ['default_return_address', '=', 2],
                ];
                $_addressWillBeDefault = StoreAddress::where($_where)->order('store_address_id DESC')->find();

                // 如果存在数据
                if (!empty($_addressWillBeDefault)) {
                    // 将他设置为默认
                    $_addressWillBeDefault->save([
                                                     'default_return_address' => 1
                                                 ]);
                }
            }

            // 删除当前选择的地址
            StoreAddress::destroy($this->request->post('id'));

            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/delivery_settings/index'];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

}