<?php
// 店铺动态
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\ArticleAttach;
use app\common\model\ArticleClassify;
use app\common\model\Brand;
use app\common\model\Goods;
use app\common\model\GoodsClassify;
use app\common\model\Store;
use think\Controller;
use think\facade\Request;
use app\common\model\Article as ArticleModel;
use app\common\model\StoreArticleAttach as StoreArticleAttachModel;

class StoreArticle extends Controller
{
    /**
     * 店铺动态列表
     * @param Request $request
     * @param ArticleModel $article
     * @return array|mixed
     */
    public function index(Request $request, ArticleModel $article)
    {

        try {
            // 获取数据
            $param = $request::get();

            // 筛选条件
            $condition[] = ['article_id', '>', 0];
            $condition[] = ['store_id', '=', $param['store_id']];

            // 获取数据
            $data = $article->where($condition)
                            ->field('article_id,store_id,title,create_time,hits')
                            ->order('create_time', 'desc')
                            ->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data'     => $data,
            'store_id' => $param['store_id']
        ]);

    }

    /**
     * 创建店铺动态旧
     * @param Request $request
     * @param ArticleModel $article
     * @param StoreArticleAttachModel $storeArticleAttach
     * @return array|mixed
     */
    public function createOld(Request $request, ArticleModel $article, StoreArticleAttachModel $storeArticleAttach)
    {
        if ($request::isPost()) {

            try {
                // 获取数据
                $param = $request::post();

                // 验证
                $check = $article->valid($param, 'create');
                if ($check['code']) return $check;

                // 写入
                $operation = $article->allowField(true)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/store_article/index?store_id=' . $param['store_id']];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }

        return $this->fetch('', [
            'store_id' => $request::param('store_id'),
        ]);
    }

    /**
     * 创建店铺动态
     * @param Request $request
     * @param ArticleClassify $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param Store $store
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
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

                if (!isset($param['file']) || empty($param['file'])){
                    return ['code' => -100, 'message' => '请上传店铺动态图片'];
                }

                if (!empty($param['picArr'])) {
                    $param['multiple_file'] = join(',', $param['picArr']);
                }else{
                    $param['multiple_file'] = '';
                }

                $state = $articleModel->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => "/store_article/index?store_id={$param['store_id']}"];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $_storeId = $this->request->get('store_id');

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
            'categoryOne'   => $categoryOne,
            'brand'         => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'storeList'     => $storeList,
            'store_id'      => $_storeId
        ]);
    }

    /**
     * 店铺动态编辑
     * @param Request $request
     * @param ArticleClassify $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param ArticleAttach $articleAttach
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param Store $store
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, ArticleClassify $articleClassifyModel, ArticleModel $articleModel, ArticleAttach $articleAttach, GoodsClassify $goodsClassify, Brand $brand, Store $store)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleModel->valid($param, 'edit');
                if ($check['code']) return $check;

                if (!isset($param['file']) || empty($param['file'])){
                    return ['code' => -1, 'message' => '请上传店铺动态图片'];
                }

                if (!empty($param['picArr'])) {
                    $param['multiple_file'] = join(',', $param['picArr']);
                }else{
                    $param['multiple_file'] = '';
                }

                $state = $articleModel->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => "/store_article/index?store_id={$param['store_id']}"];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $_param = $request::param();

        $_storeId = $_param['store_id'];


        $_data = $articleModel::where([
                                          'article_id' => $_param['article_id']
                                      ])->append(['multiple_file_raw'])->with('attachCor.goodsBlt')->find()->toArray();

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
            'categoryOne'   => $categoryOne,
            'brand'         => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'storeList'     => $storeList,
            'store_id'      => $_storeId,
            'article_id'    => $_param['article_id'],
            'item'          => $_data,
        ]);
    }

    /**
     * 店铺动态删除
     * @param Request $request
     * @param ArticleModel $article
     * @return array
     */
    public function destroy(Request $request, ArticleModel $article)
    {
        if ($request::isPost()) {
            try {
                // 删除
                $article::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 获取店铺下的商品
     * @param Request $request
     * @param GoodsModel $goods
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @return array
     */
    public function selectGoods(Request $request, Goods $goods, GoodsClassify $goodsClassify, Brand $brand)
    {
        try {
            $param = $request::param();
            $map = [];

            if (!isset($param['store_id'])) {
                return ['code' => -100, 'message' => '店铺ID不可为空'];
            }
            // 规定商品的店铺
            $map['store_id'] = $param['store_id'];
            // 规定商品是上架的商品
            $map['is_putaway'] = 1;

            if (array_key_exists('cateId', $param) && $param['cateId'])
                $map['goods_classify_id'] = $param['cateId'];
            if (array_key_exists('brandId', $param) && $param['brandId'])
                $map['brand_id'] = $param['brandId'];
            if (array_key_exists('keyword', $param) && $param['keyword'])
                $map['goods_name|spell_keyword|describe'] = ['like', '%' . $param['keyword'] . '%'];
            if (array_key_exists('search', $param) && $param['search']) {
                //查找商品
                $data = $goods->where($map)->order(['sort' => 'asc'])->field('goods_id,goods_name')->select();
            } else {
                $data = [];
            }

            $tr = '';
            foreach ($data as $key => $value) {
                $tr .= '<li style="padding-left: 20px;">' .
                       '<input type="checkbox"  class="chk" lay-filter="chk" title="' . $value['goods_name'] . '" value="' . $value['goods_id'] . '" lay-skin="primary">' .
                       '<div class="layui-unselect layui-form-checkbox" lay-skin="primary"><span>' . $value['goods_name'] . '</span><i class="layui-icon layui-icon-ok"></i></div>' .
                       '</li>';
            };

            $info = '<ul>' .
                    $tr .
                    '</ul>';

            return ['code' => 200, 'data' => $info];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }
}