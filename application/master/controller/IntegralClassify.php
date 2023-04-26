<?php
/**
 * 积分商品分类
 */
declare(strict_types = 1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\IntegralClassify as IntegralClassifyModel;
use app\common\model\Integral;

class IntegralClassify extends Controller
{
    /**
     * 积分商品分类列表
     * @param Request $request
     * @param IntegralClassifyModel $integralClassify
     * @return array|mixed
     */
    public function index(Request $request, IntegralClassifyModel $integralClassify)
    {
        try {
            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['integral_classify_id', 'neq', 0];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            // 获取数据
            $data = $integralClassify->where($condition)->field('update_time,delete_time', true)
                ->order('integral_classify_id', 'desc')->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }


        return $this->fetch('', [
            'data' => $data,
        ]);
    }


    /**
     * 添加积分商品分类
     * @param Request $request
     * @param IntegralClassifyModel $integralClassify
     * @return array|mixed
     */
    public function create(Request $request, IntegralClassifyModel $integralClassify)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();

                // 验证
                $check = $integralClassify->valid($param, 'create');
                if ($check['code']) return $check;

                // 写入
                $operation = $integralClassify->allowField(true)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/integral_classify/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('');
    }


    /**
     * 编辑积分商品分类
     * @param Request $request
     * @param IntegralClassifyModel $integralClassify
     * @return array|mixed
     */
    public function edit(Request $request, IntegralClassifyModel $integralClassify)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();

                //验证
                $check = $integralClassify->valid($param, 'edit');
                if ($check['code']) return $check;

                // 写入
                $operation = $integralClassify->allowField(true)->isUpdate(true)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/integral_classify/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }

        }

        return $this->fetch('create', [
            'item' => $integralClassify::get($request::get('integral_classify_id'))
        ]);
    }


    /**
     * 删除积分商品分类
     * @param Request $request
     * @param IntegralClassifyModel $integralClassify
     * @return array
     */
    public function destroy(Request $request,IntegralClassifyModel $integralClassify, Integral $integral)
    {
        if ($request::post()){
            try{
                if (!empty($integral->where([
                    ['integral_classify_id', 'in', $request::post('id')]
                ])->column('integral_id'))) return ['code' => -100, 'message' => '该分类下存在商品，删除失败'];

                $integralClassify::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            }catch (\Exception $e){

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 积分商品分类跟新状态
     * @param Request $request
     * @param IntegralClassifyModel $integralClassify
     * @return array
     */
    public function auditing(Request $request,IntegralClassifyModel $integralClassify)
    {
        if ($request::isPost()) {
            try {
                $integralClassify->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


}