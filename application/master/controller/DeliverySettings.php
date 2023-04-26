<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5 0005
 * Time: 16:19
 */

namespace app\master\controller;


use app\common\model\Store;
use app\common\model\StoreAddress;
use think\Controller;
use think\Db;

class DeliverySettings extends Controller
{

    public function index()
    {
        try {
            $_data = [];
            $_map = [];
            $_hasWhere = function ($query) {
                $query->where([
                    ['Store.delete_time', 'null', ''],
                ]);
            };

            $_paramGet = $this->request->get();

            $_singleStore = config('user.one_more');


            if ($_singleStore != 1) {
                $_paramGet['store_id'] = config('user.one_store_id');
            }

            if (!empty($_paramGet['store_id'])) $_map[] = ['StoreAddress.store_id', '=', $_paramGet['store_id']];
            if (!empty($_paramGet['keyword'])) {
                $_hasWhere = function ($query) use ($_paramGet) {
                    $query->where([
                        ['Store.delete_time', 'null', ''],
                        ['store_name', 'like', "%{$_paramGet['keyword']}%"],
                    ]);
                };
            }

            try {
                $_data = StoreAddress::hasWhere('storeBlt', $_hasWhere)->with('storeBlt')->where($_map)->paginate();
            } catch (\Exception $e) {
                dump($e->getMessage());
                $this->error('获取数据出错,请稍后再试');
            }

            $this->assign('store_list', Store::where(['shop' => 0])->field('store_id,store_name')->select());
            $this->assign('data', $_data);
            $this->assign('single_store', $_singleStore);
            return $this->fetch();
        } catch (\Exception $e) {

        }

        return $this->fetch();
    }

    public function create(StoreAddress $storeAddress)
    {

        if ($this->request->isPost()) {

            $_param = $this->request->post();

            $_singleStore = config('user.one_more');

            // 单店
            if ($_singleStore != 1){
                $_param['store_id'] = config('user.one_store_id');
            }else{
                if (!isset($_param['store_id']) || $_param['store_id'] == 0) {
                    return ['code' => -2, 'message' => '请选择店铺'];
                }
            }



            if (empty($_param['phone_number']) && (empty($_param['telephone'][1]) && empty($_param['telephone'][2]))) {
                return ['code' => -2, 'message' => '联系电话与座机至少填写一项'];
            }

            if (!empty($_param['telephone'][0]) || !empty($_param['telephone'][1])) {
                $reg = '(^[+]{0,1}(\d+)$)';

                if (empty($_param['telephone'][0])) {
                    return ['code' => -2, 'message' => '请填写区号'];
                } else {
                    if (!preg_match($reg, $_param['telephone'][0])) return ['code' => -2, 'message' => '区号格式错误'];
                }
                if (empty($_param['telephone'][1])) {
                    return ['code' => -2, 'message' => '请填写座机号'];
                } else {
                    if (!preg_match($reg, $_param['telephone'][1])) return ['code' => -2, 'message' => '座机号格式错误'];
                }
            }

            try {
                $check = $storeAddress->valid($_param, 'master_create');
                if ($check['code']) return $check;
                $_result = StoreAddress::_CreateForConsole($_param);

                if ($_result !== TRUE) {
                    throw new \Exception($_result);
                }
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/delivery_settings/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $_storeList = Store::all()->toArray();

        $this->assign('store_list', $_storeList);
        $this->assign('page_type', 'create');
        $this->assign('single_store', config('user.one_more'));
        $this->assign('store_id', config('user.one_store_id'));
        return $this->fetch();
    }

    public function edit(StoreAddress $storeAddress)
    {

        if ($this->request->isPost()) {
            try {
                $_param = $this->request->post();

                $check = $storeAddress->valid($_param, 'master_edit');
                if ($check['code']) return $check;

                if (empty($_param['phone_number']) && (empty($_param['telephone'][1]) && empty($_param['telephone'][2]))) {
                    return ['code' => -2, 'message' => '联系电话与座机至少填写一项'];
                }

                if (!empty($_param['telephone'][0]) || !empty($_param['telephone'][1])) {
                    $reg = '(^[+]{0,1}(\d+)$)';

                    if (empty($_param['telephone'][0])) {
                        return ['code' => -2, 'message' => '请填写区号'];
                    } else {
                        if (!preg_match($reg, $_param['telephone'][0])) return ['code' => -2, 'message' => '区号格式错误'];
                    }
                    if (empty($_param['telephone'][1])) {
                        return ['code' => -2, 'message' => '请填写座机号'];
                    } else {
                        if (!preg_match($reg, $_param['telephone'][1])) return ['code' => -2, 'message' => '座机号格式错误'];
                    }
                }

                if (!isset($_param['return_address']) && !isset($_param['shipping_address'])) {
                    return ['code' => -2, 'message' => '请至少选择一项地址类型'];
                }

                // 保存到数据库的地址

                $_result = StoreAddress::_UpdateForConsole($_param);

                if ($_result !== TRUE) {
                    throw new \Exception($_result);
                }

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/delivery_settings/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $_storeAddressId = $this->request->get('id');
        $_storeId = $this->request->get('sid',config('user.one_store_id'));
        $_data = [];

        try {
            $_data = StoreAddress::where([
                ['store_address_id', '=', $_storeAddressId],
            ])->find();
        } catch (\Exception $e) {
            $this->error('获取数据出错,请稍后再试');
        }

        $this->assign('item', $_data);
        $this->assign('store_id', $_storeId);
        $this->assign('page_type', 'edit');
        $this->assign('single_store', config('user.one_more'));
        return $this->fetch('create');
    }

    public function destroy()
    {
        try {
            StoreAddress::destroy(['store_address_id' => $this->request->post('id')]);
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/delivery_settings/index'];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    public function multiDestroy()
    {
        try {
            $arr = explode(',', $this->request->post('id'));
            StoreAddress::destroy(['store_address_id' => $arr]);
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/delivery_settings/index'];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    public function setDefault()
    {
        $_param = $this->request->param();
        $_sid = $_param['sid'];
        $_id = $_param['id'];

        Db::startTrans();

        try {
            StoreAddress::update([
                'default_return_address' => '2',
            ], [
                ['store_id', '=', $_sid],
                ['store_address_id', '<>', $_id],
                ['default_return_address', '=', '1'],
            ]);
            StoreAddress::update([
                'default_return_address' => '1',
            ], [
                ['store_id', '=', $_sid],
                ['store_address_id', '=', $_id],
            ]);

            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/delivery_settings/index'];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }
}