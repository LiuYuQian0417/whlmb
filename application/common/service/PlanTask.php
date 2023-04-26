<?php
declare(strict_types = 1);

namespace app\common\service;

use app\common\model\Consumption;
use app\common\model\Coupon;
use app\common\model\CutActivity;
use app\common\model\CutGoods;
use app\common\model\DistributionChangeRecord;
use app\common\model\DistributionLevel;
use app\common\model\Goods;
use app\common\model\GoodsEvaluate;
use app\common\model\GroupActivity;
use app\common\model\GroupGoods;
use app\common\model\Limit;
use app\common\model\Member;
use app\common\model\MemberCoupon;
use app\common\model\MemberPacket;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\OrderGoodsRefund;
use app\common\model\RedPacket;
use app\common\model\Distribution;
use EasyWeChat\Factory;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Hook;

/**
 * 计划任务
 * Class PlanTask
 * @package app\common\service
 */
class PlanTask
{
    private $msg = null;
    private $res_msg = '';
    private $file_name = '';
    
    public function __construct($msg, $file_name = '', $preMsg = '')
    {
        $this->file_name = $file_name;
        $this->res_msg = $preMsg;
        $this->res_msg .= str_repeat('-', 25) . date('Y-m-d H:i:s') . "[" .
            (microtime(true) - time()) . "]" . str_repeat('-', 25) . PHP_EOL;
        if (array_key_exists('id', $msg) && $msg['id']) {
            $idMsg = is_array($msg['id']) ? implode(',', $msg['id']) : $msg['id'];
            $this->res_msg .= 'id：' . $idMsg . PHP_EOL;
        }
        $this->res_msg .= 'state：msg actioned' . PHP_EOL;
        $this->msg = $msg;
    }
    
    /**
     * 未支付订单到期处理
     */
    public function orderExpire()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $orderAttachModel = new OrderAttach();
            // 查询订单附表未支付id
            $info = $orderAttachModel
                ->where([
                    ['order_id', '=', $this->msg['id']],
                    ['pay_type', '<>', 2],      //排出货到付款
                ])
                ->with(['orderGoodsCancel', 'orderCancel'])
                ->field('order_attach_id,status,number,used_shop_member_coupon_id,cut_activity_id,order_id')
                ->select();
            if ($info->isEmpty()) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $orderAttachModel->getLastSql() . PHP_EOL;
                return true;
            }
            $attachArr = $goodsArr = $couponUpdate = $lockKey = [];
            // 回滚库存
            $inventory = ['goods_id' => [], 'products_id' => [], 'limit_id' => []];
            foreach ($info as $item) {
                if ($item['status'] === 0) {
                    if ($item['used_shop_member_coupon_id']) {
                        array_push($couponUpdate, $item['used_shop_member_coupon_id']);
                    }
                    array_push($attachArr, [
                        'order_attach_id' => $item['order_attach_id'],
                        'status' => 6,
                    ]);
                    if ($item->order_goods_cancel) {
                        foreach ($item->order_goods_cancel as $_item) {
                            array_push($goodsArr, [
                                'order_goods_id' => $_item['order_goods_id'],
                                'status' => 6.1,
                            ]);
                            $updateKey = $_item['is_limit'] ? 'time_limit_number' : 'goods_number';
                            // 增加商品库存[正常/限时抢购]
                            array_push($inventory['goods_id'], [
                                'goods_id' => $_item['goods_id'],
                                'goods_number' => Db::raw('goods_number + ' . $_item['quantity']),
                            ]);
                            // 增加商品行锁
                            array_push($lockKey, 'goods_id_' . $_item['goods_id']);
                            // 返还限购商品剩余数量
                            if ($_item['is_limit']) {
                                array_push($inventory['limit_id'], [
                                    'limit_id' => $_item['limit_id'],
                                    'exchange_num' => Db::raw('exchange_num + ' . $_item['quantity']),
                                ]);
                                array_push($lockKey, 'limit_id_' . $_item['limit_id']);
                            }
                            if ($_item['products_id']) {
                                array_push($inventory['products_id'], [
                                    'products_id' => $_item['products_id'],
                                    'attr_' . $updateKey => Db::raw('attr_' . $updateKey . ' + ' . $_item['quantity']),
                                ]);
                                // 增加规格商品行锁
                                array_push($lockKey, 'products_id_' . $_item['products_id']);
                            }
                        }
                    }
                    if ($item['cut_activity_id']) {
                        // 减少砍价商品的销量
                        $cutAct = (new CutActivity())
                            ->where([
                                ['cut_activity_id', '=', $item['cut_activity_id']],
                            ])
                            ->field('cut_activity_id,cut_goods_id')
                            ->find();
                        if (!is_null($cutAct)) {
                            // 关联活动关联的砍价商品
                            (new CutGoods())
                                ->allowField(true)
                                ->isUpdate(true)
                                ->save([
                                    'cut_goods_id' => $cutAct['cut_goods_id'],
                                    'sales_volume' => Db::raw('sales_volume - ' . $item['number']),
                                ]);
                        }
                    }
                }
            }
            Db::startTrans();
            if ($lockKey) {
                $lockService = app('app\\common\\service\\Lock', true);
                // 加锁
                $lockData = $lockService->lock($lockKey, 10000);
                if (!$lockData) {
                    return true;
                }
                if ($inventory['goods_id']) {
                    $goods = app('app\\common\\model\\Goods', true);
                    $goods->allowField(true)->isUpdate(true)->saveAll($inventory['goods_id']);
                }
                if ($inventory['products_id']) {
                    $products = app('app\\common\\model\\Products', true);
                    $products->allowField(true)->isUpdate(true)->saveAll($inventory['products_id']);
                }
                if ($inventory['limit_id']) {
                    $limit = app('app\\common\\model\\Limit', true);
                    $limit->allowField(true)->isUpdate(true)->saveAll($inventory['limit_id']);
                }
                // 解锁
                $lockService->unlock($lockData);
            }
            // 返还用户优惠券[只还店铺的]
            if ($couponUpdate) {
                $memberCoupon = app('app\\common\\model\\MemberCoupon', true);
                $memberCoupon
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save(['status' => 0, 'used_time' => null],
                        [['member_coupon_id', 'in', implode(',', $couponUpdate)]]);
            }
            // 更改未支付店铺订单为已取消
            if ($attachArr) {
                $orderAttachModel
                    ->allowField(true)
                    ->isUpdate(true)
                    ->saveAll($attachArr);
                // 关联商品订单更改状态为已取消
                $orderGoodsModel = app('app\\common\\model\\OrderGoods', true);
                $orderGoodsModel
                    ->allowField(true)
                    ->isUpdate(true)
                    ->saveAll($goodsArr);
            }
            Db::commit();
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 未支付订单5分提醒
     * @return bool|string
     */
    public function orderExpireRemind()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $orderAttachModel = new OrderAttach();
            // 查询订单附表未支付id
            $info = $orderAttachModel
                ->alias('oa')
                ->where([
                    ['order_id', '=', $this->msg['id']],
                    ['pay_type', '<>', 2],      //排出货到付款
                ])
                ->join('store s', 's.store_id = oa.store_id')
                ->join('member m', 'm.member_id = oa.member_id')
                ->with(['orderGoodsPay'])
                ->field('oa.order_attach_id,oa.status,s.store_name,m.web_open_id,
                m.nickname,m.member_id,m.micro_open_id,m.subscribe_time,m.phone')
                ->select();
            if ($info->isEmpty()) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $orderAttachModel->getLastSql() . PHP_EOL;
                return true;
            }
            foreach ($info as $item) {
                if ($item['status'] == 0) {
                    // 推送消息[未支付订单5分钟提醒]
                    $pushServer = app('app\\interfaces\\behavior\\Push');
                    $pushServer->send([
                        'tplKey' => 'order_state',
                        'openId' => $item['web_open_id'],
                        'microId' => $item['micro_open_id'],
                        'subscribe_time' => $item['subscribe_time'],
                        'phone' => $item['phone'],
                        'data' => [0, $item['store_name'], $item['nickname']],
                        'inside_data' => [
                            'member_id' => $item['member_id'],
                            'type' => 1,
                            'jump_state' => '0',
                            'attach_id' => $item['order_attach_id'],
                            'file' => $item->order_goods_pay[0]->getData('file'),
                        ],
                        'sms_data' => [],
                    ]);
                }
            }
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 年底全员积分到期提醒
     * 到期时间后台动态设定
     * @return bool
     */
    public function integralExpireRemind()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            // 全员发送积分到期提醒
            app('app\\common\\service\\Inform', true)->integral_inform(0, '13', '', [
                'member_id' => 0,
                'web_open_id' => '',
                'subscribe_time' => '',
                'micro_open_id' => '',
                'phone' => '',
            ], 2);
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 会员积分归零
     * @return bool
     */
    public function integralClearZero()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $memberModel = app('app\\common\\model\\Member', true);
            $memberModel
                ->allowField(true)
                ->isUpdate(true)
                ->save(['pay_points' => 0], [['member_id', '>', 0]]);
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 优惠券到期提醒
     * @return bool
     */
    public function couponExpireRemind()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $info = (new Member())
                ->where([
                    ['member_id', '=', $this->msg['uid']],
                ])
                ->field('member_id,web_open_id,subscribe_time,micro_open_id,phone')
                ->find();
            if (!is_null($info)){
                // 优惠券即将到期提醒
                app('app\\common\\service\\Inform')->coupon_inform(0, '15', $info?$info->toArray():[], 1, $this->msg['id']);
            }
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 优惠券到期更改状态为已过期
     * tested
     * @return bool
     */
    public function couponExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $memberCouponModel = new MemberCoupon();
            $info = $memberCouponModel
                ->where([['member_coupon_id', '=', $this->msg['id']]])
                ->field('member_coupon_id,status')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $memberCouponModel->getLastSql() . PHP_EOL;
                return true;
            }
            // 未使用更改状态为已过期
            if ($info['status'] == 0) {
                $memberCouponModel->allowField(true)
                    ->isUpdate(true)
                    ->save([
                        'member_coupon_id' => $info['member_coupon_id'],
                        'status' => 2
                    ]);
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 优惠券领取时间到期下架
     * @return bool
     */
    public function couponGetExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $couponModel = new Coupon();
            //查询优惠券信息
            $info = $couponModel
                ->where([['coupon_id', '=', $this->msg['id']]])
                ->field('coupon_id,type,receive_end_time,status')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $couponModel->getLastSql() . PHP_EOL;
                return true;
            }
            // 更改优惠券状态
            if (strtotime($info['receive_end_time']) <= time() && $info['status'] == 1) {
                $couponModel->allowField(true)
                    ->isUpdate(true)
                    ->save(['status' => 0], [['coupon_id', '=', $info['coupon_id']]]);
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 红包到期提醒
     * @return bool
     */
    public function packetExpireRemind()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $info = (new Member())
                ->where([
                    ['member_id', '=', $this->msg['uid']],
                ])
                ->field('member_id,web_open_id,subscribe_time,micro_open_id,phone')
                ->find();
            if (!is_null($info)) {
                // 优惠券即将到期提醒
                app('app\\common\\service\\Inform', true)->packet_inform(0, '14', '', $info?$info->toArray():[], 1, $this->msg['id']);
            }
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 会员红包到期更改状态为已过期
     * @return bool
     */
    public function packetExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $memberPacketModel = new MemberPacket();
            $info = $memberPacketModel
                ->where([
                    ['member_packet_id', '=', $this->msg['id']],
                ])
                ->field('member_packet_id,status')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $memberPacketModel->getLastSql() . PHP_EOL;
                return true;
            }
            // 未使用更改状态为已过期
            if ($info['status'] == 0) {
                $info->status = 2;
                $info->save();
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 红包领取时间到期下架
     * @return bool
     */
    public function packetGetExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $packetModel = new RedPacket();
            //查询红包信息
            $info = $packetModel
                ->where([['red_packet_id', '=', $this->msg['id']]])
                ->field('red_packet_id,type,receive_end_time')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $packetModel->getLastSql() . PHP_EOL;
                return true;
            }
            // 更改优惠券状态
            if (strtotime($info['receive_end_time']) <= time()) {
                $packetModel
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save(['status' => 0], [['red_packet_id', '=', $info['red_packet_id']]]);
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 砍价活动未成功过期状态变为失败
     * @return bool
     */
    public function cutExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $cutActivityModel = new CutActivity();
            $info = $cutActivityModel
                ->where([
                    ['cut_activity_id', '=', $this->msg['id']],
                    ['status', '=', 1], //进行中
                ])
                ->field('cut_activity_id,status,end_time')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $cutActivityModel->getLastSql() . PHP_EOL;
                return true;
            }
            if (strtotime($info['end_time']) <= time()) {
                // 更改状态为失败
                $cutActivityModel->allowField(true)
                    ->isUpdate(true)
                    ->save(['status' => 3], [['cut_activity_id', '=', $info['cut_activity_id']]]);
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 砍价商品到期更改商品砍价下架状态
     * @return bool
     */
    public function cutGoodsExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $cutGoodsModel = new CutGoods();
            // 查询砍价商品信息
            $info = $cutGoodsModel
                ->where([
                    ['cut_goods_id', '=', $this->msg['id']],
                ])
                ->field('cut_goods_id,down_shelf_time,goods_id,status')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $cutGoodsModel->getLastSql() . PHP_EOL;
                return true;
            }
            if (strtotime($info['down_shelf_time']) <= time() && $info['down_shelf_time'] == date('Y-m-d')) {
                // 更改商品状态为砍价已下架,砍价默认底价归0
                (new Goods())
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save([
                        'goods_id' => $info['goods_id'],
                        'is_bargain' => 0,
                        'cut_price' => 0,
                    ]);
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 拼团未成功过期状态变为失败
     * @return bool
     */
    public function groupExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $groupModel = new GroupActivity();
            $info = $groupModel
                ->where([
                    ['group_activity_id', '=', $this->msg['id']],
                    ['status', '=', 1]    //进行中
                ])
                ->field('group_activity_id,status,is_auto,end_time')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $groupModel->getLastSql() . PHP_EOL;
                return true;
            }
            Db::startTrans();
            if ($info['status'] == 1) {
                // 更改状态为失败[自动成功下改为成功]
                $groupModel->allowField(true)
                    ->isUpdate(true)
                    ->save(['group_activity_id' => $info['group_activity_id'], 'status' => ($info['is_auto'] ? 2 : 3)]);
                $orderAttachModel = new OrderAttach();
                // 更改所有团员訂單爲已取消
                $orderAttachData = $orderAttachModel
                    ->alias('oa')
                    ->where([
                        ['oa.group_activity_id', '=', $info['group_activity_id']],
                        // 包含货到付款的订单
                        ['oa.status', 'in', '1,2'],
                    ])
                    ->with(['orderGoodsDestroy', 'memberPay', 'orderPay'])
                    ->join('store s', 's.store_id = oa.store_id')
                    ->field('oa.order_attach_id,oa.status,oa.pay_channel,oa.member_id,oa.order_id,oa.pay_type,
                    oa.subtotal_price,oa.order_attach_number,oa.case_pay_type,oa.distribution_type,oa.trade_no,
                    oa.used_shop_member_coupon_id,s.store_name,oa.group_activity_attach_id')
                    ->select();
                $oaUpdate = $ogUpdate = $refundBalanceArr = $consumptionInc = $couponUpdate
                    = $refundWeChatArr = $refundGoodsArr = $lockKey = [];
                // 回滚库存
                $inventory = ['goods_id' => [], 'products_id' => []];
                if ($orderAttachData) {
                    foreach ($orderAttachData as $_oad) {
                        array_push($oaUpdate, [
                            'order_attach_id' => $_oad['order_attach_id'],
                            'status' => $info['is_auto'] ? 1 : 6,
                        ]);
                        if ($_oad['pay_type'] != 2 && !$info['is_auto']) {
                            if ($_oad['used_shop_member_coupon_id']) {
                                array_push($couponUpdate, $_oad['used_shop_member_coupon_id']);
                            }
                            if ($_oad['pay_channel'] == 3) {
                                // 余额支付的退款
                                array_push($refundBalanceArr, [
                                    'member_id' => $_oad['member_id'],
                                    'usable_money' => Db::raw('usable_money + ' . $_oad['subtotal_price']),
                                ]);
                                array_push($consumptionInc, [
                                    'member_id' => $_oad['member_id'],
                                    // 退款
                                    'type' => 3,
                                    'order_number' => $_oad['order_pay']['order_number'],
                                    'order_attach_number' => $_oad['order_attach_number'],
                                    'price' => $_oad['subtotal_price'],
                                    'way' => 3,
                                    'balance' => $_oad['member_pay']['usable_money'] + $_oad['subtotal_price'],
                                    'status' => 1,
                                ]);
                            } elseif ($_oad['pay_channel'] == 1) {
                                // 微信支付
                                array_push($refundWeChatArr, [
                                    'member_id' => $_oad['member_id'],
                                    'case_pay_type' => $_oad['case_pay_type'],
                                    'trade_no' => $_oad['trade_no'],
                                    'subtotal_price' => $_oad['subtotal_price'],
                                    'order_attach_number' => $_oad['order_attach_number'],
                                ]);
                            }
                        }
                        if ($_oad['order_goods_destroy']) {
                            foreach ($_oad['order_goods_destroy'] as $_ogd) {
                                array_push($ogUpdate, [
                                    'order_goods_id' => $_ogd['order_goods_id'],
                                    'status' => $info['is_auto'] ? ($_oad['distribution_type'] == 2 ? 2.1 : 1.1) : 6.1,
                                ]);
                                if (!$info['is_auto']) {
                                    // 增加商品库存[正常/限时抢购]
                                    array_push($inventory['goods_id'], [
                                        'goods_id' => $_ogd['goods_id'],
                                        'goods_number' => Db::raw('goods_number + ' . $_ogd['quantity']),
                                    ]);
                                    // 增加商品行锁
                                    array_push($lockKey, 'goods_id_' . $_ogd['goods_id']);
                                    if ($_ogd['products_id']) {
                                        array_push($inventory['products_id'], [
                                            'products_id' => $_ogd['products_id'],
                                            'attr_goods_number' => Db::raw('attr_goods_number + ' . $_ogd['quantity']),
                                        ]);
                                        // 增加商品规格行锁
                                        array_push($lockKey, 'products_id_' . $_ogd['products_id']);
                                    }
                                    $refundPrice = $_ogd['single_price'] * $_ogd['quantity'] * $_ogd['discount']
                                        - $_ogd['sub_share_shop_coupon_price'] - $_ogd['sub_share_platform_coupon_price']
                                        - $_ogd['subtotal_share_platform_packet_price'] + $_ogd['sub_freight_price'] - $_ogd['sub_fullSub_price'];
                                    array_push($refundGoodsArr, [
                                        'order_goods_id' => $_ogd['order_goods_id'],
                                        'type' => 1,
                                        'is_get_goods' => 1,
                                        'return_type' => 1,
                                        'phone' => '',
                                        'refund_amount' => $refundPrice,
                                        'origin_refund_amount' => $refundPrice,
                                        'reason' => '拼团失败退款',
                                        'status' => 1,
                                    ]);
                                }
                                // 推送消息[拼团失败|成功]
                                $pushServer = app('app\\interfaces\\behavior\\Push');
                                $pushServer->send([
                                    'tplKey' => 'active_goods_state',
                                    'openId' => $_oad['member_pay']['web_open_id'],
                                    'subscribe_time' => $_oad['member_pay']['subscribe_time'],
                                    'microId' => $_oad['member_pay']['micro_open_id'],
                                    'phone' => $_oad['member_pay']['phone'],
                                    'data' => [$info['is_auto'] ? 3 : 2, $_oad['store_name'], $_oad['member_pay']['nickname'], $_ogd['goods_name']],
                                    'inside_data' => [
                                        'member_id' => $_oad['member_id'],
                                        'type' => 1,
                                        'jump_state' => '2',
                                        'attach_id' => $_oad['group_activity_attach_id'],
                                        'file' => $_ogd->getData('file'),
                                    ],
                                    'sms_data' => [],
                                ]);
                            }
                        }
                    }
                }
                // 返回库存
                if ($lockKey) {
                    $lockService = app('app\\common\\service\\Lock', true);
                    // 加锁
                    $lockData = $lockService->lock($lockKey, 10000);
                    if (!$lockData) return true;
                    if ($inventory['goods_id']) {
                        $goods = app('app\\common\\model\\Goods', true);
                        $goods->allowField(true)->isUpdate(true)->saveAll($inventory['goods_id']);
                    }
                    if ($inventory['products_id']) {
                        $products = app('app\\common\\model\\Products', true);
                        $products->allowField(true)->isUpdate(true)->saveAll($inventory['products_id']);
                    }
                    // 解锁
                    $lockService->unlock($lockData);
                }
                // 返还用户优惠券[只还店铺的]
                if ($couponUpdate) {
                    $memberCoupon = app('app\\common\\model\\MemberCoupon', true);
                    $memberCoupon
                        ->allowField(true)
                        ->isUpdate(true)
                        ->save(['status' => 0, 'used_time' => null],
                            [['member_coupon_id', 'in', implode(',', $couponUpdate)]]);
                }
                if ($oaUpdate) {
                    $orderAttachModel->allowField(true)->isUpdate(true)->saveAll($oaUpdate);
                }
                if ($ogUpdate) {
                    $orderGoodsModel = new OrderGoods();
                    $orderGoodsModel->allowField(true)->isUpdate(true)->saveAll($ogUpdate);
                }
                // 余额退款
                if ($refundBalanceArr) {
                    $memberModel = new Member();
                    $memberModel->allowField(true)->isUpdate(true)->saveAll($refundBalanceArr);
                }
                if ($refundWeChatArr) {
                    $payType = [1 => 'app', 2 => 'applet', 3 => 'pc_login', 4 => 'mobile'];
                    app('app\\common\\Behavior')->appInit();
                    foreach ($refundWeChatArr as $_refundWeChatArr) {
                        $weChatApp = Factory::payment(config('wechat.')[$payType[$_refundWeChatArr['case_pay_type']]]);
                        $refundWeChatPrice = intval(strval($_refundWeChatArr['subtotal_price'] * 100));
                        $refundRet = $weChatApp->refund->byTransactionId($_refundWeChatArr['trade_no'], $_refundWeChatArr['order_attach_number'],
                            $refundWeChatPrice, $refundWeChatPrice, [
                                // 可在此处传入其他参数，详细参数见微信支付文档
                                'refund_desc' => '拼团失败退款',
                            ]);
                        $refundLogPath = Env::get('root_path') . 'public/wx_refund_log/' . date('Y-m');
                        if (!is_dir($refundLogPath)) {
                            mkdir($refundLogPath, 0755, true);
                        }
                        $refundLogMsg = str_repeat('-', 20) . date('Y-m-d H:i:s') . str_repeat('-', 20) . PHP_EOL;
                        $refundLogMsg .= var_export($refundRet, true) . PHP_EOL;
                        file_put_contents($refundLogPath . '/' . date('d') . '.log', $refundLogMsg, FILE_APPEND);
                    }
                }
                // 插入退款记录
                if ($refundGoodsArr) {
                    $orderGoodsRefundModel = new OrderGoodsRefund();
                    $orderGoodsRefundModel->allowField(true)->isUpdate(false)->saveAll($refundGoodsArr);
                }
                // 插入消费明细
                if ($consumptionInc) {
                    $consumptionModel = new Consumption();
                    $consumptionModel->allowField(true)->isUpdate(false)->saveAll($consumptionInc);
                }
                Db::commit();
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 拼团商品到期更改商品拼团下架状态
     * @return bool
     */
    public function groupGoodsExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $groupGoodsModel = new GroupGoods();
            // 查询拼团商品信息
            $info = $groupGoodsModel
                ->where([['group_goods_id', '=', $this->msg['id']]])
                ->field('group_goods_id,down_shelf_time,goods_id,status')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $groupGoodsModel->getLastSql() . PHP_EOL;
                return true;
            }
            if (strtotime($info['down_shelf_time']) <= time() && $info['down_shelf_time'] == date('Y-m-d')) {
                // 更改商品状态为拼团已下架,拼团默认价归0,人数上限归0
                (new Goods())
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save([
                        'goods_id' => $info['goods_id'],
                        'is_group' => 0,
                        'group_price' => 0,
                        'group_num' => 0,
                    ]);
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 限时抢购商品到期更改商品下架状态
     * @return bool
     */
    public function limitGoodsExpireChangeStatus()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            $limitModel = new Limit();
            // 查询限时抢购商品信息
            $info = $limitModel
                ->where([['limit_id', '=', $this->msg['id']]])
                ->field('limit_id,goods_id,down_shelf_time,status')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $limitModel->getLastSql() . PHP_EOL;
                return true;
            }
            if (strtotime($info['down_shelf_time']) <= time() && $info['down_shelf_time'] == date('Y-m-d')) {
                // 更改商品状态为限时抢购已下架,限时抢购默认价归0,默认库存归0
                (new Goods())
                    ->allowField(true)
                    ->isUpdate(true)
                    ->save([
                        'goods_id' => $info['goods_id'],
                        'is_limit' => 0,
                        'time_limit_price' => 0,
                        'time_limit_number' => 0,
                    ]);
                $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
                return true;
            }
            $this->res_msg .= 'result：data is not acted' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 自动收货(发货后X天)
     * @return bool
     */
    public function autoCollect()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
          
            // 防呆
            if (!OrderAttach::get($this->msg['id']))
            {
                return TRUE;
            }
          
            // 查询店铺订单信息
            $orderAttachModel = new OrderAttach();
            $info = $orderAttachModel
                ->where([
                    ['order_attach_id', '=', $this->msg['id']],
                    ['status', '=', 2],   //2配送中
                ])
                ->field('order_attach_id,member_id,status,pay_type,store_id,subtotal_price,after_sale_times')
                ->with(['orderGoodsConfirmCollect'])
                ->find();
            if (empty($info) || !$info['order_goods_confirm_collect']) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $orderAttachModel->getLastSql() . PHP_EOL;
                return true;
            }
            Db::startTrans();
            $info->status = 3;   //3已完成
            // 收货时间和交易时间
            $info->deal_time = date('Y-m-d H:i:s');
            $info->save();
            $orderGoodsModel = new OrderGoods();
            $orderGoodsModel
                ->isUpdate(true)
                ->save(['status' => 3.1, 'redo_status' => null], [
                    ['order_attach_id', '=', $this->msg['id']],
                    ['status', 'in', '2.1,5.1,5.2,5.3,5.4,5.5,5.6,5.7']
                ]);
            // 含有退款退货的商品订单,删除退款退货记录
            $refundArr = $orderGoodsId = [];
            foreach ($info['order_goods_confirm_collect'] as $item) {
                if ($item->order_goods_refund_list) {
                    array_push($refundArr, $item->order_goods_refund_list->order_goods_refund_id);
                }
                array_push($orderGoodsId, $item['order_goods_id']);
            }
            if ($refundArr) OrderGoodsRefund::destroy(implode(',', $refundArr));
            // 货到付款订单记录店铺资金记录
            if ($info->pay_type == 2) {
                $storeCapitalData = [
                    [
                        'store_id' => $info['store_id'],
                        'type' => 3,
                        // 交易中
                        'status' => 2,
                        'order_attach_id' => $info['order_attach_id'],
                        'price' => $info['subtotal_price'],
                    ]
                ];
                Hook::exec(['app\\interfaces\\behavior\\StoreCapital', 'record'], $storeCapitalData);
            }
            Db::commit();
            Env::load(Env::get('APP_PATH') . 'common/ini/.config');
            // 计划任务自动评价
            (new Beanstalk())->put(json_encode([
                'queue' => 'autoEvaluate',
                'id' => $orderGoodsId,
                'uid' => $info['member_id'],
                'time' => date('Y-m-d H:i:s'),
            ]), Env::get('good_reputation') * 86400);
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 自动评价(收货后X天)
     * @return bool
     */
    public function autoEvaluate()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            Env::load(Env::get('APP_PATH') . 'common/ini/.config');
            $orderGoodsModel = new OrderGoods();
            $goodsEvaluateModel = new GoodsEvaluate();
            // 查询商品订单数据
            $orderGoodsData = $orderGoodsModel::withTrashed()
                ->where([
                    ['order_goods_id', 'in', implode(',', $this->msg['id'])],
                    // 3.1已收货
                    // 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意) 5.3 商家同意退货(退货第一步的退款成功)
                    // 5.4 申请退货(退货第二步,填写物流发货) 5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
                    ['status', 'in', '3.1, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7']
                ])
                ->with(['orderGoodsEvaluate', 'goodsEvaluate', 'storeEvaluate', 'orderGoodsRefundList'])
                ->field('order_goods_id,store_id,order_attach_id,goods_id,status,goods_attr')
                ->select();
            if ($orderGoodsData->isEmpty()) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $orderGoodsModel->getLastSql() . PHP_EOL;
                return true;
            }
            // 更改商品订单状态(可能更改店铺订单状态)
            $orderGoodsUpdate = $goodsEvaluateUpdate = $storeEvaluateUpdate =
            $lockKey = $refundArr = $goodsEvaluateData = $evaluateData = [];
            $orderAttachId = '';
            foreach ($orderGoodsData as $key => $item) {
                // 包含退款退货记录时,强制抹除
                if (!is_null($item->order_goods_refund_list)) {
                    array_push($refundArr, $item->order_goods_refund_list->order_goods_refund_id);
                }
                // 记录店铺订单id
                if ($orderAttachId === '') {
                    $orderAttachId = $item->order_attach_id;
                }
                // 记录商品订单更新数据
                array_push($orderGoodsUpdate, ['order_goods_id' => $item->order_goods_id, 'status' => 4.1]);
                // 记录商品更新数据
                if ($item->goods_evaluate) {
                    array_push($goodsEvaluateUpdate, [
                        'goods_id' => $item->goods_evaluate->goods_id,
                        'comments_number' => Db::raw('comments_number + 1')
                    ]);
                    array_push($lockKey, 'goods_id_' . $item->goods_evaluate->goods_id);
                }
                // 记录店铺更新数据
                if ($item->store_evaluate) {
                    array_push($storeEvaluateUpdate, [
                        'store_id' => $item->store_evaluate->store_id,
                        'grade' => Db::raw('grade + 5'),
                    ]);
                    array_push($lockKey, 'store_id_' . $item->store_evaluate->store_id);
                }
                // 记录商品评价数据
                array_push($evaluateData, [
                    'order_goods_id' => $item['order_goods_id'],
                    'member_id' => $this->msg['uid'],
                    'goods_id' => $item['goods_id'],
                    'store_id' => $item['store_id'],
                    'star_num' => 5,
                    'content' => '很好,很不错',
                    'express_content' => '物流很快',
                    'attr' => $item['goods_attr'],
                    'express_star_num' => 5,
                    'store_star_num' => 5,
                    'is_anonymous' => 1,
                ]);
            }
            Db::startTrans();
            // 含有退款退货的商品订单,删除退款退货记录
            if ($refundArr) {
                OrderGoodsRefund::destroy(implode(',', $refundArr));
            }
            // 更新商品订单状态
            if ($orderGoodsUpdate) {
                $orderGoodsModel->allowField(true)->isUpdate(true)->saveAll($orderGoodsUpdate);
            }
            // 新增商品评价
            if ($evaluateData) {
                $goodsEvaluateModel->allowField(true)->isUpdate(false)->saveAll($evaluateData);
            }
            $lock = app('app\\common\\service\\Lock', true);
            $lockData = $lock->lock($lockKey, 10000);
            // 更新商品评价数
            if ($goodsEvaluateUpdate) {
                $goodsModel = app('app\\common\\model\\Goods', true);
                $goodsModel->allowField(true)->isUpdate(true)->saveAll($goodsEvaluateUpdate);
            }
            // 更新店铺评分
            if ($storeEvaluateUpdate) {
                $storeModel = app('app\\common\\model\\Store', true);
                $storeModel->allowField(true)->isUpdate(true)->saveAll($storeEvaluateUpdate);
            }
            $lock->unlock($lockData);
            // 检测其他商品是否全部是已评价状态,进而更新主店铺订单状态为4已关闭
            $orderAttachModel = new OrderAttach();
            $orderAttachData = $orderAttachModel
                ->where([
                    ['order_attach_id', '=', $orderAttachId],
                    ['status', 'not in', 4],    // 不包括4已关闭
                ])
                ->with(['orderGoodsReport'])
                ->field('order_attach_id,status,after_sale_times')
                ->find();
            if (!is_null($orderAttachData) && count($orderAttachData['order_goods_report']) == 0) {
                $orderAttachData->isUpdate(true)->save(['order_attach_id' => $orderAttachId, 'status' => 4]);
            }
            // 关闭售后通道
            (new Beanstalk())->put(json_encode([
                'queue' => 'autoCloseSaleAfter',
                'id' => $orderAttachId,
                'time' => date('Y-m-d H:i:s')
            ]), $orderAttachData['after_sale_times'] * 86400);
            Db::commit();
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 确认收货后X[比自动评价的X大]天关闭售后通道
     * @return bool
     */
    public function autoCloseSaleAfter()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            if (is_array($this->msg['id']) && !empty($this->msg['id'])) {
                $this->msg['id'] = $this->msg['id'][0];
            }
            // 查询店铺订单状态
            $orderAttachModel = new OrderAttach();
            $info = $orderAttachModel::withTrashed()
                ->where([
                    ['order_attach_id', '=', $this->msg['id']],
                    ['deal_time', 'exp', Db::raw('is not null')],        // 已经收货过的店铺订单
                ])
                ->field('order_attach_id,status,sale_after_status,subtotal_price,
                subtotal_freight_price,member_id,pay_channel')
                ->with(['orderGoodsDestroy', 'member'])
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                return true;
            }
            $distributionInfo = (new Distribution())::withTrashed()
                ->where([
                    ['member_id', '=', $info['member_id']],
                ])
                ->find();
            // 检测是否为用户首次消费
            $orderGoodsModel = new OrderGoods();
            $firstC = $orderGoodsModel::withTrashed()
                ->where([
                    ['member_id', '=', $info['member_id']],
                    ['status', 'in', '3.1,4.1'],            // 已收货或已评价
                ])
                ->order(['create_time' => 'asc'])
                ->value('order_goods_id');
            $firstCF = false;
            Db::startTrans();
            // 关闭售后通道[正在处理的订单(退款退货中的)依旧进行]
            $info->sale_after_status = 0;
            $info->save();
            // 交易完成订单赠送购物积分,购物成长值,评价积分
            if (in_array($info['status'], [3, 4]) && $info['order_goods_destroy']) {
                // 统计初始化
                $hookArgs = [
                    'integral_total_price' => 0,
                    'growth_total_price' => 0,
                    'count' => 0,
                    'member_id' => $info['member_id'],
                    'web_open_id' => $info['member']['web_open_id'],
                    'micro_open_id' => $info['member']['micro_open_id'],
                    'subscribe_time' => $info['member']['subscribe_time'],
                    'phone' => $info['member']['phone'],
                    'distribution' => [
                        'distribution_id' => is_null($distributionInfo) ? '' : $distributionInfo['distribution_id'],
                        'order_sum_offset' => $info['subtotal_price'] - $info['subtotal_freight_price'],
                        'order_num_offset' => 1,
                        'update' => [],
                    ],
                ];
                foreach ($info['order_goods_destroy'] as $item) {
                    if ($item['order_goods_id'] == $firstC) {
                        $firstCF = true;
                    }
                    $valid_price = $item['subtotal_price'] - $item['sub_freight_price'];
                    $valid_price = ($valid_price >= 0 ? $valid_price : 0);
                    if (in_array($item->status, [3.1, 4.1])) {
                        $hookArgs['integral_total_price'] += $valid_price;
                        // 仅余额支付时赠送成长值
                        if ($info['pay_channel'] == 3) {
                            $hookArgs['growth_total_price'] += $valid_price;
                        }
                        if ($item->status == 4.1) {
                            $hookArgs['count']++;
                        }
                        // 分销订单下累积更新变量
                        if ($item['distribution_book_close_sale']) {
                            // 增加会员分销数据
                            array_push($hookArgs['distribution']['update'], [
                                'distribution_book_id' => $item['distribution_book_close_sale']['distribution_book_id'],
                                'distributor_a' => $item['distribution_book_close_sale']['distributor_a'],
                                'distributor_a_brokerage' => $item['distribution_book_close_sale']['distributor_a_brokerage'],
                                'distributor_b' => $item['distribution_book_close_sale']['distributor_b'],
                                'distributor_b_brokerage' => $item['distribution_book_close_sale']['distributor_b_brokerage'],
                                'distributor_c' => $item['distribution_book_close_sale']['distributor_c'],
                                'distributor_c_brokerage' => $item['distribution_book_close_sale']['distributor_c_brokerage'],
                            ]);
                        }
                    }
                }
                // 购物积分
                if ($hookArgs['integral_total_price']) {
                    Hook::exec(['app\\interfaces\\behavior\\Integral', 'inc'], $hookArgs);
                }
                // 购物成长值
                if ($hookArgs['growth_total_price']) {
                    Hook::exec(['app\\interfaces\\behavior\\Growth', 'inc'], $hookArgs);
                }
                // 评价积分和成长值
                if ($hookArgs['count']) {
                    Hook::exec(['app\\interfaces\\behavior\\Integral', 'evaluateInc'], $hookArgs);
                    Hook::exec(['app\\interfaces\\behavior\\Growth', 'evaluateInc'], $hookArgs);
                }
                Env::load(Env::get('APP_PATH') . 'common/ini/.config');
                if (Env::get('is_red_packet') == 1) {
                    //用户首次消费赠送平台红包
                    if ($firstCF) {
                        Hook::exec(['app\\interfaces\\behavior\\PtPacket', 'firstConsumption'], $hookArgs);
                    }
                }
                // 更新分销数据
                Hook::exec(['app\\interfaces\\behavior\\Distribution', 'distributionUpdate'], $hookArgs);
            }
            $hookArgs = null;
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 退款退货失败自动回滚到变化状态
     * @return bool
     */
    public function refundToRedo()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            // 查询商品订单信息
            $orderGoodsModel = new OrderGoods();
            $info = $orderGoodsModel
                ->where([
                    ['order_goods_id', '=', $this->msg['id']],
                    ['status', 'in', '5.5,5.7']
                ])
                ->field('order_goods_id,redo_status,status')
                ->find();
            if (empty($info) || is_null($info['redo_status'])) {
                $this->res_msg .= 'result：data not found，msg is ACK' . PHP_EOL;
                return true;
            }
            $info->status = $info->redo_status;
            $info->redo_status = null;
            $info->save();
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    /**
     * 分销商降级自测
     * @return bool|string
     */
    public function distributionDowngradeCheck()
    {
        try {
            if (!$this->msg) {
                $this->res_msg .= 'result：msg is empty，msg is ACK' . PHP_EOL;
                return true;
            }
            // 检测是否含有升级过滤缓存
            $redis = Cache::handler();
            $redis->select(5);
            $prefix = Config::get('cache.default')['prefix'];
            $passJson = $redis->get($prefix . 'downgrade_distribution_' . $this->msg['id']);
            if ($passJson === false) {
                // 无标识ack
                $this->res_msg .= 'result：flag is not found，msg is ACK' . PHP_EOL;
                return true;
            }
            $passArr = explode('_', $passJson);
            if (!in_array($this->msg['flag'], $passArr)) {
                // 不匹配直接ack
                $this->res_msg .= 'result：now flag is ' . $this->msg['flag'] . ' disMatch ' . $passJson . '，msg is ACK' . PHP_EOL;
                return true;
            }
            // 删除当前有效标识
            $redis->del($prefix . 'downgrade_distribution_' . $this->msg['id']);
            // 查询分销商信息
            $distributionModel = new Distribution();
            $info = $distributionModel
                ->alias('d')
                ->where([['distribution_id', '=', $this->msg['id']]])
                ->join('distribution_level dl', 'dl.distribution_level_id = d.distribution_level_id')
                ->join('member m', 'm.member_id = d.member_id')
                ->field('d.distribution_id,d.cycle_consumer,d.distribution_level_id,d.cycle_brokerage,dl.level_weight,
                total_brokerage,dl.downgrade_brokerage_cycle,dl.downgrade_brokerage_sum,dl.downgrade_order_cycle,dl.level_title,
                dl.downgrade_order_sum,dl.level_weight,d.cycle_up_order_sum,d.cycle_up_order_num,d.cycle_up_brokerage,
                d.cycle_up_referrer_num,d.cycle_up_relation_num')
                ->find();
            if (empty($info)) {
                $this->res_msg .= 'result：data not found;not distributor，msg is ACK' . PHP_EOL;
                $this->res_msg .= 'sql：' . $distributionModel->getLastSql() . PHP_EOL;
                return true;
            }
            //是否达标(默认达标不降级)
            $standard = [
                ['type', 0],
                ['brokerage_sum', $info['cycle_brokerage'], $info['downgrade_brokerage_sum'], $info['downgrade_brokerage_cycle']],
                ['order_sum', $info['cycle_consumer'], $info['downgrade_order_sum'], $info['downgrade_order_cycle']],
            ];
            // [检测项]按佣金计算[周期佣金金额不达标]
            if ($this->msg['brokerage'] && $info['downgrade_brokerage_sum'] > $info['cycle_brokerage']) {
                $standard[0] = ['type', 1];
            }
            // [检测项]按订单计算[周期订单金额不达标]
            if ($this->msg['order'] && $info['downgrade_order_sum'] > $info['cycle_consumer']) {
                $standard[0] = ($standard[0] == ['type', 1]) ? ['type', 2] : ['type', 1];
            }
            $standardMsg = implode(';', array_map(function ($v) {
                return implode(',', $v);
            }, $standard));
            // 确认降级
            if ($standard[0] != ['type', 0]) {
                $info->cycle_brokerage = 0;             // 周期性佣金金额归零
                $info->cycle_consumer = 0;              // 周期性订单金额归零
                $info->cycle_up_order_sum = 0;          // 周期性升级策略归零
                $info->cycle_up_order_num = 0;
                $info->cycle_up_brokerage = 0;
                $info->cycle_up_referrer_num = 0;
                $info->cycle_up_relation_num = 0;
                $distributionLevelModel = new DistributionLevel();
                $down_level = $distributionLevelModel
                    ->where([
                        ['level_weight', '<', $info['level_weight']],
                        ['distribution_level_id', '>', 0],
                    ])
                    ->order(['level_weight' => 'desc'])
                    ->field('distribution_level_id,level_weight,level_title,downgrade_brokerage_cycle,
                    downgrade_order_cycle')
                    ->find();
                // 含有下级分销商等级
                if ($down_level) {
                    // 添加降级记录
                    (new DistributionChangeRecord())->save([
                        'distribution_id' => $this->msg['id'],
                        'change_type' => 2,
                        'now_level_id' => $down_level['distribution_level_id'],
                        'now_level_title' => $down_level['level_title'],
                        'ago_level_id' => $info['distribution_level_id'],
                        'ago_level_title' => $info['level_title'],
                        'upgrade_down_reason' => $standardMsg,
                    ]);
                    // 降级变更等级id
                    $info->distribution_level_id = $down_level['distribution_level_id'];
                    if ($down_level['level_weight'] > 0) {
                        // 非最低等级发送降级消息
                        $checkDown[] = [
                            'distribution_id' => $this->msg['id'],
                            'downgrade_brokerage_cycle' => $down_level['downgrade_brokerage_cycle'],
                            'downgrade_order_cycle' => $down_level['downgrade_order_cycle'],
                        ];
                        Hook::exec(['app\\interfaces\\behavior\\Distribution', 'sendDownMsg'], $checkDown);
                    }
                } else {
                    $this->res_msg .= "is minimum level, no change" . PHP_EOL;
                }
                Db::startTrans();
                $info->save();      // 更新分销商数据
                Db::commit();
            } else {
                $this->res_msg .= "达标---" . $standardMsg . PHP_EOL;
            }
            $info = null;
            // 消息ack,若模块关闭,等待模块开启再次计算
            $this->res_msg .= 'result：operate msg ok' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            $this->res_msg .= 'result：file-' . $e->getFile() . 'line-' . $e->getLine() . 'msg-' . $e->getMessage() . PHP_EOL;
            return $this->res_msg;
        }
    }
    
    public function __destruct()
    {
        if ($this->file_name) {
            file_put_contents($this->file_name, $this->res_msg, FILE_APPEND);
        }
    }
    
}