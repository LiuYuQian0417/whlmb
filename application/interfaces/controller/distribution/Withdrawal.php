<?php
declare(strict_types=1);

namespace app\interfaces\controller\distribution;

use app\common\model\Distribution;
use app\common\model\DistributionWithdraw;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;

/**
 * 分销_提现
 * Class Withdrawal
 * @package app\interfaces\controller\distribution
 */
class Withdrawal extends BaseController
{

    /**
     * 获取环境设置
     * @return mixed
     */
    protected function getSet()
    {
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        return Env::get();
    }

    /**
     * 提现主页
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Distribution $distribution)
    {
        $param = $crypt->request();
        $where = [
            ['distribution_id', '=', $param['distribution_id']]
        ];
        $data = $distribution
            ->where($where)
            ->field('total_brokerage,close_brokerage')
            ->find();
        $distributionSet = self::getSet();
        $data['notify_explain'] = explode('//', $distributionSet['DISTRIBUTION_NOTIFY_EXPLAIN']);
        // 检测系统是否设置提现手续费
        $distribution_withdraw_cost = $distributionSet['DISTRIBUTION_WITHDRAW_COST'];
        if ($distribution_withdraw_cost > 0) {
            $distribution_withdraw_cost_type = $distributionSet['DISTRIBUTION_WITHDRAW_COST_TYPE'];
            $data['notify_explain'] = array_merge(
                [sprintf("提现手续费为%s", $distribution_withdraw_cost . ($distribution_withdraw_cost_type ? "%" : "元") . "，（不包含余额提现）")],
                $data['notify_explain']
            );
        }
        $type = explode(',', $distributionSet['DISTRIBUTION_WITHDRAW_TYPE']);
        $data['rule'] = [
            'type' => $type,
            'cycle' => $distributionSet['DISTRIBUTION_WITHDRAW_DAY'],
            'times' => $distributionSet['DISTRIBUTION_WITHDRAW_TIME'],
            'min_price' => $distributionSet['DISTRIBUTION_WITHDRAW_PRICE'],
        ];
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
    }

    /**
     * 提现申请
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @param DistributionWithdraw $distributionWithdraw
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function to_apply(RSACrypt $crypt,
                             Distribution $distribution,
                             DistributionWithdraw $distributionWithdraw)
    {
        $param = $crypt->request();
        $distributionWithdraw->valid($param, 'to_apply');
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distributionSet = Env::get();
        if ($param['price'] < $distributionSet['DISTRIBUTION_WITHDRAW_PRICE']) {
            return $crypt->response([
                'code' => -1,
                'message' => sprintf('提现金额不可低于%s',
                    $distributionSet['DISTRIBUTION_WITHDRAW_PRICE']),
            ], true);
        }
        // 分销商今日提现的次数
        $recordCount = $distributionWithdraw
            ->where([
                ['distribution_id', '=', $param['distribution_id']]
            ])
            ->whereTime('create_time', 'today')
            ->count('distribution_withdraw_id');
        if ($recordCount >= $distributionSet['DISTRIBUTION_WITHDRAW_TIME']) {
            return $crypt->response([
                'code' => -2,
                'message' => '您已经没有提现次数',
            ], true);
        }
        //   微信提现限制
        if($param['distribution_type'] == 2){
             //   提现金额限制
             if($param['price']>5000){
                 return $crypt->response([
                    'code' => -1,
                    'message' => '提现金额不能超过5千元',
                ], true);
            }

            //   每日累计提现限制 
            $recordSumPrice = $distributionWithdraw
                ->where([
                    ['distribution_id', '=', $param['distribution_id']]
                ])
                ->whereTime('create_time', 'today')
                ->sum('price');
            if(($recordSumPrice+$param['price'])>5000){
                return $crypt->response([
                    'code' => -1,
                    'message' => '当日累计已超过5千元，本次不能提现',
                ], true);
            }   
        }
        
        // 查询当前分销商已结算佣金[提现用]
        $where = [
            ['distribution_id', '=', $param['distribution_id']],
        ];
        $info = $distribution
            ->alias('d')
            ->join('member m', 'm.member_id = d.member_id')
            ->where($where)
            ->field('d.close_brokerage,m.wechat_open_id')
            ->find();
        if ($info->close_brokerage < $param['price']) {
            return $crypt->response([
                'code' => -3,
                'message' => '可提现佣金不足',
            ], true);
        }
        // 提现方式除余额需手续费
        $param['service_charge'] = 0;
        if ($param['distribution_type'] != 1) {
            if ($param['distribution_type'] == 2 && !$info['wechat_open_id']) {
                return $crypt->response([
                    'code' => -4,
                    'message' => '您当前尚未绑定微信,无法进行微信提现',
                ], true);
            }
            $distributionSet = self::getSet();
            $distribution_withdraw_cost = $distributionSet['DISTRIBUTION_WITHDRAW_COST'];
            // 含有手续费
            if ($distribution_withdraw_cost > 0) {
                $distribution_withdraw_cost_type = $distributionSet['DISTRIBUTION_WITHDRAW_COST_TYPE'];
                $param['service_charge'] = $distribution_withdraw_cost_type ?
                    // 百分比 | 现金
                    $param['price'] * $distribution_withdraw_cost / 100 : $distribution_withdraw_cost;
                if ($param['service_charge'] >= $param['price']) {
                    // 提现金额不足以支付手续费
                    return $crypt->response([
                        'code' => -4,
                        'message' => '提现金额不足以支付手续费',
                    ], true);
                }
            }
        }
        // 减去用户可提现佣金
        $info->close_brokerage -= $param['price'];
        Db::startTrans();
        $info->save();
        // 插入记录
        $distributionWithdraw
            ->allowField(true)
            ->isUpdate(false)
            ->save($param);
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '提交成功',
        ], true);
    }

    /**
     * 提现记录
     * @param RSACrypt $crypt
     * @param DistributionWithdraw $distributionWithdraw
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function record(RSACrypt $crypt,
                           DistributionWithdraw $distributionWithdraw)
    {
        $param = $crypt->request();
        $where = [
            ['dw.distribution_id', '=', $param['distribution_id']],
        ];
        $data = $distributionWithdraw
            ->alias('dw')
            ->where($where)
            ->field('dw.distribution_withdraw_id,dw.withdraw_number,dw.price,dw.service_charge,
                dw.distribution_type,dw.status,date_format(dw.create_time,\'%Y-%m-%d %H:%i\') as apply_time')
            ->order(['dw.create_time' => 'desc', 'dw.distribution_withdraw_id' => 'desc'])
            ->paginate($distributionWithdraw->pageLimits, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
    }
}