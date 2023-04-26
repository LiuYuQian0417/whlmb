<?php
// 砍价活动验证
declare(strict_types=1);

namespace app\computer\validate;

use think\Validate;

class CutActivity extends Validate
{
    // 验证条件
    protected $rule = [
        'cut_activity_id|砍价活动信息' => 'require',
        'goods_id|商品信息'          => 'require'
    ];


    // 验证信息
    protected $message = [
        'cut_activity_id.require' => '不能为空',
        'goods_id.require'        => '不能为空'
    ];

    // 场景验证
    protected $scene = [
        'my_cut_view' => ['cut_activity_id'],
        'my_cut_help' => ['cut_activity_id','goods_id'],
    ];
}
