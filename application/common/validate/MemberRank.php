<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class MemberRank extends Validate
{
    protected $rule = [
        'member_rank_id|会员等级' => 'require',
        'rank_name|会员等级名称'    => 'require',
        'min_points|成长值下限'     => ['require', 'reg' => '(^[0-9]\d*$)'],
        'max_points|成长值上限'     => ['require', 'reg' => '(^[0-9]\d*$)', 'gt' => 'min_points'],
        'discount|初始折扣率'      => ['require', 'reg' => '(^[1-9]\d*$)', 'elt' => 100],
        'mark|文字标示'      => 'require',
    ];

    protected $message = [
        'member_rank_id.require'   => '不可为空',
        'rank_name.require'        => '不可为空',
        'min_points.require'       => '不可为空',
        'min_points.reg'     => '正整数',
        'max_points.require' => '不可为空',
        'max_points.reg'     => '正整数',
        'max_points.gt'      => '大于成长值下限',
        'discount.require'   => '不可为空',
        'discount.reg'       => '正整数',
        'discount.lt'        => '最大值100',
        'mark.require'       => '不可为空',
    ];

    protected $scene = [
        'create' => ['rank_name', 'min_points', 'discount', 'mark'],
        'edit'   => ['member_rank_id', 'rank_name', 'min_points', 'discount', 'mark'],
    ];
}