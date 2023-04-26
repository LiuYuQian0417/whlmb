<?php
declare(strict_types=1);

namespace app\interfaces\controller\order;

use app\common\model\Goods;
use app\common\model\GoodsEvaluate;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\OrderGoodsRefund;
use app\common\model\Store;
use app\common\service\Beanstalk;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Config;
use think\facade\Env;

/**
 * 评价
 * Class Evaluate
 * @package app\interfaces\controller\order
 */
class Evaluate extends BaseController
{
    /**
     * 发表评价
     * @param RSACrypt $crypt
     * @param OrderGoods $orderGoods
     * @param GoodsEvaluate $goodsEvaluate
     * @param OrderAttach $orderAttach
     * @param Goods $goods
     * @param Store $store
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function report(RSACrypt $crypt,
                           OrderGoods $orderGoods,
                           GoodsEvaluate $goodsEvaluate,
                           OrderAttach $orderAttach,
                           Goods $goods, Store $store)
    {
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderGoods->valid($args, 'report');
        if (is_string($args['goods_set'])) {
            $args['goods_set'] = json_decode($args['goods_set'], true);
        }
        $orderGoodsData = $orderGoods
            ->where([
                ['order_goods_id', 'in', array_column($args['goods_set'], 'order_goods_id')],
                // 3.1已收货 4.1已评价(覆盖系统评价)
                // 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意) 5.3 商家同意退货(退货第一步的退款成功)
                // 5.4 申请退货(退货第二步,填写物流发货) 5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
                ['status', 'in', '3.1,4.1, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7']
            ])
            ->with(['orderGoodsEvaluate', 'goodsEvaluate', 'storeEvaluate', 'orderGoodsRefundList'])
            ->field('order_goods_id,store_id,order_attach_id,goods_id,status')
            ->select();
        if ($orderGoodsData->count() !== count($args['goods_set'])) {
            return $crypt->response([
                'code' => -1,
                'message' => '订单商品不存在',
            ], true);
        }
        // 更改商品订单状态(可能更改店铺订单状态)
        $orderGoodsUpdate = $goodsEvaluateUpdate = $storeEvaluateUpdate = $lockKey = $refundArr = [];
        $orderAttachId = '';
        foreach ($orderGoodsData as $key => $item) {
            if ($orderAttachId === '') {
                $orderAttachId = $item->order_attach_id;
            }
            if ($item['status'] != 4.1) {
                if (!is_null($item->order_goods_refund_list)) {
                    array_push($refundArr, $item->order_goods_refund_list->order_goods_refund_id);
                }
                array_push($orderGoodsUpdate, ['order_goods_id' => $item->order_goods_id, 'status' => 4.1]);
                if ($item->goods_evaluate) {
                    array_push($goodsEvaluateUpdate, [
                        'goods_id' => $item->goods_evaluate->goods_id,
                        'comments_number' => Db::raw('comments_number + 1')
                    ]);
                    array_push($lockKey, 'goods_id_' . $item->goods_evaluate->goods_id);
                }
                if ($item->store_evaluate) {
                    array_push($storeEvaluateUpdate, [
                        'store_id' => $item->store_evaluate->store_id,
                        'grade' => Db::raw('grade + ' . $args['store_star_num']),
                    ]);
                    array_push($lockKey, 'store_id_' . $item->store_evaluate->store_id);
                }
            }
            foreach ($args['goods_set'] as $_key => &$_item) {
                if ($_item['order_goods_id'] == $item['order_goods_id']) {
                    $_item['goods_id'] = $item['goods_id'];
                    $_item['store_id'] = $item['store_id'];
                    $_item['member_id'] = $args['member_id'];
                    $_item['store_star_num'] = $args['store_star_num'];
                    $_item['express_star_num'] = $args['express_star_num'];
                    $_item['express_content'] = $args['express_content'];
                }
            }
        }
        Db::startTrans();
        // 新增商品评价
        $goodsEvaluate
            ->allowField(true)
            ->isUpdate(false)
            ->saveAll($args['goods_set']);
        // 含有退款退货的商品订单,删除退款退货记录
        if (!empty($refundArr)) {
            OrderGoodsRefund::destroy(implode(',', $refundArr));
        }
        // 更新商品订单状态
        if (!empty($orderGoodsUpdate)) {
            $orderGoods
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($orderGoodsUpdate);
        }
        $lock = app('app\\common\\service\\Lock');
        $lockData = $lock->lock($lockKey, 10000);
        if (!$lockData) {
            return $crypt->response([
                'code' => -1,
                'message' => '网络繁忙,请重试',
            ], true);
        }
        // 更新商品评价数
        if (!empty($goodsEvaluateUpdate)) {
            $goods
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($goodsEvaluateUpdate);
        }
        // 更新店铺评分
        if (!empty($storeEvaluateUpdate)) {
            $store
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($storeEvaluateUpdate);
        }
        $lock->unlock($lockData);
        // 检测其他商品是否全部是已评价状态,进而更新主店铺订单状态为4已关闭
        $orderAttachData = $orderAttach
            ->where([
                ['order_attach_id', '=', $orderAttachId],
                ['status', 'not in', 4],    // 不包括4已关闭
            ])
            ->with(['orderGoodsReport'])
            ->field('order_attach_id,status,after_sale_times')
            ->find();
        if (!is_null($orderAttachData) && count($orderAttachData['order_goods_report']) == 0) {
            $orderAttachData->status = 4;
            $orderAttachData->save();
        }
        Db::commit();
        // 关闭售后通道
        (new Beanstalk())->put(json_encode([
            'queue' => 'autoCloseSaleAfter',
            'id' => $orderAttachId,
            'time' => date('Y-m-d H:i:s')]), $orderAttachData['after_sale_times'] * 86400);
        return $crypt->response([
            'code' => 0,
            'message' => '提交成功',
        ], true);
    }

    /**
     * 发表评价
     * @param RSACrypt $crypt
     * @param OrderGoods $orderGoods
     * @param GoodsEvaluate $goodsEvaluate
     * @param OrderAttach $orderAttach
     * @param Goods $goods
     * @param Store $store
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function reportIos(RSACrypt $crypt,
                              OrderGoods $orderGoods,
                              GoodsEvaluate $goodsEvaluate,
                              OrderAttach $orderAttach,
                              Goods $goods,
                              Store $store)
    {
        Config::set('rsa.debug', false);
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $orderGoods->valid($args, 'report');
        if (is_string($args['goods_set'])) {
            $args['goods_set'] = json_decode($args['goods_set'], true);
        }
        $orderGoodsData = $orderGoods
            ->where([
                ['order_goods_id', 'in', array_column($args['goods_set'], 'order_goods_id')],
                // 3.1已收货
                // 5.1 申请退款 5.2 申请退款(退货第一步退款,需商家同意) 5.3 商家同意退货(退货第一步的退款成功)
                // 5.4 申请退货(退货第二步,填写物流发货) 5.5退款失败(仅退款) 5.6退货失败(商家未收到货) 5.7退款失败(退货第一步)
                ['status', 'in', '3.1, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7']
            ])
            ->with(['orderGoodsEvaluate', 'goodsEvaluate', 'storeEvaluate', 'orderGoodsRefundList'])
            ->field('order_goods_id,store_id,order_attach_id,goods_id,status')
            ->select();
        if ($orderGoodsData->count() !== count($args['goods_set'])) {
            return $crypt->response([
                'code' => -6,
                'message' => '订单商品不存在',
            ], true);
        }
        // 更改商品订单状态(可能更改店铺订单状态)
        $orderGoodsUpdate = $goodsEvaluateUpdate = $storeEvaluateUpdate = $lockKey = $refundArr = [];
        $orderAttachId = '';
        foreach ($orderGoodsData as $key => $item) {
            if (!is_null($item->order_goods_refund_list)) {
                array_push($refundArr, $item->order_goods_refund_list->order_goods_refund_id);
            }
            if ($orderAttachId === '') {
                $orderAttachId = $item->order_attach_id;
            }
            array_push($orderGoodsUpdate, ['order_goods_id' => $item->order_goods_id, 'status' => 4.1]);
            if ($item->goods_evaluate) {
                array_push($goodsEvaluateUpdate, [
                    'goods_id' => $item->goods_evaluate->goods_id,
                    'comments_number' => Db::raw('comments_number + 1')
                ]);
                array_push($lockKey, 'goods_' . $item->goods_evaluate->goods_id);
            }
            if ($item->store_evaluate) {
                array_push($storeEvaluateUpdate, [
                    'store_id' => $item->store_evaluate->store_id,
                    'grade' => Db::raw('grade + ' . $args['store_star_num']),
                ]);
                array_push($lockKey, 'store_' . $item->store_evaluate->store_id);
            }
            foreach ($args['goods_set'] as $_key => &$_item) {
                if ($_item['order_goods_id'] == $item['order_goods_id']) {
                    $_item['goods_id'] = $item['goods_id'];
                    $_item['member_id'] = $args['member_id'];
                    $_item['store_star_num'] = $args['store_star_num'];
                    $_item['express_star_num'] = $args['express_star_num'];
                    $_item['express_content'] = $args['express_content'];
                }
            }
        }
        Db::startTrans();
        // 含有退款退货的商品订单,删除退款退货记录
        if (!empty($refundArr)) {
            OrderGoodsRefund::destroy(implode(',', $refundArr));
        }
        // 更新商品订单状态
        if (!empty($orderGoodsUpdate)) {
            $orderGoods
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($orderGoodsUpdate);
        }
        // 新增商品评价
        $goodsEvaluate
            ->allowField(true)
            ->isUpdate(false)
            ->saveAll($args['goods_set']);
        $lock = app('app\\common\\service\\Lock');
        $lockData = $lock->lock($lockKey, 10000);
        if (!$lockData) {
            return $crypt->response([
                'code' => -1,
                'message' => '网络繁忙,请重试',
            ], true);
        }
        // 更新商品评价数
        if ($goodsEvaluateUpdate) {
            $goods
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($goodsEvaluateUpdate);
        }
        // 更新店铺评分
        if ($storeEvaluateUpdate) {
            $store
                ->allowField(true)
                ->isUpdate(true)
                ->saveAll($storeEvaluateUpdate);
        }
        $lock->unlock($lockData);
        // 检测其他商品是否全部是已评价状态,进而更新主店铺订单状态为4已关闭
        $orderAttachData = $orderAttach
            ->where([
                ['order_attach_id', '=', $orderAttachId],
                ['status', 'not in', 4],    // 不包括4已关闭
            ])
            ->with(['orderGoodsReport'])
            ->field('order_attach_id,status,after_sale_times')
            ->find();
        if (count($orderAttachData['order_goods_report']) == 0) {
            $orderAttachData->status = 4;
            $orderAttachData->save();
        }
        Db::commit();
        // 关闭售后通道
        (new Beanstalk())->put(json_encode([
            'queue' => 'autoCloseSaleAfter',
            'id' => $orderAttachId,
            'time' => date('Y-m-d H:i:s')]), $orderAttachData['after_sale_times'] * 86400);
        return $crypt->response([
            'code' => 0,
            'message' => '提交成功',
        ], true);

    }

    /**
     * 我的评价列表
     * @param RSACrypt $crypt
     * @param GoodsEvaluate $goodsEvaluate
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myEvaluateList(RSACrypt $crypt,
                                   GoodsEvaluate $goodsEvaluate)
    {
        $args = $crypt->request();
        $args['member_id'] = request(0)->mid;
        $where = [
            ['member_id', '=', $args['member_id']],
            ['status', '=', 1],   //已审核
        ];
        // 含图
        if ($args['type'] == 1) {
            array_push($where, ['multiple_file', '<>', '']);
        }
        $myEvaluateData = $goodsEvaluate
            ->where($where)
            ->with(['orderGoodsMyEvaluate'])
            ->field('goods_evaluate_id,goods_id,star_num,store_star_num,content,order_goods_id,
                date_format(create_time,"%Y-%m-%d") as format_create_time,multiple_file,video')
            ->append(['video_snapshot'])
            ->order(['update_time' => 'desc'])
            ->paginate($goodsEvaluate->pageLimits, false);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $myEvaluateData,
        ], true);

    }
}