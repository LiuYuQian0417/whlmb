<?php
declare(strict_types=1);

namespace app\interfaces\behavior;

use app\common\model\IntegralRecord;
use app\common\model\Member;
use app\common\model\Message;
use think\Db;
use think\facade\Env;

/**
 * 积分
 * Class integral
 * @package app\interfaces\behavior
 */
class integral
{
    /**
     * 增加用户购物积分
     * @param $args
     */
    public function inc($args)
    {
        // 支付赠送积分
        Env::load(Env::get('app_path') . 'common/ini/.config');
        // 商品金额换算积分比例
        $scale = Env::get('integral_conversion', 0);
        if ($scale) {
            $integral = floor($scale / 100 * $args['integral_total_price']);
            // 增加用户积分
            (new Member())
                ->allowField(true)
                ->isUpdate(true)
                ->save([
                    'member_id' => $args['member_id'],
                    'pay_points' => Db::raw('pay_points + ' . $integral)
                ]);
            (new IntegralRecord())
                ->allowField(true)
                ->isUpdate(false)
                ->save([
                    'member_id' => $args['member_id'],
                    'type' => 0,
                    'origin_point' => 2,
                    'integral' => $integral,
                    'describe' => '购买商品',
                ]);
        }
    }
    
    /**
     * 增加用户评价积分
     * @param $args
     * @throws \Exception
     */
    public function evaluateInc($args)
    {
        $integralRecord = new IntegralRecord();
        $member = new Member();
        // 查看用户今日评价次数
        $integral = $integralRecord
            ->where([
                ['member_id', '=', $args['member_id']],
                ['type', '=', 0],
                ['origin_point', '=', 3],
            ])
            ->whereTime('create_time', 'today')
            ->count();
        // 每天限制评价次数
        $integral_evaluate_number = intval(Env::get('integral_evaluate_number', 0));
        // 每次评价所得积分
        $integral_evaluate = intval(Env::get('integral_evaluate', 1));
        if (($lastNum = $integral_evaluate_number - $integral) > 0) {
            $minNum = min($lastNum, $args['count']);
            $add = [];
            for ($x = 1; $x <= $minNum; $x++) {
                array_push($add, [
                    'member_id' => $args['member_id'],
                    'type' => 0,
                    'origin_point' => 3,
                    'integral' => $integral_evaluate,
                    'describe' => '评价商品',
                ]);
            }
            if (!empty($add)) {
                // 发表评价赚积分
                $integralRecord
                    ->allowField(true)
                    ->isUpdate(false)
                    ->saveAll($add);
                // 增加用户积分
                $member
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save([
                        'member_id' => $args['member_id'],
                        'pay_points' => Db::raw('pay_points + ' . intval($integral_evaluate * $minNum))
                    ]);
            }
        }
    }
}