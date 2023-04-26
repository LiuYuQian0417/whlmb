<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\Brand;
use app\common\model\GoodsClassify;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Request;
use app\common\model\Goods as GoodsModel;
use think\facade\Session;

/**
 * 商品批量操作 编辑商品
 * Class GoodsBatchOperation
 * @package app\master\controller
 */
class GoodsBatchOperation extends Controller
{
    /**
     * 自营商品批量操作 选择商品列表
     * @param Request $request
     * @param GoodsModel $goods
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @return mixed
     * @throws Exception
     */
    public function index(Request $request, GoodsModel $goods, GoodsClassify $goodsClassify, Brand $brand)
    {
        try {
            $param = $request::param();
            $map = [];
            if (array_key_exists('cateId', $param) && $param['cateId'])
                $map['goods_classify_id'] = $param['cateId'];
            if (array_key_exists('brandId', $param) && $param['brandId'])
                $map['brand_id'] = $param['brandId'];
            if (array_key_exists('keyword', $param) && $param['keyword']) {
                $mapa[] = ['goods_name', 'like', '%' . $param['keyword'] . '%'];
            } else {
                $mapa = [];
            }

            if (array_key_exists('search', $param) && $param['search']) {
                //查找商品
                $data = $goods->where($map)->where($mapa)->where(['is_group' => 0, 'is_bargain' => 0, 'is_limit' => 0])->order(['sort' => 'asc'])->field('goods_id,goods_name')->select();
            } else {
                $data = [];
            }
            //查询商品一级分类
            $categoryOne = $goodsClassify
                ->where([
                    ['parent_id', '=', 0],
                    ['status', '=', 1]
                ])
                ->field('goods_classify_id,parent_id,title,count')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])
                ->select();
            //查询全部品牌
            $brandData = $brand
                ->where([['brand_id', '>', 0]])
                ->field('brand_id,brand_name,brand_first_char')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])
                ->select();
            //查询全部品牌首字母
            $brandFirstChr = $brand
                ->where([['brand_id', '>', 0]])
                ->distinct(true)
                ->order(['brand_first_char' => 'asc'])
                ->column('brand_first_char');
            $search_key = self::editShow($param, $brand, $goodsClassify);
            return $this->fetch('', [
                'categoryOne' => $categoryOne,
                'brandFirstChr' => $brandFirstChr,
                'brand' => $brandData,
                'data' => $data,
                'search_key' => $search_key ?: []
            ]);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /*
         * 获取页面搜索关键字得展示信息
         * */
    public function editShow($param, Brand $brand, GoodsClassify $classify)
    {
        $data = [];
        if (array_key_exists('cateId', $param) && $param['cateId']) {
            $cateArr = getParCate($param['cateId'], $classify);
            $data['cateTitle'] = implode(' / ', array_column($cateArr, 'title'));
            $data['cateId'] = $param['cateId'];
        }
        if (array_key_exists('brandId', $param) && $param['brandId']) {
            $data['brandTitle'] = $brand->where('brand_id', $param['brandId'])->value('brand_name');
            $data['brandId'] = $param['brandId'];
        }
        if (array_key_exists('keyword', $param) && $param['keyword']) {
            $data['keyword'] = $param['keyword'];
        }
        return $data;
    }

    /**
     * 左边商品批量选择 展示在右边
     * @param Request $request
     * @param GoodsModel $goods
     * @return mixed
     * @throws \Exception
     */
    public function get_goods(Request $request, GoodsModel $goods)
    {
        try {
            $param_id = $request::param('id', '');
            $data = $goods->where('goods_id', 'in', $param_id)->field('goods_id,goods_name')->select();
            return $data;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 批量选择商品后，展示的列表
     * @param Request $request
     * @param GoodsModel $goods
     * @param Brand $brand
     * @return array|mixed
     * @throws \Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_list(Request $request, GoodsModel $goods, Brand $brand)
    {
        $param = $request::param();
        if ($request::isPost()) {
            $where = [];
            if ($param['type'] == 1) {
                for ($i = 0; $i < count($param['goods_id']); $i++) {
                    $where[] = [
                        'goods_id' => $param['goods_id'][$i],
                        'brand_id' => $param['brand_id'][$i],
                        'warn_number' => $param['warn_number'][$i],
                        'goods_number' => $param['goods_number'][$i],
                        'shop_price' => $param['shop_price'][$i],
                        'market_price' => $param['market_price'][$i]
                    ];
                }
                // 写入
                $data = $goods->allowField(true)->isUpdate(true)->saveAll($where);
            } else {
                $where = [
                    "market_price" => $param['market_price'],
                    "shop_price" => $param['shop_price'],
                    "goods_number" => $param['goods_number'],
                    "warn_number" => $param['warn_number'],
                    "brand_id" => $param['brand_id'],
                ];

                $data = $goods->where('goods_sn', 'in', $param['id'])->update($where);
            }
            if ($data) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods_batch_operation/index'];
        } else {
            if ($param['status'] == 1) {
                //根据商品ID
                $w = 'goods_id';
            } else {
                //根据货号
                $w = 'goods_sn';
            }
            $brandData = $brand->field('brand_id,brand_name')->select();
            $data = $goods->where($w, 'in', $param['id'])->where(['is_group' => 0, 'is_bargain' => 0, 'is_limit' => 0])->field('goods_sn,brand_id,market_price,shop_price,goods_number,warn_number,goods_id,goods_name')->select();
            return $this->fetch('', [
                'type' => $param['type'],
                'data' => $data,
                'brandData' => $brandData,
                'data' => $data
            ]);
        }
    }

}