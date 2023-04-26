<?php
declare(strict_types = 1);

namespace app\interfaces\behavior;


use app\common\model\MemberGrowthRecord;
use app\common\model\MemberRank;
use think\facade\Env;

/**
 * 成长值
 * Class Growth
 * @package app\interfaces\behavior
 */
class Growth
{
    /**
     * 增加成长值
     * @param $args
     * @throws \Exception
     */
    public function inc($args)
    {
        // 支付赠送成长值
        Env::load(Env::get('app_path') . 'common/ini/.config');
        // 商品金额换算成长值比例
        $grow = Env::get('growth_conversion', 0);
        // 余额支付额外赠送成长值
        $extra = Env::get('growth_balance', 0);
        // 每日限定次数
        $extraTimes = Env::get('growth_balance_number', 0);
        $data = [];
        if ($grow) {
            $growth = floor($grow / 100 * $args['growth_total_price']);
            if ($growth > 0) {
                $data[] = [
                    'member_id' => $args['member_id'],
                    'type' => 0,
                    'growth_value' => $growth,
                    'describe' => '余额支付赠送成长值',
                ];
            }
        }
        $mgr = new MemberGrowthRecord();
        // 查询用户金额余额支付获得额外成长值次数
        $times = $mgr
            ->whereTime('create_time', 'today')
            ->where([
                ['member_id', '=', $args['member_id']],
                ['describe', '=', '余额支付额外赠送成长值'],
            ])
            ->count();
        if ($times < $extraTimes) {
            $data[] = [
                'member_id' => $args['member_id'],
                'type' => 0,
                'growth_value' => $extra,
                'describe' => '余额支付额外赠送成长值',
            ];
        }
        if (!empty($data)) {
            $mgr
                ->allowField(true)
                ->isUpdate(false)
                ->saveAll($data);
        }
    }
    
    /**
     * 增加用户评价成长值
     * @param $args
     * @throws \Exception
     */
    public function evaluateInc($args)
    {
        $mgr = new MemberGrowthRecord();
        // 查看用户今日成长值增加次数
        $growth = $mgr
            ->where([
                ['member_id', '=', $args['member_id']],
            ])
            ->whereTime('create_time', 'today')
            ->count();
        // 每天限制评价次数
        $growth_evaluate_number = intval(Env::get('growth_evaluate_number', 0));
        // 每次评价所得成长值
        $growth_evaluate = intval(Env::get('growth_evaluate', 1));
        if (($lastNum = $growth_evaluate_number - $growth) > 0) {
            $minNum = min($lastNum, $args['count']);
            $add = [];
            for ($x = 1; $x <= $minNum; $x++) {
                array_push($add, [
                    'member_id' => $args['member_id'],
                    'type' => 1,
                    'growth_value' => $growth_evaluate,
                    'describe' => '评价商品',
                ]);
            }
            
            if (!empty($add)) {
                // 发表评价赚成长值
                $mgr
                    ->allowField(true)
                    ->isUpdate(false)
                    ->saveAll($add);
                // 检测会员成长值若升级则推送信息
                self::checkCurGrowth([
                    'member_id' => $args['member_id'],
                    'web_open_id' => $args['web_open_id'],
                    'micro_open_id' => $args['micro_open_id'],
                    'subscribe_time' => $args['subscribe_time'],
                    'phone' => $args['phone'],
                ]);
            }
            
        }
    }
    
    /**
     * 检查用户成长值,并推送消息
     * @param $arg
     */
    public function checkCurGrowth($arg)
    {
        $mgr = new MemberGrowthRecord();
        $sum = $mgr
            ->where([
                ['member_id', '=', $arg['member_id']],
            ])
            ->sum('growth_value');
        
        $mrk = new MemberRank();
        $rankName = $mrk
            ->where([
                ['min_points', '<=', $sum],
            ])
            ->order(['min_points' => 'desc'])
            ->value('rank_name');
        if ($rankName != \think\facade\Cache::store('file')->get('rank_flag_' . $arg['member_id'], '')) {
            \think\facade\Cache::store('file')->set('rank_flag_' . $arg['member_id'], $rankName);
            // 推送消息[不含短信][会员升级]
            $pushServer = app('app\\interfaces\\behavior\\Push');
            $pushServer->send([
                'tplKey' => 'member_state',
                'openId' => $arg['web_open_id'],
                'microId' => '',
                'subscribe_time' => $arg['subscribe_time'],
                'phone' => $arg['phone'],
                'data' => [2, $rankName],
                'inside_data' => [
                    'member_id' => $arg['member_id'],
                    'type' => 0,
                    'jump_state' => '8',
                    'file' => 'image/dui.png',
                ],
            ], 2);
        }
    }
}