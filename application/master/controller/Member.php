<?php
declare(strict_types = 1);

namespace app\master\controller;

use app\common\model\Distribution;
use app\common\model\IntegralRecord;
use app\common\model\Consumption;
use app\common\model\MemberTask;
use think\Controller;
use think\Db;
use think\exception\ValidateException;
use think\facade\Env;
use think\facade\Request;
use app\common\model\Member as MemberModel;
use think\facade\Session;
use app\common\model\StoreOperation;
use app\common\model\Store;
use app\common\model\Config;
use app\common\model\MemberRank;
use app\common\model\Goods;

class Member extends Controller
{
    private $filename;
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.config';
    }
    
    public function general(Request $request, MemberModel $member, MemberRank $memberRank)
    {
        try {
            
            // 获取数据
            $data = $member->count();
            
            $_memberRankDataList = $memberRank
                ->order('min_points')
                ->select();
            
            $_memberRankList = [];
            
            foreach ($_memberRankDataList as $key => $value) {
                $_memberRankList[$key] = [
                    'file' => $value['file'],
                    'rank_name' => $value['rank_name'],
                    'member_num' => 0,
                    'member_rank_id' => $value['member_rank_id']
                ];
                
                if (isset($_memberRankDataList[$key + 1])) {
                    $_memberRankList[$key]['member_num'] = $member->alias('m')
                        ->field('m.*,IFNULL(sum(m_g_r.growth_value),0) c')
                        ->join('member_growth_record m_g_r', 'm.member_id=m_g_r.member_id', 'left')
                        ->group('m.member_id')->having("c >= {$value['min_points']} AND  c < {$_memberRankDataList[$key + 1]['min_points']}")->count();
                } else {
                    $_memberRankList[$key]['member_num'] = $member->alias('m')
                        ->field('m.*,IFNULL(sum(m_g_r.growth_value),0) c')
                        ->join('member_growth_record m_g_r', 'm.member_id=m_g_r.member_id', 'left')
                        ->group('m.member_id')->having("c >= {$value['min_points']}")->count();
                }
            }

//            $member_rank_id_arr = [];
//            // 符合值的等级id
//            foreach ($growth_value as $key => $value) {
//
//                if (isset($growth_value[$key+1])) {
//                    $memberModel->alias('m')
//                        ->field('m.*,sum(m_g_r.growth_value) c')
//                        ->join('member_growth_record m_g_r','m.member_id=m_g_r.member_id')
//                        ->group('m.member_id')->having("c between {}and 60")->select();
//                } else {
//                    $member_rank_id = $memberRank
//                        ->where('min_points', '>=', $value['growth'])
//                        ->value('member_rank_id');
//                }
//                array_push($member_rank_id_arr, $member_rank_id);
//            }
//            $result = [];
//            $memberRankArr = $memberRank->select();
//            foreach ($memberRankArr as $k => $v) {
//                $result[$v['member_rank_id']] = 0;
//                foreach ($member_rank_id_arr as $r => $t) {
//                    if ($v['member_rank_id'] == $t) {
//                        $result[$v['member_rank_id']]++;
//                    }
//                }
//            }
//
            $date = [];
            for ($i = 30; $i > 0; $i--) {
                $date[] = date("Y-m-d", strtotime('-' . $i . ' days', time()));
            }
            
            $memberCount = [];
            foreach ($date as $k => $v) {
                $count = $member
                    ->whereBetweenTime('register_time', $v)
                    ->cache(TRUE, 60)
                    ->count('member_id');
                array_push($memberCount, $count);
            }
            
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        
        return $this->fetch('', [
            'item' => $data,
            'result' => $_memberRankList,
            'date' => json_encode($date),
            'member_count' => json_encode($memberCount)
        ]);
    }
    
    /**
     * 成长值设置
     * @param Request $request
     * @param Config $config
     * @return array|mixed
     */
    public function member_growth(Request $request, Config $config)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                $param = $request::post();
                $config->valid($param, 'growth_settings');
                
                foreach ($param as $key => $item) {
                    $config->where('key', $key)->update(['value' => $item]);
                    
                    if (is_numeric($item)) {
                        $item = abs($item);
                    }
                    if (!ini_file(NULL, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/member/member_growth'];
            } catch (ValidateException $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => 0, 'message' => config('message.')[-1]];
            }
        }
        
        return $this->fetch('');
    }
    
    /**
     * 会员列表
     * @param Request $request
     * @param MemberModel $memberModel
     * @return array|mixed
     */
    public function index(Request $request, MemberModel $memberModel, MemberRank $memberRank)
    {
        try {
            
            // 获取参数
            $param = $request::get();
            
            // 条件定义
            $condition[] = ['member_id', '>', 0];
            // 父ID
            if (!empty($param['keyword'])) $condition[] = ['phone|nickname', 'like', '%' . $param['keyword'] . '%'];
            
            if (!empty($param['member_rank_id']) && $param['member_rank_id'] != -1) {
                
                $first = $memberRank->where('member_rank_id', $param['member_rank_id'])->value('min_points');
                $next = $memberRank->where([
                    ['min_points', '>', $first],
                ])->order('min_points')->value('min_points');
                $idStr = '';
                if (empty($next)) {
                    $idArr = $memberModel::withTrashed()->alias('m')
                        ->join('member_growth_record m_g_r', 'm.member_id=m_g_r.member_id', 'left')
                        ->field('m.*,IFNULL(sum(m_g_r.growth_value),0) c')
                        ->group('m.member_id')->having("c >= {$first}")->select();
                    foreach ($idArr as $k => $v) {
                        $idStr .= $v['member_id'] . ',';
                    }
                } else {
                    $idArr = $memberModel::withTrashed()->alias('m')
                        ->join('member_growth_record m_g_r', 'm.member_id=m_g_r.member_id', 'left')
                        ->field('m.member_id,IFNULL(sum(m_g_r.growth_value),0) c')
                        ->group('m.member_id')->having("c >= {$first} and c < {$next}")->select();
                    $idStr = '';
                    foreach ($idArr as $k => $v) {
                        $idStr .= $v['member_id'] . ',';
                    }
                }
                $condition[] = ['member_id', 'in', trim($idStr, ',')];
            }
            
            $data = $memberModel::where($condition)
                ->field('member_id,avatar,nickname,phone,usable_money,frozen_money,pay_points,rank_points,member_id as groupValue,status,register_time,delete_time')
                ->append(['rank_name'])
                ->order(['member_id' => 'desc'])
                ->paginate(15, false, ['query' => $param]);
            $rankArr = $memberRank->order('min_points')->select();
            
        } catch (\Exception $e) {
            
            return ['code' => -100, 'message' => $e->getMessage()];
            
        }
        
        return $this->fetch('', [
            'data' => $data,
            'member_rank' => $rankArr
        ]);
    }


    /**
     * 导出会员
     * @param MemberModel $memberModel
     * @throws \PHPExcel_Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function export(MemberModel $memberModel)
    {
        $_memberList = $memberModel::field('member_id,avatar,nickname,phone,usable_money,frozen_money,pay_points,rank_points,member_id as groupValue,status,register_time,delete_time')
            ->append(['rank_name'])
            ->order(['member_id' => 'desc'])
            ->select();

        require(Env::get('root_path') . 'extend/PHPExcel/Classes/PHPExcel.php');

        $_PHPExcel = new \PHPExcel();

//        $_PHPExcel->getProperties()->setCreator("Maarten Balliauw");
//        $_PHPExcel->getProperties()->setLastModifiedBy("Maarten Balliauw");
        $_PHPExcel->getProperties()->setTitle("Office 2007 XLSX Test Document");
        $_PHPExcel->getProperties()->setSubject("Office 2007 XLSX Test Document");
        $_PHPExcel->getProperties()->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.");
        $_PHPExcel->getProperties()->setKeywords("office 2007 openxml php");
//        $_PHPExcel->getProperties()->setCategory("Test result file");

        $_PHPExcel->setActiveSheetIndex(0);
        $_PHPExcel->getActiveSheet()->setCellValue('A1', '编号');//可以指定位置
        $_PHPExcel->getActiveSheet()->setCellValue('B1', '用户昵称');
        $_PHPExcel->getActiveSheet()->setCellValue('C1', '用户手机号');
        $_PHPExcel->getActiveSheet()->setCellValue('D1', '资金情况');
        $_PHPExcel->getActiveSheet()->setCellValue('E1', '积分情况');
        $_PHPExcel->getActiveSheet()->setCellValue('F1', '用户等级');
        $_PHPExcel->getActiveSheet()->setCellValue('G1', '成长值');
        $_PHPExcel->getActiveSheet()->setCellValue('H1', '注册时间');
        $_PHPExcel->getActiveSheet()->setCellValue('I1', '是否在黑名单中');


        // 设置对齐
        $_PHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);


        // 设置列宽
        $_PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $_PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);
        $_PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(16);
        $_PHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(14);
        $_PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(14);
        $_PHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(16);
        $_PHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(12);
        $_PHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $_PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(16);

        //循环数据
        foreach ($_memberList as $_key => $_member) {

            $_index = $_key + 2;

            $_PHPExcel->getActiveSheet()->setCellValue("A{$_index}", "#{$_member['member_id']}");
            $_PHPExcel->getActiveSheet()->setCellValue("B{$_index}", "{$_member['nickname']}");
            $_PHPExcel->getActiveSheet()->setCellValue("C{$_index}", "{$_member['phone']}");
            $_PHPExcel->getActiveSheet()->setCellValue("D{$_index}", "{$_member['usable_money']}元");
            $_PHPExcel->getActiveSheet()->setCellValue("E{$_index}", "{$_member['pay_points']}");
            $_PHPExcel->getActiveSheet()->setCellValue("F{$_index}", "{$_member['rank_name']}");
            $_PHPExcel->getActiveSheet()->setCellValue("G{$_index}", "{$_member['groupValue']}");
            $_PHPExcel->getActiveSheet()->setCellValue("H{$_index}", "{$_member['register_time']}");
            $_PHPExcel->getActiveSheet()->setCellValue("I{$_index}", $_member['status'] == 2 ? '是' : '否');
        }


        $_excelOutput = new \PHPExcel_Writer_Excel2007($_PHPExcel);
        ob_end_clean();

        header("Content-Type:application/vnd.ms-excel");
        header("Content-Disposition:attachment;filename=会员列表.xlsx");
        header("Pragma:no-cache");
        header("Expires:0");
        $_excelOutput->save('php://output');
    }

    /**
     * 会员新增
     * @param Request $request
     * @param MemberModel $memberModel
     * @param IntegralRecord $integralRecord
     * @param Consumption $consumption
     * @param MemberTask $memberTask
     * @return array|mixed
     */
    public function create(Request $request, MemberModel $memberModel, IntegralRecord $integralRecord, Consumption $consumption, MemberTask $memberTask)
    {
        
        if ($request::isPost()) {
            
            try {
                
                // 获取参数
                $param = $request::post();
                
                // 验证
                $check = $memberModel->valid($param, 'create');
                if ($check['code']) return $check;
                
                $state = $memberModel->allowField(true)->save($param);
                
                if ($state) {
                    
                    // 如果增加余额
                    if ($param['add_money']) {
                        $consumption->allowField(true)->save([
                            'member_id' => $memberModel->member_id,
                            'type' => 0,
                            'order_number' => get_order_sn(),
                            'order_attach_number' => '',
                            'price' => $param['add_money'],
                            'way' => 5,
                            'balance' => $param['usable_money'] + $param['add_money'],
                            'manage_describe' => '增加' . $param['add_money'] . '余额（管理员 - ' . Session::get('manage_id') . ' - ' . Session::get('manageName') . '）',
                            'status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                            'update_time' => date('Y-m-d H:i:s'),
                        ]);
                        
                        $memberModel->where('member_id', $memberModel->member_id)->setInc('usable_money', $param['add_money']);
                    }
                    $memberTask
                        ->allowField(true)
                        ->isUpdate(false)
                        ->save([
                            'member_id' => $memberModel->member_id,
                            'phone_state' => 1,
                            'phone_is_first' => 1,
                        ]);
                    // 如果增加积分
                    if ($param['add_integral']) {
                        // 积分记录
                        $integralRecord->allowField(true)->save([
                            'member_id' => $memberModel->member_id,
                            'type' => 0,
                            'integral' => $param['add_integral'],
                            'describe' => '增加' . $param['add_integral'] . '积分（管理员）',
                            'manage_describe' => '增加' . $param['add_integral'] . '积分（管理员 - ' . Session::get('manage_id') . ' - ' . Session::get('manageName') . '）',
                        ]);
                        
                        $memberModel->where('member_id', $memberModel->member_id)->setInc('pay_points', $param['add_integral']);
                    }
                    
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/member/index'];
                }
                
            } catch (\Exception $e) {
                
                return ['code' => -100, 'message' => $e->getMessage()];
                
            }
        }
        
        return $this->fetch('');
    }
    
    /**
     * 会员查看
     * @param Request $request
     * @param MemberModel $memberModel
     * @param IntegralRecord $integralRecord
     * @param Consumption $consumption
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function view(Request $request, MemberModel $memberModel, IntegralRecord $integralRecord, Consumption $consumption)
    {
        
        if ($request::isPost()) {
            
            try {
                
                // 获取参数
                $param = $request::post();
                
                // 验证
                $check = $memberModel->valid($param, 'view');
                if ($check['code']) return $check;
                if ($param['password'] == '') {
                    unset($param['password']);
                }
                
                $state = $memberModel->allowField(true)->isUpdate(true)->save($param);
                
                if ($state) {
                    
                    // 如果增加余额
                    if ($param['add_money']) {
                        $consumption->allowField(true)->save([
                            'member_id' => $param['member_id'],
                            'type' => 0,
                            'order_number' => get_order_sn(),
                            'order_attach_number' => '',
                            'price' => $param['add_money'],
                            'way' => 5,
                            'balance' => $param['usable_money'] + $param['add_money'],
                            'manage_describe' => '增加' . $param['add_money'] . '余额（管理员 - ' . Session::get('manage_id') . ' - ' . Session::get('manageName') . '）',
                            'status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                            'update_time' => date('Y-m-d H:i:s'),
                        ]);
                        
                        $memberModel->where('member_id', $param['member_id'])->setInc('usable_money', $param['add_money']);
                    }
                    
                    // 如果增加积分
                    if ($param['add_integral']) {
                        // 积分记录
                        $integralRecord->allowField(true)->save([
                            'member_id' => $param['member_id'],
                            'type' => 0,
                            'integral' => $param['add_integral'],
                            'describe' => '增加' . $param['add_integral'] . '积分（管理员）',
                            'manage_describe' => '增加' . $param['add_integral'] . '积分（管理员 - ' . Session::get('manage_id') . ' - ' . Session::get('manageName') . '）',
                        ]);
                        
                        $memberModel->where('member_id', $param['member_id'])->setInc('pay_points', $param['add_integral']);
                    }
                    
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/member/index'];
                }
                
            } catch (\Exception $e) {
                
                return ['code' => -100, 'message' => $e->getMessage()];
                
            }
        }
        
        return $this->fetch('', [
            'item' => $memberModel->get($request::get('member_id'))
        ]);
    }
    
    /**
     * 删除会员
     * @param Request $request
     * @param MemberModel $memberModel
     * @param Distribution $distribution
     * @return array
     */
    public function destroy(Request $request,
                            MemberModel $memberModel,
                            Distribution $distribution,
                            StoreOperation $storeOperation,
                            Store $store, Goods $goods)
    {
        if ($request::isPost()) {
            try {
                $args = $request::post();
                // 查询该会员是否为分销商
//                $distInfo = $distribution
//                    ->where([
//                        ['member_id', '=', $args['member_id']],
//                        ['audit_status', '<>', 2],
//                    ])
//                    ->field('distribution_id,audit_status')
//                    ->find();
                Db::startTrans();
                $check = $memberModel->valid($args, 'log_out');
                if ($check['code']) {
                    return $check;
                }
                
                // 操作记录
                $param['manage_id'] = Session::get("manage_id");
                $param['nickname'] = Session::get("manageName");
                $param['type'] = 4;
                $param['reason'] = $args['reason'];
                $param['member_id'] = $args['member_id'];
                $storeOperation::create($param);
                
                // 软删会员 去掉
//                $memberModel::destroy(function ($query) use ($args) {
//                    $query->where([['member_id', '=', $args['member_id']]]);
//                });
//                if (!empty($distInfo)) {
//                    (new \app\common\service\Distribution())->distributionRevoke([
//                        'distribution_id' => [$distInfo['distribution_id']],
//                        'type' => 3,
//                        'audit_status' => $distInfo['audit_status'],
//                        'member_id' => [$args['member_id']],
//                    ]);
//                }
                
                $memberModel->where('member_id', $args['member_id'])->update(['status' => 0]);
                
                // 店铺注销
                if (!empty($store_id = $store->where('member_id', $args['member_id'])->value('store_id'))) {
                    // 下架商品
                    $goods->where('store_id', $store_id)->update(['is_putaway' => 0]);
                    // 软删店铺
                    $store::destroy($store_id);
                    $param['type'] = 1;
                    $param['reason'] = '会员加入黑名单';
                    $param['store_id'] = $store_id;
                    $storeOperation::create($param);
                }
                // 推送消息[只含短信][会员加入黑名单]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey' => 'member_state',
                    'data' => [0],
                    'inside_data' => [
                        'member_id' => $param['member_id'],
                        'type' => 0,
                        'jump_state' => 'member',
                        'file' => 'image/cuo.png',
                    ],
                ]);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        return $this->fetch(
            '',
            [
                'member_id' => $request::get('id'),
            ]
        );
    }
    
    /**
     * 移除黑名单
     * @param Request $request
     * @param MemberModel $member
     * @param StoreOperation $storeOperation
     * @return array
     */
    public function open_member(Request $request, MemberModel $member, StoreOperation $storeOperation)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

//                $member = $member::onlyTrashed()->find($param['member_id']);
//                $member->restore();
                
                $member->where('member_id', $param['member_id'])->update(['status' => 1]);
                
                $param['manage_id'] = Session::get("manage_id");
                $param['nickname'] = Session::get("manageName");
                $param['type'] = 5;
                $param['reason'] = '会员移除黑名单';
                $storeOperation->allowField(TRUE)->save($param);
                // 推送消息[只含短信][会员移除黑名单]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey' => 'member_state',
                    'data' => [1],
                    'inside_data' => [
                        'member_id' => $param['member_id'],
                        'type' => 0,
                        'jump_state' => 'member',
                        'file' => 'image/dui.png',
                    ],
                ]);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 会员禁用状态更新
     * @param Request $request
     * @param MemberModel $memberModel
     * @return array
     */
    public function auditing(Request $request, MemberModel $memberModel)
    {
        
        if ($request::isPost()) {
            try {
                $memberModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 积分设置
     * @return array|mixed
     */
    public function integral(Request $request, Config $config)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                $param = $request::post();
                $config->valid($param, 'integral_settings');
                
                foreach ($param as $key => $item) {
                    $config->where('key', $key)->update(['value' => $item]);
                    
                    if (is_numeric($item)) {
                        $item = abs($item);
                    }
                    if (!ini_file(NULL, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/member/integral'];
            } catch (ValidateException $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => 0, 'message' => config('message.')[-1]];
            }
        }
        
        return $this->fetch('');
    }
    
}