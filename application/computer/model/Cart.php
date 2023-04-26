<?php
declare(strict_types=1);

namespace app\computer\model;
;

use app\common\model\Cart as CartModel;
use app\computer\controller\BaseController;
use think\facade\Env;
use think\facade\Session;

/**
 * 购物车表
 * Class Cart
 * @package app\common\model
 */
class Cart extends CartModel
{
    /**
     * 获取收藏状态
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getCollectStatusAttr($value, $data)
    {
        return Session::has('member_info.member_id') ? (new CollectGoods())
            ->where(
                [
                    ['member_id', '=', Session::get('member_info')['member_id']],
                    ['goods_id', '=', $data['goods_id']],
                ]
            )
            ->value('collect_goods_id') : 0;

    }

    /**
     * 获取配送方式
     * @param $value
     * @param $data
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsDistributionAttr($value, $data)
    {

        return (new Store())
            ->where([
                        ['store_id', '=', $data['store_id']]
                    ])
            ->field('is_city,is_shop,is_express,is_pay_delivery,is_delivery')
            ->find();
    }
    /**
     * 获取店铺是否支持开增值发票
     */
    public function getIsAddedValueTaxAttr($value, $data)
    {
        if (BaseController::$oneOrMore !== TRUE)
        {
            Env::load(Env::get('app_path') . 'common/ini/.config');
            $is_added_value_tax = Env::get('is_added-value_tax', 0);
        } else
        {
            $store = (new Store());
            if (in_array('is_added_value_tax', $store->getTableFields()))
            {
                $is_added_value_tax = Store::where(
                    [
                        ['store_id', '=', $data['store_id']],
                    ]
                )
                    ->value('is_added_value_tax', 0);
            } else
            {
                $is_added_value_tax = 0;
            };
        }
        return $is_added_value_tax;
    }

}