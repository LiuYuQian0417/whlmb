<?php
declare(strict_types=1);

namespace app\interfaces\controller\distribution;

use app\common\model\Distribution;
use app\common\model\DistributionChangeRecord;
use app\common\model\DistributionLevel;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 等级记录
 * Class Level
 * @package app\interfaces\controller\distribution
 */
class Level extends BaseController
{
    /**
     * 我的等级
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @param DistributionLevel $distributionLevel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_level(RSACrypt $crypt,
                             Distribution $distribution,
                             DistributionLevel $distributionLevel)
    {
        $param = $crypt->request();
        $where = [
            ['d.distribution_id', '=', $param['distribution_id']],
        ];
        $data = $distribution
            ->alias('d')
            ->where($where)
            ->join('member m','m.member_id = d.member_id')
            ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
            ->field('dl.distribution_level_id,dl.level_title,d.cycle_up_order_sum,d.cycle_up_order_num,
                d.cycle_up_brokerage,d.cycle_up_referrer_num,d.cycle_up_relation_num,m.nickname,m.avatar')
            ->find();
        $level = $distributionLevel
            ->field('distribution_level_id,level_title,mark,upgrade_total_brokerage,
                upgrade_total_order_num,upgrade_total_order_sum,upgrade_direct_next_num,
                upgrade_next_num,upgrade_relation,downgrade_brokerage_cycle,downgrade_brokerage_sum,
                downgrade_order_cycle,downgrade_order_sum,level_weight')
            ->append(['mark_alias', 'upgrade_rule', 'down_rule'])
            ->hidden(['mark', 'level_weight'])
            ->order(['level_weight' => 'asc', 'distribution_level_id' => 'asc'])
            ->select()
            ->toArray();
        $data['level'] = [];
        if (!empty($level)) {
            foreach ($level as $key => &$item) {
                if ($key == 0) {
                    $item['down_rule'] = '无';
                }
                if ($key == count($level) - 1) {
                    $item['upgrade_rule'] = '无';
                }
            }
            $data['level'] = $level;
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
    }

    /**
     * 分销商升降级记录
     * @param RSACrypt $crypt
     * @param DistributionChangeRecord $distributionChangeRecord
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function change_record(RSACrypt $crypt,
                                  DistributionChangeRecord $distributionChangeRecord)
    {
        $param = $crypt->request();
        $where = [
            ['distribution_id', '=', $param['distribution_id']],
        ];
        $data = $distributionChangeRecord
            ->alias('dcr')
            ->where($where)
            ->field('distribution_change_record_id,change_type,date_format(create_time,\'%Y-%m-%d %H:%i\') as change_time,
                ifnull((select level_title from `ishop_distribution_level` where distribution_level_id = now_level_id)
                ,now_level_title) as now_title,ifnull((select level_title from `ishop_distribution_level` where 
                distribution_level_id = ago_level_id),ago_level_title) as ago_title')
            ->append(['record_content'])
            ->hidden(['now_title', 'ago_title'])
            ->order(['create_time' => 'desc'])
            ->paginate($distributionChangeRecord->pageLimits, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
    }
}