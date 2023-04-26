<?php
declare(strict_types = 1);
namespace app\common\validate;

use  think\Validate;

class Adv extends Validate
{
    protected $rule = [
        'adv_position_id|广告位置' => 'require',
        'adv_id|广告编号' => 'require',
        'title|广告标题' => 'require',
        'type|广告类型' => 'require',
        'content|编号' => 'requireCallback:check_require|checkContent',
//        'start_time|开始时间' => 'require',
//        'end_time|结束时间' => 'require|gt:start_time',
    ];

    protected $message = [
        'adv_id.require' => '不可为空',
        'title.require' => '不可为空',
        'adv_position_id.require' => '不可为空',
        'type.require' => '不可为空',
//        'start_time.require' => '不可为空',
        'end_time.require' => '不可为空',
        'content.require' => '不可为空',
        'content.checkContent' => '格式错误',
//        'end_time.gt' => '大于开始时间',
    ];

    protected $scene = [
        'create' => ['adv_position_id', 'title', 'type', 'content'],
        'edit'   => ['adv_id', 'adv_position_id', 'title', 'type', 'content'],
    ];

    protected function check_require($value, $data)
    {
        // 无操作广告
        if ($data['type'] != 3 && $data['type'] != '') {
            return true;
        }
        return false;
    }

    protected function checkContent($value, $rule, $data)
    {

        if ($data['type'] == 0 && $data['type'] != '') {
            $reg = "/^([hH][tT]{2}[pP]:\/\/|[hH][tT]{2}[pP][sS]:\/\/|www\.)(([A-Za-z0-9-~]+)\.)+([A-Za-z0-9-~\/])+$/";
            if (!preg_match($reg, $value)) {
                return false;
            }
        }
        return true;
    }
}