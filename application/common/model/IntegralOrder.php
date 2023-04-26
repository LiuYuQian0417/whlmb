<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Request;
use app\common\model\Express as ExpressModel;

/**
 * 积分订单模型
 * Class IntegralOrder
 * @package app\common\model
 */
class IntegralOrder extends BaseModel
{
    protected $pk = 'integral_order_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
            $e->order_number = get_order_sn();
            $e->is_delete = 0;      //客户端是否删除
        });
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
            // 订单状态
            $expressValue = Request::post('express_value');
            $expressNumber = Request::post('express_number');
            if ($expressValue && $expressNumber) {
                $e->express_name = (new ExpressModel())
                    ->where([
                        ['code', '=', $expressValue],
                    ])
                    ->value('name');
            }
        });
    }

    // 定义积分记录关联
    public function integralRecord()
    {
        return $this->hasOne('IntegralRecord');
    }

    // 定义积分关联
    public function integralGoods()
    {
        return $this->hasOne('Integral', 'integral_id', 'integral_id');
    }

    // 定义会员关联
    public function member()
    {
        return $this->hasOne('Member', 'member_id', 'member_id');
    }

    // 订单来自状态获取器
    public function getFormTextAttr($value, $data)
    {
        $formArr = [1 => 'PC', 2 => '小程序', 3 => '手机站（微信端）', 4 => 'APP'];
        return $formArr[$data['from']];
    }

    // 订单状态获取器
    public function getStatusTextAttr($value, $data)
    {
        $statusArr = [0 => '待发货', 1 => '已发货', 2 => '已收货（已完成）', 3 => '待支付'];
        return $statusArr[$data['status']];
    }
}