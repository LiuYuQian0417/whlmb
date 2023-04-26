<?php


namespace app\master\model;


use app\common\model\BaseModel;

class Tag extends BaseModel
{
    protected $pk = 'tag_id';

    public function tagClassify()
    {
        return $this->belongsTo('TagClassify','tag_classify_id','tag_classify_id');
    }

    public function tagBindGoods()
    {
        return $this->hasMany('TagBindGoods','tag_id');
    }
}