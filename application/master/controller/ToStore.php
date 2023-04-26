<?php
// 到店自提
declare(strict_types=1);

namespace app\master\controller;

use think\Db;
use think\Controller;
use think\facade\Request;
use app\common\model\Order as OrderModel;
use app\common\model\OrderGoods as OrderGoodsModel;
use app\common\model\OrderAttach as OrderAttachModel;

class ToStore extends Controller
{
    public function writeOff(Request $request,OrderModel $order,OrderGoodsModel $orderGoods,OrderAttachModel $orderAttach)
    {
        try {
            if ($request::isPost()){
                // 获取参数

            }

        } catch (\Exception $e) {

        }


        return $this->fetch('',[

        ]);
    }


    /**
     * 店铺自提订单类表
     * @param Request $request
     * @param OrderAttachModel $orderAttach
     * @return array|mixed
     */
    public function index(Request $request,OrderAttachModel $orderAttach)
    {
        try {
            // 获取数据
            $param = $request::param();

            // 筛选条件
            $condition[] = ['oa.order_attach_id','>',0];
            if (!empty($param['keyword'])) $condition[] = ['oa.order_attach_number','=',$param['order_attach_number']];
            if (!empty($param['store_id'])) $condition[] = ['oa.store_id','=',$param['store_id']];

            // 获取数据
            $data = $orderAttach->alias('oa')
                ->join(['ishop_order'=>'io'],'io.order_id=oa.order_id')
                ->where($condition)->field('io.create_time,oa.order_attach_number,oa.subtotal_price,oa.distribution_type,oa.status')
                ->paginate(10,false,['query'=>$param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('',[
            'data'=>$data
        ]);
    }


    /**
     * 店铺自提订单结算
     * @param Request $request
     * @param OrderAttachModel $orderAttach
     * @return mixed
     */
    public function cancellation(Request $request,OrderAttachModel $orderAttach)
    {
        if ($request::isPost()){
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();




                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }

        $item = $orderAttach->alias('oa')
            ->join(['ishop_order'=>'io'],'io.order_id=oa.order_id')
            ->join(['ishop_order_goods'=>'og'],'og.order_attach_id=oa.order_attach_id')
            ->join(['ishop_goods'=>'go'],'go.goods_id=og.goods_id')
            ->join(['ishop_products'=>'pp'],'pp.products_id=oa.products_id')
            ->where(['order_id'=>$request::get('order_id')])
            ->field('io.create_time,oa.order_attach_number,oa.subtotal_price,oa.distribution_type,
            oa.status,og.goods_id,go.goods_name,pp.attr_file,pp.goods_attr')
            ->find();

        return $this->fetch('',[
            'item'=>$item
        ]);
    }
}