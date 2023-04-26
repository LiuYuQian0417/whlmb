<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18 0018
 * Time: 18:08
 */

namespace app\master\controller;


use app\common\model\Customer;
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
        $_singleStore = config('user.one_more');
        $_oneStoreId = config('user.one_store_id');

        $_GET = $this->request->get();

        $_where = [];

        // 单店
        if ($_singleStore != 1){
            $_where[] = [
                'Store.store_id',
                '=',
                $_oneStoreId
            ];
        }else{
            // 多店
            if (isset($_GET['store_id']) && $_GET['store_id'] != 0) {
                $_where[] = [
                    'Store.store_id',
                    '=',
                    $_GET['store_id'],
                ];
            }
        }

        if (isset($_GET['customer_group_name']) && $_GET['customer_group_name'] != '') {
            $_where[] = [
                'CustomerGroup.name',
                'like',
                '%' . $_GET['customer_group_name'] . '%',
            ];
        }

        $_data = CustomerGroupModel::alias('CustomerGroup')
            ->join('Store Store', 'Store.store_id = CustomerGroup.store_id')// 店铺
            ->join('Customer Customer', 'Customer.customer_group_id = CustomerGroup.customer_group_id', 'left')// 客服
            ->where($_where)
            ->field([
                'CustomerGroup.customer_group_id' => 'customer_group_id',
                'CustomerGroup.name'              => 'name',
                'CustomerGroup.reception_ceiling' => 'reception_ceiling',
                'Store.store_id'                  => 'store_id',
                'Store.store_name'                => 'store_name',
                'COUNT(Customer.customer_id)'     => 'customer_count',
            ])
            ->group('CustomerGroup.customer_group_id')
            ->paginate();

        $_store = \app\common\model\Store::withTrashed()
            ->field([
                'store_id',
                'store_name',
            ])
            ->select();
        $this->assign('data', $_data);
        $this->assign('store_list', $_store);
        $this->assign('single_store', $_singleStore);
        $this->assign('one_store_id', $_oneStoreId);

        return $this->fetch();
    }

    /**
     * 创建客服组
     */
    public function create()
    {
        $_singleStore = config('user.one_more');
        $_oneStoreId = config('user.one_store_id');

        $_customer = new Customer();

        if ($this->request->isPost()) {

            $_post = $this->request->post();

            try {

                $_createCustomerResult = CustomerGroupModel::create(
                    [
                        'name'              => $_post['name'],
                        'store_id'          => $_singleStore!=1?$_oneStoreId:0,
                        'reception_ceiling' => 10,
                    ]
                );

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => '客服组创建失败,请稍后再试'];
            }

            // 如果客服列表有被选中的元素

            if (isset($_post['customer_list'])) {
                $_updateData = [];
                try {
                    foreach ($_post['customer_list'] as $value) {
                        $_updateData[] = [
                            'customer_id'       => $value,
                            'customer_group_id' => $_createCustomerResult->customer_group_id,
                        ];
                    }

                    $_customer->isUpdate()->saveAll($_updateData);
                } catch (\Exception $e) {
                    return ['code' => -100, 'message' => '客服转移失败,请稍后再试'];
                }
            }

            return ['code' => 0, 'message' => '操作成功', 'url' => '/customer_group/index'];
        }

        $_customerList = $_customer->where(
            [
                ['store_id', '=', $_singleStore!=1?$_oneStoreId:0],
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
                        'name'              => $_post['name'],
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
                            'customer_id'       => $value,
                            'customer_group_id' => $_post['group_id'],
                        ];
                    }

                    $_customer->isUpdate()->saveAll($_updateData);
                } catch (\Exception $e) {
                    return ['code' => -100, 'message' => '客服转移失败,请稍后再试'];
                }
            }

            return ['code' => 0, 'message' => '操作成功', 'url' => '/customer_group/index'];

        }

        $_groupId = $this->request->get('customer_group_id');

        try {
            $_groupInfo = CustomerGroupModel::find($_groupId);

            $_customerList = Customer::where(
                [
                    ['store_id', '=', $_groupInfo['store_id']],
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
            if (count($_id) > 1) {
                // 多选删除
                $_customerGroup = new CustomerGroupModel;

                $_saveData = [];
                $_timeNow = date('Y-m-d H:i:s');

                foreach ($_id as $value) {
                    $_saveData[] = [
                        'customer_group_id' => $value,
                        'delete_time'       => $_timeNow,
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