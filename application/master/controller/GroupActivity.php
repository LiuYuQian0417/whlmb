<?php
// 团购活动
declare(strict_types = 1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\GroupActivity as GroupActivityModel;
use app\common\model\OrderAttach;
use think\swoole\facade\Task;

class GroupActivity extends Controller
{
    /**
     * 团购活动表
     * @param Request $request
     * @param GroupActivityModel $groupActivity
     * @return array
     */
    public function index(Request $request, GroupActivityModel $groupActivity)
    {
//        try {
            // 获取数据
            $param = $request::get();

            // 筛选条件
            $condition[] = ['ga.group_activity_id', 'neq', 0];
            if (!empty($param['keyword'])) $condition[] = ['sg.goods_name', 'like', '%' . $param['keyword'] . '%'];

            $_singleStore = config('user.one_more');

            // 单店
            if ($_singleStore != 1){
                $condition[] = ['sg.store_id','=',config('user.one_store_id')];
            }

            // 计算总数
            $count = $groupActivity->countOrder();


            if (array_key_exists('term', $param) && $param['term']) {
                switch ($param['term']) {
                    case 1:
                        //进行中
                        $condition[] = ['ga.status', '=', 1];
                        break;
                    case 2:
                        //成功
                        $condition[] = ['ga.status', '=', 2];
                        break;
                    case 3:
                        //失败
                        $condition[] = ['ga.status', '=', 3];
                        break;

                }
            };

            $data = $groupActivity->alias('ga')
                ->join(['ishop_group_goods' => 'gg'], 'gg.group_goods_id = ga.group_goods_id')
                ->join('member m', 'm.member_id = ga.owner')
                ->join(['ishop_goods' => 'sg'], 'sg.goods_id = gg.goods_id')->where($condition)
                ->field('ga.group_activity_id,sg.goods_name,ga.create_time,ga.surplus_num,ga.status,ga.end_time,m.nickname')
                ->order(['ga.create_time' => 'desc'])
                ->paginate(10, false, ['query' => $param]);

//        } catch (\Exception $e) {
//            dump($e->getMessage());
//            return ['code' => -100, 'message' => $e->getMessage()];
//        }

        return $this->fetch('', [
            'data'  => $data,
            'count' => $count,
        ]);
    }


    /**
     * 拼团详情
     * @param Request $request
     * @param GroupActivityModel $groupActivity
     * @return array|mixed
     */
    public function editAL(Request $request, GroupActivityModel $groupActivity, OrderAttach $orderAttach)
    {
        try {
            $param = $request::get();

            $data = $groupActivity
                ->alias('a')
                ->join('group_goods group_goods', 'a.group_goods_id = group_goods.group_goods_id')
                ->join('goods goods', 'group_goods.goods_id = goods.goods_id')
                ->where('a.group_activity_id', '=', $param['group_activity_id'])
                ->field('a.*,goods.goods_name,goods.group_price,group_goods.group_num,goods.file')
                ->find();

            $groupOrder = $orderAttach
                ->alias('a')
                ->join('member member', 'member.member_id = a.member_id', 'left')
                ->join('order_goods order_goods', 'order_goods.order_attach_id = a.order_attach_id', 'left')
                ->where('a.group_activity_id', $param['group_activity_id'])
                ->field('a.order_attach_number,nickname,phone,quantity,a.status,order_goods.subtotal_price')
                ->paginate(10, false);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('edit', [
            'item' => $data,
            'group_oder' => $groupOrder,
        ]);
    }


}