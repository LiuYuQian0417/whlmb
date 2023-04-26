<?php
declare(strict_types = 1);

namespace app\computer\model;

use app\common\model\Distribution as DistributionModel;
use think\facade\Env;
use think\facade\Session;

/**
 * 分销商
 * Class Distribution
 * @package app\common\model
 */
class Distribution extends DistributionModel
{
    /**
     * 获取分销商id
     */
    public function get_distribution_id()
    {
        //分销
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $result['distribution_status'] = Env::get('distribution_status', 0);
        // 开启分销的前提下
        if ($result['distribution_status']) {
            $member_id = Session::get('member_info')['member_id'] ?: 0;
            $distribution_id = (new DistributionLevel)->alias('dl')
                ->where(
                    [
                        ['d.member_id', '=', $member_id],
                        ['audit_status', '=', 1],                       //已审核
                    ]
                )
                ->join(
                    'distribution d',
                    'dl.distribution_level_id = d.distribution_level_id and d.delete_time is null'
                )
                ->value('d.distribution_id', null);
            //如果查询到分销数据
            if (!$distribution_id && $member_id) {
                //开启注册后用户自动成为分销商自动会员成为分销商
                if (Env::get('distribution_register', 0) == 1) {
                    //自动成为分销商
                    (new \app\interfaces\behavior\Distribution)->toBeDistributor(
                        array_merge(
                            Member::get($member_id)->toArray(),
                            [
                                'distribution_superior' => 0,
                                'bType' => 2,
                                //成为分销商途径注册自动成为分销商
                                'text' => 2,//注册即成为分销商
                            ]
                        )
                    );
                    return (new DistributionLevel)->alias('dl')
                        ->where(
                            [
                                ['d.member_id', '=', $member_id],
                                ['audit_status', '=', 1],                       //已审核
                            ]
                        )
                        ->join(
                            'distribution d',
                            'dl.distribution_level_id = d.distribution_level_id and d.delete_time is null'
                        )
                        ->value('d.distribution_id', null);
                }
                //开启手动申请成为分销商跳转到申请页面
                if (Env::get('distribution_manual', 0) == 1) {
                    header("Location: /pc2.0/distribution_become/distribution_form_set");
                    die;
                }
                //开启购买指定商品成为分销商
                if (Env::get('distribution_buy', 0) == 1) {
                    header("Location: /pc2.0/distribution_become/tobe_distributor_rule");
                    die;
                }
                //如果上诉规则都没开启跳转到成为代言人首页
                header("Location: /pc2.0/distribution_become/tobe_distributor_rule");
                die;
            }
            return $distribution_id;
        } else {
            //如果沒开启分销跳转到我的首页
            header("Location: /pc2.0/my/index");
            die;
        }
    }
}