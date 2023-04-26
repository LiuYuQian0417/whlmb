<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\Distribution;
use think\Controller;
use app\common\model\DistributionLevel as DistributionLevelModel;
use think\facade\Env;
use think\facade\Request;

class DistributionLevel extends Controller
{
    /**
     * 分销商等级列表
     * @param DistributionLevelModel $distributionLevel
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function levelList(DistributionLevelModel $distributionLevel)
    {
        $where = [];
        $list = $distributionLevel
            ->where($where)
            ->field('distribution_level_id,level_title,mark,upgrade_total_brokerage,
            upgrade_total_order_num,upgrade_total_order_sum,upgrade_direct_next_num,level_weight,
            upgrade_next_num,upgrade_relation,downgrade_brokerage_cycle,downgrade_brokerage_sum,
            downgrade_order_cycle,downgrade_order_sum,one_ratio,two_ratio,three_ratio')
            ->withCount(['distributionLevelList'])
            ->order(['level_weight' => 'asc', 'distribution_level_id' => 'asc'])
            ->paginate($distributionLevel->pageLimits);
        return $this->fetch('', [
            'data' => $list,
            'single_store' => config('user.one_more'),
        ]);
    }

    /**
     * 分销商等级新增/修改
     * @param Request $request
     * @param DistributionLevelModel $distributionLevel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distributionPut(Request $request, DistributionLevelModel $distributionLevel)
    {
        $get = $request::get();
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $ratio = Env::get('RATIO_SET');
        if ($request::isPost()) {
            $param = $request::post();
            $param = self::checkData($param);
            if (array_key_exists('code', $param)) {
                return $param;
            }
            $check = $distributionLevel->valid($param, 'create');
            if ($check['code']) {
                return $check;
            }
            // 检测三级分销比例和
            $sum = $param['one_ratio'] + $param['two_ratio'] + $param['three_ratio'];
            if ($sum > $ratio) {
                return ['code' => 1, 'message' => "分销比例之和应小于$ratio%"];
            }
            $flag = array_key_exists('distribution_level_id', $param) && $param['distribution_level_id'];
            if (!$flag) {
                $lw = $distributionLevel->where([['level_weight', '=', $param['level_weight']]])->find();
                if ($lw) {
                    return ['code' => -2, 'message' => "当前等级级别已存在,请更换"];
                }
            }
            $distributionLevel
                ->allowField(true)
                ->isUpdate($flag)
                ->save($param);
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/distribution_level/level_list'];
        }
        // 查找当前包含权重
        $weight = $distributionLevel
            ->field('level_weight,level_title')
            ->order(['level_weight' => 'desc', 'distribution_level_id' => 'desc'])
            ->select();
        // 查找当前分销商等级设置
        $find = [];
        if (array_key_exists('id', $get) && $get['id']) {
            $find = $distributionLevel
                ->where([['distribution_level_id', '=', $get['id']]])
                ->field('delete_time,update_time', true)
                ->find();
        }
        return $this->fetch('', [
            'weight' => $weight,
            'find' => $find,
        ]);
    }

    /**
     * 检测分销商等级数据
     * @param $param
     * @return array
     */
    protected function checkData($param)
    {
        if ($param['upgrade_relation'] == 2) {
            if (!$param['upgrade_total_brokerage'] &&
                !$param['upgrade_total_order_num'] &&
                !$param['upgrade_total_order_sum'] &&
                !$param['upgrade_direct_next_num'] &&
                !$param['upgrade_next_num']) {
                return ['code' => -1, 'message' => '升级策略不可全部为空'];
            }
            if ($param['upgrade_total_brokerage'] === '') {
                $param['upgrade_total_brokerage'] = 0;
            }
            if ($param['upgrade_total_order_num'] === '') {
                $param['upgrade_total_order_num'] = 0;
            }
            if ($param['upgrade_total_order_sum'] === '') {
                $param['upgrade_total_order_sum'] = 0;
            }
            if ($param['upgrade_direct_next_num'] === '') {
                $param['upgrade_direct_next_num'] = 0;
            }
            if ($param['upgrade_next_num'] === '') {
                $param['upgrade_next_num'] = 0;
            }
        } else {
            if (!$param['upgrade_total_brokerage'] &&
                !$param['upgrade_total_order_num'] &&
                !$param['upgrade_total_order_sum'] &&
                !$param['upgrade_direct_next_num'] &&
                !$param['upgrade_next_num']) {
                return ['code' => -1, 'message' => '升级策略缺少'];
            }
        }
        if (!array_key_exists('downgrade_brokerage_cycle', $param) &&
            !array_key_exists('downgrade_order_cycle', $param) &&
            !array_key_exists('downgrade_brokerage_sum', $param) &&
            !array_key_exists('downgrade_order_sum', $param)) {
            // 默认分销等级
            $param['downgrade_brokerage_cycle'] = $param['downgrade_order_cycle'] = 0;
            $param['downgrade_brokerage_sum'] = $param['downgrade_order_sum'] = "0.00";
        }
        return $param;
    }

    /**
     * 删除分销商等级
     * @param Request $request
     * @param Distribution $distribution
     * @return array
     */
    public function distributionDel(Request $request,
                                    Distribution $distribution)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                // 查询此等级下是否含有分销商
                $arr = $distribution
                    ->where([
                        ['distribution_level_id', 'in', $param['id']],
                        ['audit_status', '=', 1],
                    ])
                    ->distinct('distribution_level_id')
                    ->column('distribution_level_id');
                $diff = array_diff(explode(',', $param['id']), $arr);
                if (count($diff) > 0) {
                    DistributionLevelModel::destroy(implode(',', $diff));
                    return ['code' => 0, 'message' => '删除成功'];
                }
                return ['code' => 1, 'message' => '含有对应分销商,未删除对应等级'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}