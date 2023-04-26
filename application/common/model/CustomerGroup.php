<?php
declare(strict_types=1);

namespace app\common\model;


use think\facade\Session;

class CustomerGroup extends BaseModel
{
    protected $pk = 'customer_group_id';

    public function customerRel()
    {
        return $this->hasMany('Customer', 'customer_group_id', 'customer_group_id');
    }

    /**
     * 获取客服组列表
     *
     * @return array|\PDOStatement|string|\think\Collection
     */
    public static function _GetList()
    {
        try {
            return self::where([
                ['store_id', '=', Session::get('client_store_id')],
            ])
                ->select();
        } catch (\Exception $e) {
            return [];
        }
    }
}