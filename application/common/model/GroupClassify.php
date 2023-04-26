<?php
// 团购商品分类表
declare(strict_types=1);

namespace app\common\model;

class GroupClassify extends BaseModel
{
    protected $pk = 'group_classify_id';

    public function subset()
    {
        return $this
            ->hasMany('GroupClassify', 'parent_id', 'group_classify_id')
            ->field('group_classify_id,title,parent_id');
    }
}