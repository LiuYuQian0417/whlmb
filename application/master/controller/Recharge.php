<?php
// 充值管理
declare(strict_types = 1);
namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\Recharge as RechargeModel;
use app\common\model\Store as StoreModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;


class Recharge extends Controller
{
    /**
     * 充值列表
     * @param Request $request
     * @param CouponModel $coupon
     * @return array|mixed
     */
    public function index(Request $request, RechargeModel $recharge)
    {
        try {
            // 获取参数
            $param = $request::get();
            // 条件定义
            $data = $recharge
                ->order('recharge_id', 'asc')
                ->paginate(15, false, ['query' => $param]);
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'data' => $data,
        ]);
    }

    /**
     * 新增优惠券
     * @param Request $request
     * @param CouponModel $coupon
     * @param GoodsClassifyModel $goodsClassify
     * @param StoreModel $store
     * @return array|mixed
     */
    public function create(Request $request, RechargeModel $recharge)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();
                // 验证
                $check = $recharge->valid($param, 'create');
                if ($check['code']) return $check;
                // 写入
                $operation = $recharge->allowField(true)->save($param);
                if ($operation) {
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/recharge/index'];
                }
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('', [
        ]);
    }


    /**
     * 编辑优惠券
     * @param Request $request
     * @param RechargeModel $recharge
     * @return array|mixed
     * @throws \think\Exception\DbException
     */
    public function edit(Request $request, RechargeModel $recharge)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();
                // 验证器
                $check = $recharge->valid($param, 'edit');
                if ($check['code']) return $check;
                if (empty($param['file'])) unset($param['file']);
                //编辑
                $operation = $recharge->allowField(true)->isUpdate(true)->save($param);
                if ($operation) {
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/recharge/index'];
                }
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $item = $recharge->get($request::get('recharge_id'));
        $item['file_data'] = $item->getData('file');
        return $this->fetch('create', [
            'item' => $item,
        ]);
    }

    /**
     * 删除充值
     * @param Request $request
     * @param CouponModel $coupon
     * @param CouponAttachModel $couponAttach
     * @return array
     */
    public function destroy(Request $request, RechargeModel $recharge)
    {
        if ($request::isPost()) {
            try {
                // 删除
                $recharge::destroy($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }



}