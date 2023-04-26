<?php


namespace app\master\model;


use app\common\model\BaseModel;

class TagClassify extends BaseModel
{
    protected $pk = 'tag_classify_id';

    /**
     * 对于标签的一对多关联
     *
     * @return \think\model\relation\HasMany
     */
    public function tagHm()
    {
        return $this->hasMany('Tag', 'tag_classify_id', 'tag_classify_id');
    }

    /**
     * 取消
     *
     * @return \think\model\relation\HasMany
     */
    public function tagWSD()
    {
        return $this->hasMany('Tag', 'tag_classify_id', 'tag_classify_id')->removeOption('soft_delete');
    }
}