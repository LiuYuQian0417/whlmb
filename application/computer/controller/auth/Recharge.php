<?php
declare(strict_types=1);

namespace app\computer\controller\auth;

use app\computer\model\Consumption;
use app\computer\model\Recharge as RechargeModel;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Request;
use think\facade\Session;
use app\computer\model\Article;

/**
 * 充值
 * Class Register
 * @package app\computer\controller\auth
 */
class Recharge extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login'
    ];

    /**
     * 充值列表
     * @param RechargeModel $rechargeModel
     * @param Article $article
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RechargeModel $rechargeModel,RSACrypt $crypt,Article $article)
    {
        // 读取个人信息
        $result = $rechargeModel
            ->field('recharge_id,file,recharge_money,award_money')
            ->order('sort', 'desc')
            ->select();
        $info =  $article
            ->where('article_id', 24)
            ->field('title,content')
            ->find();

        return $this->fetch('',['code' => 0, 'result' => $result,'info' => $info]);
    }


//    /** 废弃 */
//     * 微信 和商品支付用一个页面
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param RechargeModel $rechargeModel
//     * @return mixed
//     */
//    public function underway(Request $request,RSACrypt $crypt,RechargeModel $rechargeModel)
//    {
//        $recharge_id = $request::instance()->param('recharge_id');
//
//         $order_number =  get_order_sn();
//         $member_id = Session::get('member_info')['member_id'];
//         $total_price = $rechargeModel->where(['recharge_id' => $recharge_id])->value('recharge_money');
//         $order_data = urlsafe_b64encode(
//            $crypt->singleEnc(
//                [
//                    'order_number' => $order_number,
//                    'total_price'  => $total_price > 0 ? $total_price : "0",
//                    'attach'       => "recharge |3|".$member_id."|".$recharge_id,
//                ]
//            )
//        );
//
//        return $this->fetch('',[
//            'order_number' => $order_number,
//            'total_price' =>$total_price,
//            'order_data' => $order_data,
//            'pay_type'     => 'recharge',//订单类型  pay 支付订单  recharge  充值 exchange 积分换购
//            ]);
//    }

    public function successful()
    {

        return $this->fetch('');
    }

    public function generate_order(Request $request,RSACrypt $crypt,RechargeModel $rechargeModel)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();
                $order_number =  get_order_sn();
                $member_id = Session::get('member_info')['member_id'];
                $total_price = $rechargeModel->where(['recharge_id' => $param['recharge_id']])->value('recharge_money');

                $order_data = urlsafe_b64encode(
                    $crypt->singleEnc(
                        [
                            'order_number' => $order_number,
                            'total_price'  => $total_price > 0 ? $total_price : "0",
                            'attach'       => "recharge|3|{$member_id}|{$param['recharge_id']}",
                        ]
                    )
                );
                return $crypt->response(['code' => 0, 'order_data' => $order_data], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
        
    }


    /**
     * 账户余额记录
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Consumption $consumption
     * @return mixed
     */
    public function balance_record(Request $request, RSACrypt $crypt,Consumption $consumption)
    {
        try {
            // 获取参数
            $param = Request::instance()->param();
            $param['member_id'] = Session::get('member_info')['member_id'];

            $balance = Db::name('member')->where([['member_id','=',$param['member_id']]])->value('usable_money');

            // 条件
            $condition[] = ['status', '=', 1];
            $condition[] = ['member_id', '=', $param['member_id']];

            // 状态
            if (!empty($param['type'])) {
                if ($param['type'] == 4){
                    $condition[] = ['type', '=', 0];
                }else{
                    $condition[] = ['type', '=', $param['type']];
                }

            }
            // 月份
            if (!empty($param['month'])) $condition[] = ['create_time', 'between time', [date('Y-' . $param['month'] . '-01'), date('Y-' . $param['month'] . '-31')]];
            $result = $consumption
                ->where($condition)
                ->field('consumption_id,type,manage_describe as describle,price,create_time')
                ->order('create_time', 'desc')
                ->paginate(10);

        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }

        return $this->fetch('',['code' => 0,  'balance' => $balance, 'result' => $result]);
    }

}