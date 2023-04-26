<?php


namespace app\master\controller;


use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use app\master\model\Tag as TagModel;
use app\master\model\TagBindGoods as TagBindGoodsModel;
use app\master\model\TagClassify as TagClassifyModel;
use app\master\validate\Tag as TagValid;
use function foo\func;
use think\Controller;
use think\Db;

class Tag extends Controller
{
    public function index()
    {
        // GET 参数
        $_GET = $this->request->get();
        // 查询条件
        $_where = [];
        // 标签分类条件
        $_tagClassifyWhere = [];

        // 标签名称
        if (isset($_GET['name'])) {
            $_where[] = [
                'name', 'like', "%{$_GET['name']}%",
            ];
        }

        // 商品分类ID
        if (isset($_GET['tag_classify_id']) && !empty($_GET['tag_classify_id'])) {

            $_tagClassifyIdList = explode('-', $_GET['tag_classify_id']);

            switch ($_tagClassifyIdList[0]) {
                case 'c':
                    $_where[] = [
                        'tag_classify_id', '=', $_tagClassifyIdList[1]
                    ];
                    break;
                case 't':
                    $_where[] = [
                        'tag_id', '=', $_tagClassifyIdList[1]
                    ];
                    break;
            }
        }

        $_tagList = TagModel::where($_where)
            ->paginate(10, NULL, [
                'query' => $_GET,
            ]);

        // 标签分类列表
        $_tagClassifyList = TagClassifyModel::withTrashed()->with('tagWSD')->select();

        $this->assign('tagClassifyList', $_tagClassifyList);
        $this->assign('tagList', $_tagList);

        return $this->fetch();
    }

    /**
     * 创建标签
     * @return array|mixed
     */
    public function create()
    {
        // 提交
        if ($this->request->isPost()) {
            $_valid = new TagValid;

            $_POST = $this->request->post();

            if (!$_valid->scene('create')->check($_POST)) {
                return [
                    'code' => -1000,
                    'message' => $_valid->getError(),
                ];
            }

            Db::startTrans();
            try {
                // 判断标签分类是否存在
                if (!TagClassifyModel::get($_POST['tag_classify_id'])) {
                    return [
                        'code' => -1000,
                        'message' => '标签分类不存在',
                    ];
                }
                // 创建标签,并获取结果
                $_result = TagModel::create([
                    'tag_classify_id' => $_POST['tag_classify_id'],
                    'name' => $_POST['name'],
                    'content' => $_POST['content'],
                ]);

                // 已输入商品ID
                if (isset($_POST['goods']) && !empty($_POST['goods'])) {
                    // 将 1,2,3 格式的字符串 转为数组
                    $_goodsIdArr = explode(',', $_POST['goods']);

                    $_createData = [];
                    // 多店
                    if (config('user.')['one_more'] == 1) {

                        // 获取商品信息数组 商品ID 店铺ID
                        $_goodsList = GoodsModel::where([
                            ['goods_id', 'in', $_POST['goods']]
                        ])->field([
                            'goods_id',
                            'store_id'
                        ])->select();

                        // 构建 保存的数组
                        foreach ($_goodsList as $_goods) {
                            $_createData[] = [
                                'goods_id' => $_goods['goods_id'],
                                'tag_id' => $_result['tag_id'],
                                'store_id' => $_goods['store_id']
                            ];
                        }
                    } else {
                        // 单店
                        $_storeId = config('user.')['one_store_id'];
                        foreach ($_goodsIdArr as $_goodsId) {
                            $_createData[] = [
                                'goods_id' => $_goodsId,
                                'tag_id' => $_result['tag_id'],
                                'store_id' => $_storeId,
                            ];
                        }
                    }

                    (new TagBindGoodsModel)->saveAll($_createData);
                }

                Db::commit();
                return [
                    'code' => 0,
                    'message' => config('message.')[0],
                    'url' => '/tag/index'
                ];
            } catch (\Exception $e) {
                Db::rollback();
                return [
                    'code' => -1000,
                    'message' => config('message.')[-1],
                ];
            }
        }

        try {
            $_tagClassifyList = TagClassifyModel::all();
            $this->assign('tagClassifyList', $_tagClassifyList);
        } catch (\Exception $e) {
            $this->error(config('message.')[-1]);
        }


        return $this->fetch();
    }

    /**
     * 编辑标签
     *
     * @return array|mixed|void
     * @throws \think\Exception\DbException
     */
    public function edit()
    {
        $_valid = new TagValid;

        if ($this->request->isPost()) {
            $_POST = $this->request->post();

            if (!$_valid->scene('edit_post')->check($_POST)) {
                return [
                    'code' => -1000,
                    'message' => $_valid->getError()
                ];
            }

            $_tag = TagModel::get($_POST['id']);

            if (!$_tag) {
                return [
                    'code' => -1000,
                    'message' => '不存在的标签'
                ];
            }

            try {
                Db::startTrans();
                // 先保存基本信息
                $_tag->save([
                    'tag_classify_id' => $_POST['tag_classify_id'],
                    'name' => $_POST['name'],
                    'content' => $_POST['content'],
                ]);

                // 已输入商品ID
                if (isset($_POST['goods'])) {
                    // 创建列表
                    $_createData = [];
                    // 删除列表
                    $_deleteData = [];
                    // 删除时间
                    $_dateTime = date('Y-m-d H:i:s');

                    // 获取旧的关联信息信息
                    $_tagBindGoodsList = TagBindGoodsModel::where([
                        ['tag_id', '=', $_POST['id']]
                    ])->select();

                    // 解析新的商品信息
                    $_goodsIdListArr = explode(',', $_POST['goods']);

                    // 构建 创建信息 与 删除信息
                    foreach ($_tagBindGoodsList as $_tagBindGoods) {
                        // 判断之前的是否还在 新的列表中
                        if (!in_array($_tagBindGoods['goods_id'], $_goodsIdListArr)) {
                            // 如果不在 说明 已经被删除
                            $_deleteData[] = [
                                'tag_bind_goods_id' => $_tagBindGoods['tag_bind_goods_id'],
                                'delete_time' => $_dateTime
                            ];
                        }else{
                            // 卸载 新的列表中的元素
                            unset($_goodsIdListArr[array_search($_tagBindGoods['goods_id'], $_goodsIdListArr)]);
                        }
                    }

                    $_tagBindGoodsModel = new TagBindGoodsModel();

                    if (!empty($_goodsIdListArr)){
                        // 获取商品信息数组 商品ID 店铺ID
                        $_goodsList = GoodsModel::where([
                            ['goods_id', 'in', join(',', $_goodsIdListArr)]
                        ])->field([
                            'goods_id',
                            'store_id'
                        ])->select();

                        // 循环剩下的就是 新建的
                        foreach ($_goodsList as $_goods) {
                            $_createData[] = [
                                'goods_id' => $_goods['goods_id'],
                                'tag_id' => $_POST['id'],
                                'store_id' => $_goods['store_id']
                            ];
                        }

                        // 新建
                        $_tagBindGoodsModel->isUpdate(false)->saveAll($_createData);
                    }

                    if (!empty($_deleteData)){
                        // 删除
                        $_tagBindGoodsModel->isUpdate(true)->saveAll($_deleteData);
                    }
                }

                Db::commit();
                return [
                    'code' => 0,
                    'message' => config('message.')[0],
                    'url' => '/tag/index'
                ];
            } catch (\Exception $e) {
                Db::rollback();
                return [
                    'code' => -1000,
                    'message' => config('message.')[-1],
                ];
            }

        }


        $_GET = $this->request->get();

        // 验证参数
        if (!$_valid->scene('edit_get')->check($_GET)) {
            $this->error($_valid->getError());
            return;
        }

        try {
            $_tag = TagModel::with('tagBindGoods')->where([
                ['tag_id', '=', $_GET['id']]
            ])->find();

            if (!$_tag) {
                $this->error('不存在的标签');
            }
            $_tagClassifyList = TagClassifyModel::all();

            $_tagBindGoods = [];
            if (!empty($_tag['tagBindGoods'])) {
                foreach ($_tag['tagBindGoods'] as $_goods) {
                    $_tagBindGoods[] = $_goods['goods_id'];
                }
            }

            $this->assign('tag', $_tag);
            $this->assign('goods', join(',', $_tagBindGoods));
            $this->assign('tagClassifyList', $_tagClassifyList);
        } catch (\Exception $e) {
            $this->error(config('message.')[-1]);
        }


        return $this->fetch('create');
    }

    /**
     * 删除标签
     *
     * @return array
     */
    public function delete()
    {
        $_valid = new TagValid;

        $_POST = $this->request->post();

        if (!$_valid->scene('delete')->check($_POST)) {
            return [
                'code' => -1000,
                'message' => $_valid->getError()
            ];
        }

        Db::startTrans();
        try {
            TagModel::destroy($_POST['id']);

//            TagBindGoodsModel::where([
//                ['tag_id', '=', $_POST1['id']]
//            ])->delete();

            TagBindGoodsModel::destroy(function ($query) {
                $query->where([
                    ['tag_id', '=', $_POST['id']]
                ]);
            });

            Db::commit();

            return [
                'code' => 0,
                'message' => config('message.')[0]
            ];
        } catch (\Exception $e) {
            Db::rollback();
            return [
                'code' => -1000,
                'message' => config('message.')[-1]
            ];
        }
    }

    /**
     * 选择商品
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function choose_goods()
    {
        // 获取 get 请求
        $_GET = $this->request->get();
        // 设置sql参数
        $_where = [
            ['is_putaway', '=', '1'],
        ];

        // 单店
        if (config('user.')['one_more'] == 0) {
            $_where[] = [
                'store_id', '=', config('user.')['one_store_id']
            ];
        }


        // 如果设置了 商品分类
        if (isset($_GET['goods_classify_id']) && !empty($_GET['goods_classify_id'])) {
            $_where[] = [
                'goods_classify_id', '=', $_GET['goods_classify_id'],
            ];
        }

        // 如果设置了 商品名称
        if (isset($_GET['goods_name']) && !empty($_GET['goods_name'])) {
            $_where[] = [
                'goods_name', 'like', "%{$_GET['goods_name']}%",
            ];
        }

        // 如果 设置了 已选择的商品
        if (isset($_GET['choose_goods_id'])) {
            $_where[] = [
                'goods_id', (($_GET['choose_type'] ?? 1) == 1) ? 'not in' : 'in', $_GET['choose_goods_id'],
            ];
        }

        // 商品分类列表
        $_goodsClassifyListSourceData = GoodsClassifyModel::where([
            ['status', '=', '1'],
        ])->select();

        // 商品分类数据
        $_goodsClassifyList = [];

        // 构建数组下标为分类ID
        foreach ($_goodsClassifyListSourceData as $value) {
            $_goodsClassifyList[$value['goods_classify_id']] = $value;
        }

        // 给页面显示的商品分类
        $_goodsClassifyListForPage = find_level(
            $_goodsClassifyListSourceData,
            'goods_classify_id'
        );

        // 商品
        $_goodsList = GoodsModel::where($_where)->select();
        // 商品数据
        $_goodsListData = [];

        if (!empty($_goodsList)) {
            foreach ($_goodsList as $key => $val) {

                $_goodsListData[$key]['classify_name'] = [];
                $_goodsListData[$key]['goods_id'] = $val['goods_id'];
                $_goodsListData[$key]['goods_name'] = $val['goods_name'];
                $_goodsListData[$key]['file'] = $val['file'];

                // 商品分类ID
                $_goodsClassifyId = $val['goods_classify_id'];
                // 如果商品分类ID存在 循环出商品三级分类
                while (isset($_goodsClassifyList[$_goodsClassifyId])) {
                    array_unshift($_goodsListData[$key]['classify_name'], $_goodsClassifyList[$_goodsClassifyId]['title']);
                    $_goodsClassifyId = $_goodsClassifyList[$_goodsClassifyId]['parent_id'];
                }
                // 拼接分类字符串
                if (empty($_goodsListData[$key]['classify_name'])) {
                    $_goodsListData[$key]['classify_name'] = '-';
                } else {
                    $_goodsListData[$key]['classify_name'] = join('/', $_goodsListData[$key]['classify_name']);
                }
            }
        }

        $this->assign('goodsList', $_goodsListData);
        $this->assign('goodsClassifyList', $_goodsClassifyListForPage);

        return $this->fetch();
    }
}