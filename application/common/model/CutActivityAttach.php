<?php
declare(strict_types=1);

namespace app\common\model;


/**
 * 砍价活动附表
 * Class CutActivityAttach
 * @package app\common\model
 */
class CutActivityAttach extends BaseModel
{
    protected $pk = 'cut_activity_attach_id';

    /**
     * 关联会员信息
     * @return \think\model\relation\HasOne
     */
    public function member()
    {
        return $this->hasOne('Member', 'member_id', 'helper')->field('member_id,avatar,nickname');
    }

}