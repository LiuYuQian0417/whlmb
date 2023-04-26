<?php
/**
 * Created by PhpStorm.
 * User: Hhd
 * Date: 2019/3/12
 * Time: 10:22
 */

namespace app\master\controller;

use think\Controller;
use think\Db;
use app\common\model\Icon as IconModel;
use app\common\model\GoodsClassify;
use think\facade\Env;
use think\facade\Request;


class Icon extends Controller
{
    /**
     * 首页图标设置
     * @param IconModel $icon
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function index_img(IconModel $icon)
    {

        $_where = [];

        // 单店
        if (!config('user.one_more')) {
            $_where[] = [
                'name', '<>', 'merchant',
            ];
        }

        $data = $icon->where($_where)->select();
        return $this->fetch('', ['data' => $data]);
    }

    /**
     * 图标设置
     * @param Request $request
     * @param GoodsClassify $classify
     * @param IconModel $icon
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function img_create(Request $request, GoodsClassify $classify, IconModel $icon)
    {
        if ($request::isPost()) {
            try {
                $data = $request::post();
                $check = $icon->valid($data, 'master_create');
                if ($check['code']) return $check;
                //判断是功能模块还是分类
                if (isset($data['parent_id'])) {
                    if ($classify->where([['goods_classify_id', '=', $data['parent_id']]])->count() <= 0) {
                        exception('商品分类选择错误');
                    };
                    $data['name'] = $data['path'] = $data['parent_id'];
                    $data['type'] = 2;
                } else {
                    $data['type'] = 1;
                }
                $icon->save($data);
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/icon/index_img'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }
        //排除掉已经加入的分类
        $icon_classify_list = $icon->where([['type', '=', '2']])->value('group_concat(name)');
        return $this->fetch(
            '',
            [
                'classify_list' => $classify->where([['parent_id', '=', 0], ['goods_classify_id', 'not in', $icon_classify_list]])->field(
                    'goods_classify_id,title,parent_id'
                )->select()->toArray(),
            ]
        );
    }


    /**
     * 首页编辑
     * @param Request $request
     * @param GoodsClassify $classify
     * @param IconModel $icon
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function img_edit(Request $request, GoodsClassify $classify, IconModel $icon)
    {
        if ($request::isPost()) {
            try {
                $data = $request::post();
                $check = $icon->valid($data, 'master_create');
                if ($check['code']) return $check;
                if (isset($data['parent_id'])) {
                    if ($classify->where([['goods_classify_id', '=', $data['parent_id']]])->count() <= 0) {
                        exception('商品分类选择错误');
                    };
                    $data['name'] = $data['path'] = $data['parent_id'];
                    $data['type'] = 2;
                } else {
                    $data['type'] = 1;
                }

                $icon->isUpdate(TRUE)->save($data);
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/icon/index_img'];
            } catch (\Exception $e) {
                halt($e->getMessage());
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }
        //排除掉已经加入的分类
        $data = $icon->get(input('id'));
        $data['img_data'] = $data->getData('img');

        $icon_classify_list = $icon->where([['type', '=', '2'], ['name', '<>', $data->name ?? '']])->value('group_concat(name)');
        return $this->fetch(
            'img_create',
            [
                'classify_list' => $classify->where([['parent_id', '=', 0], ['goods_classify_id', 'not in', $icon_classify_list]])->field(
                    'goods_classify_id,title,parent_id'
                )->select()->toArray(),
                'data'          => $data,
            ]
        );
    }


    /**
     * 图片是否显示
     * @param Request $request
     * @param IconModel $icon
     * @return array
     */
    public function img_is_show(Request $request, IconModel $icon)
    {
        try {
            $index_icon_id = $request::post('index_icon_id') ?? \exception('图标数据错误');
            $icon->where([['index_icon_id', '=', $index_icon_id]])->update(['is_show' => Db::raw('if(is_show=1,2,1)')]);
            return ['code' => 0, 'message' => config('message.')[0]];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 更新图标排序
     * @param Request $request
     * @param IconModel $icon
     * @return array
     */
    public function store_update(Request $request, IconModel $icon)
    {
        try {
            $index_icon_id = $request::post('id') ?? \exception('图标数据错误');
            $icon->where([['index_icon_id', '=', $index_icon_id]])->update(['sort' => $request::post('data', 0)]);
            return ['code' => 0, 'message' => config('message.')[0]];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    /**
     * 删除图片
     * @param Request $request
     * @param IconModel $icon
     * @return array
     */
    public function img_delete(Request $request, IconModel $icon)
    {
        try {
            $index_icon_id = $request::post('id') ?? \exception('图标数据错误');
            $icon_data = $icon->get($index_icon_id);
            if (file_exists($icon_data->img)) {
                unlink($icon_data->img);
            }
            $icon->where([['index_icon_id', '=', $index_icon_id]])->delete();
            return ['code' => 0, 'message' => config('message.')[0]];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }
}