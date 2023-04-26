<?php
// 积分换购订单
declare(strict_types=1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\Message;
use app\common\model\Express as ExpressModel;
use app\common\model\IntegralOrder as IntegralOrderModel;

class IntegralOrder extends Controller
{
    /**
     * 积分兑换订单列表
     * @param Request $request
     * @param IntegralOrderModel $integralOrder
     * @return array|mixed
     */
    public function index(Request $request, IntegralOrderModel $integralOrder)
    {
        try {
            // 获取参数
            $param = $request::param();

            // 筛选条件
            $condition[] = ['integral_order_id', 'neq', 0];
            $condition[] = ['status', 'neq', 3];            // 未支付排除

            if (!empty($param['keyword'])) $condition[] = ['a.order_number', 'like', '%' . $param['keyword'] . '%'];
            if (isset($param['status']) && $param['status'] != -1) $condition[] = ['a.status', '=', $param['status']];
            if (isset($param['type']) && $param['type'] != -1) $condition[] = ['integral.type', '=', $param['type']];
            // 获取数据
            $data = $integralOrder->alias('a')
                ->join('integral integral', 'integral.integral_id = a.integral_id')
                ->where($condition)
                ->field('a.integral_order_id,a.order_number,a.integral_name,a.integral,a.price,a.number,a.from,a.status,a.create_time,integral.type')
                ->order('a.create_time', 'desc')
                ->paginate(10, false, ['query' => $param]);
            //halt($data);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }


    /**
     * 操作积分商品订单
     * @param Request $request
     * @param IntegralOrderModel $integralOrder
     * @param ExpressModel $express
     * @param Message $message
     * @return array|mixed
     * @throws \think\Exception\DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function edit(Request $request, IntegralOrderModel $integralOrder, ExpressModel $express, Message $message)
    {
        if ($request::isPost()) {
            try {
                // 获取数据
                $param = $request::post();
                
                // 验证
                $check = $integralOrder->valid($param, 'edit');
                if ($check['code']) return $check;
                
                // 写入
                $operation = $integralOrder->allowField(true)->isUpdate(true)->save($param);
                
                if ($param['express_value']) {
                    
                    $goods = $integralOrder
                        ->alias('io')
                        ->where('integral_order_id', $param['integral_order_id'])
                        ->join('member m', 'm.member_id = io.member_id')
                        ->field('m.member_id,io.integral_name,io.file,m.web_open_id,
                        m.subscribe_time,m.micro_open_id,m.phone,m.nickname')
                        ->find();
                    // 推送消息[积分订单已发货]
                    $pushServer = app('app\\interfaces\\behavior\\Push');
                    $pushServer->send([
                        'tplKey' => 'order_state',
                        'openId' => $goods['web_open_id'],
                        'subscribe_time' => $goods['subscribe_time'],
                        'microId' => $goods['micro_open_id'],
                        'phone' => $goods['phone'],
                        'data' => [9, '平台', $goods['nickname'], $goods['integral_name']],
                        'inside_data' => [
                            'member_id' => $goods['member_id'],
                            'type' => 1,
                            'attach_id' => $param['integral_order_id'],
                            'jump_state' => '11',
                            'file' => $goods->getData('file'),
                        ],
                    ]);
                }
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/integral_order/index'];
                
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
            
        }
        
        return $this->fetch('', [
            'item' => $integralOrder->relation(['member' => function ($query) {
                $query->field('phone,username,nickname');
            }, 'integralGoods' => function ($query) {
                $query->field('integral_id,type');
            }])->where('integral_order_id', $request::get('integral_order_id'))->find(),
            'express' => $express::all()
        ]);
    }
    
    
}