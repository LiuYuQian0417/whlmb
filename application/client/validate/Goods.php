<?php


namespace app\client\validate;


use think\Validate;

class Goods extends Validate
{

    protected $rule = [
        'goods_id|'      => 'require|number',                // 商品ID,
        'goods_id_list|' => 'require',                  // 商品ID 列表
        'up|'            => 'require|in:1,2',                      // 1 上架 2 下架

        'goods_classify_id|'       => 'require|number',               // 商品分类ID
        'goods_name|'              => 'require',                             // 商品名称
        'store_goods_classify_id|' => 'require|number',         // 店铺商品分类
        'goods_weight|'            => 'float',                    // 商品库存
        'market_price|'            => 'require|between:0.01,99999999.99',  // 市场售价
        'shop_price|'              => 'require|between:0.01,99999999.99',    // 本店售价
        'cost_price|'              => 'require|between:0.01,99999999.99',    // 成本价
        'goods_number|'            => 'require|max:30',                    // 库存
        'warn_number|'             => 'require|number|max:99999999',        // 库存预警值
        'keyword|'                 => 'require|max:200',                        // 商品关键字
        'freight_status|'          => 'requireIf:express,1|in:0,1',      // 运费设置
        'freight_price|'           => 'requireIf:freight_status,0|between:0,99999999.99',       // 运费价格
        'freight_id|'              => 'requireIf:freight_status,1|number',   // 运费模板ID
        'default_express_type|'    => 'require|in:1,2,3',          // 默认配送方式
    ];

    protected $message = [
        'goods_id.require'      => '商品ID错误',
        'goods_id.number'       => '商品ID格式错误',
        'goods_id_list.require' => '商品ID列表错误',
        'up.require'            => '上下架标识错误',
        'up.in'                 => '上下架标识格式错误',

        'goods_classify_id.require'       => '请选择商品分类',
        'goods_classify_id.number'        => '商品分类ID格式错误',
        'goods_name.require'              => '请输入商品名称',
        'store_goods_classify_id.require' => '请选择店铺商品分类',
        'store_goods_classify_id.number'  => '店铺商品分类格式错误',
//        'goods_weight.require'            => '请输入商品重量',
        'goods_weight.float'              => '商品重量格式错误',
        'market_price.require'            => '请输入市场售价',
        'market_price.between'            => '市场售价应在0.01至99999999.99之间',
        'shop_price.require'              => '请输入本店售价',
        'shop_price.between'              => '本店售价应在0.01至99999999.99之间',
        'cost_price.require'              => '请输入成本价',
        'cost_price.between'              => '成本价应在0.01至99999999.99之间',
        'goods_number.require'            => '请输入商品库存',
        'goods_number.max'                => '商品库存不能超过30位数字',
        'warn_number.require'             => '请输入库存预警值',
        'warn_number.number'              => '库存预警值格式错误',
        'warn_number.max'                 => '库存预警值应小于99999999',
        'keyword.require'                 => '请输入商品关键字',
        'keyword.max'                     => '商品关键字不能超过',
        'freight_status.requireIf'        => '请选择运费设置',
        'freight_status.in'               => '运费设置格式错误',
        'freight_price.requireIf'         => '请输入运费价格',
        'freight_price.between'           => '运费价格应在0.01至99999999.99之间',
        'freight_id.requireIf'            => '请选择运费模板',
        'freight_id.number'               => '运费模板格式错误',
        'default_express_type.require'    => '请设置默认配送方式',
        'default_express_type.in'         => '默认配送方式格式错误',
    ];

    protected $scene = [
        // 单个上架
        'change_put_away' => [
            'goods_id',
            'up',
        ],
        // 商品ID列表
        'goods_id_list'   => [
            'goods_id_list',
        ],
        // 创建商品
        'create'          => [
            'goods_classify_id',
            'goods_name',
            'store_goods_classify_id',
            'goods_weight',
            'market_price',
            'shop_price',
            'cost_price',
            'goods_number',
            'warn_number',
            'keyword',
            'freight_status',
            'freight_price',
            'freight_id',
            'default_express_type',
        ],
    ];
}