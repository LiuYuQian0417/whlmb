<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 分销商
 * Class Distribution
 * @package app\common\model
 */
class Distribution extends BaseModel
{
    protected $pk = 'distribution_id';

    /**
     * 获取会员等级
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getRankNameAttr($value, $data)
    {
        if ($data['member_id']) {
            $growth_value = countGrowth($data['member_id']);
            return (new MemberRank())
                ->where([
                    ['min_points', '<=', $growth_value],
                    ['max_points', '>=', $growth_value],
                ])
                ->value('rank_name');
        }
        return '';
    }

    /**
     * 关联会员信息
     * @return \think\model\relation\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo('Member', 'member_id', 'member_id');
    }

    /**
     * 获取用户基础信息
     * @return \think\model\relation\BelongsTo
     */
    public function memberBaseInfo()
    {
        return $this->member()->field('member_id,nickname,avatar,phone,sex');
    }
}