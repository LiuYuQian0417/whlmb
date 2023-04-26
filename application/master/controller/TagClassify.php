<?php


namespace app\master\controller;


use app\master\model\TagClassify as TagClassifyModel;
use app\master\validate\TagClassify as TagClassifyValid;
use think\Controller;

class TagClassify extends Controller
{
    /**
     * 获取标签分类列表
     */
    public function index()
    {
        // GET 参数
        $_GET = $this->request->get();
        // 查询条件
        $_where = [];

        if (isset($_GET['name'])) {
            $_where[] = [
                'name', 'like', "%{$_GET['name']}%",
            ];
        }

        $_tagClassifyList = TagClassifyModel::where($_where)->paginate(10, NULL, [
            'query' => $_GET,
        ]);

        $this->assign('tagClassifyList', $_tagClassifyList);
        return $this->fetch();
    }

    /**
     * 创建标签分类
     */
    public function create()
    {
        if ($this->request->isPost()) {
            $_POST = $this->request->post();
            $_valid = new TagClassifyValid;

            if (!$_valid->scene('create')->check($_POST)) {
                return [
                    'code' => -100,
                    'message' => $_valid->getError(),
                ];
            }

            try {
                TagClassifyModel::create([
                    'name' => $_POST['name'],
                ]);
                return [
                    'code' => 0,
                    'message' => config('message.')[0],
                    'url' => 'tag_classify/index',
                ];
            } catch (\Exception $exception) {
                return [
                    'code' => -100,
                    'message' => config('message.')[-1],
                ];
            }

        }
        return $this->fetch();
    }

    /**
     * 编辑标签分类
     */
    public function edit()
    {
        $_valid = new TagClassifyValid;

        // 编辑操作
        if ($this->request->isPost()) {
            $_POST = $this->request->post();

            if (!$_valid->scene('edit_post')->check($_POST)) {
                return [
                    'code' => -1000,
                    'message' => $_valid->getError(),
                ];
            }

            $_tagClassify = TagClassifyModel::get($_POST['id']);

            if (!$_tagClassify) {
                return [
                    'code' => -1000,
                    'message' => '不存在的标签分类',
                ];
            }

            try {
                $_tagClassify->save([
                    'name' => $_POST['name']
                ]);
                return [
                    'code' => 0,
                    'message' => config('message.')[0],
                    'url' => '/tag_classify/index'
                ];
            } catch (\Exception $e) {
                return [
                    'code' => -1000,
                    'message' => config('message.')[-1],
                ];
            }
        }

        // 编辑页面

        $_GET = $this->request->get();

        if (!$_valid->scene('edit_get')->check($_GET)) {
            $this->error($_valid->getError());
        }

        try {
            $_tagClassify = TagClassifyModel::get($_GET['id']);

            if (!$_tagClassify) {
                $this->error('标签分类不存在');
            }

            $this->assign('tag_classify', $_tagClassify);
        } catch (\Exception $e) {
            $this->error(config('message.')[-1]);
        }

        return $this->fetch('create');
    }

    /**
     * 删除标签分类
     */
    public function delete()
    {
        $_POST = $this->request->post();

        $_valid = new TagClassifyValid;

        if (!$_valid->scene('delete')->check($_POST)) {
            return [
                'code' => -100,
                'message' => $_valid->getError(),
            ];
        }

        $_tagClassify = TagClassifyModel::with('tag_hm')->where([
            ['tag_classify_id', '=', $_POST['id']],
        ])->find();


        if (!$_tagClassify['tag_hm']->isEmpty()) {
            return [
                'code' => -1000,
                'message' => '标签分类下存在标签,无法删除',
            ];
        }

        try {
            TagClassifyModel::destroy([
                'tag_classify_id' => $_POST['id'],
            ]);
            return [
                'code' => 0,
                'message' => config('message.')[0],
            ];
        } catch (\Exception $e) {
            return [
                'code' => -1000,
                'message' => '删除失败,请稍后再试',
            ];
        }
    }
}