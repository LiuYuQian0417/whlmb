<?php
declare(strict_types=1);

namespace app\master\controller;


use think\Controller;
use think\facade\Request;
use app\common\model\ArticleClassify as ArticleClassifyModel;
use app\common\model\Article as ArticleModel;
use app\common\model\GoodsClassify;
use app\common\model\Brand;
use app\common\model\Store;
use think\Exception;
use app\common\model\Goods as GoodsModel;
use app\common\model\ArticleAttach as ArticleAttachModel;

class Article extends Controller
{

    /**
     * 店铺动态列表
     * @param Request $request
     * @param Store $store
     * @param ArticleModel $articleModel
     * @return \Exception|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function store_index(Request $request, Store $store, ArticleModel $articleModel)
    {
        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['article_id', '>', 0];
            $condition[] = ['article_classify_id', 'eq', 3];

            // 父ID
            if (!empty($param['store_id'])) $condition[] = ['store_id', '=', $param['store_id']];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            $data = $articleModel
                ->where($condition)
                ->field('author,keyword,describe,file,content,update_time', TRUE)
                ->append(['store_name'])
                ->order(['article_id' => 'desc'])
                ->paginate(15, FALSE, ['query' => $param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data'       => $data,
            'store_list' => $store->where('status', 1)->field('store_id,store_name')->select(),
        ]);
    }

    /**
     * 店铺动态新增
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param Store $store
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function store_create(Request $request, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand, Store $store)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $articleModel->allowField(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/store_index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where([
                ['parent_id', '=', 0],
                ['status', '=', 1],
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
            ->distinct(TRUE)
            ->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');
        // 店铺列表
        $storeList = $store
            ->where(['status' => 1])
            ->field('store_id,store_name')
            ->select();
        return $this->fetch('', [
            'classify_list' => find_level($articleClassifyModel->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'categoryOne'   => $categoryOne,
            'brand'         => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'storeList'     => $storeList,
        ]);
    }

    /**
     * 店铺动态编辑
     * @param Request $request
     * @param Store $store
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param ArticleAttachModel $articleAttach
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function store_edit(Request $request, Store $store, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand, ArticleAttachModel $articleAttach)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $articleModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/store_index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where([
                ['parent_id', '=', 0],
                ['status', '=', 1],
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
        $storeList = $store
            ->where(['status' => 1])
            ->field('store_id,store_name')
            ->select();
        //查询全部品牌首字母
        $brandFirstChr = $brand
            ->where([['brand_id', '>', 0]])
            ->distinct(TRUE)
            ->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');

        return $this->fetch('store_create', [
            'classify_list' => find_level($articleClassifyModel->where('article_classify_id', 'neq', $request::get('article_classify_id'))->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'item'          => $articleModel->get($request::get('article_id')),
            'goods'         => $articleAttach->alias('a')->join('goods g', 'a.goods_id = g.goods_id')->field('g.goods_id,g.goods_name')->where(['a.article_id' => $request::get('article_id')])->select(),
            'categoryOne'   => $categoryOne,
            'brand'         => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'article_id'    => $request::get('article_id'),
            'storeList'     => $storeList,
        ]);
    }

    /**
     * 删除店铺动态
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array
     */
    public function store_destroy(Request $request, ArticleModel $articleModel)
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

    /**
     * 帮助中心文章列表
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return \Exception|mixed
     */
    public function help_index(Request $request, ArticleModel $articleModel)
    {
        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['article_id', '>', 0];
            $condition[] = ['article_classify_id', '>', 16];

            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            $data = $articleModel
                ->where($condition)
                ->field('author,keyword,describe,file,content,update_time', TRUE)
                ->order(['article_id' => 'desc'])
                ->paginate(15, FALSE, ['query' => $param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 协议说明
     * @param Request $request
     * @param ArticleModel $article
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function agreement(ArticleModel $article)
    {
        // 单店
        $_where = [];
        if (config('user.one_more') != 1) {
            $_where[] = [
                'article_id',
                '<>',
                34,
            ];
        }
        $data = $article
            ->where('article_id', 'in', '17,34')
            ->where($_where)
            ->field('author,keyword,describe,file,content,update_time', TRUE)
            ->order(['article_id' => 'asc'])
            ->select();
        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 协议说明编辑
     * @param Request $request
     * @param ArticleModel $article
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function agreement_edit(Request $request, ArticleModel $article)
    {
        if ($request::isPost()) {
            try {

                // 获取参数
                $param = $request::post();

                $article->allowField(TRUE)->isUpdate(TRUE)->save($param);

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/agreement'];
            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('', [
            'item'       => $article->where('article_id', $request::get('article_id'))->find(),
            'article_id' => $request::get('article_id'),
        ]);
    }

    /**
     * 帮助中心文章新增
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function help_create(Request $request, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleModel->valid($param, 'help_create');
                if ($check['code']) return $check;

                $state = $articleModel->allowField(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/help_index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('', [
            'classify_list' => $articleClassifyModel->where('parent_id', 16)->field('article_classify_id,title,parent_id')->select(),
        ]);
    }

    /**
     * 帮助中心文章编辑
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function help_edit(Request $request, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleModel->valid($param, 'help_edit');
                if ($check['code']) return $check;

                $state = $articleModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/help_index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('help_create', [
            'classify_list' => $articleClassifyModel->where('parent_id', 'eq', 16)->field('article_classify_id,title,parent_id')->select(),
            'item'          => $articleModel->get($request::get('article_id')),
            'article_id'    => $request::get('article_id'),
        ]);
    }

    /**
     * 删除帮助中心
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array
     */
    public function help_destroy(Request $request, ArticleModel $articleModel)
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

    /**
     * 文章列表
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @return \Exception|mixed
     */
    public function index(Request $request, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel)
    {
        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['article_id', '>', 0];
            $condition[] = ['article_classify_id', 'eq', 2];

            // 父ID
            if (!empty($param['classify_id'])) $condition[] = ['article_classify_id', '=', $param['classify_id']];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            $data = $articleModel
                ->where($condition)
                ->field('author,keyword,describe,file,content,update_time', TRUE)
                ->order(['article_id' => 'desc'])
                ->paginate(15, FALSE, ['query' => $param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 文章新增
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param Store $store
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand, Store $store)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $articleModel->allowField(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where([
                ['parent_id', '=', 0],
                ['status', '=', 1],
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
            ->distinct(TRUE)
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
            'pc_config'     => config('user.pc')['is_include'],
        ]);
    }

    /**
     * 文章分类编辑
     * @param Request $request
     * @param Store $store
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param ArticleAttachModel $articleAttach
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, Store $store, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand, ArticleAttachModel $articleAttach)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleModel->valid($param, ($param['article_classify_id'] == 14) ? 'system_edit' : 'edit');
                if ($check['code']) return $check;

                $state = $articleModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($param['article_classify_id'] == 14) {
                    if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/rule'];
                } else {
                    if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/index'];
                }

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where([
                ['parent_id', '=', 0],
                ['status', '=', 1],
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
        $storeList = $store
            ->where(['status' => 1, 'shop' => 0])
            ->field('store_id,store_name')
            ->select();
        //查询全部品牌首字母
        $brandFirstChr = $brand
            ->where([['brand_id', '>', 0]])
            ->distinct(TRUE)
            ->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');

        $data = $articleModel->get($request::get('article_id'));
        $data['file_data'] = $data->getData('file');
        return $this->fetch('create', [
            'classify_list' => find_level($articleClassifyModel->where('article_classify_id', 'neq', $request::get('article_classify_id'))->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'item'          => $data,
            'goods'         => $articleAttach->alias('a')->join('goods g', 'a.goods_id = g.goods_id')->field('g.goods_id,g.goods_name')->where(['a.article_id' => $request::get('article_id')])->select(),
            'categoryOne'   => $categoryOne,
            'brand'         => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'article_id'    => $request::get('article_id'),
            'storeList'     => $storeList,
            'pc_config'     => config('user.pc')['is_include'],
        ]);
    }

    /**
     * 选择商品
     * @param Request $request
     * @param GoodsModel $goods
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @return string
     * @throws Exception
     */
    public function selectGoods(Request $request, GoodsModel $goods, GoodsClassify $goodsClassify, Brand $brand)
    {
        try {
            $param = $request::param();
            $map[] = ['is_putaway','=','1'];

            if (config('user.one_more') != 1) {
                $map[] = ['store_id','=',config('user.one_store_id')];
            }

            if (array_key_exists('cateId', $param) && $param['cateId'])
                $map[] = ['goods_classify_id','=',$param['cateId']];
            if (array_key_exists('brandId', $param) && $param['brandId'])
                $map[] = ['brand_id','=',$param['brandId']];
            if (array_key_exists('keyword', $param) && $param['keyword'])
                $map[] = ['goods_name|spell_keyword|describe','like', '%' . $param['keyword'] . '%'];
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

            return $info;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
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
     * 删除文章分类
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

    /**
     * 文章分类显示状态更新
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array
     */
    public function auditing(Request $request, ArticleModel $articleModel)
    {

        if ($request::isPost()) {
            try {
                $articleModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 文章分类排序更新
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array
     */
    public function text_update(Request $request, ArticleModel $articleModel)
    {

        if ($request::isPost()) {
            try {
                $articleModel->clickEdit($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 系统说明
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array|mixed
     */
    public function rule(Request $request, ArticleModel $articleModel)
    {
        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition = [['article_classify_id', 'eq', 14], ['status', '=', '1']];
            $condition[] = ['article_id', 'not in', '17,34'];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            // 单店
            if (config('user.one_more') != 1) {
                $condition[] = [
                    'article_id',
                    '<>',
                    35,
                ];
            }

            $data = $articleModel
                ->where($condition)
                ->field('author,keyword,describe,file,content,update_time', TRUE)
                ->order(['article_id' => 'desc'])
                ->paginate(10, FALSE, ['query' => $param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 文章分类编辑
     * @param Request $request
     * @param Store $store
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param ArticleAttachModel $articleAttach
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rule_edit(Request $request, Store $store, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand, ArticleAttachModel $articleAttach)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                $param['article_classify_id'] = 14;

                // 验证
                $check = $articleModel->valid($param, 'system_edit');
                if ($check['code']) return $check;

                $state = $articleModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article/rule'];

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
        $storeList = $store
            ->where(['status' => 1, 'shop' => 0])
            ->field('store_id,store_name')
            ->select();
        //查询全部品牌首字母
        $brandFirstChr = $brand
            ->where([['brand_id', '>', 0]])
            ->distinct(TRUE)
            ->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');

        return $this->fetch('create', [
            'classify_list' => find_level($articleClassifyModel->where('article_classify_id', 'neq', $request::get('article_classify_id'))->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'item'          => $articleModel->get($request::get('article_id')),
            'goods'         => $articleAttach->alias('a')->join('goods g', 'a.goods_id = g.goods_id')->field('g.goods_id,g.goods_name')->where(['a.article_id' => $request::get('article_id')])->select(),
            'categoryOne'   => $categoryOne,
            'brand'         => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'article_id'    => $request::get('article_id'),
            'storeList'     => $storeList,
            'pc_config'     => config('user.pc.is_include'),
        ]);
    }
}