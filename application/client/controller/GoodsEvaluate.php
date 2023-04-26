<?php
// 用户评论
declare(strict_types=1);

namespace app\client\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\GoodsEvaluate as GoodsEvaluateModel;
use think\facade\Session;

class GoodsEvaluate extends Controller
{
    /**
     * 用户评论
     * @param Request $request
     * @param GoodsEvaluateModel $goodsEvaluate
     * @return array|mixed
     */
    public function index(Request $request, GoodsEvaluateModel $goodsEvaluate)
    {
        try {
            // 获取数据
            $param = $request::param();
            // 筛选条件
            $condition[] = ['ge.goods_evaluate_id', '>', 0];
            $condition[] = ['st.store_id', 'eq', Session::get('client_store_id')];
            // 获取数据
            $data = $goodsEvaluate
                ->alias('ge')
                ->join(['ishop_goods' => 'go'], 'go.goods_id=ge.goods_id')
                ->join(['ishop_store' => 'st'], 'st.store_id=go.store_id')
                ->join(['ishop_member' => 'me'], 'me.member_id=ge.member_id')
                ->where($condition)
                ->order('ge.create_time','desc')
                ->field('ge.reply,ge.star_num,ge.create_time,st.shop,st.store_name,me.nickname,ge.status,go.goods_name,ge.goods_evaluate_id,
                ge.store_star_num,ge.express_star_num,ge.multiple_file,ge.video')
                ->paginate(10, false, ['query' => $param]);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => config('message.')['-1']];
        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }


    /**
     * 回复评论
     *
     * @param Request            $request
     * @param GoodsEvaluateModel $goodsEvaluate
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, GoodsEvaluateModel $goodsEvaluate)
    {
        if ($request::isPost()) {

            try {
                // 获取数据
                $param = $request::post();

                // 验证
                $check = $goodsEvaluate->valid($param, 'edit');
                if ($check['code']) return $check;

                // 更新
                $operation = $goodsEvaluate->allowField(true)->isUpdate(true)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/goods_evaluate/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }

        }

        // 获取信息
        $item = $goodsEvaluate
            ->alias('ge')
            ->join(['ishop_goods' => 'go'], 'go.goods_id=ge.goods_id')
            ->join(['ishop_store' => 'st'], 'st.store_id=go.store_id')
            ->join(['ishop_member' => 'me'], 'me.member_id=ge.member_id')
            ->where(['goods_evaluate_id' => $request::get('id')])
            ->field('ge.multiple_file,ge.video,ge.reply,ge.create_time,st.shop,st.store_name,me.username,ge.status,go.goods_name,
            ge.attr,ge.star_num,ge.content,ge.goods_evaluate_id,ge.store_star_num,ge.express_star_num,ge.express_content,nickname')
            ->find();

        return $this->fetch('create', [
            'item'      => $item,
        ]);
    }


    /**
     * 删除订单评论
     * @param Request $request
     * @param GoodsEvaluateModel $goodsEvaluate
     * @return array
     */
    public function destroy(Request $request, GoodsEvaluateModel $goodsEvaluate)
    {
        if ($request::isPost()) {
            try {
                $goodsEvaluate::destroy($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }


    /**
     * 更新评论显示状态
     * @param Request $request
     * @param GoodsEvaluateModel $goodsEvaluate
     * @return array
     */
    public function auditing(Request $request, GoodsEvaluateModel $goodsEvaluate)
    {

        if ($request::isPost()) {
            try {
                $goodsEvaluate->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
}