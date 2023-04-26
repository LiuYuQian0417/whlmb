<?php
declare(strict_types = 1);

namespace app\interfaces\controller\order;

use app\common\model\CutActivity;
use app\common\model\Member;
use app\common\model\Order;
use app\common\model\OrderGoods;
use app\common\service\Beanstalk;
use app\common\service\Distribution;
use app\common\service\Lock;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Config;
use think\facade\Env;

/**
 * 建立订单
 * Class Establish
 * @package app\interfaces\controller\order
 */
class Establish extends BaseController
{
    /**
     * 确认订单
     * @param RSACrypt $crypt
     * @param Order $order
     * @param CutActivity $cutActivity
     * @param Lock $lock
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function confirm(RSACrypt $crypt,
                            Order $order,
                            CutActivity $cutActivity,
                            Lock $lock,
                            Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $lr = $lock->lock([$param['member_id']], 3000);
        if ($lr) {
            // 判断活动是否开启
            Env::load(Env::get('app_path') . 'common/ini/.config');
            // 拼团关闭
            if (Env::get('is_group', 1) == 0 && $param['order_type'] == 2) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '拼团活动已关闭，暂时不能下单',
                ], true);
            }
            // 砍价关闭
            if (Env::get('is_cut', 1) == 0 && $param['order_type'] == 3) {
                return $crypt->response([
                    'code' => -2,
                    'message' => '砍价活动已关闭，暂时不能下单',
                ], true);
            }
            // 限时抢购关闭
            if (Env::get('is_limit', 1) == 0 && $param['order_type'] == 4) {
                return $crypt->response([
                    'code' => -3,
                    'message' => '限时抢购活动已关闭，暂时不能下单',
                ], true);
            }
            $order->valid($param, 'confirm');
            $orderActService = app('app\\common\\service\\OrderAct');
            Db::startTrans();
            // 获取订单数据
            $data = $orderActService->outData($param);
            if ($data['code'] !== 0) {
                Db::rollback();
                return $crypt->response($data, true);
            }
            // 保存订单数据
            $order->saveOrder($data);
            // 砍价活动绑定店铺订单id并终止砍价活动
            if ($param['order_type'] == 3) {
                $cutActivityUpdate = [];
                foreach ($data['attachData'] as $key => $value) {
                    if (array_key_exists('cut_activity_id', $value) && $value['cut_activity_id']) {
                        array_push($cutActivityUpdate, [
                            'cut_activity_id' => $value['cut_activity_id'],
                            'status' => 2,
                            'order_attach_id' => $value['order_attach_id'],
                        ]);
                    }
                }
                if ($cutActivityUpdate) {
                    $cutActivity
                        ->allowField(true)
                        ->isUpdate(true)
                        ->saveAll($cutActivityUpdate);
                }
            }
            // 消息队列
            (new Beanstalk())->put(json_encode([
                'queue' => 'orderExpire',
                'id' => $data['data']['order_id'],
                'time' => date('Y-m-d H:i:s'),
            ]), 15 * 60);
            // 检测用户是否设置过支付密码
            $memberInfo = $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->with(['distributionRecord'])
                ->field('member_id,nickname,phone,sex,distribution_superior,cumulative_order_sum,pay_password')
                ->find();
            $pwd = $memberInfo['pay_password'];
            unset($memberInfo['pay_password']);
            // 货到付款
            if (!empty($data['distribution']['goods']) || !empty($data['distribution']['distributor_goods'])) {
                $data['distribution']['cumulative_order_sum'] =
                    ($memberInfo->cumulative_order_sum += $data['data']['total_cod_price']);
                $memberInfo->save();
                $distributionGoodsArr = (new Distribution())->opera($data['distribution'], $memberInfo);
                if (!empty($distributionGoodsArr['distributionGoodsArr'])) {
                    $order_goods_update = [];
                    // 当前会员为稳定型分销商并分销了此次支付商品
                    foreach ($distributionGoodsArr['distributionGoodsArr'] as $_dga) {
                        $order_goods_update[$_dga] = [
                            'order_goods_id' => $_dga,
                            'is_distribution' => 1,
                        ];
                    }
                    if (!empty($order_goods_update)) {
                        (new OrderGoods())->allowField(true)->isUpdate(true)->saveAll($order_goods_update);
                    }
                }
            }
            $total_price = fmtPrice($data['data']['total_price'] - $data['data']['total_cod_price']);
            Db::commit();
            return $crypt->response([
                'code' => 0,
                'message' => '提交成功',
                'result' => [
                    'order_number' => $data['data']['order_number'],
                    'order_id' => $data['data']['order_id'],
                    'total_price' => $total_price > 0 ? $total_price : "0",
                    'has_pay_password' => $pwd ? 1 : 0,
                    'group_activity_attach_id' => $data['group_activity_attach_id'],
                ]
            ], true);
        }
        $lock->unlock($lr);
        return $crypt->response([
            'code' => -1,
            'message' => '不可重复提交',
        ], true);
    }
    
    /**
     * 下单ios
     * @param RSACrypt $crypt
     * @param Order $order
     * @param CutActivity $cutActivity
     * @param Member $member
     * @param Lock $lock
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function confirmIos(RSACrypt $crypt, Order $order, CutActivity $cutActivity, Member $member,Lock $lock)
    {
        Config::set('rsa.debug', false);
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $lr = $lock->lock([$param['member_id']], 3000);
        if ($lr) {
            Db::startTrans();
            // 判断活动是否开启
            Env::load(Env::get('app_path') . 'common/ini/.config');
            // 拼团关闭
            if (Env::get('is_group', 1) == 0 && $param['order_type'] == 2) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '拼团活动已关闭，暂时不能下单',
                ], true);
            }
            // 砍价关闭
            if (Env::get('is_cut', 1) == 0 && $param['order_type'] == 3) {
                return $crypt->response([
                    'code' => -2,
                    'message' => '砍价活动已关闭，暂时不能下单',
                ], true);
            }
            // 限时抢购关闭
            if (Env::get('is_limit', 1) == 0 && $param['order_type'] == 4) {
                return $crypt->response([
                    'code' => -3,
                    'message' => '限时抢购活动已关闭，暂时不能下单',
                ], true);
            }
            $order->valid($param, 'confirm');
            $orderActService = app('app\\common\\service\\OrderAct');
            // 获取订单数据
            $data = $orderActService->outData($param);
            if ($data['code'] !== 0) {
                return $crypt->response($data, true);
            }
            // 保存订单数据
            $order->saveOrder($data);
            // 砍价活动绑定店铺订单id并终止砍价活动
            if ($param['order_type'] == 3) {
                $cutActivityUpdate = [];
                foreach ($data['attachData'] as $key => $value) {
                    if (array_key_exists('cut_activity_id', $value) && $value['cut_activity_id']) {
                        array_push($cutActivityUpdate, [
                            'cut_activity_id' => $value['cut_activity_id'],
                            'status' => 2,
                            'order_attach_id' => $value['order_attach_id'],
                        ]);
                    }
                }
                if ($cutActivityUpdate) {
                    $cutActivity
                        ->allowField(true)
                        ->isUpdate(true)
                        ->saveAll($cutActivityUpdate);
                }
            }
            // 插入消息队列
            (new Beanstalk())->put(json_encode(['queue' => 'orderExpire',
                'id' => $data['data']['order_id'], 'time' => date('Y-m-d H:i:s')]), 15 * 60);
            // 检测用户是否设置过支付密码
            $memberInfo = $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->with(['distributionRecord'])
                ->field('member_id,nickname,phone,sex,distribution_superior,cumulative_order_sum,pay_password')
                ->find();
            $pwd = $memberInfo['pay_password'];
            unset($memberInfo['pay_password']);
            // 货到付款
            if (!empty($data['distribution']['goods']) || !empty($data['distribution']['distributor_goods'])) {
                $data['distribution']['cumulative_order_sum'] =
                    ($memberInfo->cumulative_order_sum += $data['data']['total_cod_price']);
                $memberInfo->save();
                $distributionGoodsArr = (new Distribution())->opera($data['distribution'], $memberInfo);
                if (!empty($distributionGoodsArr['distributionGoodsArr'])) {
                    $order_goods_update = [];
                    // 当前会员为稳定型分销商并分销了此次支付商品
                    foreach ($distributionGoodsArr['distributionGoodsArr'] as $_dga) {
                        $order_goods_update[$_dga] = [
                            'order_goods_id' => $_dga,
                            'is_distribution' => 1,
                        ];
                    }
                    if (!empty($order_goods_update)) {
                        (new OrderGoods())->allowField(true)->isUpdate(true)->saveAll($order_goods_update);
                    }
                }
            }
            $total_price = fmtPrice($data['data']['total_price'] - $data['data']['total_cod_price']);
            Db::commit();
            return $crypt->response([
                'code' => 0,
                'message' => '提交成功',
                'result' => [
                    'order_number' => $data['data']['order_number'],
                    'order_id' => $data['data']['order_id'],
                    'total_price' => $total_price > 0 ? $total_price : "0",
                    'has_pay_password' => $pwd ? 1 : 0,
                    'group_activity_attach_id' => $data['group_activity_attach_id'],
                ]
            ], true);
        }
        $lock->unlock($lr);
        return $crypt->response([
            'code' => -1,
            'message' => '不可重复提交',
        ]);
    }
}