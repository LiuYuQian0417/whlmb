<?php
/**
 * 关于商家发票信息.
 * User: Heng
 * Date: 2019/2/11
 * Time: 14:13
 */

namespace app\master\controller;

use think\Controller;
use app\common\model\Store as StoreModel;
use think\facade\Request;

class InvoiceStore extends Controller
{
    /**
     * 店铺发票信息列表
     * @param Request $request
     * @param StoreModel $store
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function index(Request $request, StoreModel $store)
    {
        // 获取数据
        $param = $request::param();
        // 条件
        $condition = [
            ['store_id', 'neq', 0],
            ['shop', 'eq', 0],
            ['status', 'eq', 1]
        ];

        if (array_key_exists('keyword', $param) && $param['keyword']) $condition[] = ['store_name', 'like', '%' . $param['keyword'] . '%'];
        // 字段
        $field = 'store_name,store_id,invoiced_freight,invoiced_second_order,invoice_code';
        $data = $store->where($condition)->field($field)->paginate(10, false, ['query' => $param]);


        return $this->fetch('', [
            'data' => $data
        ]);
    }


    /**
     * 更新发票设置
     * @param Request $request
     * @param StoreModel $store
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, StoreModel $store)
    {
        if ($request::isPost()) {
            try {
                // 获取数据
                $param = $request::post();

                // 更新
                $state = $store->allowField(true)->isUpdate(true)->save($param);

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/store_index/index'];

            } catch (\Exception $e) {
                halt($e->getMessage());
                return ['code' => -100, 'message' => $e->getMessage()];
            }


        }

        // 数据
        $item = $store->where('store_id', 'eq', $request::param('id'))
            ->field('store_id,store_name,invoiced_freight,invoiced_second_order,invoice_code')
            ->find();


        return $this->fetch('', [
            'item' => $item
        ]);
    }


    /**
     * 是否寄送发票运费
     * @param Request $request
     * @param StoreModel $store
     * @return array
     */
    public function auditingFreight(Request $request,StoreModel $store)
    {
        if ($request::isPost()){
            try {
                $store->changeIsInvoicedFreight($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 是否发票有运费
     * @param Request $request
     * @param StoreModel $store
     * @return array
     */
    public function auditingSecondOrder(Request $request,StoreModel $store)
    {
        if ($request::isPost()){
            try{
                $store->changeIsSecondOrder($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            }catch (\Exception $e){
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }
    }
}