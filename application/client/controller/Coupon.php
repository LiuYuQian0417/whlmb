<?php
// 优惠券管理
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\Coupon as CouponModel;
use app\common\service\Beanstalk;
use think\Controller;
use think\Exception;
use think\facade\Request;
use think\facade\Session;

class Coupon extends Controller
{
    /**
     * 优惠券列表
     *
     * @param Request     $request
     * @param CouponModel $coupon
     *
     * @optimization Malson 2019-03-15 09:21
     *
     * @return array
     */
    public function index(Request $request, CouponModel $coupon)
    {
        try
        {
            // 获取参数
            $param = $request::get();
            // 条件定义
            $condition = [
                ['coupon_id', 'neq', 0],
                ['type', '=', 0],
                ['classify_str', '=', Session::get('client_store_id')],
            ];

            // 判断是否专递 优惠券名称 这个字段
            if (!empty($param['keyword']))
            {
                $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];
            }

            // 领券精选状态
            if (isset($param['is_recommend']) && $param['is_recommend'] != -1)
            {
                $condition[] = ['is_recommend', '=', $param['is_recommend']];
            }

            // 积分兑换状态
            if (isset($param['is_integral_exchage']) && $param['is_integral_exchage'] != -1)
            {
                $condition[] = ['is_integral_exchage', '=', $param['is_integral_exchage']];
            }

            $data = $coupon
                ->where($condition)
                ->field('*,create_time,update_time,delete_time,(total_num-exchange_num) as used')
                ->paginate(15, FALSE, ['query' => $param]);

        } catch (Exception $e)
        {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch(
            '', [
                  'data' => $data,
              ]
        );
    }

    /**
     * 添加优惠券
     *
     * @param Request     $request
     * @param CouponModel $coupon
     *
     * @return array
     */
    public function create(Request $request, CouponModel $coupon)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $request::post();
                // 验证
                $param['classify_str'] = Session::get('client_store_id');
                $check = $coupon->valid($param, 'create');
                if ($check['code'])
                {
                    return $check;
                }
                $param['exchange_num'] = intval($param['exchange_num']);
                if (!$param['exchange_num'] || $param['exchange_num'] < 0 || $param['exchange_num'] > $param['total_num']) {
                    $param['exchange_num'] = $param['total_num'];
                }
                // 写入
                $operation = $coupon->allowField(TRUE)->save($param);
                if ($operation)
                {
                    // 生成消息到优惠券使用到期下架队列
                    $time = strtotime($param['receive_end_time']) - time();
                    (new Beanstalk())->put(
                        json_encode(
                            [
                                'queue' => 'couponGetExpireChangeStatus',
                                'id'    => $coupon->coupon_id,
                                'time'  => date('Y-m-d H:i:s'),
                            ]
                        ), ($time >= 0 ? $time : 0)
                    );
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/coupon/index'];
                }

            } catch (\Exception $e)
            {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch(
            '', [
                  'member_id' => Session::get('client_store_id'),
              ]
        );
    }

    /**
     * 编辑优惠券
     *
     * @param Request     $request
     * @param CouponModel $coupon
     *
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, CouponModel $coupon)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取参数
                $param = $request::post();
                if (empty($param['member_id']))
                {
                    return ['code' => -1, 'message' => '登录已过期，请重新登录', 'url' => 'client/login/index'];
                }
                // 验证器
                $param['classify_str'] = Session::get('client_store_id');
                $check = $coupon->valid($param, 'edit');
                if ($check['code'])
                {
                    return $check;
                }
                $param['exchange_num'] = intval($param['exchange_num']);
                $exchange_num = $coupon->where(['coupon_id' => $param['coupon_id']])->value('exchange_num');
                if ($exchange_num > $param['total_num']) {
                    $exchange_num = $param['total_num'];
                }
                if (!$param['exchange_num'] || $param['exchange_num'] < 0 || $param['exchange_num'] > $param['total_num']) {
                    $param['exchange_num'] = $exchange_num;
                }
                //编辑
                $operation = $coupon->allowField(TRUE)->isUpdate(TRUE)->save($param);
                if ($operation)
                {
                    // 生成消息到优惠券使用到期下架队列
                    $time = strtotime($param['receive_end_time']) - time();
                    (new Beanstalk())->put(
                        json_encode(
                            [
                                'queue' => 'couponGetExpireChangeStatus',
                                'id'    => $param['coupon_id'],
                                'time'  => date('Y-m-d H:i:s'),
                            ]
                        ), ($time >= 0 ? $time : 0)
                    );
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/coupon/index'];
                }

            } catch (\Exception $e)
            {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $data = $coupon->where('coupon_id', $request::get('coupon_id'))->find();
        $data['file_data'] = $data->getData('file');
        return $this->fetch(
            'create', [
                        'item'      => $data,
                        'member_id' => Session::get('client_store_id'),
                    ]
        );
    }

    /**
     * 删除优惠券
     *
     * @param Request     $request
     * @param CouponModel $coupon
     *
     * @return array
     */
    public function destroy(Request $request, CouponModel $coupon)
    {
        if ($request::isPost())
        {
            try
            {
                // 删除
                $coupon::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e)
            {

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 更新开启状态
     *
     * @param Request     $request
     * @param CouponModel $coupon
     *
     * @return array
     */
    public function editVal(Request $request, CouponModel $coupon)
    {
        if ($request::isPost())
        {
            try
            {
                $param = $request::post();

                $coupon->allowField(TRUE)->isUpdate(TRUE)->save($param);

                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e)
            {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}