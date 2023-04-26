<?php


namespace app\master\model;


use app\common\model\BaseModel;

class TagClick extends BaseModel
{
    protected $pk = 'tag_click_id';

    public function goods()
    {
        return $this->hasOne('Goods', 'goods_id', 'goods_id');
    }

    public function tag()
    {
        return $this->hasOne('Tag', 'tag_id', 'tag_id');
    }

}