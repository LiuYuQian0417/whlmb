<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 商品属性字段模型
 * Class Goods
 * @package app\common\model
 */
class GoodsAttr extends BaseModel
{
    /**
     * 设置商品属性
     * @param $param
     * @param $goods_id
     * @throws \Exception
     */
    public function setGoodsAttr($param, $goods_id)
    {

        // 需要创建的
        $_create = [];
        // 需要删除的
        $_delete = [];

        $_oldAttr = $this->where([['goods_id', '=', $goods_id],])->field([
            'goods_attr_id',
            'attr_id',
            'attr_value',
        ])->select();

        if ($_oldAttr) {
            $_oldAttr = $_oldAttr->toArray();
        } else {
            $_oldAttr = [];
        }

        if ($param) {
            foreach ($param as $paramAttrId => $paramValueList) {
                foreach ($paramValueList as $paramValueKey => $paramValue) {
                    foreach ($_oldAttr as $oldAttrKey => $oldAttrValue) {
                        if ($paramAttrId == $oldAttrValue['attr_id'] && $paramValue == $oldAttrValue['attr_value']){
                            unset($param[$paramAttrId][$paramValueKey]);
                            unset($_oldAttr[$oldAttrKey]);
                        }
                    }
                }
            }
        }


        // 循环出来需要删除的
        foreach ($_oldAttr as $_key => $_value) {
            $_delete[] = $_value['goods_attr_id'];
        }

        // 循环出来需要添加的
        foreach ($param as $_attrId => $_value) {
            // 如果 当前是空的的话 直接跳过 进行下一个 loop
            if (empty($_value)) {
                continue;
            }

            foreach ($_value as $_attrName) {
                $_create[] = [
                    'goods_id'   => $goods_id,
                    'attr_id'    => $_attrId,
                    'attr_value' => $_attrName,
                ];
            }
        }

        // 如果创建列表不为空的话 则创建创建列表中需要的数据
        if (!empty($_create)) {
            $this->allowField(TRUE)
                ->isUpdate(FALSE)
                ->saveAll($_create);
        }

        // 如果删除列表不为空的话 则删除掉删除列表中的数据
        if (!empty($_delete)) {
            $this->where([
                ['goods_id', '=', $goods_id],
                ['goods_attr_id', 'in', join(',', $_delete)],
            ])->delete(TRUE);
        }
    }
}