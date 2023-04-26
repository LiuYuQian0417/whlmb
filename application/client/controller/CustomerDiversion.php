<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-01-29
 * Time: 13:05
 */

namespace app\client\controller;

use app\common\model\CustomerDiversion as CustomerDiversionModel;
use app\common\model\CustomerGroup;
use function Composer\Autoload\includeFile;
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
        $_storeId = Session::get('client_store_id');

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
                $_customerDiversionSourceData = CustomerDiversionModel::where(
                    [
                        'store_id' => $_storeId,
                    ]
                )->field(
                    [
                        'customer_diversion_id',
                        'diversion_id',
                        'customer_group_id',
                    ]
                )->select();

                $_saveData = [];
                $_createData = [];

                if (empty($_customerDiversionSourceData)) {
                    foreach ($_data as $key => $val) {
                        $_createData[] = [
                            'store_id' => $_storeId,
                            'diversion_id' => $key,
                            'customer_group_id' => $val,
                        ];
                    }
                } else {
                    // 客服分流ID列表
                    $_diversionIdList = [
//                        1000,
                        1001,
                        1002,
                        1003,
                        1004,
                        1005,
//                        1006,
//                        1007,
                    ];
                    $_customerDiversionData = [];
                    foreach ($_customerDiversionSourceData as $val) {
                        $_customerDiversionData[$val['diversion_id']] = $val;
                    }

                    foreach ($_diversionIdList as $key => $val) {
                        if (isset($_data[$val]) && !empty($_data[$val])) {
                            if (isset($_customerDiversionData[$val])) {
                                if ($_customerDiversionData[$val]['customer_group_id'] != $_data[$val]) {
                                    $_saveData[] = [
                                        'customer_diversion_id' => $_customerDiversionData[$val]['customer_diversion_id'],
                                        'diversion_id' => $val,
                                        'customer_group_id' => $_data[$val]
                                    ];
                                }
                            } else {
                                $_createData[] = [
                                    'store_id' => $_storeId,
                                    'diversion_id' => $val,
                                    'customer_group_id' => $_data[$val]
                                ];
                            }
                        }
                    }
                }

                if (!empty($_saveData)) {
                    (new CustomerDiversionModel)->saveAll($_saveData);
                }

                if (!empty($_createData)) {
                    (new CustomerDiversionModel)->saveAll($_createData);
                }

                return ['code' => 200, 'msg' => '操作成功'];
            } catch (\Exception $e) {
                return ['code' => -100, 'msg' => $e->getMessage()];
            }
        }
    }
}