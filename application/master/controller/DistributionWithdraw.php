<?php

namespace app\master\controller;

use think\Db;
use think\Controller;
use think\facade\Env;
use think\facade\Request;
use app\common\model\DistributionWithdraw as DistributionWithdrawModel;
use app\common\model\Consumption;
use app\common\model\Distribution;
use app\common\model\Member as MemberModel;
use EasyWeChat\Factory;

class DistributionWithdraw extends Controller
{
    /**
     * 提现规则设置文件路径
     * @var
     */
    private $filename;

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.distribution');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.distribution';
    }

    public function rule()
    {
        return $this->fetch('', [
            'single_store' => config('user.one_more'),
        ]);
    }

    /**
     * 保存提现规则设置
     * @param Request $request
     * @return array
     */
    public function editVal(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

                if (!isset($param['distribution_withdraw_type'])) return ['code' => -100, 'message' => '提现方式至少选择一种'];
                $param['distribution_withdraw_type'] = implode(',', $param['distribution_withdraw_type']);

                foreach ($param as $key => $item) {
                    if (is_numeric($item)) $item = abs($item);
                    if (!ini_file(NULL, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/distribution_withdraw/rule'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 分销提现审核列表
     * @param Request $request
     * @param DistributionWithdrawModel $distributionWithdraw
     * @return array|mixed
     */
    public function index(Request $request, DistributionWithdrawModel $distributionWithdraw)
    {
        try {
            $param = $request::get();
            $timeWhere = [];
            if (!empty($param['start_date'])) {
                $timeWhere[] = ['distribution.create_time', '>=', $param['start_date']];
            }
            if (!empty($param['end_date'])) {
                $timeWhere[] = ['distribution.create_time', '<=', $param['end_date']];
            }
            $createTimeWhere = [];
            if (!empty($param['start_date1'])) {
                $createTimeWhere[] = ['a.create_time', '>=', $param['start_date1']];
            }
            if (!empty($param['end_date1'])) {
                $createTimeWhere[] = ['a.create_time', '<=', $param['end_date1']];
            }
            $condition = [];
            if (!empty($param['distribution_type']) && $param['distribution_type'] != -1) $condition[] = ['distribution_type', 'eq', $param['distribution_type']];
            if (!empty($param['keywords'])) $condition[] = ['member.phone|member.nickname|withdraw_number', 'like', '%' . $param['keywords'] . '%'];
            // 提现列表
            $data = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member member', 'member.member_id = distribution.member_id', 'left')
                ->join('distribution_level distribution_level', 'distribution_level.distribution_level_id = distribution.distribution_level_id', 'left')
                ->join('member_card mc','mc.card_id = a.card_id','left')
                ->where($condition)
                ->where($timeWhere)
                ->where($createTimeWhere)
                ->field('a.*,member.nickname,member.phone,member.avatar,level_title,member.phone,
                mc.card_bank_name,mc.card_bank_owner,mc.card_number')
                ->whereTime('a.create_time', '>=', '-48 hours')
                ->order('a.create_time', 'desc')
                ->paginate(10, false, ['query' => $param]);
        
            // 申请提现单数
            $count = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member member', 'member.member_id = distribution.member_id', 'left')
                ->where($condition)
                ->where('a.status', 'eq', 0)
                ->where($timeWhere)
                ->whereTime('a.create_time', '>=', '-48 hours')
                ->count();
        
            $total = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member member', 'member.member_id = distribution.member_id', 'left')
                ->where($condition)
                ->where($timeWhere)
                ->whereTime('a.create_time', '>=', '-48 hours')
                ->sum('price');
        
            $serviceCharge = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member member', 'member.member_id = distribution.member_id', 'left')
                ->where($condition)
                ->where($timeWhere)
                ->whereTime('a.create_time', '>=', '-48 hours')
                ->sum('service_charge');
        
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'data' => $data,
            'count' => $count ?: 0,
            'total' => $total ?: 0,
            'service_charge' => $serviceCharge ?: 0,
            'single_store' => config('user.one_more'),
        ]);
    }

    /**
     * 确认转账
     * @param Request $request
     * @param DistributionWithdrawModel $distributionWithdraw
     * @param MemberModel $member
     * @param Consumption $consumption
     * @return array
     */
    public function checkTransfer(Request $request,
                                  DistributionWithdrawModel $distributionWithdraw,
                                  MemberModel $member,
                                  Consumption $consumption)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                $param = $request::post();
                $data = $distributionWithdraw
                    ->alias('dw')
                    ->where([
                        ['dw.distribution_withdraw_id', 'in', $param['distribution_withdraw_id']],
                        ['dw.status', '<>', 1],
                    ])
                    ->join('distribution d', 'd.distribution_id = dw.distribution_id')
                    ->field('dw.distribution_withdraw_id,dw.distribution_id,dw.withdraw_number,
                    dw.price,dw.service_charge,dw.distribution_type,dw.status,d.member_id')
                    ->select();
                $consArr = [];
                foreach ($data as $k => $v) {
                    switch ($v['distribution_type']) {
                        case 2:
                            // 微信转到零钱
                            $memberInfo = $member
                                ->where('member_id', 'eq', $v['member_id'])
                                ->field('nickname,app_open_id,micro_open_id,web_open_id,wechat_open_id')
                                ->find();
                            $t = $memberInfo['app_open_id'] ? 'app' : ($memberInfo['micro_open_id'] ? 'applet' : 'mobile');
                            $app = Factory::payment(config('wechat.')[$t]);
                            $number = strtoupper(dechex(date('m'))) . date('d') . substr(strval(time()), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
                            $transferMessage = $app->transfer->toBalance([
                                'partner_trade_no' => $number, // 商户订单号，需保持唯一性(只能是字母或者数字，不能包含有符号)
                                'openid'           => $memberInfo['wechat_open_id'],
                                'check_name'       => 'NO_CHECK', // NO_CHECK：不校验真实姓名, FORCE_CHECK：强校验真实姓名
                                're_user_name'     => $memberInfo['nickname'], // 如果 check_name 设置为FORCE_CHECK，则必填用户真实姓名
                                'amount'           => ($v['price'] - $v['service_charge']) * 100, // 企业付款金额，单位为分  [扣除手续费]
                                'desc'             => '提现转账', // 企业付款操作说明信息。必填
                            ]);
                            if($transferMessage['result_code'] == 'FAIL'){
                            	 return ['code' => -100, 'message' => $transferMessage['err_code_des']];
                            }
                            break;
                        default:
                            // 余额转账
                            $um = $member
                                ->where('member_id', 'eq', $v['member_id'])
                                ->field('usable_money')
                                ->find();
                            $um->usable_money += $v['price'];
                            $um->save();
                            // 增加余额记录
                            $consArr[] = [
                                'member_id'       => $v['member_id'],
                                'type'            => 1,
                                'price'           => $v['price'],
                                'way'             => 4,
                                'order_number'    => $v['withdraw_number'],
                                'balance'         => $um->usable_money,
                                'status'          => 1,
                                'manage_describe' => '佣金转入',
                            ];
                            break;
                    }
                    $distributionWithdraw
                        ->where('distribution_withdraw_id', 'eq', $v['distribution_withdraw_id'])
                        ->update([
                            'status'   => 1,
                            'end_time' => date('Y-m-d'),
                        ]);
                }
                if (!empty($consArr)) {
                    $consumption
                        ->allowField(TRUE)
                        ->isUpdate(FALSE)
                        ->saveAll($consArr);
                }
                Db::commit();
                return ['code' => 0, 'message' => '操作成功'];
            } catch (\ Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 暂停转账
     * @param Request $request
     * @param DistributionWithdrawModel $distributionWithdraw
     * @return array
     */
    public function stopTransfer(Request $request, DistributionWithdrawModel $distributionWithdraw)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                $param = $request::post();

                $distributionWithdraw->where([
                    ['distribution_withdraw_id', 'in', $param['distribution_withdraw_id']],
                ])
                    ->update(['status' => 2]);

                Db::commit();
                return ['code' => 0, 'message' => '操作成功'];
            } catch (\ Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 提现管理列表
     * @param Request $request
     * @param DistributionWithdrawModel $distributionWithdraw
     * @param Distribution $distribution
     * @return array|mixed
     */
    public function getList(Request $request, DistributionWithdrawModel $distributionWithdraw, Distribution $distribution)
    {
        try {
            $param = $request::get();

            if (empty($param['start_date']) && empty($param['end_date'])) {
                $timeWhere = [];
            } else {
                $timeWhere = [
                    ['distribution.create_time', '>=', $param['start_date']],
                    ['distribution.create_time', '<=', $param['end_date'] . ' 23:59:59'],
                ];
            }

            $condition = [];
            if (!empty($param['distribution_type']) && $param['distribution_type'] != -1) $condition[] = ['distribution_type', 'eq', $param['distribution_type']];
            if (!empty($param['keywords'])) $condition[] = ['member.phone|nickname|withdraw_number', 'like', '%' . $param['keywords'] . '%'];

            // 提现列表
            $data = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member member', 'member.member_id = distribution.member_id', 'left')
                ->join('distribution_level distribution_level', 'distribution_level.distribution_level_id = distribution.distribution_level_id', 'left')
                ->where($condition)
                ->where($timeWhere)
                ->field('a.*,member.nickname,member.phone,member.avatar,level_title,IFNULL(sum(a.price),0) as price,IFNULL(sum(service_charge),0) as service_charge,
                IFNULL(count(a.distribution_withdraw_id),0) as withdrawCount,IFNULL(sum(case when a.status = 1 then a.price else 0 end),0) as getTotal')
                ->group('a.distribution_id')
                ->order('a.create_time', 'desc')
                ->paginate(10, FALSE, ['query' => $param]);

            // 分销商数量
            $disCount = $distribution->count();

            // 申请提现总金额
            $count = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member member', 'member.member_id = distribution.member_id', 'left')
                ->where($condition)
                ->where($timeWhere)
                ->sum('price');

            // 到账总金额
            $total = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member member', 'member.member_id = distribution.member_id', 'left')
                ->where($condition)
                ->where($timeWhere)
                ->where('a.status', 'eq', 1)
                ->sum('price');

            // 手续费
            $serviceCharge = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member member', 'member.member_id = distribution.member_id', 'left')
                ->where($condition)
                ->where($timeWhere)
                ->sum('service_charge');

        } catch (\Exception $e) {
//            $this->error($e->getMessage());
            $this->error(config('message.')[-1]);
            return 0;
        }

        return $this->fetch('', [
            'data'           => $data,
            'count'          => $count ?: 0,
            'disCount'       => $disCount ?: 0,
            'total'          => $total ?: 0,
            'service_charge' => $serviceCharge ?: 0,
            'single_store'   => config('user.one_more'),
        ]);
    }

    /**
     * 提现管理个人分销商详情
     * @param Request $request
     * @param DistributionWithdrawModel $distributionWithdraw
     * @param MemberModel $member
     * @return array|mixed
     */
    public function details(Request $request, DistributionWithdrawModel $distributionWithdraw, MemberModel $member)
    {
        try {
            $param = $request::get();

            $condition = [
                ['a.distribution_id', 'eq', $param['distribution_id']],
            ];
            if (isset($param['keywords']) && !empty($param['keywords'])) {
                $condition[] = [
                    'a.withdraw_number', 'like', '%' . $param['keywords'] . '%',
                ];
            }
            if (isset($param['start_date']) && !empty($param['start_date']) && isset($param['end_date']) && !empty($param['end_date'])) {
                $_time_between[] = $param['start_date'];
                $_time_between[] = $param['end_date'];
                $condition[] = [
                    ['a.create_time', 'between time', $_time_between],
                ];
            }
            // 提现列表
            $data = $distributionWithdraw
                ->alias('a')
                ->join('distribution distribution', 'distribution.distribution_id = a.distribution_id', 'left')
                ->join('member_card mc','mc.card_id = a.card_id','left')
                ->where($condition)
                ->field('a.*,distribution.member_id,mc.card_bank_name,mc.card_bank_owner,mc.card_number')
                ->order('a.create_time', 'desc')
                ->paginate(10, false, ['query' => $param]);


            $memberInfo = $member->alias('Member')
                ->join('Distribution Distribution', 'Distribution.member_id = Member.member_id')
                ->where([
                    ['Distribution.distribution_id', '=', $param['distribution_id']],
                ])->field([
                    'Member.avatar'   => 'avatar',
                    'Member.nickname' => 'nickname',
                ])->find();

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        return $this->fetch('', [
            'data'            => $data,
            'memberInfo'      => $memberInfo,
            'distribution_id' => $param['distribution_id'],
        ]);
    }
}