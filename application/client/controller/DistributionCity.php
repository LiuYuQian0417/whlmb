<?php
/**
 * 同城配送
 * heng
 */
declare(strict_types=1);

namespace app\client\controller;

use think\Controller;
use think\Db;
use think\facade\Request;
use think\facade\Session;
use app\common\model\Area as AreaModel;
use app\common\model\Store as StoreModel;
use app\common\model\DistributionCity as DistributionCityModel;

class DistributionCity extends Controller
{
    /**
     * 同城配送管理
     * @param Request $request
     * @param StoreModel $store
     * @param DistributionCityModel $distributionCity
     * @param AreaModel $area
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, StoreModel $store, DistributionCityModel $distributionCity, AreaModel $area)
    {
        if ($request::isPost()) {

            try {
                Db::startTrans();
                // 获取数据
                $param = $request::post();

                $param['store_id'] = Session::get('client_store_id');

                if (isset($param['discount_postage_rules'])) {
                    if ($param['discount_postage_rules'] == 2) {
                        $param['discount'] = $param['discount_1'];
                        $param['postage'] = $param['postage_1'];
                    }
                    if ($param['discount_postage_rules'] == 3) {
                        $param['discount'] = $param['discount_2'];
                        $param['postage'] = $param['postage_2'];
                    }
                } else {
                    $param['discount_postage_rules'] = NULL;
                    unset($param['discount']);
                    unset($param['discount_1']);
                    unset($param['postage']);
                    unset($param['postage_1']);
                    unset($param['discount_2']);
                    unset($param['postage_2']);
                }

                // 验证
                $check = $distributionCity->valid($param);
                if ($check['code']) return $check;

                $store->where('store_id', $param['store_id'])->update(['is_pay_delivery' => $param['is_pay_delivery']]);

                // 判断是否存在
                $_distributionCityCount = $distributionCity->where(['store_id' => $param['store_id']])->count('store_id');

                if ($_distributionCityCount >= 1) {
                    // 更新
                    $distributionCity->allowField(true)->isUpdate(true)->save($param);
                } else {
                    unset($param['shop_address']);
                    unset($param['is_pay_delivery']);
                    $param['type'] = 1;
                    $distributionCity->allowField(true)->save($param);
                }

                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/distribution_city/index'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        // 配送地区
        $item = $store->where('store_id', Session::get('client_store_id'))->field('lat,lng,address,city,is_city,is_delivery,is_shop,is_pay_delivery')->find();
        $areaId = $area->where('area_name', $item['city'])->value('area_id');
        $cityArea = $area->where('parent_id', $areaId)->field('area_id,area_name')->select();
        return $this->fetch('', [
            'coordinate' => $item,
            'item'       => $distributionCity->where('store_id', Session::get('client_store_id'))
                                             ->field('create_time,update_time,delete_time', true)->find(),
            'city_area'  => $cityArea
        ]);

    }
}