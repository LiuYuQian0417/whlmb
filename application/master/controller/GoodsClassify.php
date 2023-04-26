<?php
declare(strict_types=1);

namespace app\master\controller;

use app\master\validate\GoodsClassify as GoodsClassifyValid;
use think\Controller;
use think\Db;
use think\exception\ValidateException;
use think\facade\Request;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use app\common\model\Goods;
use app\common\model\Adv;
use app\common\model\AdvPosition;

class GoodsClassify extends Controller
{

    /**
     * 商品分类列表
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @return \Exception|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, GoodsClassifyModel $GoodsClassifyModel)
    {
//        try {
        // 获取参数
        $param = $request::get();

        // 如果未设置 分类ID 或分类ID 是第一级
        if (!isset($param['classify_id']) || $param['classify_id'] == 0) {
            $condition = [['parent_id', '=', 0]];
        } else {
            $condition = [['parent_id', '=', $param['classify_id']]];
        }

        // 分类层级
        if (!isset($param['level'])) {
            $_level = 1;
            $condition = [['parent_id', '=', 0]];
        } else {
            $_level = $param['level'];
        }

        // 父ID
        $data = $GoodsClassifyModel
            ->where($condition)
            ->field('keyword,update_time', true)
            ->order(['goods_classify_id' => 'desc'])
            ->paginate(15, false, ['query' => $param]);

        // 上级分类名称
        if ($_level != 1) {
            $_parentClassify = $GoodsClassifyModel->where([
                ['goods_classify_id', '=', $param['classify_id']],
            ])->find();
        }

//        } catch (\Exception $e) {
//            return ['code' => -100, 'message' => $e->getMessage()];
//        }

        return $this->fetch('', [
            'data' => $data,
            'classify_list' => find_level($GoodsClassifyModel->field('goods_classify_id,title,parent_id')->select()->toArray(), 'goods_classify_id'),
            'level' => $_level,
            'parent_classify' => $_parentClassify ?? [],
        ]);
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
    public function create(Request $request, GoodsClassifyModel $GoodsClassifyModel, Adv $adv)
    {

        if ($request::isPost()) {

            Db::startTrans();
            try {
                // 获取参数
                $param = $request::post();

                $_allowedParam = [
                    'adv_position_id',
                    'title',
                    'file',
                    'content',
                    'status',
                    'sort',
                    'start_time',
                    'end_time',
                    'client',
                    'type',
                ];

                foreach ($_allowedParam as $value) {
                    if ($param['a']['start_time'] == '') $param['a']['start_time'] = $param['a']['end_time'] = NULL;
                    if ($param['b']['start_time'] == '') $param['b']['start_time'] = $param['b']['end_time'] = NULL;
                    if ($param['c']['start_time'] == '') $param['c']['start_time'] = $param['c']['end_time'] = NULL;

//                    if (isset($param['a'][$value]) && !empty($param['a'][$value])) {
                        $adv_17[$value] = $param['a'][$value];
//                    }

//                    if (isset($param['b'][$value]) && !empty($param['b'][$value])) {
                        $adv_10[$value] = $param['b'][$value];
//                    }
//                    if (isset($param['c'][$value]) && !empty($param['c'][$value])) {
                        $adv_25[$value] = $param['c'][$value];
//                    }
                }

                // 移动端分类顶部展示图片
                if (!empty($adv_17) && !empty($adv_17['adv_position_id'])) {
                    $adv->valid($adv_17, 'create');
                    $param['classify_adv_id'] = $adv::create($adv_17)['adv_id'];
                }

                // 首页推荐
                if (!empty($adv_10) && !empty($adv_10['adv_position_id'])) {
                    $adv->valid($adv_10, 'create');
                    $param['adv_id'] = $adv::create($adv_10)['adv_id'];
                }

                // 电脑端首页推荐
                if (!empty($adv_25) && !empty($adv_25['adv_position_id'])) {
                    $adv->valid($adv_25, 'create');
                    $param['pc_adv_id'] = $adv::create($adv_25)['adv_id'];
                }


                $check = $GoodsClassifyModel->valid($param, 'create');
                if ($check['code']) return $check;
//                if (array_key_exists('date', $param) && $param['date']) {
//                    list($param['start_time'], $param['end_time']) = explode(' - ', $param['date']);
//                }
                $state = $GoodsClassifyModel->allowField(true)->save($param);

                // 提交事务
                Db::commit();
                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods_classify/index?classify_id=' . $param['parent_id']];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $pc = config('user.')['pc']['is_include']  ? 1 : 0; //判断是否含有pc
        return $this->fetch('', [
            'classify_list' => find_level($GoodsClassifyModel->where('count', '<>', 3)->field('goods_classify_id,title,parent_id')->select()->toArray(), 'goods_classify_id'),
            'pc' => $pc,
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
    public function edit(Request $request, GoodsClassifyModel $GoodsClassifyModel, Adv $adv)
    {

        if ($request::isPost()) {
            Db::startTrans();
            try {

                // 获取参数
                $param = $request::post();

                $_allowedParam = [
                    'adv_position_id',
                    'title',
                    'file',
                    'content',
                    'status',
                    'sort',
                    'start_time',
                    'end_time',
                    'client',
                    'type',
                    'adv_id'
                ];

                foreach ($_allowedParam as $value) {
                    if ($param['a']['start_time'] == '') $param['a']['start_time'] = $param['a']['end_time'] = NULL;
                    if ($param['b']['start_time'] == '') $param['b']['start_time'] = $param['b']['end_time'] = NULL;
                    if ($param['c']['start_time'] == '') $param['c']['start_time'] = $param['c']['end_time'] = NULL;

//                    if (isset($param['a'][$value]) && !empty($param['a'][$value])) {
                        $param['a']['file'] = $param['a']['file_data'];
                        $adv_17[$value] = $param['a'][$value];
//                    }

//                    if (isset($param['b'][$value]) && !empty($param['b'][$value])) {
                        $param['b']['file'] = $param['b']['file_data'];
                        $adv_10[$value] = $param['b'][$value];
//                    }
//                    if (isset($param['b'][$value]) && !empty($param['b'][$value])) {
                        $param['c']['file'] = $param['c']['file_data'];
                        $adv_25[$value] = $param['c'][$value];
//                    }
                }

                // 移动端分类顶部展示图片 分类页面右侧广告图 17
                if (!empty($adv_17) && isset($adv_17['adv_id']) && !empty($adv_17['adv_position_id']) && $adv_17['adv_id'] != 0) {
                    $adv->valid($adv_17, 'edit');
                    $adv::update($adv_17,[
                        'adv_id'=>$adv_17['adv_id']
                    ]);
                } elseif (!empty($adv_17) && $adv_17['adv_id'] == '' && $adv_17['adv_position_id'] != '') {
                    $adv->valid($adv_17, 'create');
                    $param['classify_adv_id'] = $adv::create($adv_17)['adv_id'];
                }

                // 首页推荐 首页推荐分类广告图  10
                if (!empty($adv_10) && isset($adv_10['adv_id']) && !empty($adv_10['adv_position_id']) && $adv_10['adv_id'] != 0) {
                    $adv->valid($adv_10, 'edit');
                    $adv::update($adv_10,[
                        'adv_id'=>$adv_10['adv_id']
                    ]);
                } elseif (!empty($adv_10) && $adv_10['adv_id'] == '' && $adv_10['adv_position_id'] != '') {
                    $adv->valid($adv_10, 'create');
                    $param['adv_id'] = $adv::create($adv_10)['adv_id'];
                }

                // 首页推荐 电脑端 首页推荐分类广告图  25
                if (!empty($adv_25) && isset($adv_25['adv_id']) && !empty($adv_25['adv_position_id']) && $adv_25['adv_id'] != 0) {
                    $adv->valid($adv_25, 'edit');
                    $adv::update($adv_25,[
                        'adv_id'=>$adv_25['adv_id']
                    ]);
                } elseif (!empty($adv_25) && $adv_25['adv_id'] == '' && $adv_25['adv_position_id'] != '') {
                    $adv->valid($adv_25, 'create');
                    $param['pc_adv_id'] = $adv::create($adv_25)['adv_id'];
                }

                $check = $GoodsClassifyModel->valid($param, 'edit');
                if ($check['code']) return $check;
                if (array_key_exists('date', $param) && $param['date']) {
                    list($param['start_time'], $param['end_time']) = explode(' - ', $param['date']);
                }
                $state = $GoodsClassifyModel->allowField(true)->isUpdate(true)->save($param);

                // 提交事务
                Db::commit();
                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods_classify/index?classify_id=' . $param['parent_id']];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        // 分类信息
        $item = $GoodsClassifyModel->get($request::get('goods_classify_id'), 'adv,classify_adv,pc_adv');
        $item['web_file_data'] = $item->getData('web_file');

        // 移动端分类顶部展示图片 17 classify_adv_id
        $advArr = $adv->where('adv_id', $item['classify_adv_id'])->find();
        if ($advArr){
            $advArr['file_data'] = $advArr->getData('file');
        }
        // 首页推荐分类广告图 10 adv_id
        $classifyAdvArr = $adv->where('adv_id', $item['adv_id'])->find();
        if ($classifyAdvArr){
            $classifyAdvArr['file_data'] = $classifyAdvArr->getData('file');
        }

        // 电脑端首页推荐分类广告图 25 pc_adv_id
        $pcAdvArr = $adv->where('adv_id', $item['pc_adv_id'])->find();
        if ($pcAdvArr){
            $pcAdvArr['file_data'] = $pcAdvArr->getData('file');
        }

        $pc = config('user.')['pc']['is_include']  ? 1 : 0; //判断是否含有pc

        return $this->fetch('create', [
            'classify_list' => find_level($GoodsClassifyModel->where('goods_classify_id', '<>', $request::get('goods_classify_id'))->field('goods_classify_id,title,parent_id')->select()->toArray(), 'goods_classify_id'),
            'adv' => $advArr,
            'classify_adv' => $classifyAdvArr,
            'pc_advArr' => $pcAdvArr,
            'item' => $item,
            'pc' => $pc,
        ]);
    }

    /**
     * 分类广告
     * @param Request $request
     * @param AdvPosition $advPosition
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function adv_create(Request $request, AdvPosition $advPosition, Adv $adv)
    {
        $param = $request::get();

        if ($request::isPost()) {

            try {
                // 获取参数
                $param = $request::post();

                if (!empty($param['date'])) {
                    list($param['start_time'], $param['end_time']) = explode(' - ', $param['date']);
                    if ($param['start_time'] == $param['end_time']) {
                        $param['end_time'] = date('Y-m-d', strtotime($param['end_time'] . '+1 days'));
                    }
                } else {
                    $param['start_time'] = NULL;
                    $param['end_time'] = NULL;
                }
                $adv->valid($param, 'create');

                return ['code' => 0, 'message' => config('message.')[0], 'data' => $param];

            } catch (ValidateException $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $condition = [];
        $item_1 = [];
        $item_2 = [];
        $item_3 = [];
        foreach ($param as $k => $v) {
            if (strstr($k, 'a_')) {
                $item_1 = array_merge($item_1, [str_replace('a_', '', $k) => $v]);
            }
            if (strstr($k, 'b_')) {
                $item_2 = array_merge($item_2, [str_replace('b_', '', $k) => $v]);
            }
            if (strstr($k, 'c_')) {
                $item_3 = array_merge($item_3, [str_replace('c_', '', $k) => $v]);
            }
        }

        $config = config('oss.')['prefix'];
        if($param['flag'] == 1){
            $condition[] = ['adv_position_id', 'eq', 17];
            $item = $item_1;
        }elseif ($param['flag'] == 2){
            $condition[] = ['adv_position_id', 'eq', 10];
            $item = $item_2;
        }else{
            $condition[] = ['adv_position_id', 'eq', 25];
            $item = $item_3;
        }

//        $param['flag'] == 1 ? $condition[] = ['adv_position_id', 'eq', 17] : $condition[] = ['adv_position_id', 'eq', 10];
//        $param['flag'] == 1 ? $item = $item_1 :  $item = $item_2;


        return $this->fetch('', [
            'adv_position' => $advPosition->field('adv_position_id,title,parent_id,width,height')->where($condition)->find(),
            'flag' => $param['flag'],
            'item' => $item,
            'oss_config' => $config,
            'one_more'             => config('user.one_more'),
        ]);
    }

    /**
     * 删除商品分类
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @return array
     */
    public function destroy(Request $request, GoodsClassifyModel $GoodsClassifyModel, Goods $goods)
    {
        if ($request::isPost()) {

            try {

                $param = $request::post('id');

                if (!empty($goods->where('goods_classify_id', $param)->find())) return ['code' => -100, 'message' => '该分类下存在商品，请先删除商品'];
                if (!empty($GoodsClassifyModel->where('parent_id', $param)->value('goods_classify_id'))) return ['code' => -100, 'message' => '该分类下还有子分类，请先删除其子分类'];

                $GoodsClassifyModel::destroy($param);

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 商品分类显示状态更新
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @return array
     */
    public function auditing(Request $request, GoodsClassifyModel $GoodsClassifyModel)
    {

        if ($request::isPost()) {
            try {
                $GoodsClassifyModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 商品分类排序更新
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @return array
     */
    public function text_update(Request $request, GoodsClassifyModel $GoodsClassifyModel)
    {

        if ($request::isPost()) {
            try {
                $GoodsClassifyModel->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 获取首页分类广告list
     * @param Request $request
     * @param Adv $adv
     * @return array
     */
    public function get_adv(Request $request, Adv $adv)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $data = $adv
                    ->where('adv_position_id', 10)
                    ->where('title', 'like', '%' . $param['title'] . '%')
                    ->field('adv_id,title')
                    ->select();
                $html = '';
                if ($data->count()) {
                    foreach ($data as $key => $item) {
                        $html .= '<li title="' . $item['title'] . '" value="' . $item['adv_id'] . '" >' . $item['title'] . '</li>';
                    }
                } else {
                    $html .= '<li class="empty">暂无广告数据</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $html];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 获取分类首页广告list
     * @param Request $request
     * @param Adv $adv
     * @return array
     */
    public function get_classify_adv(Request $request, Adv $adv)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $data = $adv
                    ->where('adv_position_id', 17)
                    ->where('title', 'like', '%' . $param['title'] . '%')
                    ->field('adv_id,title')
                    ->select();
                $html = '';
                if ($data->count()) {
                    foreach ($data as $key => $item) {
                        $html .= '<li title="' . $item['title'] . '" value="' . $item['adv_id'] . '" >' . $item['title'] . '</li>';
                    }
                } else {
                    $html .= '<li class="empty">暂无广告数据</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $html];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 添加二三级分类
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create_child(Request $request, GoodsClassifyModel $GoodsClassifyModel, Adv $adv)
    {

        $param = $request::get();

        if ($request::isPost()) {

            Db::startTrans();
            try {
                // 获取参数
                $param = $request::post();

                $check = $GoodsClassifyModel->valid($param, 'create');
                if ($check['code']) return $check;
                $state = $GoodsClassifyModel->allowField(true)->save($param);

                if ($param['parent_id'] != '0') {
                    $GoodsClassifyModel->setCount($param['parent_id'], $GoodsClassifyModel->goods_classify_id);
                }
                // 提交事务
                Db::commit();
                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods_classify/index?level='. $param['level'] .'&classify_id=' . $param['parent_id']];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $parentData = $GoodsClassifyModel->where([
            ['goods_classify_id', 'eq', $param['goods_classify_id']]
        ])->field('title,goods_classify_id')->find();

        return $this->fetch('', [
            'level' => $param['level'],
            'parent_data' => $parentData,
        ]);
    }

    /**
     * 编辑二三级分类
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit_child(Request $request, GoodsClassifyModel $GoodsClassifyModel, Adv $adv)
    {

        $param = $request::get();

        if ($request::isPost()) {

            Db::startTrans();
            try {
                // 获取参数
                $param = $request::post();

                $check = $GoodsClassifyModel->valid($param, 'edit');
                if ($check['code']) return $check;
                $state = $GoodsClassifyModel->allowField(true)->isUpdate(true)->save($param);

                if ($param['parent_id'] != '0') {
                    $GoodsClassifyModel->setCount($param['parent_id'], $GoodsClassifyModel->goods_classify_id);
                }
                // 提交事务
                Db::commit();
                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods_classify/index?level='. $param['level'] .'&classify_id=' . $param['parent_id']];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
//dump(1);
        // 本级信息
        $data = $GoodsClassifyModel->where([
            ['goods_classify_id', 'eq', $param['goods_classify_id']]
        ])->find();
        $data['web_file_data'] = $data->getData('web_file');

        // 上级信息
        $parentData = $GoodsClassifyModel->where([
            ['goods_classify_id', 'eq', $data['parent_id']]
        ])->field('title,goods_classify_id')->find();

        return $this->fetch('create_child', [
            'level' => $param['level'],
            'parent_data' => $parentData,
            'item' => $data
        ]);
    }

    /**
     * 快捷添加分类
     * @param GoodsClassifyValid $classifyValid 验证器
     * @param GoodsClassifyModel $classifyModel 模型
     * @return mixed
     */
    public function fastCreate(GoodsClassifyValid $classifyValid, GoodsClassifyModel $classifyModel)
    {

        if ($this->request->isPost()) {
            $_param = $this->request->post();

            $_data = [];

            $_parentId = $this->request->get('parent_id', 0);

            try {
                // 将发送过来的数组分组
                foreach ($_param['title'] as $key => $row) {
                    $_data[] = [
                        'title' => $row,
                        'sort' => $_param['sort'][$key],
                        'keyword' => $_param['keyword'][$key],
                        'status' => $_param['status'][$key],
                        'parent_id' => $_parentId
                    ];
                }

                // 循环验证 如果有错误则返回
                foreach ($_data as $row) {
                    if (!$classifyValid->scene('fast_create')->check($row)) {
                        return ['code' => -100, 'message' => $classifyValid->getError()];
                    };
                }

                $classifyModel->saveAll($_data);

                return ['code' => 0,'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }

        $this->assign($this->request->get('level','1'));

        return $this->fetch();
    }

    /**
     * 快捷添加分类-商品
     * @param GoodsClassifyValid $classifyValid 验证器
     * @param GoodsClassifyModel $classifyModel 模型
     * @return mixed
     */
    public function fastCreateGoods(GoodsClassifyValid $classifyValid, GoodsClassifyModel $classifyModel)
    {

        if ($this->request->isPost()) {
            $_param = $this->request->post();

            $_data = [];

            $_parentId = $this->request->get('parent_id', 0);
            $_level = $this->request->get('level','1');

            try {
            // 将发送过来的数组分组
            foreach ($_param['title'] as $key => $row) {
                $_data[] = [
                    'title' => $row,
                    'sort' => $_param['sort'][$key],
                    'keyword' => $_param['keyword'][$key],
                    'status' => $_param['status'][$key],
                    'parent_id' => $_parentId
                ];
            }

            // 循环验证 如果有错误则返回
            foreach ($_data as $row) {
                if (!$classifyValid->scene('fast_create')->check($row)) {
                    return ['code' => -100, 'message' => $classifyValid->getError()];
                };
            }

            $classifyModel->saveAll($_data);

            return ['code' => 0,'level'=> $_level-1,'parentId'=>$_parentId, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }

        $this->assign($this->request->get('level','1'));

        return $this->fetch();
    }
}