<?php
declare(strict_types = 1);

namespace app\interfaces\controller\distribution;

use app\common\model\Distribution;
use app\common\model\DistributionLevel;
use app\common\model\Member;
use app\common\model\Message;
use app\common\model\OrderAttach;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Hook;

class Become extends BaseController
{
    /**
     * 提交申请分销商表单
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @param DistributionLevel $distributionLevel
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function apply(RSACrypt $crypt,
                          Distribution $distribution,
                          DistributionLevel $distributionLevel,
                          Member $member)
    {
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
            return $crypt->response([
                'code' => -1,
                'message' => '您已经申请分销商,请勿重复操作',
            ], true);
        }
        // 查询会员上级分销商存储值
        $distribution_superior = $member
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->value('distribution_superior');
        // 查询邀请人信息
        $param['referrer_id'] = $param['top_id'] = 0;
        $param['branch_strand'] = 1;
        if ($distribution_superior) {
            $referrer = $distribution
                ->where([
                    ['distribution_id', '=', $distribution_superior],
                    ['audit_status', '=', 1],
                ])
                ->field('member_id,top_id,branch_strand')
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
        Db::startTrans();
        // 插入申请记录
        $distribution
            ->allowField(true)
            ->isUpdate(false)
            ->save($param);
        if ($distribution->top_id == 0) {
            // 设置自己为自己的顶级分销商
            $distribution->top_id = $distribution->distribution_id;
            $distribution->save();
        }
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '提交成功',
        ], true);
    }
    
    /**
     * 成为代言规则
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tobe_distributor_rule(RSACrypt $crypt,
                                          Distribution $distribution)
    {
        $member_id = request()->mid ?? '';
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distributionSet = Env::get();
        $set = [];
        //申请代言表单
        if ($distributionSet['DISTRIBUTION_MANUAL']) {
            $audit_status = 2;
            if ($member_id) {
                $audit_status = $distribution
                    ->where([
                        ['member_id', '=', $member_id],
                    ])
                    ->value('audit_status');
                if (is_null($audit_status)) {
                    $audit_status = 2;
                }
            }
            array_push($set, [
                'title' => '成为代言人规则',
                'rule' => [
                    [
                        'keyword' => 'apply',
                        'audit_status' => $audit_status,
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
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $set,
        ], true);
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
            ],
            'id_card' => [
                'display' => $distributionSet['DISTRIBUTION_NUMBER_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_NUMBER_REQUIRED'],
            ],
            'wechat_no' => [
                'display' => $distributionSet['DISTRIBUTION_WECHAT_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_WECHAT_REQUIRED'],
            ],
            'phone' => [
                'display' => $distributionSet['DISTRIBUTION_PHONE_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_PHONE_REQUIRED'],
            ],
            'sex' => [
                'display' => $distributionSet['DISTRIBUTION_SEX_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_SEX_REQUIRED'],
            ],
            'address' => [
                'display' => $distributionSet['DISTRIBUTION_ADDRESS_SHOW'],
                'require' => $distributionSet['DISTRIBUTION_ADDRESS_REQUIRED'],
            ],
        ];
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $formSet,
        ], true);
    }
    
    /**
     * 检测用户此次支付是否含有指定成为分销商商品
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function queryPoint(RSACrypt $crypt,
                               OrderAttach $orderAttach,
                               Distribution $distribution)
    {
        $args = $crypt->request();
        $args['member_id'] = request(false)->mid ?? '';
        $where = [
            ['status', '=', 1],                 // 已付款
            ['order_number|order_attach_number', '=', $args['out_trade_no']],
        ];
        $data = $orderAttach
            ->where($where)
            ->with(['orderGoodsList'])
            ->field('order_attach_id,order_id')
            ->select();
        $ret = [
            'is_first_tobe' => 0,       //是否此单才成为分销商 0否 1是
            'has_distributor' => 0,
            'has_distribution' => 0,
            'distribution_id' => '',
        ];
        if (!$data->isEmpty()) {
            foreach ($data as $item) {
                if ($item['order_goods_list']) {
                    foreach ($item['order_goods_list'] as $_ogl) {
                        if ($_ogl['is_distributor']) {
                            $ret['has_distributor'] = 1;
                        }
                        if ($_ogl['is_distribution']) {
                            $ret['has_distribution'] = 1;
                        }
                    }
                }
            }
        }
        if ($args['member_id']) {
            $did = $distribution
                ->where([
                    ['member_id', '=', $args['member_id']],
                    ['audit_status', '=', 1],
                ])
                ->value('distribution_id');
            if ($did) {
                $ret['distribution_id'] = $did;
                if ($flag = Cache::store('file')->get($did)) {
                    $ret['is_first_tobe'] = $flag;
                }
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $ret,
        ], true);
    }
    
    /**
     * 会员转分销商
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @param DistributionLevel $distributionLevel
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function vipTurnDist(RSACrypt $crypt,
                                Distribution $distribution,
                                DistributionLevel $distributionLevel,
                                Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 查询默认分销商等级
        $lowest = $distributionLevel
            ->order(['level_weight' => 'asc'])
            ->value('distribution_level_id');
        $info = $member
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->with(['distributionRecord'])
            ->field('member_id,nickname,phone,sex,distribution_superior,web_open_id,
            subscribe_time,micro_open_id,phone')
            ->find();
        if (!is_null($info['distribution_record'])) {
            return $crypt->response([
                'code' => -1,
                'message' => '该会员已经是分销商',
            ], true);
        }
        $data = ['distribution_id' => ''];
        if ($info) {
            $inc = [
                'referrer_id' => 0,
                'top_id' => 0,
                'branch_strand' => 1,
            ];
            if ($info['distribution_superior']) {
                $referrer = $distribution
                    ->where([
                        ['distribution_id', '=', $info['distribution_superior']],
                        ['audit_status', '=', 1],
                    ])
                    ->field('member_id,top_id,branch_strand,(select count(distribution_id) from `ishop_distribution` 
                    where delete_time is null and audit_status = 1 and referrer_id = ' . $info['distribution_superior'] . ') as num')
                    ->find();
                // 合法上级
                if (!is_null($referrer)) {
                    // 同一分销链
                    $inc['top_id'] = $referrer['top_id'];
                    // 开下级新分支
                    $inc['branch_strand'] = $referrer['branch_strand'] . ',' . ($referrer['num'] + 1);
                    $inc['referrer_id'] = $info['distribution_superior'];
                }
            }
            $data = [
                'member_id' => $param['member_id'],
                'distribution_level_id' => $lowest,
                'phone' => $info['phone'],
                'real_name' => $info['nickname'],
                'sex' => $info['sex'],
                // 默认已通过
                'audit_status' => 1,
                'audit_time' => date('Y-m-d H:i:s'),
                'referrer_id' => $inc['referrer_id'],       // 上级id
                'top_id' => $inc['top_id'],
                'branch_strand' => $inc['branch_strand'],
            ];
            Db::startTrans();
            // 插入新分销商
            $distribution
                ->allowField(true)
                ->isUpdate(false)
                ->save($data);
            // 推送消息[会员转分销商][只含站内信]
            $pushServer = app('app\\interfaces\\behavior\\Push');
            $pushServer->send([
                'tplKey' => 'distribution_state',
                'openId' => $info['web_open_id'],
                'subscribe_time' => $info['subscribe_time'],
                'microId' => $info['micro_open_id'],
                'phone' => $info['phone'],
                'data' => [3],
                'inside_data' => [
                    'member_id' => $param['member_id'],
                    'type' => 0,
                    'jump_state' => '3',
                    'attach_id' => $distribution->distribution_id,
                    'file' => 'image/dui.png',
                ],
                'sms_data' => [],
            ], 3);
            if ($inc['top_id'] === 0) {
                // 自己为自己的顶级分销商
                $distribution
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save([
                        'distribution_id' => $distribution->distribution_id,
                        'top_id' => $distribution->distribution_id,
                    ]);
            } else {
                // 含上3级分销商
                $strand = '(`distribution_id` != ' . $distribution->distribution_id . ' and `top_id` = '
                    . $inc['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' . $inc['branch_strand'] . '\',1) = 1 and'
                    . ' (length(`branch_strand`) - length(replace(`branch_strand`,\',\',\'\'))) >= ' .
                    (substr_count($inc['branch_strand'], ',') - 3) . ')';
                // 有关系的分销商
                $relation = '(`top_id` = ' . $inc['top_id'] . ' and locate(concat(`branch_strand`,\',\'),\'' .
                    $inc['branch_strand'] . '\',1) = 1 and' . ' (length(`branch_strand`) - ' .
                    'length(replace(`branch_strand`,\',\',\'\'))) < ' . substr_count($inc['branch_strand'], ',') . ')';
                $args = [
                    'strand' => $strand,
                    'relation' => $relation,
                    'info' => [
                        'top_id' => $inc['top_id'],
                        'count' => substr_count($inc['branch_strand'], ','),
                    ],
                ];
                Hook::exec(['app\\interfaces\\behavior\\Distribution', 'updateUpper'], $args);
            }
            Db::commit();
            $data['distribution_id'] = $distribution->distribution_id;
        }
        return $crypt->response([
            'code' => 0,
            'message' => '转化成功',
            'data' => $data,
        ], true);
    }
}