<?php

namespace app\client\controller;

use app\common\model\ArticleAttach;
use app\common\model\ArticleClassify;
use app\common\model\Store;
use think\Controller;
use think\facade\Request;
use think\facade\Session;
use app\common\model\Article as ArticleModel;
use app\common\model\GoodsClassify;
use app\common\model\Brand;
use app\common\model\Goods as GoodsModel;

class Article extends Controller
{
    /**
     * 店铺动态列表
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array|mixed
     */
    public function index(Request $request, ArticleModel $articleModel)
    {
        try {
            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['article_id', '>', 0];
            $condition[] = ['store_id', 'eq', Session::get('client_store_id')];
            $condition[] = ['article_classify_id', 'eq', 3];

            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];
            $data = $articleModel
                ->where($condition)
                ->order(['article_id' => 'desc'])
                ->paginate(15, false, ['query' => $param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }
        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    public function createOld(Request $request, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $articleModel->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where(['parent_id' => 0])
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

        return $this->fetch('', [
            'categoryOne' => $categoryOne,
            'brand' => $brandData,
            'brandFirstChr' => $brandFirstChr,
        ]);
    }

    public function create(Request $request, ArticleClassify $articleClassifyModel, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand, Store $store)
    {
        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::param();

                $param['article_classify_id'] = 3;
                // 验证
                $check = $articleModel->valid($param, 'create');
                if ($check['code']) return $check;

                if (!isset($param['file']) || empty($param['file'])) {
                    return ['code' => -100, 'message' => '请上传店铺动态图片'];
                }

                $param['store_id'] = Session::get('client_store_id');

                if (!empty($param['picArr'])) {
                    $param['multiple_file'] = join(',', $param['picArr']);
                } else {
                    $param['multiple_file'] = '';
                }

                $state = $articleModel->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => "/client/article/index"];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where(['parent_id' => 0])
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
        $storeList = $store
            ->where(['status' => 1, 'shop' => 0])
            ->field('store_id,store_name')
            ->select();
        return $this->fetch('', [
            'classify_list' => find_level($articleClassifyModel->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'categoryOne' => $categoryOne,
            'brand' => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'storeList' => $storeList,
            'pc_config'         => config('user.pc.is_include'),
        ]);

    }

    public function edit(Request $request, ArticleClassify $articleClassifyModel, ArticleModel $articleModel, ArticleAttach $articleAttach, GoodsClassify $goodsClassify, Brand $brand, Store $store)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();


                // 验证
                $check = $articleModel->valid($param, 'edit');
                if ($check['code']) return $check;

                if (!isset($param['file']) || empty($param['file'])) {
                    return ['code' => -100, 'message' => '请上传店铺动态图片'];
                }

                if (!empty($param['picArr'])) {
                    $param['multiple_file'] = join(',', $param['picArr']);
                } else {
                    $param['multiple_file'] = '';
                }

                $param['store_id'] = Session::get('client_store_id');

                $state = $articleModel->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => "/client/article/index"];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $_param = $request::param();

        $_data = $articleModel::where([
            'article_id' => $_param['article_id']
        ])->append(['multiple_file_raw'])->with('attachCor.goodsBlt')->find();
        $_data['file_data'] = $_data->getData('file');
//halt($_data);
        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where(['parent_id' => 0])
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
        $storeList = $store
            ->where(['status' => 1, 'shop' => 0])
            ->field('store_id,store_name')
            ->select();

        return $this->fetch('create', [
            'classify_list' => find_level($articleClassifyModel->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'categoryOne' => $categoryOne,
            'brand' => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'storeList' => $storeList,
            'article_id' => $_param['article_id'],
            'item' => $_data,
            'pc_config'         => config('user.pc.is_include'),
        ]);
    }

    public function selectGoods(Request $request, GoodsModel $goods)
    {
        try {
            $param = $request::post();
            $map = [];
            $map[] = ['is_putaway','=',1];
            if (array_key_exists('cateId', $param) && $param['cateId'])
                $map[]=['goods_classify_id','=',$param['cateId']];
            if (array_key_exists('brandId', $param) && $param['brandId'])
                $map[]=['brand_id','=',$param['brandId']];
            if (array_key_exists('keyword', $param) && $param['keyword'])
                $map[]=['goods_name|spell_keyword|describe','like', '%' . $param['keyword'] . '%'];
            if (array_key_exists('search', $param) && $param['search']) {
                //查找商品
                $data = $goods->where('store_id', 'eq', Session::get('client_store_id'))->where($map)->order(['sort' => 'asc'])->field('goods_id,goods_name');
            } else {
                $data = [];
            }

            $tr = '';
            if (array_key_exists('batch', $param)) {
                if ($data) {
                    $data = $data->where([['is_group&is_bargain&is_limit','=',0]])->select();
                }
                foreach ($data as $key => $value) {
                    $tr .= '<li>' .
                        '<input type="checkbox" name="checkList" value="' . $value['goods_name'] . '" title="' . $value['goods_name'] . '" lay-skin="primary" class="chk" lay-filter="chk" data-id="' . $value['goods_id'] . '" data-name="' . $value['goods_name'] . '"> ' .
                        '<div class="layui-unselect layui-form-checkbox" lay-skin="primary"><span>' . $value['goods_name'] . '</span><i class="layui-icon layui-icon-ok"></i></div>' .
                        '</li>';
                };
            } else {
                if ($data) {
                    $data = $data->select();
                }
                foreach ($data as $key => $value) {
                    $tr .= '<li>' .
                        '<input type="checkbox"  title="' . $value['goods_name'] . '" value="' . $value['goods_id'] . '" lay-skin="primary" class="chk" lay-filter="chk" >' .
                        '<div class="layui-unselect layui-form-checkbox" lay-skin="primary"><span>' . $value['goods_name'] . '</span><i class="layui-icon layui-icon-ok"></i></div>' .
                        '</li>';
                };
            }

            $info = '<ul>' .
                $tr .
                '</ul>';

            return ['code' => 200, 'data' => $info];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 右侧商品选择
     * @param Request $request
     * @param GoodsModel $goods
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \Exception
     */
    public function getGoods(Request $request, GoodsModel $goods)
    {
        try {
            $param = $request::param();
            $data = $goods->where('goods_id', 'in', $param['id'])->field('goods_id,goods_name')->select();
            return $data;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 删除
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array
     */
    public function destroy(Request $request, ArticleModel $articleModel)
    {
        if ($request::isPost()) {

            try {

                $articleModel::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }
}