<?php
declare(strict_types=1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\AdvPosition as AdvPositionModel;
use app\common\model\Adv as AdvModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\Store as StoreModel;

class Adv extends Controller
{

    /**
     * 电脑端广告列表
     * @param Request $request
     * @param AdvPositionModel $advPositionModel
     * @param AdvModel $advModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pc_index(Request $request, AdvPositionModel $advPositionModel, AdvModel $advModel)
    {


        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['client', '=', 1];

            $condition[] = ['adv_position_id', '<>', 25]; //去除pc商品分类广告
            // 父ID
            if (!empty($param['classify_id'])) $condition[] = ['adv_position_id', '=', $param['classify_id']];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            $data = $advModel
                ->where($condition)
                ->field('content,sort,update_time', TRUE)
                ->order(['adv_id' => 'desc'])
                ->paginate(15, FALSE, ['query' => $param]);



            //如果当前页数大于总页数 则从定向到最大页数
            if (($param['page']??0) > $data->lastPage()){
                $param['page'] = $data->lastPage();
                $_param_str = '';
                foreach ($param as $key => $v){
                    $_param_str .= "&{$key}={$v}";
                }
                $_param_str = trim($_param_str,'&');
                //如果用框架重定向方法会触发异常捕获
                header('Location:' . $this->request->baseUrl().'?' .$_param_str);
            }

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data'          => $data,
            'classify_list' => $advPositionModel->field('adv_position_id,title,parent_id')->where([['parent_id' ,'=',1],['adv_position_id', '<>', 25]])->select(),
        ]);
    }

    /**
     * 电脑端广告新增
     * @param Request $request
     * @param AdvPositionModel $advPositionModel
     * @param AdvModel $advModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pc_create(Request $request, AdvPositionModel $advPositionModel, AdvModel $advModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $advModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $advModel->allowField(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/adv/pc_index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('', [
            'classify_list' => $advPositionModel->field('adv_position_id,title,parent_id,width,height')->where([['parent_id' ,'=',1],['adv_position_id', '<>', 25]])->select(),
            'one_more'      => config('user.one_more'),
            'one_store_id'  => config('user.one_store_id'),
        ]);
    }

    /**
     * 电脑端广告编辑
     * @param Request $request
     * @param AdvPositionModel $advPositionModel
     * @param AdvModel $advModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pc_edit(Request $request, AdvPositionModel $advPositionModel, AdvModel $advModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $advModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $advModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/adv/pc_index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
        $data = $advModel->get($request::get('adv_id'));
        $data['file_data'] = $data->getData('file');
        return $this->fetch('pc_create', [
            'classify_list' => $advPositionModel->field('adv_position_id,title,parent_id,width,height')->where('parent_id', '1')->select(),
            'item'          => $data,
            'one_more'      => config('user.one_more'),
            'one_store_id'  => config('user.one_store_id'),
        ]);
    }

    /**
     * 手机端广告列表
     * @param Request $request
     * @param AdvPositionModel $advPositionModel
     * @param AdvModel $advModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function web_index(Request $request, AdvPositionModel $advPositionModel, AdvModel $advModel)
    {

        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['client', '=', 2];

            // 父ID
            if (!empty($param['classify_id'])) $condition[] = ['adv_position_id', '=', $param['classify_id']];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            $data = $advModel
                ->where($condition)
                ->field('content,sort,update_time', TRUE)
                ->order(['adv_id' => 'desc'])
                ->paginate(15, FALSE, ['query' => $param]);

            //如果当前页数大于总页数 则从定向到最大页数
            if (($param['page']??0) > $data->lastPage()){
                $param['page'] = $data->lastPage();
                $_param_str = '';
                foreach ($param as $key => $v){
                    $_param_str .= "&{$key}={$v}";
                }
                $_param_str = trim($_param_str,'&');
                //如果用框架重定向方法会触发异常捕获
                header('Location:' . $this->request->baseUrl().'?' .$_param_str);
            }

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data'          => $data,
            'classify_list' => $advPositionModel->field('adv_position_id,title,parent_id')->where('parent_id', '2')->select(),
            'one_more'      => config('user.one_more'),
            'one_store_id'  => config('user.one_store_id'),
        ]);
    }

    /**
     * 手机端广告新增
     * @param Request $request
     * @param AdvPositionModel $advPositionModel
     * @param AdvModel $advModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function web_create(Request $request, AdvPositionModel $advPositionModel, AdvModel $advModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

//                if ($param['type'] == 0) {
//                    if(strpos($param['content'],'http://') === false) return ['code' => -100, 'message' => '链接必须http://格式'];
//                }

                if (!empty($param['date'])) {
                    list($param['start_time'], $param['end_time']) = explode(' - ', $param['date']);
                    if ($param['start_time'] == $param['end_time']) {
                        $param['end_time'] = date('Y-m-d', strtotime($param['end_time'] . '+1 days'));
                    }
                } else {
                    $param['start_time'] = NULL;
                    $param['end_time'] = NULL;
                }
                // 验证
                $check = $advModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $advModel->allowField(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/adv/web_index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $_where = [
            ['parent_id', '=', '2'],
            ['adv_position_id', 'not in', []],
        ];

        // 积分商城
        if (!INI_CONFIG['IS_INTEGRAL_MALL']) {
            $_where[1][2][] = '4'; // 积分商城列表banner广告
            $_where[1][2][] = '9'; // 积分商城 - 积分任务banner
        }

        // 首页推荐分类
        if (!INI_CONFIG['IS_CLASSIFY_RECOMMEND']) {
            $_where[1][2][] = '10'; // 首页推荐分类广告图
        }
        $_where[1][2] = join(',', $_where[1][2]);

        $_advPosition = $advPositionModel
            ->field([
                'adv_position_id',
                'title',
                'parent_id',
                'width',
                'height',
            ])->where($_where)
            ->select();

        return $this->fetch('', [
            'classify_list' => $_advPosition,
            'one_more'      => config('user.one_more'),
            'one_store_id'  => config('user.one_store_id'),
        ]);
    }

    /**
     * 手机端广告编辑
     * @param Request $request
     * @param AdvPositionModel $advPositionModel
     * @param AdvModel $advModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function web_edit(Request $request, AdvPositionModel $advPositionModel, AdvModel $advModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();
                if ($param['type'] != 3) {
                    if (empty($param['content'])) return ['code' => -100, 'message' => '请输入链接/编号'];
                }
                if ($param['type'] == 0) {
                    if (strpos($param['content'], 'http://') === FALSE) return ['code' => -100, 'message' => '链接必须http://格式'];
                }
                if (!empty($param['date'])) {
                    list($param['start_time'], $param['end_time']) = explode(' - ', $param['date']);
                    if ($param['start_time'] == $param['end_time']) {
                        $param['end_time'] = date('Y-m-d', strtotime($param['end_time'] . '+1 days'));
                    }
                } else {
                    $param['start_time'] = NULL;
                    $param['end_time'] = NULL;
                }
                // 验证
                $check = $advModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $advModel->allowField(TRUE)->isUpdate(TRUE)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/adv/web_index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $data = $advModel->get($request::get('adv_id'));
        $data['file_data'] = $data->getData('file');
        return $this->fetch('web_create', [
            'classify_list' => $advPositionModel->field('adv_position_id,title,parent_id,width,height')->where('parent_id', '2')->select(),
            'item'          => $data,
            'one_more'      => config('user.one_more'),
            'one_store_id'  => config('user.one_store_id'),
        ]);
    }


    /**
     * 删除广告列表
     * @param Request $request
     * @param AdvModel $advModel
     * @return array
     */
    public function destroy(Request $request, AdvModel $advModel)
    {
        if ($request::isPost()) {

            try {

                $advModel::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 广告状态更新
     * @param Request $request
     * @param AdvModel $advModel
     * @return array
     */
    public function auditing(Request $request, AdvModel $advModel)
    {

        if ($request::isPost()) {
            try {
                $advModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 广告文本更新
     * @param Request $request
     * @param AdvModel $advModel
     * @return array
     */
    public function text_update(Request $request, AdvModel $advModel)
    {

        if ($request::isPost()) {
            try {
                $advModel->clickEdit($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 广告列表 - 店铺搜索
     * @param Request $request
     * @param StoreModel $store
     * @return array|mixed
     */
    public function store_search(Request $request, StoreModel $store)
    {
        try {
            $param = $request::get();

            // 条件定义
            $condition[] = ['status', '=', 4];
            $condition[] = ['end_time', ['NULL', 'null'], ['>=', date('Y-m-d')], 'or'];

            // 标题
            if (!empty($param['keyword'])) $condition[] = ['store_name', 'like', '%' . $param['keyword'] . '%'];

            $data = $store
                ->where($condition)
                ->where([
                    ['status', '=', 4],
                ])
                ->field('store_id,store_name')
                ->paginate(10, FALSE, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 广告列表 - 商品搜索
     * @param Request $request
     * @param GoodsModel $goods
     * @return array|mixed
     */
    public function goods_search(Request $request, GoodsModel $goods)
    {
        try {

            $param = $request::get();

            // 条件定义
            $condition[] = ['goods_number', '>', 0];
            $condition[] = ['review_status', '=', 1];
            $condition[] = ['is_putaway', '=', 1];

            // 标题
            if (!empty($param['keyword'])) $condition[] = ['goods_name', 'like', '%' . $param['keyword'] . '%'];

            $data = $goods
                ->alias('goods')
                ->join('store store', 'store.store_id = goods.store_id and store.status = 4 and store.delete_time is null')
                ->where($condition)
                ->field('goods.goods_id,store.store_name,store.store_id,goods.goods_name,goods.file')
                ->paginate(10, FALSE, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
        ]);
    }

}