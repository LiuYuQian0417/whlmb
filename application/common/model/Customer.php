<?php

namespace app\common\model;

class Customer extends BaseModel
{
    protected $pk = 'customer_id';

    // 反向关联联系人组表
    public function groupBlt(){
        return $this->belongsTo('CustomerGroup','customer_group_id','customer_group_id');
    }

    /**
     * 获取客服列表
     */
    public static function _GetList()
    {

    }

    /**
     * 获取被禁用的客服列表
     */
    public static function _GetDisabledList()
    {

    }
}