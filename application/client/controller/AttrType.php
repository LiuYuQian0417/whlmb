<?php
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\Attr as AttrModel;
use app\common\model\GoodsAttr;
use app\common\model\Store;
use think\Controller;
use think\Exception;
use think\facade\Request;
use think\facade\Session;
use app\common\model\AttrType as AttrTypeModel;

/**
 * 商品属性类型
 * Class AttrType
 * @package app\client\controller
 */
class AttrType extends Controller
{
    /**
     * 店铺商品属性类型列表
     * @param Request $request
     * @param AttrTypeModel $attrType
     * @return mixed
     * @throws Exception
     */
    public function index(Request $request, AttrTypeModel $attrType)
    {
        try {

            $param = $request::param();

            // 条件筛选
            $condition[] = ['store_id', '=', Session::get('client_store_id')];

            if (isset($param['keyword'])) $condition[] = ['type_name', 'like', '%' . $param['keyword'] . '%'];

            $data = $attrType
                ->where($condition)
                ->field('attr_type_id,status,create_time,type_name')
                ->order('create_time', 'desc')
                ->paginate(15, FALSE, ['query' => $param]);
            return $this->fetch('', [
                'data' => $data,
            ]);

        } catch (\Exception $e) {

            throw new Exception($e->getMessage());

        }
    }


    /**
     * 商品类型显示状态更新
     * @param Request $request
     * @param AttrTypeModel $attrType
     * @return array
     */
    public function auditing(Request $request, AttrTypeModel $attrType)
    {

        if ($request::isPost()) {

            try {

                $attrType->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }

        }
    }

    /**
     * 商品分类新增
     * @param Request $request
     * @param AttrTypeModel $attrType
     * @return array|mixed
     */
    public function create(Request $request, AttrTypeModel $attrType)
    {

        if ($request::isPost()) {

            try {
                // 获取参数
                $param = $request::post();

                // 定义店铺ID
                $param['store_id'] = Session::get('client_store_id');

                // 验证
                $check = $attrType->valid($param, 'create');
                if ($check['code']) return $check;

                $attrType->allowField(TRUE)->save($param);

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/attr_type/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }

        }

        return $this->fetch('');
    }

    /**
     * 商品分类编辑
     * @param Request $request
     * @param AttrTypeModel $attrType
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, AttrTypeModel $attrType)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 定义店铺ID
                $param['store_id'] = Session::get('client_store_id');

                // 验证
                $check = $attrType->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $attrType->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/attr_type/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('create', [
            'item' => $attrType->get($request::get('id')),
        ]);
    }

    /**
     * 删除商品类型
     * @param Request $request
     * @param AttrTypeModel $attrType
     * @return array
     */
    public function destroy(Request $request, AttrTypeModel $attrType)
    {
        if ($request::isPost()) {

            try {
                $_attrIdList = AttrModel::where([
                    ['attr_type_id', '=',$request::post('id')],
                ])->column('attr_id');

                $_goodsAttrList = GoodsAttr::where([
                    ['attr_id','in',join(',',$_attrIdList)]
                ])->select();

                if (!$_goodsAttrList->isEmpty()){
                    return ['code' => -100, 'message' => '店铺存在使用此类型的商品,无法删除'];
                }

                $attrType::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

}