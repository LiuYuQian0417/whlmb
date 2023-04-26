<?php
declare(strict_types=1);

namespace app\computer\controller\order;

use app\computer\model\GoodsEvaluate;
use app\computer\model\OrderAttach;
use app\computer\controller\BaseController;
use think\facade\Request;
use think\facade\Session;

/**
 * 评价
 * Class Evaluate
 * @package app\computer\controller\order
 */
class Evaluate extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => ''],
    ];

    /**
     * 发表评价
     * @param OrderAttach $orderAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function report(OrderAttach $orderAttach)
    {
        $condition[] = ['status','in','3.1,5.5,5.6,5.7'];
        $data = $orderAttach->with(
            [
                'orderGoods' => function ($query) use ($condition)
                {
                    $query->where($condition)->field('order_attach_id,order_goods_id,file,attr,goods_name,single_price');
                },
            ]
        )->field('order_attach_id,create_time,order_attach_number')->where(
            [['order_attach_id', '=', Request::get('order_attach_id')]]
        )->find();
        return $this->fetch('', ['result' => $data]);
    }

    /**
     * 我的评价列表
     * @param GoodsEvaluate $goodsEvaluate
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function myEvaluateList(GoodsEvaluate $goodsEvaluate)
    {
        $where = [
            ['member_id', '=', Session::get('member_info')['member_id']],
            //                ['status', '=', 1],   //已审核
        ];
        $myEvaluateData = $goodsEvaluate
            ->where($where)
            ->with(
                [
                    'orderGoods' => function ($query)
                    {
                        $query->field('order_goods_id,file,goods_id,goods_name,original_price');
                    },
                ]
            )
            ->field('goods_evaluate_id,order_goods_id,attr,create_time')
            ->order(['update_time' => 'desc'])
            ->paginate(6, FALSE, $where);
//        halt($myEvaluateData->toArray());
        return $this->fetch('', ['result' => $myEvaluateData]);
    }


    /**
     * 评价详情查看
     * @param Request $request
     * @param GoodsEvaluate $goodsEvaluate
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myEvaluateExamine(Request $request, GoodsEvaluate $goodsEvaluate)
    {

        $args = $request::get();
        $where = [
            ['goods_evaluate_id', '=', $args['goods_evaluate_id'] ?? \exception('评价id不能为空')],
        ];
        $myEvaluateData = $goodsEvaluate
            ->where($where)
            ->with(
                [
                    'orderGoods' => function ($query)
                    {
                        $query->field('order_goods_id,file,goods_name,original_price');
                    },
                ]
            )
            ->field('goods_evaluate_id,order_goods_id,create_time,star_num,content,video,multiple_file,attr')
            ->find();
        return $this->fetch('', ['result' => $myEvaluateData]);
    }
}