<?php
/**
 * 快递配送.
 * User: Heng
 * Date: 2018/10/10
 * Time: 16:43
 */
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\Area;
use app\common\model\FreightExpress;
use app\common\model\FreightExpressClassify;
use app\common\model\Goods;
use think\Controller;
use think\Db;
use think\facade\Request;
use app\common\model\Store as StoreModel;
use app\common\model\FreightExpress as FreightExpressModel;
use think\response\Redirect;

class Express extends Controller
{

    private $store = '';

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function __construct()
    {
        parent::__construct();
        $this->store = (new StoreModel())
            ->where(['shop' => 0])
            ->field('store_id,store_name')
            ->select();
    }

    /**
     * 快递配送列表
     * @param Request $request
     * @return array|mixed
     */
    public function index(Request $request)
    {
        try {
            // 获取数据
            $param = $request::get();

            // 筛选条件
            $condition = [];

            $_singleShop = config('user.one_more');
            $is_delivery = '';
            if ($_singleShop != 1) {
                $param['store_id'] = config('user.one_store_id');

                $is_delivery = Db::name('store')->where('store_id',$param['store_id'])->value('is_delivery');
            }

            if (array_key_exists('store_id', $param) && $param['store_id']) $condition[] = ['ishop_freight_express_classify.store_id', '=', $param['store_id']];

            if (array_key_exists('keyword', $param) && $param['keyword']) $condition[] = ['name', 'like', "%{$param['keyword']}%"];

            $data = FreightExpressClassify::with(['freightExpressCor', 'storeBlt'])
                ->alias('ishop_freight_express_classify')
                ->join('store store', 'ishop_freight_express_classify.store_id = store.store_id and store.delete_time is null')
                ->field('ishop_freight_express_classify.*,store.store_name')
                ->where($condition ?? [])
                ->paginate(10, FALSE, ['query' => $param]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return FALSE;
        }

        return $this->fetch('', [
            'data'        => $data,
            'store_list'  => $this->store,
            'single_shop' => $_singleShop,
            'is_delivery' => $is_delivery,
        ]);
    }


    /**
     * 创建运费模板
     * @param Request $request
     * @return array|mixed
     */
    public function create(Request $request)
    {
        $_singleStore = config('user.one_more');

        // 单店
        if ($_singleStore != 1) {
            $this->assign('store_id', config('user.one_store_id'));
        } else {
            $this->assign('store_id', $this->request->get('store_id', ''));
        }


        $this->assign('freight_express_classify_id', -1);
        $this->assign('single_store', $_singleStore);
        return $this->fetch('create');
    }

    /**
     * 编辑运费模板
     */
    public function edit()
    {
        $_freightExpressClassifyId = $this->request->get('id');
        try {
            $_storeId = FreightExpressClassify::get([
                'freight_express_classify_id' => $_freightExpressClassifyId,
            ])['store_id'];
        } catch (\Exception $e) {
            $_storeId = '';
        }


        $this->assign('store_id', $_storeId);
        $this->assign('freight_express_classify_id', $_freightExpressClassifyId);
        $this->assign('single_store', config('user.one_more'));
        return $this->fetch('create');
    }

    /**
     * 获取店铺列表
     *
     * @return array
     */
    public function getStoreList()
    {
        $_storeData = [];

        try {
            $_storeList = StoreModel::all();
            if (!empty($_storeList)) {
                foreach ($_storeList as $key => $value) {
                    $_storeData[$key] = [
                        'store_id'   => (string)$value['store_id'],
                        'store_name' => $value['store_name'],
                    ];
                }
            }
        } catch (\Exception $e) {
            return json(['code' => -100, 'message' => '获取店铺列表失败,请稍后再试']);
        }

        return json(['code' => 200, 'data' => $_storeData]);
    }

    /**
     * 多删除
     *
     * @param Request $request
     * @return array
     */
    public function multiDestroy(Request $request)
    {
        try {
            $_post = $request::post('id');

            if (empty($_post)) {
                return ['code' => -100, 'message' => '请选择需要删除的条目'];
            }
            $_countGoodsWithCurrentFreightExpressList = Goods::where([
                ['freight_status', '=', 1],
                ['freight_id', 'in', $_post],
            ])->field('id')->count();

            if ($_countGoodsWithCurrentFreightExpressList > 0) {
                return ['code' => -100, 'message' => '无法删除,因为已有商品正在使用选中的模板'];
            }
            FreightExpressClassify::where([
                ['freight_express_classify_id', 'in', $_post],
            ])->delete();
            FreightExpress::where([
                ['freight_express_classify_id', 'in', $_post],
            ])->delete();
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/freight_express/index'];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 单删除
     *
     * @param Request $request
     * @return array
     */
    public function destroy(Request $request)
    {
        try {
            $_post = $request::post('id');

            if (empty($_post)) {
                return ['code' => -100, 'message' => '请输入ID'];
            }

            $_countGoodsWithCurrentFreightExpress = Goods::where([
                ['freight_status', '=', 1],
                ['freight_id', '=', $_post],
            ])->field('id')->count();

            if ($_countGoodsWithCurrentFreightExpress > 0) {
                return ['code' => -100, 'message' => '无法删除,因为已有商品正在使用此模板'];
            }

            FreightExpressClassify::destroy($_post);
            FreightExpress::destroy(['freight_express_classify_id' => $_post]);
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/freight_express/index'];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    public function save(FreightExpress $freightExpress, FreightExpressClassify $freightExpressClassify)
    {
        $_data = $this->request->post();

        try {
            Db::startTrans();
            // 运费模板ID
            $_freightExpressClassifyId = $_data['express_classify']['freight_express_classify_id'];

            // 如果所有地区默认配送为否 同时 配送列表 为空
            if ($_data['express_classify']['all_address_express'] == 2 && empty($_data['express_list'])) {
                Db::rollback();
                return json(['code' => -1, 'message' => '如不勾选所有地区默认配送,请添加可配送区域和运费']);
            }

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
                if ($_data['express_classify']['express'] == 1) {
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
                    'freight_express_id' => $_data['delete_list'],
                ])->delete();
            }

            // 新增分类
            if ($_freightExpressClassifyId == -1) {
                // 设置 商家ID
                $freightExpressClassifyResult = $freightExpressClassify::create([
                    'store_id'               => $_data['express_classify']['store_id'],
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
                        $_updateData[$key] = $value;
                    } else {
                        // 新增数据的话 为数据附加商家ID和模板分类ID
                        $value['store_id'] = $_data['express_classify']['store_id'];
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
            return json(['code' => -1, 'message' => config('message.')[-1]]);
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

    public function setting(Request $request){
        $param = $request::post();
        $store_id = config('user.one_store_id');
        $_data[$param['switchType']] = $param['checked'];
        Db::startTrans();
        try {
            (new StoreModel())->where('store_id',$store_id)->update($_data);
            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0]];
        } catch (\Exception $e) {
            return ['code' => 0, 'message' => config('message.')[-1]];
        }
    }
}