<?php
declare(strict_types=1);

namespace app\common\model;

class LotteryActivity extends BaseModel
{
    protected $pk = 'activity_id';

    /**
     * 活动规则修改器
     * @param $value
     * @return mixed
     */
    public function setActionRuleAttr($value)
    {
        return trim(str_replace('，', ',', $value), ',');
    }

    /**
     * 活动规则获取器
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getActionRuleAttr($value, $data)
    {
        return explode(',', $value);
    }

    /**
     * 原始活动规则数据
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getOriginalActionRuleAttr($value, $data)
    {
        return $data['action_rule'];
    }


    //抽奖活动和活动商品一对多
    public function lotteryPrize()
    {
        return $this->hasMany('LotteryPrize', 'activity_id');
    }
}