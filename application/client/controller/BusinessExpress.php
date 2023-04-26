<?php
// 商家配送
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\Area;
use app\common\model\FreightExpress;
use app\common\model\FreightExpress as FreightModel;
use app\common\model\FreightExpressClassify;
use app\common\model\Goods;
use think\Controller;
use think\Db;
use think\facade\Request;
use think\facade\Session;

class BusinessExpress extends Controller
{
    /**
     * 删除运费模板
     *
     * @param Request $request
     * @param FreightModel $freightExpress
     *
     * @return array
     */
    public function destroy(FreightModel $freightExpress, FreightExpressClassify $freightExpressClassify)
    {
        try {
            $_freightExpressClassifyId = $this->request->post('id');

            if (empty($_freightExpressClassifyId)) {
                return ['code' => -100, 'message' => '请选择需要删除的条目'];
            }
            $_countGoodsWithCurrentFreightExpress = Goods::where([
                ['freight_status', '=', 1],
                ['freight_id', '=', $_freightExpressClassifyId],
            ])->field('id')->count();

            if ($_countGoodsWithCurrentFreightExpress > 0) {
                return ['code' => -100, 'message' => '无法删除,因为已有商品正在使用此模板'];
            }

            Db::startTrans();
            FreightExpressClassify::destroy([
                'freight_express_classify_id' => $_freightExpressClassifyId,
            ]);

            FreightExpress::destroy([
                'freight_express_classify_id' => $_freightExpressClassifyId,
            ]);
            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0]];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }


    /**
     * 首页
     * @param \app\common\model\Store $store
     * @return mixed
     */
    public function index(\app\common\model\Store $store)
    {
        $_data = [];
        try {
            // 获取模板分类
            $_ExpressClassify = FreightExpressClassify::where([
                'store_id' => Session::get('client_store_id'),
            ])->select();

            $_Express = FreightExpress::where([
                'store_id' => Session::get('client_store_id'),
            ])->select();
            $_store = $store->where([['store_id', '=', Session::get('client_store_id')]])->find();
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

        $this->assign('data', $_data);
        $this->assign('store', $_store ?? []);

        return $this->fetch();
    }

    /**
     * 新建
     */
    public function create()
    {
        $this->assign('freight_express_classify_id', -1);

        return $this->fetch('create');
    }

    /**
     * 编辑
     */
    public function edit()
    {
        $this->assign('freight_express_classify_id', $this->request->get('id'));

        return $this->fetch('create');
    }

    /**
     * 保存前台发送给后台的数据
     *
     * @param FreightModel $freightExpress
     * @param FreightExpressClassify $freightExpressClassify
     * @return \think\response\Json
     */
    public function save(FreightExpress $freightExpress, FreightExpressClassify $freightExpressClassify)
    {
        $_data = $this->request->post();

        try {
            Db::startTrans();
            // 运费模板ID
            $_freightExpressClassifyId = $_data['express_classify']['freight_express_classify_id'];

            // 判断是否设置了邮递优惠规则
            if (isset($_data['express_classify']['discount_postage_rules'])) {
                // 验证 邮递优惠规则 是否合法
                if (!in_array($_data['express_classify']['discount_postage_rules'], ['1', '2'])) {
                    Db::rollback();
                    return json(['code' => -1, 'message' => '请选择正确的邮递优惠规则']);
                }

                // 验证 满
                if (!isset($_data['express_classify']['discount'])) {
                    Db::rollback();
                    return json(['code' => -1, 'message' => '请输入正确的邮递优惠规则']);
                }

                // 卖家包邮的时候 不验证减
                if ($_data['express_classify']['express'] != 2) {
                    if (!isset($_data['express_classify']['postage'])) {
                        Db::rollback();
                        return json(['code' => -1, 'message' => '请输入正确的邮递优惠规则']);
                    }
                } else {
                    // 卖家包邮 减为 0
                    $_data['express_classify']['postage'] = 0;
                }

                $_fmtDiscount = fmtPrice($_data['express_classify']['discount']);
                $_fmtPostage = fmtPrice($_data['express_classify']['postage']);
                if (
                    $_data['express_classify']['discount'] != $_fmtDiscount ||
                    $_data['express_classify']['postage'] != $_fmtPostage ||
                    $_fmtDiscount > 65535 ||
                    $_fmtPostage > 65535
                ) {
                    Db::rollback();
                    return json(['code' => -1, 'message' => '邮递优惠规则的值只能输入数字且小数点只能有两位数字,同时不大于 65535']);
                }
            } else {
                // 取消所有的邮递优惠规则
                $_data['express_classify']['discount_postage_rules'] = NULL;
                $_data['express_classify']['discount'] = NULL;
                $_data['express_classify']['postage'] = NULL;
            }

            // 删除删除列表中的内容
            if (!empty($_data['delete_list'])) {
                $freightExpress::where([
                    ['freight_express_id', 'in', $_data['delete_list']],
                ])->delete();
            }

            // 新增分类
            if ($_freightExpressClassifyId == -1) {
                // 设置 商家ID
                $freightExpressClassifyResult = $freightExpressClassify::create([
                    'store_id'               => Session::get('client_store_id'),
                    'name'                   => $_data['express_classify']['name'],
                    'type'                   => $_data['express_classify']['type'],
                    'express'                => $_data['express_classify']['express'],
                    'all_address_express'    => $_data['express_classify']['all_address_express'],
                    'upper_num'              => $_data['express_classify']['upper_num'],
                    'base_amount'            => $_data['express_classify']['base_amount'],
                    'extend_num_unit'        => $_data['express_classify']['extend_num_unit'],
                    'extend_amount'          => $_data['express_classify']['extend_amount'],
                    'discount_postage_rules' => $_data['express_classify']['discount_postage_rules'] ?? NULL,
                    'discount'               => $_data['express_classify']['discount'] ?? NULL,
                    'postage'                => $_data['express_classify']['postage'] ?? NULL,
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
                    'discount_postage_rules',
                    'discount',
                    'postage',
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
                        $_updateData[] = $value;
                    } else {
                        // 新增数据的话 为数据附加商家ID和模板分类ID
                        $value['store_id'] = Session::get('client_store_id');
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

            Db::commit();
            return json(['code' => 200]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -1, 'message' => $e->getMessage()]);
        }

    }

    /**
     * 根据运费模板分类ID获取运费模板 如果模板分类不存在 则返回让前台创建的数据
     *
     * @return \think\response\Json
     */
    public function getRow()
    {
        try {
            $_param = $this->request->get();

            // 符合的模板分类
            $_freightExpressClassify = FreightExpressClassify::where([
                'freight_express_classify_id' => $_param['freight_express_classify_id'],
            ])->find();

//            dump($_freightExpressClassify);

            // 模板分类下的模板
            $_freightExpress = FreightExpress::where([
                'freight_express_classify_id' => $_param['freight_express_classify_id'],
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
                'freight_express_classify_id' => $_param['freight_express_classify_id'],
                'all_address_express'         => $_freightExpressClassify['all_address_express'] == 1,
                'name'                        => $_freightExpressClassify['name'] ?? '',
                'type'                        => $_freightExpressClassify['type'] ?? 1,
                'express'                     => $_freightExpressClassify['express'] ?? 1,
                'children'                    => $_dataFreightExpress,
                'discount_postage_rules'      => $_freightExpressClassify['discount_postage_rules'],
                'discount'                    => $_freightExpressClassify['discount'],
                'postage'                     => $_freightExpressClassify['postage'],
            ];

            return json(['code' => 0, 'message' => config('message.')[0], 'data' => $_data]);
        } catch (\Exception $e) {
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

    public function getList()
    {
        try {
            $_freightExpress = FreightExpressClassify::where('store_id', Session::get('client_store_id'))
                ->field([
                    'freight_express_classify_id' => 'id',
                    'name',
                ])->select();
            return json(['code' => 0, 'data' => $_freightExpress]);
        } catch (\Exception $e) {
            return json(['code' => -100, 'message' => $e->getMessage()]);
        }
    }
}
