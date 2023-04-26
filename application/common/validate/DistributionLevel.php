<?php
declare(strict_types = 1);
namespace app\common\validate;

use think\Validate;

class DistributionLevel extends Validate
{
    protected $rule = [
        'level_title|等级名称' => ['require','max' => 20],
        'level_weight|等级级别' => ['require','integer'],
        'one_ratio|一级分销比例' => ['require','regex' => '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^0$)|(^\d\.\d{1,2}$)/'],
        'two_ratio|二级分销比例' => ['require','regex' => '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^0$)|(^\d\.\d{1,2}$)/'],
        'three_ratio|三级分销比例' => ['require','regex' => '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^0$)|(^\d\.\d{1,2}$)/'],
        'upgrade_total_brokerage|佣金总金额' => ['regex' => '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^0$)|(^\d\.\d{1,2}$)/'],
        'upgrade_total_order_num|订单笔数' => 'number',
        'upgrade_total_order_sum|订单总金额' => ['regex' => '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^0$)|(^\d\.\d{1,2}$)/'],
        'upgrade_direct_next_num|直属下级分销商数量' => 'number',
        'upgrade_next_num|下级分销商数量' => 'number',
        'downgrade_brokerage_cycle|根据佣金金额规定时间' => 'require|number',
        'downgrade_brokerage_sum|根据佣金金额指定金额' => ['require','regex' => '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/'],
        'downgrade_order_cycle|根据订单金额规定时间' => 'require|number',
        'downgrade_order_sum|根据订单金额指定金额' => ['require','regex' => '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/'],
    ];

    protected $message = [
        'level_title.require' => '不可为空',
        'level_title.max' => '不可超过20字符',
        'level_weight.require' => '不可为空',
        'level_weight.integer' => '应为整数',
        'one_ratio.require' => '不可为空',
        'one_ratio.regex' => '格式不正确,最多保留两位小数',
        'two_ratio.require' => '不可为空',
        'two_ratio.regex' => '格式不正确,最多保留两位小数',
        'three_ratio.require' => '不可为空',
        'three_ratio.regex' => '格式不正确,最多保留两位小数',
        'upgrade_total_brokerage.regex' => '格式不正确,最多保留两位小数',
        'upgrade_total_order_num.number' => '应为纯数字',
        'upgrade_total_order_sum.regex' => '格式不正确,最多保留两位小数',
        'upgrade_direct_next_num.number' => '应为纯数字',
        'upgrade_next_num.number' => '应为纯数字',
        'downgrade_brokerage_cycle.require' => '不可为空',
        'downgrade_brokerage_cycle.number' => '应为纯数字',
        'downgrade_brokerage_sum.require' => '不可为空',
        'downgrade_brokerage_sum.regex' => '格式不正确,最多保留两位小数',
        'downgrade_order_cycle.require' => '不可为空',
        'downgrade_order_cycle.number' => '应为纯数字',
        'downgrade_order_sum.require' => '不可为空',
        'downgrade_order_sum.regex' => '格式不正确,最多保留两位小数',
    ];

    protected $scene = [
        'create' => ['level_title', 'level_weight', 'one_ratio', 'two_ratio', 'three_ratio',
            'upgrade_total_brokerage', 'upgrade_total_order_num', 'upgrade_total_order_sum',
            'upgrade_direct_next_num', 'upgrade_next_num', 'downgrade_brokerage_cycle',
            'downgrade_brokerage_sum', 'downgrade_order_cycle', 'downgrade_order_sum'],
    ];
}