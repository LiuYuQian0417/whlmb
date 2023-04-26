<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 分销商等级
 * Class DistributionLevel
 * @package app\common\model
 */
class DistributionLevel extends BaseModel
{
    protected $pk = 'distribution_level_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
            $markPath = self::upload('image', 'distribution/level/');
            if ($markPath) {
                $e->mark = $markPath;
            }
        });
    }

    /**
     * 获取加密等级标志
     * @param $value
     * @param $data
     * @return string
     */
    public function getMarkAliasAttr($value, $data)
    {
        $mark = '';
        if ($data['mark']) {
            $config = config('oss.');
            $mark = $config['prefix'] . $data['mark'];
        }
        return $mark;
    }

    /**
     * 分销商升降级条件
     * @param $value
     * @param $data
     * @return string
     */
    public function getConditionAttr($value, $data)
    {
        $max = $this->max('level_weight');
        $condition = '';
        if ($data['level_weight'] < $max) {
            $upgrade_relation = [1 => '与 ', 2 => '或 '];
            if (array_key_exists('upgrade_total_brokerage', $data) && $data['upgrade_total_brokerage'] != 0) {
                $condition .= "<p>" . ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                    '佣金总金额满' . $data['upgrade_total_brokerage'] . '元</p>';
            }
            if (array_key_exists('upgrade_total_order_num', $data) && $data['upgrade_total_order_num'] != 0) {
                $condition .= "<p>" . ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                    '订单笔数满' . $data['upgrade_total_order_num'] . '笔</p>';
            }
            if (array_key_exists('upgrade_total_order_sum', $data) && $data['upgrade_total_order_sum'] != 0) {
                $condition .= "<p>" . ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                    '订单总金额满' . $data['upgrade_total_order_sum'] . '元</p>';
            }
            if (array_key_exists('upgrade_direct_next_num', $data) && $data['upgrade_direct_next_num'] != 0) {
                $condition .= "<p>" . ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                    '直属下级分销商数量满' . $data['upgrade_direct_next_num'] . '个</p>';
            }
            if (array_key_exists('upgrade_next_num', $data) && $data['upgrade_next_num'] != 0) {
                $condition .= "<p>" . ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                    '下级分销商数量满' . $data['upgrade_next_num'] . '个</p>';
            }
            $condition = "<p style='color: green;float:left;'>升级条件:</p><div style='text-align: left;padding-left: 70px;'>" .
                ($condition ?: '<p>未设置升级策略</p>') . "</div><hr style='margin:2px 0;'/>" . "<p style='color: red;float:left;'>降级条件:</p>";
        } else {
            $condition = "<p style='color: green;float:left;'>升级条件:</p><div style='text-align: left;padding-left: 70px;'>" .
                "<p>等级最高,无升级规则</p></div><hr style='margin:2px 0;'/>" . "<p style='color: red;float:left;'>降级条件:</p>";
        }
        if ($data['level_weight']) {
            $condition .= "<div style='text-align: left;padding-left: 70px;'><p>"
                . $data['downgrade_brokerage_cycle'] . "天内佣金金额需达到" . $data['downgrade_brokerage_sum'] . '元</p>';
            $condition .= "<p>与 " . $data['downgrade_order_cycle'] . "天内订单消费金额需达到" . $data['downgrade_order_sum'] . '元</p></div>';
        } else {
            $condition .= "<div style='text-align: left;padding-left: 70px;'><p>等级最低,无降级规则</p></div>";
        }
        return $condition;
    }

    /**
     * 接口升级规则
     * @param $value
     * @param $data
     * @return string
     */
    public function getUpgradeRuleAttr($value, $data)
    {
        $condition = '';
        $upgrade_relation = [1 => '并且', 2 => '或者'];
        if (array_key_exists('upgrade_total_brokerage', $data) && $data['upgrade_total_brokerage'] != 0) {
            $condition .= ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                '佣金总金额满' . $data['upgrade_total_brokerage'] . '元';
        }
        if (array_key_exists('upgrade_total_order_num', $data) && $data['upgrade_total_order_num'] != 0) {
            $condition .= ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                '订单笔数满' . $data['upgrade_total_order_num'] . '笔';
        }
        if (array_key_exists('upgrade_total_order_sum', $data) && $data['upgrade_total_order_sum'] != 0) {
            $condition .= ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                '订单总金额满' . $data['upgrade_total_order_sum'] . '元';
        }
        if (array_key_exists('upgrade_direct_next_num', $data) && $data['upgrade_direct_next_num'] != 0) {
            $condition .= ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                '直属下级分销商数量满' . $data['upgrade_direct_next_num'] . '个';
        }
        if (array_key_exists('upgrade_next_num', $data) && $data['upgrade_next_num'] != 0) {
            $condition .= ($condition ? $upgrade_relation[$data['upgrade_relation']] : '') .
                '下级分销商数量满' . $data['upgrade_next_num'] . '个';
        }
        return $condition;
    }

    /**
     * 接口降级规则
     * @param $value
     * @param $data
     * @return string
     */
    public function getDownRuleAttr($value, $data)
    {
        $content = $data['downgrade_brokerage_cycle'] . "天内佣金金额需达到" . $data['downgrade_brokerage_sum'] . '元并且' .
            $data['downgrade_order_cycle'] . "天内订单消费金额需达到" . $data['downgrade_order_sum'] . '元';
        return $content;
    }

    /**
     * 分销商(相对一对多)
     * @return \think\model\relation\HasMany
     */
    public function distribution()
    {
        return $this->hasMany('Distribution', 'distribution_level_id', 'distribution_level_id');
    }

    /**
     * 分销商等级
     * @return \think\model\relation\HasMany
     */
    public function distributionLevelList()
    {
        return $this->distribution()->where([['audit_status', '=', 1]]);
    }
}