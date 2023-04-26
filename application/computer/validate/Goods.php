<?php
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

/**
 * 商品验证类
 * Class Goods
 * @package app\computer\validate
 */
class Goods extends Validate
{
    protected $rule = [
        'id|商品信息'                => 'require',
        'goods_id|商品信息'          => 'require',
        'store_id|店铺信息'          => 'require',
        'collect_goods_id|商品信息'  => 'require',
        'goods_classify_id|商品分类' => 'require|number',
        'goods_name|商品名称'        => 'require|max:30',
        'market_price|市场售价'      => 'require|between:0.01,99999999.99',
        'shop_price|本店售价'        => 'require|between:0.01,99999999.99',
        'cost_price|成本价'          => 'require|between:0.01,99999999.99',
        'goods_weight|商品重量'      => 'between:0,9999999.999',
        'price|订阅价'               => 'require',
        'goods_number|库存'          => 'require|number|between:0,65535',
        'warn_number|库存预警值'      => 'require|number|between:0,65535',
        'keyword|商品关键字'          => 'require|max:200',
        'describe|商品简单描述'       => 'require|max:200',
        'file|商品图片'              => 'require',
        'brand_id|商品品牌'          => 'require',
        'shop|店铺类型'              => 'require',
    ];

    protected $message = [
        'goods_id.require'          => '不能为空',
        'id.require'                => '不能为空',
        'store_id.require'          => '不能为空',
        'goods_classify_id.require' => '不能为空',
        'goods_classify_id.number'  => '不符合条件',
        'goods_name.require'        => '不能为空',
        'goods_name.max'            => '不能超过30字符',
        'market_price.require'      => '不能为空',
        'market_price.between'      => '应该在0.01-99999999.99间',
        'shop_price.require'        => '不能为空',
        'shop_price.between'        => '应该在0.01-99999999.99间',
        'cost_price.require'        => '不能为空',
        'cost_price.between'        => '应该在0.01-99999999.99间',
        'goods_weight.between'      => '最大99999999.999',
        'price.require'             => '不能为空',
        'goods_number.require'      => '不能为空',
        'goods_number.number'       => '应为正整数',
        'goods_number.between'      => '最大65535',
        'warn_number.require'       => '不能为空',
        'warn_number.number'        => '应为正整数',
        'warn_number.between'       => '最大65535',
        'keyword.require'           => '不能为空',
        'keyword.max'               => '不能超过200字符',
        'describe.require'          => '不能为空',
        'describe.max'              => '不能超过200字符',
    ];

    protected $scene = [
        'create'                    => ['goods_classify_id', 'goods_name', 'file','goods_weight',
            'market_price', 'shop_price', 'cost_price', 'goods_number', 'warn_number', 'keyword', 'describe'],
        'edit'                      => ['goods_classify_id', 'goods_name', 'file','goods_weight',
            'market_price', 'shop_price', 'cost_price', 'goods_number', 'warn_number', 'keyword', 'describe'],
        'view'                      => ['goods_id'],
        'attr'                      => ['goods_id'],
        'destroy'                   => ['id'],
        'coupon_list'               => ['goods_classify_id','store_id'],
        'attr_find'                 => ['goods_id', 'goods_attr'],
        'collect_goods'             => ['goods_id', 'store_id'],
        'depreciate_goods'          => ['goods_id', 'store_id', 'price'],
        'collect_goods_delete'      => ['collect_goods_id', 'goods_id'],
        'view_collect_goods_delete' => ['goods_id'],
        'store_goods_list'          => ['store_id'],
        'new_product_list'          => ['store_id'],
        'good_recommend_list'       => ['goods_classify_id'],
    ];

}