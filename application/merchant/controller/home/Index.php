<?php
declare(strict_types=1);

namespace app\merchant\controller\home;

use app\interfaces\controller\common\Order;
use think\Controller;
use think\Db;
use think\Request;
use mrmiao\encryption\RSACrypt;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\Goods;
use app\common\model\Store;
use app\common\model\Member;


class Index extends Controller
{
    /**
     * 首页
     * @param Request $request
     * @param OrderAttach $orderAttach
     * @param OrderGoods $orderGoods
     * @param Goods $goods
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, OrderAttach $orderAttach, OrderGoods $orderGoods, Goods $goods)
    {
        $param = $request->param();
        $member_id = $param['member_id'];
        //商家身份
        if ($param['m_type'] == 1) {
            $today_price_where = [
                ['store_id', 'eq', $member_id],
                ['status', 'in', '1,2,3,4'],
            ];
            //今日支付金额
            $today_price = $orderAttach->where($today_price_where)->whereTime('pay_time', 'today')->sum('subtotal_price');
            //昨日支付金额
            $yesterday_price = $orderAttach->where($today_price_where)->whereTime('pay_time', 'yesterday')->sum('subtotal_price');
            //代发货
            $drop_shipping_where = [
                ['store_id', 'eq', $member_id],
                ['status', 'eq', 1],
            ];
            $drop_shipping = $orderAttach->where($drop_shipping_where)->count();
            //待退款
            $for_refund_where = [
                ['store_id', 'eq', $member_id],
                ['status', 'eq', 5.1],
            ];
            $for_refund = $orderGoods->where($for_refund_where)->count();
            //商品销量排行
            $goods_market_where = [
                ['goods.store_id', 'eq', $member_id],
                ['is_putaway', 'eq', 1],
                ['review_status', 'eq', 1],
            ];
            $goods_market_list = $goods->alias('goods')
                ->join('store store', 'store.store_id = goods.store_id')
                ->where($goods_market_where)
                ->field('goods_id,goods_name,file,goods.create_time,goods.sales_volume,store.shop')
                ->order('sales_volume desc')->limit(5)->select();
//
//            //商品访客排行


        } //平台身份
        else {

            $today_price_where['status'] = ['in', '1,2,3,4'];
            //今日支付金额
            $today_price = $orderAttach->where($today_price_where)->whereTime('pay_time', 'today')->sum('subtotal_price');
            //昨日支付金额
            $yesterday_price = $orderAttach->where($today_price_where)->whereTime('pay_time', 'yesterday')->sum('subtotal_price');
            //代发货
            $drop_shipping_where['statue'] = ['eq', 1];
            $drop_shipping = $orderAttach->where($drop_shipping_where)->count();
            //待退款
            $for_refund_where['statue'] = ['eq', 5.1];
            $for_refund = $orderGoods->where($for_refund_where)->count();
            //商品销量排行
            $goods_market_where['is_putaway'] = 1;
            $goods_market_where['review_status'] = 1;
            $goods_market_list = $goods->where($goods_market_where)
                ->join('store', 'store.store_id = goods.store_id')
                ->field('goods.goods_id,goods.goods_name,goods.file,goods.create_time,goods.sales_volume,store.shop')
                ->order('sales_volume desc')->limit(5)->select();

            //商品支付金额排行
            $payment_amount_where['review_status'] = 1;
            $payment_amount_where['is_putaway'] = 1;
            $payment_amount_list = $goods->where($payment_amount_where)
                ->join('order_goods', 'goods.goods_id = order_goods.goods_id')
                ->field('sum(order_goods.subtotal_price) as all_price')
                ->order('all_price desc')
                ->limit(5)->select();

            //商品访客排行

        }

        $result['today_price'] = $today_price;
        $result['yesterday_price'] = $yesterday_price;
        $result['drop_shipping'] = $drop_shipping;
        $result['for_refund'] = $for_refund;
        $result['goods_market_list'] = $goods_market_list;
        $result['payment_amount_list'] = $payment_amount_list;

        return json([
            'code' => 0,
            'message' => '成功',
            'data' => $result,
        ]);
    }

    /**
     * 商品排行
     * @param Request $request
     * @param Goods $goods
     * @return \think\response\Json
     * @throws \think\exception\DbException
     *
     */
        public function ranking_list(Request $request, Goods $goods)
        {
            $param = $request->param();
            $member_id = $param['member_id'];
            if($param['list_type'] == 1)
            {
                //商家身份
                if ($param['m_type'] == 1) {
                    $condition[] = ['goods.store_id', '=', $member_id];

                }
                //商品销量排行
                $condition[] = ['is_putaway', '=', 1];
                $condition[] = ['review_status', '=', 1];
                $result = $goods->alias('goods')
                    ->join('store store', 'store.store_id = goods.store_id')
                    ->where($condition)
                    ->field('goods_id,goods_name,file,goods.create_time,goods.sales_volume,store.shop,store.store_id')
                    ->order('sales_volume desc')->paginate(5);
            }
            elseif ($param['list_type'] == 2)
            {
                //商家身份
                if ($param['m_type'] == 1) {
                    $condition[] = ['store_id', '=', $member_id];
                }
                //商品支付金额排行
                $condition[] = ['review_status', '=', 1];
                $condition[] = ['is_putaway', '=', 1];

                $result = $goods
//                ->alias('goods')
                    ->field('goods_id,goods_name,file,create_time,sales_volume')

                    ->withSum('GoodsAllPrice','subtotal_price')
                ->where($condition)
                    ->order('goods_all_price_sum desc')
                    ->paginate(5);

                foreach ($result as $value)
                {
                    if($value['goods_all_price_sum'] == null) $value['goods_all_price_sum'] = 0;
                }
            }
            else
            {
                $result = array();
            }


            $data['code'] = 0;
            $data['message'] = '成功';
            $data['data'] = $result;
            return json($data);
        }

    /**
     * 首页搜索
     * @param Request $request
     * @param Store $store
     * @param Goods $goods
     * @param OrderAttach $orderAttach
     * @param Member $member
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
        public function index_seek(Request $request, Store $store, Goods $goods, OrderAttach $orderAttach, Member $member)
        {
            $param = $request->param();
            //店铺搜索
            if($param['status'] == 1)
            {
                $condition[] = ['store_name|phone','like','%'.$param['keys'].'%'];
                $result = $store->where($condition)
                    ->field('store_id,shop,category,store_name,status,category')->limit(1)->select();
                foreach ($result as &$value)
                {
                    $value['category_name'] = $value->CategoryName;
                }
            }
            //商品搜索
            elseif ($param['status'] == 2)
            {
                $condition[] = ['goods_name','like','%'.$param['keys'].'%'];
                $result = $goods->with(['store_status'])->where($condition)->field('goods_id,goods_name,shop_price,goods_number,review_status,file,store_id')->limit(1)->select();
            }
            //订单搜索
            elseif ($param['status'] == 3)
            {
                $condition[] = ['order_number|order_attach_number|trade_no','like','%'.$param['keys'].'%'];
                $result = $orderAttach->with('member,store,orderGoods')->where($condition)->field('order_attach_id,order_attach_number,distribution_type,status,subtotal_price,create_time,store_id,member_id')->limit(1)->select();
            }
            elseif ($param['status'] == 4)
            {
                $condition[] = ['phone|nickname','like','%'.$param['keys'].'%'];
                $result = $member->where($condition)->field('member_id,phone,nickname,avatar,register_time')->limit(1)->select();
            }

            $data['code'] = 0;
            $data['message'] = '成功';
            $data['data'] = $result;
            return json($data);
        }
}