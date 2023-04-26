<?php
// 用户评论
declare(strict_types=1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\GoodsEvaluate as GoodsEvaluateModel;
use app\common\model\Goods;

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

            switch (array_key_exists('type', $param) && $param['type']) {
                case 0:
                    $condition[] = ['st.shop', 'eq', 0];
                    break;
                case 1:
                    $condition[] = ['st.shop', 'neq', 0];
                    break;
                default;
            }

            // 获取数据
            $data = $goodsEvaluate->alias('ge')
                ->join(['ishop_goods' => 'go'], 'go.goods_id=ge.goods_id')
                ->join(['ishop_store' => 'st'], 'st.store_id=go.store_id')
                ->join(['ishop_member' => 'me'], 'me.member_id=ge.member_id')
                ->where($condition)
                ->field('ge.create_time,st.shop,st.store_name,me.nickname,ge.status,go.goods_name,ge.goods_evaluate_id,ge.reply,ge.store_star_num,ge.express_star_num')
                ->order(['ge.create_time' => 'desc'])
                ->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }


        return $this->fetch('', [
            'data' => $data
        ]);
    }


    /**
     * 回复评论
     * @param Request $request
     * @param GoodsEvaluateModel $goodsEvaluate
     * @return array|mixed
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

                $param['reply_time'] = date('Y-m-d H:i:s');
                // 更新
                $operation = $goodsEvaluate->allowField(true)->isUpdate(true)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods/evaluate?goods_id='. $param['goods_id']];

            } catch (\Exception $e) {
                halt($e->getMessage());
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }

        // 获取信息
        $item = $goodsEvaluate->alias('ge')
            ->join(['ishop_goods' => 'go'], 'go.goods_id=ge.goods_id')
            ->join(['ishop_store' => 'st'], 'st.store_id=go.store_id')
            ->join(['ishop_member' => 'me'], 'me.member_id=ge.member_id')
            ->where(['goods_evaluate_id' => $request::get('id')])
            ->field('ge.reply,ge.create_time,st.shop,st.store_name,me.nickname,me.username,st.status,go.goods_name,ge.attr,ge.star_num,ge.content,ge.goods_evaluate_id,
            ge.store_star_num,ge.express_star_num,ge.express_content,ge.multiple_file,ge.video,me.avatar,go.goods_id')
            ->find();

        return $this->fetch('create', [
            'item' => $item
        ]);
    }


    /**
     * 删除订单评论
     * @param Request $request
     * @param GoodsEvaluateModel $goodsEvaluate
     * @param Goods $goods
     * @return array
     */
    public function destroy(Request $request, GoodsEvaluateModel $goodsEvaluate, Goods $goods)
    {
        if ($request::isPost()) {
            try {

                $goods_id = $goodsEvaluate
                    ->where('goods_evaluate_id', 'in', $request::post('id'))
                    ->column('goods_id');

                foreach ($goods_id as $value) {
                    $goods->where('goods_id', $value)->setDec('comments_number');
                }

                $goodsEvaluate::destroy($request::post('id'));


                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 更新评论显示状态
     * @param Request $request
     * @param GoodsEvaluateModel $goodsEvaluate
     * @param Goods $goods
     * @return array
     */
    public function auditing(Request $request, GoodsEvaluateModel $goodsEvaluate, Goods $goods)
    {

        if ($request::isPost()) {
            try {

                $goodsEvaluate->changeStatus($request::post('id'));

                // 查询数据
                $find = $goodsEvaluate
                    ->where('goods_evaluate_id', $request::post('id'))
                    ->field('status,goods_id')
                    ->find();

                // 增减操作
                if ($find['status'] == 1) {
                    $goods->where('goods_id', $find['goods_id'])->setInc('comments_number');
                } else {
                    $goods->where('goods_id', $find['goods_id'])->setDec('comments_number');
                }

                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}