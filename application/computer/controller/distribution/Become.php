<?php
declare(strict_types=1);

namespace app\computer\controller\distribution;

use app\computer\model\Distribution;
use app\computer\model\DistributionLevel;
use app\computer\model\Member;
use app\computer\model\Message;
use app\computer\model\OrderAttach;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\exception\Handle;
use think\facade\Env;
use think\facade\Hook;

class Become extends BaseController
{
    protected $beforeActionList = [
        //检查是否登录
        'is_login'=>['except' => '']
    ];

    /**
     * 提交申请分销商表单
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @param DistributionLevel $distributionLevel
     * @param Member $member
     * @return mixed
     */
    public function apply(RSACrypt $crypt,
                          Distribution $distribution,
                          DistributionLevel $distributionLevel,
                          Member $member)
    {
        try {
            $param = $crypt->request();
            $param = array_filter($param);
            $param['member_id'] = request(0)->mid;
            $checkRet = $distribution->valid($param, 'apply');
            if ($checkRet['code'] != 0) {
                return $crypt->response($checkRet, true);
            }
            // 检测用户是否已经为分销商或者有待审核的申请记录
            $record = $distribution
                ->where([['member_id', '=', $param['member_id']]])
                ->value('audit_status');
            if (!is_null($record) && $record != 2) {
                // 您已经申请分销商,请勿重复操作
                return $crypt->response(['code' => -1, 'message' => config('message.')[-18][-3]], true);
            }
            // 查询会员上级分销商存储值
            $distribution_superior = $member
                ->where([['member_id', '=', $param['member_id']]])
                ->value('distribution_superior');
            // 查询邀请人信息
            $param['referrer_id'] = 0;
            $param['branch_strand'] = 1;
            if ($distribution_superior) {
                $referrer = $distribution
                    ->where([
                        ['distribution_id', '=', $distribution_superior],
                        ['audit_status', '=', 1],
                    ])
                    ->field('member_id,top_id,branch_strand,(select count(distribution_id) from `ishop_distribution` 
                    where delete_time is null and audit_status <> 2 and referrer_id = ' . $distribution_superior . ') as num')
                    ->find();
                // 合法上级
                if (!is_null($referrer)) {
                    // 同一分销链
                    $param['top_id'] = $referrer['top_id'];
                    // 开下级新分支[先记录父类链值,审核处整理自己的顺序]
                    $param['branch_strand'] = $referrer['branch_strand'];
                    $param['referrer_id'] = $distribution_superior;
                }
            }
            // 查询最低级分销商等级id
            $param['distribution_level_id'] = $distributionLevel
                ->order(['level_weight' => 'asc'])
                ->value('distribution_level_id');
            $param['apply_time'] = date('Y-m-d H:i:s');
            $param['become_type'] = 1;  //成为分销商途径
            // 插入申请记录
            $distribution->allowField(true)->isUpdate(false)->save($param);
            if (!array_key_exists('top_id', $param)) {
                // 自己为自己的顶级分销商
                $distribution
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save(['distribution_id' => $distribution->distribution_id, 'top_id' => $distribution->distribution_id]);
            }
            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0],'url' =>'apply_success'], true);
        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }
    }

    public function apply_success()
    {
       return  $this->fetch();
    }

    /**
     * 成为代言规则
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function tobe_distributor_rule(RSACrypt $crypt)
    {
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distributionSet = Env::get();
        $set = [];
        //申请代言
        if ($distributionSet['DISTRIBUTION_MANUAL']) {
            array_push($set, [
                'title' => '成为代言人规则',
                'rule' => [
                    [
                        'keyword' => 'apply',
                    ],
                ],
            ]);
        }
        if ($distributionSet['DISTRIBUTION_BUY'] || $distributionSet['DISTRIBUTION_ACCUMULATIVE']) {
            $rule = ['title' => '成为代言人规则', 'rule' => []];
            if ($distributionSet['DISTRIBUTION_BUY']) {
                array_push($rule['rule'], [
                    // 购买指定商品
                    'keyword' => 'special_area',
                ]);
            }
            if ($distributionSet['DISTRIBUTION_ACCUMULATIVE']) {
                array_push($rule['rule'], [
                    // 满X元成为分销商
                    'keyword' => 'full',
                    'condition' => $distributionSet['DISTRIBUTION_ACCUMULATIVE_PRICE'],
                ]);
            }
            array_push($set, $rule);
        }
//        halt($set);
        return $this->fetch('',['data' => $set]);
    }

    /**
     * 成为分销商表单设置
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function distribution_form_set(RSACrypt $crypt)
    {
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distributionSet = Env::get();
        $formSet = [
            'real_name' => [
                'display' => $distributionSet['DISTRIBUTION_NAME_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_NAME_REQUIRED'],
                'name' => 'real_name',
                'text' => '姓 名',
            ],
            'id_card' => [
                'display' => $distributionSet['DISTRIBUTION_NUMBER_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_NUMBER_REQUIRED'],
                'name' => 'id_card',
                'text' => '身份证',
            ],
            'wechat_no' => [
                'display' => $distributionSet['DISTRIBUTION_WECHAT_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_WECHAT_REQUIRED'],
                'name' => 'wechat_no',
                'text' => '微信号',
            ],
            'phone' => [
                'display' => $distributionSet['DISTRIBUTION_PHONE_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_PHONE_REQUIRED'],
                'name' => 'phone',
                'text' => '手机号',
            ],
            'sex' => [
                'display' => $distributionSet['DISTRIBUTION_SEX_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_SEX_REQUIRED'],
                'name' => 'sex',
                'text' => '性 别',
            ],
            'address' => [
                'display' => $distributionSet['DISTRIBUTION_ADDRESS_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_ADDRESS_REQUIRED'],
                'name' => 'address',
                'text' => '地 址',
            ],
        ];
//        halt($formSet);
        return $this->fetch('',['data' => $formSet]);
    }


    /**************废弃****************/

//    /**
//     * 检测用户此次支付是否含有指定成为分销商商品
//     * @param RSACrypt $crypt
//     * @param OrderAttach $orderAttach
//     * @param Distribution $distribution
//     * @return mixed
//     */
//    public function queryPoint(RSACrypt $crypt, OrderAttach $orderAttach, Distribution $distribution)
//    {
//        try {
//            $args = $crypt->request();
//            $args['member_id'] = request(false)->mid ?? '';
//            $where = [
//                // 已付款
//                ['status', '=', 1],
//                ['order_number|order_attach_number', '=', $args['out_trade_no']],
//            ];
//            $data = $orderAttach
//                ->where($where)
//                ->with(['orderGoodsList'])
//                ->field('order_attach_id,order_id')
//                ->select();
//            $ret = [
//                'has_distributor' => 0,
//                'has_distribution' => 0,
//                'distribution_id' => '',
//            ];
//            if (!$data->isEmpty()) {
//                foreach ($data as $item) {
//                    if ($item['order_goods_list']) {
//                        foreach ($item['order_goods_list'] as $_ogl) {
//                            if ($_ogl['is_distributor']) {
//                                $ret['has_distributor'] = 1;
//                            }
//                            if ($_ogl['is_distribution']) {
//                                $ret['has_distribution'] = 1;
//                            }
//                        }
//                    }
//                }
//            }
//            if ($args['member_id']) {
//                $did = $distribution
//                    ->where([
//                        ['member_id', '=', $args['member_id']],
//                        ['audit_status', '=', 1],
//                    ])
//                    ->value('distribution_id');
//                if ($did) {
//                    $ret['distribution_id'] = $did;
//                }
//            }
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'data' => $ret], true);
//        } catch (\Exception $e) {
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//        }
//    }
//
//    /**
//     * 会员转分销商
//     * @param RSACrypt $crypt
//     * @param Distribution $distribution
//     * @param DistributionLevel $distributionLevel
//     * @param Message $message
//     * @param Member $member
//     * @return mixed
//     */
//    public function vipTurnDist(RSACrypt $crypt,
//                                Distribution $distribution,
//                                DistributionLevel $distributionLevel,
//                                Message $message,
//                                Member $member)
//    {
//        try {
//            $param = $crypt->request();
//            $param['member_id'] = request(0)->mid;
//            Db::startTrans();
//            // 查询默认分销商等级
//            $lowest = $distributionLevel
//                ->order(['level_weight' => 'asc'])
//                ->value('distribution_level_id');
//            $info = $member
//                ->where([
//                    ['member_id', '=', $param['member_id']],
//                ])
//                ->with(['distributionRecord'])
//                ->field('member_id,nickname,phone,sex,distribution_superior')
//                ->find();
//            if (!is_null($info['distribution_record'])) {
//                return $crypt->response(['code' => -1, 'message' => config('message.')[-18][-6]], true);
//            }
//            $data = ['distribution_id' => ''];
//            if ($info) {
//                $inc = [
//                    'referrer_id' => 0,
//                    'top_id' => 0,
//                    'branch_strand' => 1,
//                ];
//                if ($info['distribution_superior']) {
//                    $referrer = $distribution
//                        ->where([
//                            ['distribution_id', '=', $info['distribution_superior']],
//                            ['audit_status', '=', 1],
//                        ])
//                        ->field('member_id,top_id,branch_strand,(select count(distribution_id) from `ishop_distribution`
//                    where delete_time is null and audit_status = 1 and referrer_id = ' . $info['distribution_superior'] . ') as num')
//                        ->find();
//                    // 合法上级
//                    if (!is_null($referrer)) {
//                        // 同一分销链
//                        $inc['top_id'] = $referrer['top_id'];
//                        // 开下级新分支
//                        $inc['branch_strand'] = $referrer['branch_strand'] . ',' . ($referrer['num'] + 1);
//                        $inc['referrer_id'] = $info['distribution_superior'];
//                    }
//                }
//                $data = [
//                    'member_id' => $param['member_id'],
//                    'distribution_level_id' => $lowest,
//                    'phone' => $info['phone'],
//                    'real_name' => $info['nickname'],
//                    'sex' => $info['sex'],
//                    // 默认已通过
//                    'audit_status' => 1,
//                    'audit_time' => date('Y-m-d H:i:s'),
//                    'referrer_id' => $inc['referrer_id'],       // 上级id
//                    'top_id' => $inc['top_id'],
//                    'branch_strand' => $inc['branch_strand'],
//                ];
//                $msg = [
//                    'member_id' => $param['member_id'],
//                    'type' => 0,
//                    'jump_state' => 'distribution',
//                    'title' => '会员转化分销商成功',
//                    'describe' => '恭喜您转化分销商成功',
//                    'file' => 'image/dui.png',
//                ];
//                if ($data) {
//                    // 插入新分销商
//                    $distribution->allowField(true)->isUpdate(false)->save($data);
//                    // 插入消息通知
//                    $message->allowField(true)->isUpdate(false)->save($msg);
//                    if ($inc['top_id'] === 0) {
//                        // 自己为自己的顶级分销商
//                        $distribution
//                            ->allowField(true)
//                            ->isUpdate(true)
//                            ->save(['distribution_id' => $distribution->distribution_id, 'top_id' => $distribution->distribution_id]);
//                    } else {
//                        // 含上级分销商
//                        $strand = '(`distribution_id` != ' . $distribution->distribution_id . ' and `top_id` = '
//                            . $inc['top_id'] . ' and locate(`branch_strand`,\'' . $inc['branch_strand'] . '\',1) = 1 and'
//                            . ' (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) >= ' .
//                            (substr_count($inc['branch_strand'], ',') - 2) . ')';
//                        $relation = '(`top_id` = ' . $inc['top_id'] . ' and locate(`branch_strand`,\'' .
//                            $inc['branch_strand'] . '\',1) = 1 and' . ' (length(`branch_strand`) - ' .
//                            'length(replace(`branch_strand`,\',\',\'\'))) < ' . substr_count($inc['branch_strand'], ',') . ')';
//                        $args = [
//                            'strand' => $strand,
//                            'relation' => $relation,
//                            'info' => [
//                                'top_id' => $inc['top_id'],
//                                'count' => substr_count($inc['branch_strand'], ','),
//                            ],
//                        ];
//                        Hook::exec(['app\\interfaces\\behavior\\Distribution', 'updateUpper'], $args);
//                    }
//                    $data['distribution_id'] = $distribution->distribution_id;
//                }
//            }
//            Db::commit();
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'data' => $data], true);
//        } catch (\Exception $e) {
//            Db::rollback();
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//        }
//    }
}