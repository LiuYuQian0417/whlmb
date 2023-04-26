<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/3 0003
 * Time: 15:57
 */

namespace app\master\controller;


use app\common\model\Area;
use app\common\model\DadaMerchant;
use app\common\model\DadaShop;
use app\common\model\DistributionCity as DistributionCityModel;
use app\common\model\Store;
use app\common\service\Dada;
use think\Controller;
use think\Db;
use think\facade\Cache;
use think\facade\Env;
use think\Request;

class DistributionCity extends Controller
{
    public function index()
    {
        $_data = [];
        $_map = [];
        $_paramGet = $this->request->get();
        if (!empty($_paramGet['store_id'])) $_map[] = ['dc.store_id', '=', $_paramGet['store_id']];
        try {
            $_data = DistributionCityModel::where($_map)
                ->alias('dc')
                ->join('store s', 's.store_id = dc.store_id')
                ->field('dc.distribution_city_id,dc.store_id,dc.distribution_type,dc.staircase,dc.update_time,
                s.store_name,s.address,dc.create_time')
                ->order(['dc.create_time' => 'desc', 'dc.distribution_city_id' => 'desc'])
                ->paginate(10, false, ['query' => $_paramGet]);
        } catch (\Exception $e) {
            $this->error('错误');
        }
        $this->assign('store_list', Store::where(['shop' => 0])->field('store_id,store_name')->select());
        $this->assign('data', $_data);
        return $this->fetch();
    }

    public function edit(DistributionCityModel $distributionCity, Store $store)
    {
        if ($this->request->isPost()) {
            try {
                Db::startTrans();
                // 获取数据
                $param = $this->request->post();

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

                $url = config('user.one_more') == 0 ? '/distribution_city/edit?id='.config('user.one_store_id') : '/distribution_city/index';
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => $url];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];

            }

        }
        $_storeId = $this->request->get('id');
        // 配送地区
        $item = Store::where('store_id', $_storeId)->field('lat,lng,address,city,is_pay_delivery')->find();
        $areaId = Area::where('area_name', $item['city'])->value('area_id');
        $cityArea = Area::where('parent_id', $areaId)->field('area_id,area_name')->select();

        return $this->fetch('create', [
            'coordinate' => $item,
            'item' => DistributionCityModel::where('store_id', $_storeId)
                ->field('create_time,update_time,delete_time', true)->find(),
            'city_area' => $cityArea,
            'store_id' => $_storeId,
            'type' => 'edit'
        ]);
    }

    /**
     * 第三方
     * @param Request $request
     * @param Area $area
     * @param Store $store
     * @param DadaMerchant $dadaMerchant
     * @return mixed
     */
    public function third(Request $request, Area $area, Store $store, DadaMerchant $dadaMerchant)
    {
        $param = $request->get();
        if ($request->isPost()) {
            try {
                $args = $request->post();
                $validRet = $area->valid($args, 'create_merchant');
                if ($validRet['code']) return $validRet;
                // 创建达达商户
                $dadaServer = new Dada('', $args);
                $ret = $dadaServer->request('/merchantApi/merchant/add');
                if ($ret['code'] != 0) return ['code' => -1, 'message' => $ret['message']];
                $args['store_id'] = $param['id'];
                $args['source_id'] = $ret['data'];
                $dadaMerchant->allowField(true)->isUpdate(false)->save($args);
                return ['code' => 0, 'message' => '注册成功'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        // 检测店铺是否是自营店铺
        $storeState = $store
            ->where([
                ['store_id', '=', $param['id']],
            ])
            ->value('shop');
        // 检测店铺是否创建第三方商户
        $find = $dadaMerchant
            ->where([
                ['store_id', '=', $param['id']],
            ])
            ->field('delete_time', true)
            ->find();
        // 查询市级地区
        $area_list = Cache::store('file')->get('area_list');
        if (!$area_list) {
            $area_list = $area
                ->where([
                    ['deep', '=', 2],
                ])
                ->field('area_id,area_name')
                ->select();
            Cache::store('file')->set('area_list', $area_list);
        }
        return $this->fetch('third', [
            'area_list' => $area_list,
            'storeState' => $storeState,
            'find' => $find,
        ]);
    }

    /**
     * 门店列表
     * @param Request $request
     * @param DadaShop $dadaShop
     * @param DadaMerchant $dadaMerchant
     * @return mixed
     */
    public function shopList(Request $request, DadaShop $dadaShop, DadaMerchant $dadaMerchant)
    {
        $args = $request->get();
        // 查询商家商户信息
        $source_id = $dadaMerchant
            ->where([
                ['store_id', '=', $args['id']],
            ])
            ->value('source_id');
        // 查找该商户门店
        $data = $dadaShop
            ->alias('ds')
            ->where([
                ['store_id', '=', $args['id']],
            ])
            ->join('dada_business db', 'db.business_id = ds.business')
            ->field('delete_time', true)
            ->field('db.name as business_name')
            ->paginate($dadaShop->pageLimits, false, ['query' => $args]);
        if (!$data->isEmpty()) {
            $updateData = [];
            foreach ($data as &$item) {
                $dadaRet = (new Dada($source_id, ['origin_shop_id' => $item['origin_shop_id']]))->request('/api/shop/detail');
                if ($dadaRet['code'] == 0) {
                    $updateData[$item['dada_shop_id']] = [];
                    foreach ($item->getData() as $_k => $_i) {
                        if (!in_array($_k, ['dada_shop_id', 'store_id', 'create_time',
                                'city_name', 'update_time', 'business_name']) && $dadaRet['data'][$_k] != $_i
                        ) {
                            $updateData[$item['dada_shop_id']][$_k] = $item[$_k] = $dadaRet['data'][$_k];
                        }
                    }
                    if (!empty($updateData[$item['dada_shop_id']])) {
                        $updateData[$item['dada_shop_id']]['dada_shop_id'] = $item['dada_shop_id'];
                    } else {
                        unset($updateData[$item['dada_shop_id']]);
                    }
                }
            }
            if ($updateData) {
                $dadaShop->allowField(true)->isUpdate(true)->saveAll(array_values($updateData));
            }

        }
        return $this->fetch('distribution_city/shopList', [
            'data' => $data,
            'source_id' => $source_id,
        ]);
    }

    /**
     * 添加/更新门店
     * @param Request $request
     * @param DadaShop $dadaShop
     * @param Area $area
     * @return array|mixed
     */
    public function shopCreate(Request $request, DadaShop $dadaShop, Area $area)
    {
        $param = $request->get();
        if ($request->isPost()) {
            try {
                Db::startTrans();
                $args = $request->post();
                $validRet = $dadaShop->valid($args, 'create');
                if ($validRet['code']) return $validRet;
                // 查询商户id
                $flag = true;
                if (!array_key_exists('dada_shop_id', $args) || !$args['dada_shop_id']) {
                    // 添加门店
                    $dadaRet = (new Dada($param['source_id'], [$args]))->request('/api/shop/add');
                    if ($dadaRet['code'] != 0) {
                        return ['code' => -1, 'message' => $dadaRet['data']['failedList'][0]['msg']];
                    }
                    $args['origin_shop_id'] = $dadaRet['data']['successList'][0]['originShopId'];
                    $args['store_id'] = $param['id'];
                    // 插入本地数据库
                    $dadaShop->allowField(true)->isUpdate(false)->save($args);
                } else {
                    // 更新门店
                    $dadaRet = (new Dada($param['source_id'], $args))->request('/api/shop/update');
                    if ($dadaRet['code'] != 0) {
                        return ['code' => -1, 'message' => $dadaRet['data']['failedList'][0]['msg']];
                    }
                    $flag = false;
                    $dadaShop->allowField(true)->isUpdate(true)->save($args);
                }
                Db::commit();
                return ['code' => 0, 'message' => ($flag ? '添加' : '编辑') . '成功', 'url' => '/distribution_city/shopList?id=' . $param['id']];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $shop_data = [];
        $city_id = '';
        if (array_key_exists('dada_shop_id', $param) && $param['dada_shop_id']) {
            $shop_data = $dadaShop
                ->where([
                    ['dada_shop_id', '=', $param['dada_shop_id']],
                ])
                ->field('delete_time', true)
                ->find();
            $city_id = $area->where([['area_name', '=', $shop_data['city_name']]])->value('area_id');
        }
        // 查询市级地区
        $area_list = Cache::store('file')->get('area_list');
        if (!$area_list) {
            $area_list = $area
                ->where([
                    ['deep', '=', 2],
                ])
                ->field('area_id,area_name')
                ->select();
            Cache::store('file')->set('area_list', $area_list);
        }
        $area_data = $area
            ->where([
                ['parent_id', '=', ($city_id ?: $area_list[0]['area_id'])],
            ])
            ->field('area_id,area_name')
            ->select();
        $business_list = Db::name('dada_business')
            ->field('business_id,name')
            ->select();
        return $this->fetch('distribution_city/shopCreate', [
            'area_list' => $area_list,
            'area_data' => $area_data,
            'business_list' => $business_list,
            'shop_data' => $shop_data,
        ]);
    }

    /**
     * 获取下级城市id
     * @param Request $request
     * @param Area $area
     * @return array
     */
    public function getNextArea(Request $request, Area $area)
    {
        if ($request->isPost()) {
            try {
                $args = $request->post();
                $data = $area
                    ->where([
                        ['parent_id', '=', $args['area_id']],
                    ])
                    ->field('area_id,area_name')
                    ->select();
                return ['code' => 0, 'message' => '查询成功', 'data' => $data];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 删除门店
     * @param Request $request
     * @param DadaShop $dadaShop
     * @return array
     */
    public function destroy(Request $request, DadaShop $dadaShop)
    {
        if ($request->isPost()) {
            try {
                $args = $request->post();
                if ($args['id']) {
                    $info = $dadaShop
                        ->alias('ds')
                        ->where([['dada_shop_id', 'in', $args['id']]])
                        ->join('dada_merchant dm', 'dm.store_id = ds.store_id')
                        ->field('dm.source_id,origin_shop_id,dada_shop_id')
                        ->select();
                    if (!$info->isEmpty()) {
                        $success = $error = [];
                        foreach ($info as $_info) {
                            // 更新门店(下架)
                            $dadaRet = (new Dada($_info['source_id'], ['origin_shop_id' => $_info['origin_shop_id'], 'status' => 0]))->request('/api/shop/update');
                            if ($dadaRet['code'] != 0) {
                                $error = ['code' => -1, 'message' => $dadaRet['data']['failedList'][0]['msg']];
                            } else {
                                array_push($success, $_info['dada_shop_id']);
                            }
                        }
                        if ($error) return $error;
                        if ($success) {
                            DadaShop::destroy(implode(',', $success));
                        }
                    }
                }
                return ['code' => 0, 'message' => '删除成功'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}