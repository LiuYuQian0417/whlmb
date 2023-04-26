<?php
// 砍价活动验证
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

class CutGoods extends Validate
{
    // 验证条件
    protected $rule = [
        'cut_goods_id|编号'       => 'require',
        'goods_id|商品信息'         => 'require',
        'up_shelf_time|参与时间'    => 'require',
        'down_shelf_time|参与时间'  => 'require',
        'single_cut_min|砍价单刀阈值' => 'require',
        'single_cut_max|砍价单刀阈值' => 'require',
        'status|审核状态'           => 'require',
        'price|价格'              => 'require',
    ];


    // 验证信息
    protected $message = [
        'cut_goods_id.require'    => '不能为空',
        'goods_id.require'        => '不能为空',
        'up_shelf_time.require'   => '不能为空',
        'down_shelf_time.require' => '不能为空',
        'single_cut_min.require'  => '不能为空',
        'single_cut_max.require'  => '不能为空',
        'status.require'          => '不能为空',
        'price.require'           => '不能为空',
    ];

    // 场景验证
    protected $scene = [
        'create'      => ['goods_id', 'up_shelf_time', 'down_shelf_time', 'single_cut_min', 'single_cut_max', 'status'],
        'edit'        => ['cut_goods_id', 'goods_id', 'up_shelf_time', 'down_shelf_time', 'single_cut_min', 'single_cut_max', 'status'],
        'immediately' => ['goods_id', 'price']
    ];
}
