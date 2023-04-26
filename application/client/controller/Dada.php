<?php
namespace app\client\controller;

use think\Controller;

use think\facade\Request;
use think\facade\Session;
use app\common\model\Area as AreaModel;
use app\common\service\Dada as DadaService;
use app\common\model\DadaMerchant as DadaMerchantModel;
use app\common\model\DadaShop as DadaShopModel;
use think\Db;

class Dada extends Controller
{
    /**
     * 创建商户
     * @param Request $request
     * @param AreaModel $area
     * @param DadaMerchantModel $dadaMerchant
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function merchantAdd(Request $request, AreaModel $area, DadaMerchantModel $dadaMerchant)
    {
        $city = $area->where(['deep' => 2])->select();
        $merchant = $dadaMerchant->where(['store_id' => Session::get('client_store_id')])->find();
        if ($request::isPost()) {

            try {
                $param = $request::post();

                $Data = new DadaService('', $param);
//                73753 测试
                $result = $Data->request('merchantApi/merchant/add');

                if ($result['code'] == 0) {
                    $param['store_id'] = Session::get('client_store_id');
                    $param['source_id'] = $result['data'];
                    $dadaMerchant->allowField(true)->save($param);
                    return ['code' => 0, 'message' => '创建达达商户成功', 'url' => '/client/dada/merchantAdd'];
                } else {
                    return $result;
                }

            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch('', [
            'city'          => $city,
            'merchant'     => $merchant,
        ]);
    }

    /**
     * 门店列表
     * @param DadaShopModel $dadaShop
     * @param Request $request
     * @return array|mixed
     */
    public function dadaShop(DadaShopModel $dadaShop, Request $request)
    {
        try {
            // 获取数据
            $param = $request::post();

            // 获取数据
            $data = $dadaShop
                ->where(['store_id' => Session::get('client_store_id')])
                ->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 创建门店（多条，需数组）
     * @param DadaMerchantModel $dadaMerchant
     * @param Request $request
     * @return array|mixed
     */
    public function create(DadaMerchantModel $dadaMerchant, Request $request, DadaShopModel $dadaShop)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

                if (empty($source_id = $dadaMerchant->where(['store_id' => Session::get('client_store_id')])->value('source_id'))) return ['code' => -100, 'message' => '请先创建商户'];

                $param['city_name'] = (new AreaModel())->where('area_id', $param['city_name'])->value('area_name');
                $param['area_name'] = (new AreaModel())->where('area_id', $param['area_name'])->value('area_name');
                $shop[] = $param;
                $Dada = new DadaService($source_id, $shop);

                $result = $Dada->request('api/shop/add');

                if ($result['code'] == 0) {
                    $param['store_id'] = Session::get('client_store_id');
                    $param['origin_shop_id'] = $result['data']['successList'][0]['originShopId'];
                    $dadaShop->allowField(true)->save($param);
                    return ['code' => 0, 'message' => '创建门店成功', 'url' => '/client/dada/dadaShop'];
                } else {
                    return ['code' => $result['code'], 'message' => $result['data']['failedList'][0]['msg']];
                }

            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $business = Db::name('dada_business')->select();
        return $this->fetch('', [
            'business' => $business,
        ]);
    }

    /**
     * 编辑门店
     * @param DadaMerchantModel $dadaMerchant
     * @param Request $request
     * @param DadaShopModel $dadaShop
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(DadaMerchantModel $dadaMerchant, Request $request, DadaShopModel $dadaShop)
    {
        $source_id = $dadaMerchant->where(['store_id' => Session::get('client_store_id')])->value('source_id');
        if ($request::isPost()) {
            try {
                $param = $request::post();

                if (empty($source_id)) return ['code' => -100, 'message' => '请先创建商户'];

                $param['city_name'] = (new AreaModel())->where('area_id', $param['city_name'])->value('area_name');
                $param['area_name'] = (new AreaModel())->where('area_id', $param['area_name'])->value('area_name');

                $Dada = new DadaService($source_id, $param);

                $result = $Dada->request('api/shop/update');

                if ($result['code'] == 0) {
                    $param['store_id'] = Session::get('client_store_id');
                    $dadaShop->isUpdate(true)->allowField(true)->save($param);
                    return ['code' => 0, 'message' => '编辑门店成功', 'url' => '/client/dada/dadaShop'];
                } else {
                    return $result;
                }

            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $item = $dadaShop->where(['dada_shop_id' => Request::get('dada_shop_id')])->find();
        $business = Db::name('dada_business')->select();
        return $this->fetch('create', [
            'business' => $business,
            'item' => $item,
        ]);
    }

    public function area(Request $request, AreaModel $area)
    {
        try {

            // 获取参数
            $param = $request::get();

            // 父ID
            $condition[] = !empty($param['id']) ? ['parent_id', '=', $param['id']] : ['deep', '=', 2];

            $data = $area
                ->where($condition)
                ->select();
        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $data;
    }
}