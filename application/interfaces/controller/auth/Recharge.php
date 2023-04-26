<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\Consumption;
use app\common\model\Member;
use app\common\model\Recharge as RechargeModel;
use app\common\service\TemplateMessage;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;

/**
 * 充值 - Joy
 * Class Register
 * @package app\interfaces\controller\auth
 */
class Recharge extends BaseController
{
    /**
     * 公共充值逻辑区
     * @param $args
     * @return string
     * @throws \think\Exception
     */
    public function rechargeBody($args)
    {
        // 读取会员的余额
        $usable_money = Db::name('member')
            ->where('member_id', $args['member_id'])
            ->value('usable_money');
        // 读取充值赠送金额
        $award_money = Db::name('recharge')
            ->where('recharge_id', $args['recharge_id'])
            ->value('award_money') ?: 0;
        Db::startTrans();
        // 插入余额记录
        $consumption_id = Db::name('consumption')->insertGetId([
            'member_id' => $args['member_id'],
            'type' => 0,        //账号明细消费类型 0充值 1提现 2消费 3退款
            'order_number' => $args['out_trade_no'],
            'price' => $args['total_fee'] + $award_money,
            'balance' => $usable_money + $args['total_fee'] + $award_money,
            'way' => $args['way'],  //资金方式：1支付宝2微信3银行卡4余额5线下
            'trade_no' => $args['trade_no'],
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ]);
        // 插入账户记录
        if ($award_money > 0) {
            Db::name('store_capital')->insert([
                'parameter_id' => $consumption_id,
                'status' => 4,  //充值赠送金额
                'create_time' => date('Y-m-d H:i:s'),
                'price' => $award_money,
            ]);
        }
        // 插入账户记录
        Db::name('store_capital')->insert([
            'parameter_id' => $consumption_id,
            'status' => 0,//充值金额
            'create_time' => date('Y-m-d H:i:s'),
            'price' => $args['total_fee'],
        ]);
        // 更新会员余额
        Db::name('member')
            ->where('member_id', $args['member_id'])
            ->setInc('usable_money', $args['total_fee'] + $award_money);
        Db::commit();
//        if ($args['way'] == 2) {
//            // 发送微信模板消息
//            $args['usable_money'] = $usable_money;
//            $args['award_money'] = $award_money;
//            self::sendTpl($args);
//        }
        return true;
    }
    
    
    /**
     * 充值列表
     * @param RSACrypt $crypt
     * @param RechargeModel $rechargeModel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          RechargeModel $rechargeModel)
    {
        // 读取个人信息
        $result = $rechargeModel
            ->field('recharge_id,file,recharge_money,award_money')
            ->order('sort', 'desc')
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 账户余额记录
     * @param RSACrypt $crypt
     * @param Consumption $consumption
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function balance_record(RSACrypt $crypt,
                                   Consumption $consumption)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $condition = [
            ['status', '=', 1],
            ['member_id', '=', $param['member_id']],
        ];
        // 状态
        if ($param['type'] <> Null) {
            $condition[] = ['type', 'in', $param['type']];
        }
        // 月份
        if (!empty($param['month'])) {
            $timeArea = [
                $begin = date('Y-' . $param['month'] . '-01'),
                date('Y-m-d', strtotime('+ 1 month', strtotime($begin))),
            ];
            $condition[] = ['create_time', 'between time', $timeArea];
        }
        $result = $consumption
            ->where($condition)
            ->field('consumption_id,type,manage_describe as describle,price,create_time')
            ->order('create_time', 'desc')
            ->paginate(10);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
}