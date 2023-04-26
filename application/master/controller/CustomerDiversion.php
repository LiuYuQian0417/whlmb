<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-01-29
 * Time: 13:05
 */

namespace app\master\controller;

use app\common\model\CustomerDiversion as CustomerDiversionModel;
use app\common\model\CustomerGroup;
use think\Controller;
use think\facade\Session;

/**
 * 客服分流设置
 *
 * Class CustomerDiversion
 * @package app\client\controller
 */
class CustomerDiversion extends Controller
{

    function manage()
    {

        $_singleStore = config('user.one_more');
        $_oneStoreId = config('user.one_store_id');

        // 单店
        if ($_singleStore != 1) {
            $_storeId = $_oneStoreId;
        } else {
            $_storeId = 0;
        }

        // GET 请求
        if ($this->request->isGet()) {
            $_customerGroup = CustomerGroup::where(
                [
                    'store_id' => $_storeId,
                ]
            )->field(
                [
                    'customer_group_id',
                    'name',
                ]
            )->select();

            $_customerDiversion = CustomerDiversionModel::where(
                [
                    'store_id' => $_storeId,
                ]
            )->field(
                [
                    'diversion_id',
                    'customer_group_id',
                ]
            )->select();

            $_customerDiversionMap = [];

            foreach ($_customerDiversion as $val) {
                $_customerDiversionMap[$val['diversion_id']] = $val['customer_group_id'];
            }

            $this->assign('customer_diversion_map', $_customerDiversionMap);
            $this->assign('customer_group', $_customerGroup);

            return $this->fetch();
        }

        // POST 请求
        if ($this->request->isPost()) {
            $_data = $this->request->post();
            try {
                $_customerDiversionData = CustomerDiversionModel::where(
                    [
                        'store_id' => $_storeId,
                    ]
                )->field(
                    [
                        'customer_diversion_id',
                        'diversion_id',
                        'customer_group_id',
                    ]
                )->select()->toArray();

                $_saveData = [];

                if (empty($_customerDiversionData)) {
                    foreach ($_data as $key => $val) {
                        $_saveData[] = [
                            'store_id'          => $_storeId,
                            'diversion_id'      => $key,
                            'customer_group_id' => $val,
                        ];
                    }
                } else {
                    foreach ($_customerDiversionData as $key => $val) {
                        $_saveData[$key] = [
                            'customer_diversion_id' => $val['customer_diversion_id'],
                            'diversion_id'          => $val['diversion_id'],
                            'customer_group_id'     => $_data[$val['diversion_id']],
                        ];
                    }
                }


                (new CustomerDiversionModel)->saveAll($_saveData);

                return ['code' => 200, 'msg' => '操作成功'];

            } catch (\Exception $e) {
                return ['code' => -100, 'msg' => $e->getMessage()];
            }
        }
    }
}