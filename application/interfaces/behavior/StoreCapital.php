<?php
declare(strict_types = 1);
namespace app\interfaces\behavior;


class StoreCapital
{
    /**
     * 记录店铺资金记录
     * @param $args
     */
    public function record($args)
    {
        $storeCapitalModel = app('app\\common\\model\\StoreCapital');
        $storeCapitalModel
            ->allowField(true)
            ->isUpdate(false)
            ->saveAll($args);
    }
}