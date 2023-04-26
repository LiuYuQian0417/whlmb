<?php

namespace app\common\model;

use think\facade\Request;

/**
 * 属性规格设置模型
 * Class Attr
 * @package app\common\model
 */
class Attr extends BaseModel
{
    protected $pk = 'attr_id';

    public function GoodsAttr()
    {
        // 差个商品ID条件查询
        return $this
            ->hasMany('GoodsAttr', 'attr_id', 'attr_id')
            ->where([
                ['goods_id', '=', Request::post('goods_id')],
            ])
            ->field('goods_attr_id,attr_value,attr_id');
    }

    public function getAttrInputTypeAttr($value)
    {
        if ($value == 0) {
            return '手工录入';
        } else {
            return '系统定义';
        }
    }
}