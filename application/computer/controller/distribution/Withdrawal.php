<?php
declare(strict_types=1);

namespace app\computer\controller\distribution;

use app\computer\model\Distribution;
use app\computer\model\DistributionWithdraw;
use app\computer\controller\BaseController;
use app\common\model\MemberCard as MemberCardModel;
use app\computer\model\Member;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;

/**
 * 分销_提现
 * Class Withdrawal
 * @package app\computer\controller\distribution
 */
class Withdrawal extends BaseController
{
    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => ''],
    ];


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
     * @param Request $request
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, Distribution $distribution,MemberCardModel $memberCard)
    {
        $param = $request::instance()->param();
        $param['distribution_id'] = $distribution->get_distribution_id();
        $where = [
            ['distribution_id', '=', $param['distribution_id']]
        ];
        $data = $distribution
            ->where($where)
            ->field('total_brokerage,close_brokerage,member_id')
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
        $data['rule'] = [
            'type' => explode(',', $distributionSet['DISTRIBUTION_WITHDRAW_TYPE']),
            'cycle' => $distributionSet['DISTRIBUTION_WITHDRAW_DAY'],
            'times' => $distributionSet['DISTRIBUTION_WITHDRAW_TIME'],
            'min_price' => $distributionSet['DISTRIBUTION_WITHDRAW_PRICE'],
        ];
        //  银行卡信息
        $cardArr = $memberCard
                ->where([['member_id', '=', $data['member_id']]])
                ->field('card_id,card_bank_name,card_remark,RIGHT(card_number,4) as card_number_enc')
                ->select();
        // $cardArr = [];
        return $this->fetch('',['code' => 0, 'message' => config('message.')[0][0], 'data' => $data,'distribution_id' => $param['distribution_id'],'card'=> $cardArr]);
    }

    /**
     * 提现申请
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @param DistributionWithdraw $distributionWithdraw
     * @return mixed
     */
    public function to_apply(RSACrypt $crypt,
                             Distribution $distribution,
                             Member $member,
                             DistributionWithdraw $distributionWithdraw)
    {
        try {
            $param = $crypt->request();

            $ret = $distributionWithdraw->valid($param, 'to_apply');
            if ($ret['code']) {
                return $crypt->response($ret, true);
            }


            //   判断是否绑定微信账号
            if($param['distribution_type'] == 2){
                $member_id = Session::get('member_info')['member_id'];
                $wechat_open_id = $member->where([['member_id','=',$member_id]])->value('wechat_open_id');
                if(empty($wechat_open_id)){
                    return $crypt->response(['code' => -2, 'message' => '没有绑定微信账号，请绑定后提交'], true);
                }
            }


            if($param['distribution_type'] == 3 ){
                if(!isset($param['card_id']) || empty($param['card_id'])){
                     return $crypt->response(['code' => -2, 'message' => '没有选择银行卡'], true);
                }
            }

            // halt($param);
            Env::load(Env::get('app_path') . 'common/ini/.distribution');
            $distributionSet = Env::get();
            if ($param['price'] < $distributionSet['DISTRIBUTION_WITHDRAW_PRICE']) {
                // 提现金额不可低于%d
                return $crypt->response(['code' => -1, 'message' => sprintf(config('message.')[-18][-4],
                    $distributionSet['DISTRIBUTION_WITHDRAW_PRICE'])], true);
            }
            // 查询用户提现记录
            $recordWhere = [
                ['create_time', 'between time', [date('Y-m-') . '01',
                    date('Y-m-d', strtotime(date('Y-m-') . '01'
                        . '+ ' . $distributionSet['DISTRIBUTION_WITHDRAW_DAY'] . 'days'))]],
                ['distribution_id', '=', $param['distribution_id']],
            ];
            // 分销商指定周期内提现得次数[开始时间为月初计]
            $recordCount = $distributionWithdraw
                ->where($recordWhere)
                ->count('distribution_withdraw_id');
            if ($recordCount >= $distributionSet['DISTRIBUTION_WITHDRAW_TIME']) {
                // 您已经没有提现次数
                return $crypt->response(['code' => -2, 'message' => config('message.')[-18][-5]], true);
            }
            // 查询当前分销商已结算佣金[提现用]
            $where = [
                ['distribution_id', '=', $param['distribution_id']],
            ];
            $info = $distribution
                ->where($where)
                ->field('close_brokerage')
                ->find();
            if ($info->close_brokerage < $param['price']) {
                // 可提现佣金不足
                return $crypt->response(['code' => -3, 'message' => config('message.')[-18][-2]], true);
            }
            // 提现方式除余额需手续费
            $param['service_charge'] = 0;
            if ($param['distribution_type'] != 1) {
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
                        return $crypt->response(['code' => -4, 'message' => config('message.')[-18][-7]], true);
                    }
                }
            }
            // 减去用户可提现佣金
            $info->close_brokerage -= $param['price'];
            Db::startTrans();
            $info->save();
            // 插入记录
            $distributionWithdraw->allowField(true)->isUpdate(false)->save($param);
            Db::commit();
            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], true);
        } catch (\Exception $e) {
            Db::rollback();
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }
    }

    public function success1()
    {
        return $this->fetch();
    }

    /**
     * 提现记录
     * @param Distribution $distribution
     * @param DistributionWithdraw $distributionWithdraw
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function record(Distribution $distribution,
                           DistributionWithdraw $distributionWithdraw)
    {

        $distribution_id = $distribution->get_distribution_id();
        $where = [
            ['dw.distribution_id', '=', $distribution_id],
        ];
        $data = $distributionWithdraw
            ->alias('dw')
            ->where($where)
            ->field('dw.distribution_withdraw_id,dw.withdraw_number,dw.price,dw.service_charge,status,
            dw.distribution_type,dw.status,date_format(dw.create_time,\'%Y-%m-%d %H:%i\') as apply_time')
            ->withAttr('status_text', function ($e, $data) {
                return ['待审核','已通过','暂停转账'][$data['status']];
            })
            ->order(['dw.create_time' => 'desc', 'dw.distribution_withdraw_id' => 'desc'])
            ->paginate($distributionWithdraw->pageLimits);
        return $this->fetch('', ['code' => 0, 'message' => config('message.')[0][0], 'data' => $data]);

    }
}