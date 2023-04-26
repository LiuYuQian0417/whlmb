<?php

namespace app\client\validate;

use app\common\validate\BaseValidate;

class Store extends BaseValidate
{
    protected $rule = [
        'category|' => 'require|storeCategoryExist',// 主营项目
        'store_name|' => 'require|max:50',// 店铺名称
        'logo|' => 'require',//店铺LOGO
        'phone|' => 'require|max:12',     //客服电话
        'province|' => 'require|number|areaExist',  // 省
        'city|' => 'require|number|areaExist',      // 市
        'area|' => 'require|number|areaExist',      // 区
        'address|' => 'require|max:50',   // 详细地址
        'lng|' => 'require|float',       // 经度
        'lat|' => 'require|float',       // 纬度
        'keywords|' => 'require|max:100',  // 关键字
        'describe|' => 'require|max:100',  // 描述

        'is_city|' => 'require|in:0,1',         // 同城配送
        'is_pay_delivery|' => 'require|in:0,1', // 货到付款
        'is_shop|' => 'require|in:0,1',         // 门店自提
        'is_express|' => 'require|in:0,1',      // 全国包邮
        'is_added_value_tax|' => 'require|in:0,1', //增值税专用发票
        'is_good|' => 'require|in:0,1',         // 发现好店
        'good_image|' => 'requireIf:is_good,1', // 发现好店图片

        'back_image|' => 'require', // 店铺背景图
        'goods_style|' => 'require|in:0,1', // 店铺模式切换

    ];

    protected $message = [
        'category.require' => '请选择主营项目',
        'category.storeCategoryExist' => '主营类目不存在',
        'store_name.require' => '请填写店铺名称',
        'store_name.max' => '店铺名称不能超过50个字符',
        'logo.require' => '请上传LOGO',
        'phone.require' => '请填写店铺客服电话',
        'phone.length' => '店铺客服电话不能超过11个字符',
        'province.require' => '请选择店铺所在地-省',
        'province.number' => '店铺所在地-省-格式错误',
        'province.areaExist' => '店铺所在地-省-不存在',
        'city.require' => '请选择店铺所在地-市',
        'city.number' => '店铺所在地-市-格式错误',
        'city.areaExist' => '店铺所在地-市-不存在',
        'area.require' => '请选择店铺所在地-区',
        'area.number' => '店铺所在地-区-格式错误',
        'area.areaExist' => '店铺所在地-区-不存在',
        'address.require' => '请填写店铺详细地址',
        'address.max' => '店铺详细地址不能超过50个字符',
        'lng.require' => '请设置地图地址',
        'lng.float' => '经度格式错误',
        'lat.require' => '请设置地图地址',
        'lat.float' => '维度格式错误',
        'keywords.require' => '请填写关键字',
        'keywords.max' => '关键字不能超过100个字符',
        'describe.require' => '请填写描述',
        'describe.max' => '描述不能超过100个字符',

        'is_city.require' => '请选择是否开启同城配送',
        'is_city.in' => '同城配送格式错误',
        'is_pay_delivery.require' => '请选择是否开启货到付款',
        'is_pay_delivery.in' => '货到付款格式错误',
        'is_shop.require' => '请选择是否开启门店自提',
        'is_shop.in' => '门店自提格式错误',
        'is_express.require' => '请选择是否开启全国包邮',
        'is_express.in' => '增值税专用发票格式错误',
        'is_added_value_tax.require' => '请选择是否开启开具增值税专用发票',
        'is_added_value_tax.in' => '全国包邮格式错误',
        'is_good.require' => '请选择是否开启发现好店',
        'is_good.in' => '发现好店格式错误',
        'good_image.requireIf' => '请上传发现好店图片',

        'back_image.require' => '请上传店铺背景图',
        'goods_style.require' => '请选择店铺模式',
        'goods_style.in' => '店铺模式错误',
    ];

    protected $scene = [
        // 更新店铺信息
        'update_info' => [
            'category',
            'store_name',
            'logo',
            'phone',
            'province',
            'city',
            'area',
            'address',
            'lng',
            'lat',
            'keywords',
            'describe',
        ],
        // 店铺设置
        'setting' => [
            'is_city',
//            'is_pay_delivery',
            'is_shop',
            'is_delivery',
            'pc_head_back_image',
//            'is_express',
//            'is_good',
//            'good_image',
        ],

        // 店铺装修
        'fitment' => [
            'back_image',
            'goods_style',
        ]
    ];
}