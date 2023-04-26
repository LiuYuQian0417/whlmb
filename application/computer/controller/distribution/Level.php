<?php
declare(strict_types=1);

namespace app\computer\controller\distribution;

use app\computer\model\Distribution;
use app\computer\model\DistributionChangeRecord;
use app\computer\model\DistributionLevel;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;

/**
 * 等级记录
 * Class Level
 * @package app\computer\controller\distribution
 */
class Level extends BaseController
{
    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => ''],
    ];

    /**
     * 我的等级
     * @param Distribution $distribution
     * @param DistributionLevel $distributionLevel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_level(Distribution $distribution,
                             DistributionLevel $distributionLevel)
    {
        //获取分销商id
        $distribution_id = $distribution->get_distribution_id();
        //分销数据
        $data = $distribution
            ->alias('d')
            ->where(
                [
                    ['d.distribution_id', '=', $distribution_id],
                ]
            )
            ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
            ->field(
                'dl.distribution_level_id,dl.level_title,d.cycle_up_order_sum,d.cycle_up_brokerage'
            )
            ->find();
        $level = $distributionLevel
            ->field(
                'distribution_level_id,level_title,mark,upgrade_total_brokerage,
                upgrade_total_order_num,upgrade_total_order_sum,upgrade_direct_next_num,
                upgrade_next_num,upgrade_relation,downgrade_brokerage_cycle,downgrade_brokerage_sum,
                downgrade_order_cycle,downgrade_order_sum,level_weight'
            )
            ->append(['mark_alias', 'upgrade_rule', 'down_rule'])
            ->hidden(['mark', 'level_weight'])
            ->order(['level_weight' => 'asc', 'distribution_level_id' => 'asc'])
            ->select()
            ->toArray();
        $data['level'] = [];
        $data['level_index'] = $data['upgrade_total_brokerage'] = $data['upgrade_total_order_sum'] = 0;
        if (!empty($level))
        {
            foreach ($level as $key => &$item)
            {
                //判断当前等级索引
                if ($item['distribution_level_id'] == $data['distribution_level_id'])
                {
                    $data['upgrade_total_order_sum'] = $item['upgrade_total_order_sum'];
                    $data['upgrade_total_brokerage'] = $item['upgrade_total_brokerage'];
                    $data['level_index'] = $key;
                }
                if ($key == 0)
                {
                    $item['down_rule'] = '无';
                }
                if ($key == count($level) - 1)
                {
                    $item['upgrade_rule'] = '无';
                }
            }
            $data['level'] = $level;
            //计算当前等级进度条百分比
            if ((count($level) - 1) > 0)
            {
                $count_level = 100 / (count($level) - 1);
                $data['level_style'] = round($data['level_index'] * $count_level + $count_level / 2, 2);
                if ($data['level_style'] > 100)
                {
                    $data['level_style'] = 100;
                }
            }
        }
        return $this->fetch('', ['data' => $data]);
    }

    /**
     * 分销商升降级记录
     * @param Distribution $distribution
     * @param DistributionChangeRecord $distributionChangeRecord
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function change_record(Distribution $distribution,DistributionChangeRecord $distributionChangeRecord)
    {
        $member_id = Session::get('member_info')['member_id'];


        $param['distribution_id'] = $distribution->where('member_id',$member_id)->value('distribution_id');

        $where = [
            ['distribution_id', '=', $param['distribution_id']],
        ];
        $data = $distributionChangeRecord
            ->alias('dcr')
            ->where($where)
            ->field(
                'distribution_change_record_id,change_type,date_format(create_time,\'%Y-%m-%d %H:%i\') as change_time,
            ifnull((select level_title from `ishop_distribution_level` where distribution_level_id = now_level_id)
            ,now_level_title) as now_title,ifnull((select level_title from `ishop_distribution_level` where 
            distribution_level_id = ago_level_id),ago_level_title) as ago_title'
            )
            ->append(['record_content'])
            ->hidden(['now_title', 'ago_title'])
            ->select();
//        halt($data);

        return $this->fetch('',['code' => 0, 'message' => config('message.')[0][0], 'data' => $data], TRUE);

    }
}