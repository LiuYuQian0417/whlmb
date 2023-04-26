<?php
declare(strict_types = 1);
namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\ArticleClassify as ArticleClassifyModel;

class ArticleClassify extends Controller
{

    /**
     * 文章分类列表
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @return \Exception|mixed
     */
    public function index(Request $request, ArticleClassifyModel $articleClassifyModel)
    {


        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition = [['parent_id', '=', 16]];


            $data = $articleClassifyModel
                ->where($condition)
                ->field('keyword,update_time', true)
                ->order(['sort' => 'asc','article_classify_id' => 'asc'])
                ->paginate(15, false, ['query' => $param]);

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data'          => $data
        ]);
    }

    /**
     * 文章分类新增
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, ArticleClassifyModel $articleClassifyModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleClassifyModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $articleClassifyModel->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article_classify/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('', [
            'classify_list' => $articleClassifyModel->where('parent_id',16)->field('article_classify_id,title,parent_id')->select()
        ]);
    }

    /**
     * 文章分类编辑
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, ArticleClassifyModel $articleClassifyModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $articleClassifyModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $articleClassifyModel->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/article_classify/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('create', [
            'classify_list' => $articleClassifyModel->where('article_classify_id', 16)->field('article_classify_id,title,parent_id')->select(),
            'item'          => $articleClassifyModel->get($request::get('article_classify_id'))
        ]);
    }

    /**
     * 删除文章分类
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @return array
     */
    public function destroy(Request $request, ArticleClassifyModel $articleClassifyModel)
    {
        if ($request::isPost()) {

            try {

                $param = $request::post('id');

                $article_classify_id = $articleClassifyModel->where('parent_id', $param)->value('article_classify_id');

                if (!empty($article_classify_id)) return ['code' => -100, 'message' => '该分类下还有子分类，请先删除其子分类'];

                $articleClassifyModel::destroy($param);

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 文章分类显示状态更新
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @return array
     */
    public function auditing(Request $request, ArticleClassifyModel $articleClassifyModel)
    {

        if ($request::isPost()) {
            try {
                $articleClassifyModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 文章分类排序更新
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @return array
     */
    public function text_update(Request $request, ArticleClassifyModel $articleClassifyModel)
    {

        if ($request::isPost()) {
            try {
                $articleClassifyModel->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}