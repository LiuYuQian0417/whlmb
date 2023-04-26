<?php
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

class Store extends Validate
{
    protected $rule = [
        'store_id|店铺信息'            => 'require',
        'member_id|会员信息'           => ['require', 'unique:store'],
        'store_name|店铺名称'          => 'require',
        'category|主营分类'            => 'require',
        'goods_classify_id|主营分类'   => 'require',
        'brand_classify_id|主营品牌'   => 'require',
        'shop|店铺类型'                => 'require',
        'type|公司店铺分类'              => 'require',
        'status|审核状态'              => 'require',
        'province|省份'              => 'require',
        'city|城市'                  => 'require',
        'area|地区'                  => 'require',
        'address|详细地址'             => 'require',
        'lng|经度'                   => 'require',
        'lat|纬度'                   => 'require',
        'price|价格'                 => 'require',
        'number|数量'                => 'require',
        'goods_style|店铺模式'         => ['require', 'in:0,1'],
        'is_pay_delivery|是否开启货到付款' => ['require', 'in:0,1'],
        'is_express|是否开启全国包邮'      => ['require', 'in:0,1'],
        'keywords|店铺关键字'           => ['require', 'max:100'],
        'describe|店铺简介'            => ['require', 'max:100'],
        'phone|客服电话'               => ['require', 'mobile'],
        'image|店铺LOGO'             => 'image',
        'back_images|店铺背景图'        => 'image',
        'brand_images|店铺品牌甄选展示图'   => 'image',
        'good_images|店铺发现好店展示图'    => 'image',
    ];

    protected $message = [
        'store_id.require'          => '不可为空',
        'store_name.require'        => '不可为空',
        'category.require'          => '不可为空',
        'goods_classify_id.require' => '不可为空',
        'brand_classify_id.require' => '不可为空',
        'member_id.require'         => '不可为空',
        'member_id.unique'          => '该账号已经成为店主,请勿重复操作',
        'shop.require'              => '不可为空',
        'type.require'              => '不可为空',
        'status.require'            => '不可为空',
        'province.require'          => '不可为空',
        'city.require'              => '不可为空',
        'area.require'              => '不可为空',
        'address.require'           => '不可为空',
        'lng.require'               => '不可为空',
        'lat.require'               => '不可为空',
        'price.require'             => '不可为空',
        'number.require'            => '不可为空',
        'goods_style.require'       => '不可为空',
        'goods_style.in'            => '数据错误',
        'is_pay_delivery.require'   => '必须选择',
        'is_pay_delivery.in'        => '格式错误',
        'is_express.require'        => '必须选择',
        'is_express.in'             => '格式错误',
        'keywords.require'          => '不可为空',
        'keywords.max'              => '不能超过100个字符',
        'describe.require'          => '不可为空',
        'describe.max'              => '不能超过100个字符',
        'phone.require'             => '不可为空',
        'phone.mobile'              => '格式错误',
    ];

    protected $scene = [
        'create'                    => [
            'store_name',
            'member_id',
            'category',
            'shop',
            'type',
            'status',
            'province',
            'city',
            'area',
            'address',
            'lng',
            'lat',
        ],
        'edit'                      => [
            'store_id',
            'store_name',
            'category',
            'shop',
            'type',
            'status',
            'province',
            'city',
            'area',
            'address',
            'lng',
            'lat',
        ],
        'index'                     => ['store_id'],
        'head'                      => ['store_id'],
        'info'                      => ['store_id'],
        'collect_store'             => ['store_id'],
        'collect_store_delete'      => ['collect_goods_id'],
        'view_collect_store_delete' => ['goods_id'],
        'good_list'                 => ['category'],
        'store_ranking'             => ['goods_classify_id'],
        'brand_list'                => ['brand_classify_id'],
        'create_store'              => ['store_name', 'category', 'shop', 'province', 'city', 'area', 'address'],
        'common_confirm_order'      => ['store_id', 'price', 'number'],
        'client'                    => ['store_id', 'shop'],
        'master'                    => ['store_name', 'member_id', 'category', 'shop', 'type', 'status'],
        // 更新店铺信息
        'client_update_store_info'  => [
            // 店铺类型
            'shop',
            // 是否开启付款付款
            'is_pay_delivery',
            // 是否开启全国包邮
            'is_express',
            // 店铺关键字
            'keywords',
            // 店铺简介
            'describe',
        ],
        'client_update_images'      => [
            'image',
            'back_images',
            'good_images',
            'brand_images',
        ],
        'client_update_contact'     => [
            // 省
            'province',
            // 市
            'city',
            // 区
            'area',
            // 地址
            'address',
            // 经度
            'lng',
            // 纬度
            'lat',
            // 客服电话
            "phone",
        ],
        'client_update_fitment'     => [
            // 店铺模式
            'goods_style' => [
                'require',
                'in:0,1',
            ],
        ],
        'master_update_images'      => ['store_id'],
        'master_update_fitment'     => ['goods_style'],
        'master_update_contact'     => ['province', 'city', 'area', 'address', 'lng', 'lat', 'store_id'],
    ];
}