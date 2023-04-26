<?php
declare(strict_types=1);

namespace app\common\model;

use think\Db;

/**
 * 会员签到模型
 * Class SignTake
 * @package app\common\model
 */
class SignTask extends BaseModel
{
    protected $pk = 'sign_task_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
        self::afterInsert(function ($e) {
            // 增加用户积分
            (new Member())->where('member_id', $e->member_id)->update([
                'pay_points' => Db::raw('pay_points+' . $e->integral)
            ]);

        });
    }

    // 定义积分记录关联
    public function integralRecord()
    {
        return $this->hasOne('IntegralRecord');
    }
}