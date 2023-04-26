<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18 0018
 * Time: 18:08
 */

namespace app\client\controller;


use app\common\model\Customer;
use app\common\model\CustomerDiversion as CustomerDiversionModel;
use app\common\model\CustomerGroup as CustomerGroupModel;
use app\common\service\OSS;
use think\Controller;
use think\Db;
use think\facade\Session;

/**
 * 客服组控制器
 *
 * Class CustomerGroup
 *
 * @package app\client\controller
 */
class CustomerGroup extends Controller
{
    /**
     * 客服组首页
     *
     * @return mixed
     * @throws \OSS\Core\OssException
     * @throws \think\exception\DbException
     */
    public function index()
    {

        $_data = CustomerGroupModel::where(
            [
                'store_id' => Session::get('client_store_id'),
            ]
        )->field(['customer_group_id,name,reception_ceiling'])->paginate();


        $_oss = new OSS();

        $_group = [];
        foreach ($_data as $key => $val) {
            $_group[$key] = [
                'customer_group_id' => $val['customer_group_id'],
                'name' => $val['name'],
                'reception_ceiling' => $val['reception_ceiling'],
                'customers_count' => $val->customerRel()->field(['customer_id'])->count(),
            ];
//            $_customers = $val->customerRel()->field(['customer_group_id', 'img'])->limit(2)->select();

//            foreach ($_customers as $value) {
//                $_group[$key]['customers'][] = $value['img'];
//            }
        }

        $this->assign('data', $_data);
        $this->assign('group', $_group);
        return $this->fetch();
    }

    /**
     * 创建客服组
     */
    public function create()
    {

        $_customer = new Customer();

        if ($this->request->isPost()) {

            $_post = $this->request->post();
            Db::startTrans();
            try {

                $_createCustomerResult = CustomerGroupModel::create(
                    [
                        'name' => $_post['name'],
                        'store_id' => Session::get('client_store_id'),
                        'reception_ceiling' => 10,
                    ]
                );
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => '客服组创建失败,请稍后再试'];
            }

            try {
                // 判断是否有客服组分流信息
                $_createCustomerDiversionResult = CustomerDiversionModel::where([
                    ['store_id', '=', Session::get('client_store_id')]
                ])->count();

                // 如果没有 把当前客服分流设置为默认客服分流
                if ($_createCustomerDiversionResult <= 0) {
                    (new CustomerDiversionModel)->saveAll([
                        ['store_id' => Session::get('client_store_id'), 'diversion_id' => 1000, 'customer_group_id' => $_createCustomerResult['customer_group_id']],
                        ['store_id' => Session::get('client_store_id'), 'diversion_id' => 1001, 'customer_group_id' => $_createCustomerResult['customer_group_id']],
                        ['store_id' => Session::get('client_store_id'), 'diversion_id' => 1002, 'customer_group_id' => $_createCustomerResult['customer_group_id']],
                        ['store_id' => Session::get('client_store_id'), 'diversion_id' => 1003, 'customer_group_id' => $_createCustomerResult['customer_group_id']],
                        ['store_id' => Session::get('client_store_id'), 'diversion_id' => 1004, 'customer_group_id' => $_createCustomerResult['customer_group_id']],
                        ['store_id' => Session::get('client_store_id'), 'diversion_id' => 1005, 'customer_group_id' => $_createCustomerResult['customer_group_id']],
                        ['store_id' => Session::get('client_store_id'), 'diversion_id' => 1006, 'customer_group_id' => $_createCustomerResult['customer_group_id']],
                        ['store_id' => Session::get('client_store_id'), 'diversion_id' => 1007, 'customer_group_id' => $_createCustomerResult['customer_group_id']],
                    ]);
                }
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => '客服分流信息更新失败,请稍后再试'];
            }

            // 如果客服列表有被选中的元素

            if (isset($_post['customer_list'])) {
                $_updateData = [];
                try {
                    foreach ($_post['customer_list'] as $value) {
                        $_updateData[] = [
                            'customer_id' => $value,
                            'customer_group_id' => $_createCustomerResult->customer_group_id,
                        ];
                    }

                    $_customer->isUpdate()->saveAll($_updateData);
                } catch (\Exception $e) {
                    Db::rollback();
                    return ['code' => -100, 'message' => '客服转移失败,请稍后再试'];
                }
            }

            Db::commit();
            return ['code' => 0, 'message' => '操作成功', 'url' => '/client/customer_group/index'];
        }

        $_customerList = $_customer->where(
            [
                ['store_id', '=', Session::get('client_store_id')],
            ]
        )->select();

        $this->assign('customer_list', $_customerList);

        return $this->fetch();
    }

    public function update()
    {
        if ($this->request->isPost()) {

            $_customer = new Customer();

            $_post = $this->request->post();

            try {
                CustomerGroupModel::where(
                    [
                        ['customer_group_id', '=', $_post['group_id']],
                    ]
                )->update(
                    [
                        'name' => $_post['name'],
                        'reception_ceiling' => 10,
                    ]
                );
            } catch (\Exception $e) {
                return ['code' => -100, '更新客服组新新城失败,请稍后再试'];
            }

            if (isset($_post['customer_list'])) {
                $_updateData = [];
                try {
                    foreach ($_post['customer_list'] as $value) {
                        $_updateData[] = [
                            'customer_id' => $value,
                            'customer_group_id' => $_post['group_id'],
                        ];
                    }

                    $_customer->isUpdate()->saveAll($_updateData);
                } catch (\Exception $e) {
                    return ['code' => -100, 'message' => '客服转移失败,请稍后再试'];
                }
            }

            return ['code' => 0, 'message' => '操作成功', 'url' => '/client/customer_group/index'];

        }

        $_groupId = $this->request->get('customer_group_id');

        try {
            $_groupInfo = CustomerGroupModel::find($_groupId);

            $_customerList = Customer::where(
                [
                    ['store_id', '=', Session::get('client_store_id')],
                ]
            )->select();
        } catch (\Exception $e) {
            $this->error('系统出错,请稍后再试');
            return FALSE;
        }

        $this->assign('group', $_groupInfo);

        $this->assign('customer_list', $_customerList);

        return $this->fetch('create');
    }

    /**
     * 删除客服组
     *
     * @param $id
     *
     * @return array
     */
    public function destroy($id)
    {

        $_id = explode(',', $id);

        try {
            Db::startTrans();

            // 查看是否存在分流信息
            $_customerDiversion = CustomerDiversionModel::where([
                ['store_id', '=', Session::get('client_store_id')],
                ['customer_group_id', 'in', $id]
            ])->count();

            if ($_customerDiversion > 0) {
                return ['code' => -100, 'message' => '相关分类已关联客服分流,请解除分流后再进行删除操作'];
            }

            if (count($_id) > 1) {
                // 多选删除
                $_customerGroup = new CustomerGroupModel;

                $_saveData = [];
                $_timeNow = date('Y-m-d H:i:s');

                foreach ($_id as $value) {
                    $_saveData[] = [
                        'customer_group_id' => $value,
                        'delete_time' => $_timeNow,
                    ];
                }
                $_customerGroup->saveAll($_saveData);
            } else {
                // 单一删除
                CustomerGroupModel::destroy($id);
            }

            // 清理客服分组下面的客服
            Customer::where(
                [
                    'customer_group_id' => $_id,
                ]
            )->update(
                [
                    'customer_group_id' => 0,
                ]
            );

            Db::commit();
            return ['code' => 0, 'message' => '操作成功'];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -100, 'message' => '操作失败,请稍后再试'];
        }
    }

    public function enabled()
    {

    }

    public function disabled()
    {

    }
}