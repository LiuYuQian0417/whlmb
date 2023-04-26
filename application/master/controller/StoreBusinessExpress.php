<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/2 0002
 * Time: 9:16
 */

namespace app\master\controller;


use app\common\model\FreightExpress;
use app\common\model\Area;
use app\common\model\FreightExpressClassify;
use think\Controller;
use think\Db;
use think\facade\Session;

class StoreBusinessExpress extends Controller
{

    // 店铺ID
    private $_storeId = -1;
    // 模板分类ID
    private $_freight_express_classify_id = -1;

    protected function initialize()
    {
        $this->_storeId = $this->request->get('store_id', $this->_storeId);
        $this->_freight_express_classify_id = $this->request->get('freight_express_classify_id', $this->_freight_express_classify_id);
    }

    public function index()
    {
        $_data = [];
        try {
            // 获取模板分类
            $_ExpressClassify = FreightExpressClassify::where([
                                                                  'store_id' => $this->_storeId,
                                                              ])->select();

            $_Express = FreightExpress::where([
                                                  'store_id' => $this->_storeId,
                                              ])->select();

            foreach ($_ExpressClassify as $keyClassify => $valueClassify) {
                $_data[$keyClassify] = [
                    'freight_express_classify_id' => $valueClassify['freight_express_classify_id'],
                    'name'                        => $valueClassify['name'],
                    'update_time'                 => $valueClassify['update_time'],
                    'type'                        => $valueClassify['type'],
                    'all_address_express'         => $valueClassify['all_address_express'],
                    'upper_num'                   => $valueClassify['upper_num'],
                    'base_amount'                 => $valueClassify['base_amount'],
                    'extend_num_unit'             => $valueClassify['extend_num_unit'],
                    'extend_amount'               => $valueClassify['extend_amount'],
                    'is_default'                  => $valueClassify['is_default'],
                    'children'                    => [],
                ];

                foreach ($_Express as $keyItem => $valueItem) {
                    if ($valueItem['freight_express_classify_id'] == $valueClassify['freight_express_classify_id']) {
                        $_data[$keyClassify]['children'][] = [
                            'freight_express_id'     => $valueItem['freight_express_id'],
                            'distribution_area_name' => $valueItem['distribution_area_name'],
                            'upper_num'              => $valueItem['upper_num'],
                            'base_amount'            => $valueItem['base_amount'],
                            'extend_num_unit'        => $valueItem['extend_num_unit'],
                            'extend_amount'          => $valueItem['extend_amount'],
                        ];

                        unset($_Express[$keyItem]);
                    }
                }
            }
        } catch (\Exception $e) {
            $_data = [];
        }

        $this->assign('store_id', $this->_storeId);
        $this->assign('data', $_data);

        return $this->fetch();
    }

    /**
     * 创建模板
     */
    public function create()
    {
        $this->assign('freight_express_classify_id', $this->_freight_express_classify_id);
        $this->assign('store_id', $this->_storeId);
        return $this->fetch();
    }

    /**
     * 编辑模板
     */
    public function edit()
    {
        $this->assign('freight_express_classify_id', $this->_freight_express_classify_id);
        $this->assign('store_id', $this->_storeId);
        return $this->fetch('create');
    }

    /**
     * 保存前台发送给后台的数据
     *
     * @param FreightExpress $freightExpress
     * @param FreightExpressClassify $freightExpressClassify
     * @return \think\response\Json
     */
    public function save(FreightExpress $freightExpress, FreightExpressClassify $freightExpressClassify)
    {
        $_data = $this->request->post();

        try {
            $_freightExpressClassifyId = $_data['express_classify']['freight_express_classify_id'];

            // 删除删除列表中的内容
            if (!empty($_data['delete_list'])) {
                $freightExpress::where([
                                           'freight_express_id' => $_data['delete_list'],
                                       ])->delete();
            }

            // 新增分类
            if ($_freightExpressClassifyId == -1) {
                // 设置 商家ID
                $freightExpressClassifyResult = $freightExpressClassify::create([
                                                                                    'store_id'            => $this->_storeId,
                                                                                    'name'                => $_data['express_classify']['name'],
                                                                                    'type'                => $_data['express_classify']['type'],
                                                                                    'express'             => $_data['express_classify']['express'],
                                                                                    'all_address_express' => $_data['express_classify']['all_address_express'],
                                                                                    'upper_num'           => $_data['express_classify']['upper_num'],
                                                                                    'base_amount'         => $_data['express_classify']['base_amount'],
                                                                                    'extend_num_unit'     => $_data['express_classify']['extend_num_unit'],
                                                                                    'extend_amount'       => $_data['express_classify']['extend_amount'],
                                                                                ]);
                $_freightExpressClassifyId = $freightExpressClassifyResult->freight_express_classify_id;
            } else {
                // 更新分类数据
                $freightExpressClassify->allowField([
                                                        'name',
                                                        'type',
                                                        'express',
                                                        'all_address_express',
                                                        'upper_num',
                                                        'base_amount',
                                                        'extend_num_unit',
                                                        'extend_amount',
                                                    ])->isUpdate(TRUE)->save($_data['express_classify']);
            }

            // 如果数据列表不为空的话处理数据列表
            if (!empty($_data['express_list'])) {
                $_updateData = [];
                $_insertData = [];
                //循环数据列表
                foreach ($_data['express_list'] as $key => $value) {
                    // 通过 freight_express_id 属性区分是新增数据还是更新数据
                    if ($value['freight_express_id'] !== NULL) {
                        $_updateData[$key] = $value;
                    } else {
                        // 新增数据的话 为数据附加商家ID和模板分类ID
                        $value['store_id'] = $this->_storeId;
                        $value['freight_express_classify_id'] = $_freightExpressClassifyId;
                        $_insertData[$key] = $value;
                        // 去除数据中的模板ID,此时他的值是null
                        unset($_insertData[$key]['freight_express_id']);
                    }
                }

                if (!empty($_updateData)) {
                    // 更新旧的数据
                    $freightExpress->allowField(TRUE)->isUpdate(TRUE)->saveAll($_updateData);
                }
                if (!empty($_insertData)) {
                    // 新增新的数据
                    $freightExpress->allowField(TRUE)->isUpdate(FALSE)->saveAll($_insertData);
                }

            }

            return json(['code' => 200]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 获取模板列表
     */
    public function getRow()
    {

        try {
            // 符合的模板分类
            $_freightExpressClassify = FreightExpressClassify::where([
                                                                         'freight_express_classify_id' => $this->_freight_express_classify_id,
                                                                         'store_id'                    => $this->_storeId,
                                                                     ])->find();

            // 模板分类下的模板
            $_freightExpress = FreightExpress::where([
                                                         'freight_express_classify_id' => $this->_freight_express_classify_id,
                                                         'store_id'                    => $this->_storeId,
                                                     ])->select();

            // 判断全国配送是否禁用
            $_inputDisabled = $_freightExpressClassify['all_address_express'] !== 1;
            // 全国统一配送 是否开启
            $_globalExpress = [
                'upper_num'                => $_freightExpressClassify['upper_num'] ?? 0,
                'base_amount'              => $_freightExpressClassify['base_amount'] ?? 0,
                'extend_num_unit'          => $_freightExpressClassify['extend_num_unit'] ?? 0,
                'extend_amount'            => $_freightExpressClassify['extend_amount'] ?? 0,
                'distribution_area_id'     => -1,
                'disabled_upper_num'       => $_inputDisabled,
                'disabled_base_amount'     => $_inputDisabled,
                'disabled_extend_num_unit' => $_inputDisabled,
                'disabled_extend_amount'   => $_inputDisabled,
            ];

            // 模板数据
            $_dataFreightExpress = [];

            $_freightExpress = $_freightExpress->isEmpty() ? [] : $_freightExpress->toArray();

            foreach ($_freightExpress as $key => $value) {
                $_dataFreightExpress[$key] = [
                    // 模板ID
                    'freight_express_id'     => $value['freight_express_id'],
                    // 首（件、重量）
                    'upper_num'              => $value['upper_num'],
                    // 首运费
                    'base_amount'            => $value['base_amount'],
                    // 续（件、重量）
                    'extend_num_unit'        => $value['extend_num_unit'],
                    // 续运费
                    'extend_amount'          => $value['extend_amount'],
                    // 配送区域id串
                    'distribution_area_id'   => $value['distribution_area_id'],
                    // 配送区域名称（展示用）
                    'distribution_area_name' => $value['distribution_area_name'],
                    // 前端辅助记录信息
                    'distribution_area_data' => $value['distribution_area_data'],
                ];
            }
            array_unshift($_dataFreightExpress, $_globalExpress);

            $_data = [
                'freight_express_classify_id' => $this->_freight_express_classify_id,
                'all_address_express'         => $_freightExpressClassify['all_address_express'] == 1,
                'name'                        => $_freightExpressClassify['name'] ?? '',
                'type'                        => $_freightExpressClassify['type'] ?? 1,
                'express'                     => $_freightExpressClassify['express'] ?? 1,
                'children'                    => $_dataFreightExpress,
            ];

            return json(['code' => 0, 'message' => config('message.')[0], 'data' => $_data]);
        } catch (\Exception $e) {
            return json(['code' => -100, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 销毁模板
     * @param FreightExpressClassify $freightExpressClassify
     * @param FreightExpress $freightExpress
     * @return array
     */
    public function destroy(FreightExpressClassify $freightExpressClassify, FreightExpress $freightExpress)
    {

        if ($this->request->isPost()) {

            try {
                Db::startTrans();
                $_param = $this->request->post();
                $_valid = $freightExpressClassify->valid($_param, 'destroy');
                if ($_valid['code']) return $_valid;

                $_id = $_param['id'];
                // 删除分类
                $freightExpressClassify::destroy([
                                                     'freight_express_classify_id' => $_id,
                                                 ]);
                // 删除分类下的模板
                $freightExpress::destroy([
                                             'freight_express_classify_id' => $_id,
                                         ]);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => "/store_business_express/index?store_id={$this->_storeId}"];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return ['code' => -100, 'message' => config('message.404')];
    }

    /**
     * 设置默认
     */
    public function setDefault()
    {

        $_freightExpressClassifyId = $this->request->post('freight_express_classify_id');

        $_storeId = Session::get('client_store_id');

        try {
            Db::startTrans();
            // 把非自己的设置为非默认
            FreightExpressClassify::where([
                                              'store_id'   => $_storeId,
                                              'is_default' => 1,
                                          ])->update([
                                                         'is_default' => 2,
                                                     ]);
            // 把自己设置为默认
            FreightExpressClassify::where([
                                              'freight_express_classify_id' => $_freightExpressClassifyId,
                                          ])->update([
                                                         'is_default' => 1,
                                                     ]);
            Db::commit();
            return json(['code' => 0, 'message' => config('message.')[0]]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -100, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 获取区域信息
     *
     * @return \think\response\Json
     */
    public function getArea()
    {
        $parentId = $this->request->get('parent_id');

        try {
            $_area = Area::where([
                'parent_id' => $parentId,
                'status'    => 1,
            ])->field([
                'area_id',
                'area_name',
                'parent_id',
            ])->select();

            return json(['code' => 0, 'message' => config('message.')[0], 'data' => $_area]);
        } catch (\Exception $e) {
            return json(['code' => -100, 'message' => $e->getMessage()]);
        }
    }

}