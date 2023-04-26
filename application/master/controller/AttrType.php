<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\Products;
use app\common\model\Store;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Cache;
use think\facade\Request;
use app\common\model\Goods;
use app\common\model\AttrType as AttrTypeModel;

use think\Queue;

/**
 * 商品属性类型
 * Class AttrType
 * @package app\master\controller
 */
class AttrType extends Controller
{
    /**
     * 自营/店铺商品属性类型列表
     * @param Request $request
     * @param AttrTypeModel $attrType
     * @return mixed
     * @throws Exception
     */
    public function index(Request $request, AttrTypeModel $attrType)
    {
        try {
            $param = $request::get();
            //初始查询自营的降价商品列表
            $where[0] = ['s.shop', '=', 0];
            $pageLimits = $attrType->pageLimits;

            if (array_key_exists('type', $param) && $param['type']) $where[0] = ['s.shop', '=', $param['type']];
            if (array_key_exists('keyword', $param) && $param['keyword'])
                $where[] = ['a.type_name|s.store_name', 'like', "%" . $param['keyword'] . '%'];
            // 单店
            if (config('user.one_more') != 1) {
                $where[] = [
                    'a.store_id',
                    '=',
                    config('user.one_store_id'),
                ];
            }
            $data = $attrType
                ->alias('a')
                ->join('store s', 's.store_id = a.store_id')
                ->where($where)
                ->field('s.store_name,a.attr_type_id,a.status,a.create_time,a.type_name')
                ->order(['a.create_time' => 'desc'])
                ->paginate($pageLimits, false, ['query' => $param]);
            return $this->fetch('', [
                'data' => $data,
                'one_more'         => config('user.one_more'),
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
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, AttrTypeModel $attrType, Store $store)
    {
        $param = $request::param();
        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();
                // 验证
                $check = $attrType->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $attrType->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/attr_type/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('', [
            'storeData' => $store->where(['status' => 4, 'shop' => $param['type']])->field('store_id,store_name')->select(),
            'one_more'         => config('user.one_more'),
            'one_store_id'         => config('user.one_store_id'),
        ]);
    }

    /**
     * 商品分类编辑
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, AttrTypeModel $attrType, Store $store)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $attrType->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $attrType->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/attr_type/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('create', [
            'item' => $attrType->get($request::get('id')),
            'storeData' => $store->where(['status' => 4, 'shop' => 0])->field('store_id,store_name')->select(),
            'one_more'         => config('user.one_more'),
            'one_store_id'         => config('user.one_store_id'),
        ]);
    }

    /**
     * 删除商品类型
     * @param Request $request
     * @param brandModel $brand
     * @return array
     */
    public function destroy(Request $request, AttrTypeModel $attrType)
    {
        if ($request::isPost()) {
            try {
                // 删除
                $attrType::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}