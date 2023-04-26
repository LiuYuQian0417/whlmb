<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 分销商
 * Class Distribution
 * @package app\common\model
 */
class DistributionBook extends BaseModel
{
    protected $pk = 'distribution_book_id';

    /**
     * 分销商A关联用户
     * @return \think\model\relation\BelongsTo
     */
    public function memberA()
    {
        return $this->belongsTo('Distribution', 'distributor_a', 'distribution_id');
    }

    /**
     * 结算
     * @return \think\model\relation\BelongsTo
     */
    public function memberADistribution()
    {
        return $this->memberA()->field('distribution_id,member_id')->with(['memberBaseInfo']);
    }

    /**
     * 分销商B关联用户
     * @return \think\model\relation\BelongsTo
     */
    public function memberB()
    {
        return $this->belongsTo('Distribution', 'distributor_b', 'distribution_id');
    }

    /**
     * 结算
     * @return \think\model\relation\BelongsTo
     */
    public function memberBDistribution()
    {
        return $this->memberB()->field('distribution_id,member_id')->with(['memberBaseInfo']);
    }

    /**
     * 分销商C关联用户
     * @return \think\model\relation\BelongsTo
     */
    public function memberC()
    {
        return $this->belongsTo('Distribution', 'distributor_c', 'distribution_id');
    }

    /**
     * 结算
     * @return \think\model\relation\BelongsTo
     */
    public function memberCDistribution()
    {
        return $this->memberC()->field('distribution_id,member_id')->with(['memberBaseInfo']);
    }

    /**
     * A规则类型名称
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getRuleTypeAAliasAttr($value, $data)
    {
        $ret = '';
        if (array_key_exists('rule_type_a', $data)) {
            $text = ['单品佣金规则', '分销商佣金规则', '平台默认佣金规则'];
            $ret = $text[$data['rule_type_a'] - 1];
        }
        return $ret;
    }

    /**
     * B规则类型名称
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getRuleTypeBAliasAttr($value, $data)
    {
        $ret = '';
        if (array_key_exists('rule_type_b', $data)) {
            $text = ['单品佣金规则', '分销商佣金规则', '平台默认佣金规则'];
            $ret = $text[$data['rule_type_b'] - 1];
        }
        return $ret;
    }

    /**
     * C规则类型名称
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getRuleTypeCAliasAttr($value, $data)
    {
        $ret = '';
        if (array_key_exists('rule_type_c', $data)) {
            $text = ['单品佣金规则', '分销商佣金规则', '平台默认佣金规则'];
            $ret = $text[$data['rule_type_c'] - 1];
        }
        return $ret;
    }
}