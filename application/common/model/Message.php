<?php

namespace app\common\model;

/**
 * 消息模型
 * Class Message
 * @package app\common\model
 */
class Message extends BaseModel
{
    protected $pk = 'message_id';
    
    /**
     * 获取当前时间
     * @param $value
     * @return mixed
     */
    public function getCurrentTimeAttr($value)
    {
        return date('Y-m-d H:i:s');
    }

    //消息获得物流订单标题
    public function getOrderTitleAttr($value, $data)
    {
        return OrderGoods::where([
            ['order_goods_id|order_attach_id', '=', $data['attach_id']],
        ])->value('goods_name', '');
    }
}