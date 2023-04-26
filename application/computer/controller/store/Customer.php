<?php
/**
 * Created by PhpStorm.
 * User: Faith
 * Date: 2019/6/3
 * Time: 9:39
 */

namespace app\computer\controller\store;


use app\computer\controller\BaseController;
use app\computer\model\Goods;
use app\computer\model\Member;
use app\computer\model\OrderAttach;
use app\computer\model\Store;
use app\daemonService\customer\MongoDb;
use think\facade\Request;
use think\facade\Session;

class Customer extends BaseController
{
    protected $beforeActionList = [
        'is_login',
    ];
    public function customer_index(Member $member, MongoDb $db)
    {
        $customer_list = $member->getCustomerList($db, Session::get('member_info')['member_id']);
        return $this->fetch('', ['customer_list' => $customer_list]);
    }

    /**
     * 获得店铺信息
     * @param Request $request
     * @param Store $store
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_store_info(Request $request, Store $store)
    {
        $store_id = $request::post('store_id', NULL);
        $data = $store->getStoreInfo(['store_name', 'store_id', 'phone', 'logo'], $store_id ?: 0);
        return ['code' => 0, 'message' => 'ok', 'data' => $data];
    }

    /**
     * 获得店铺的订单列表
     * @param Request $request
     * @param OrderAttach $orderAttach
     * @return array
     * @throws \think\exception\DbException
     */
    public function get_store_order_list(Request $request, OrderAttach $orderAttach)
    {
        $param = $request::post();
        $where = [['member_id', '=', Session::get('member_info')['member_id']]];
        if ($param['store_id'] != 0)
        {
            array_push($where, ['store_id', '=', $param['store_id']]);
        }
        $field = 'order_attach_id,order_attach_number,subtotal_price,
            number,subtotal_freight_price,store_id,status,create_time,express_value,express_number,distribution_type';
        $data = $orderAttach::with(
            [
                'orderGoods' => function ($query)
                {
                    $query->field(
                        'order_attach_id,goods_id,quantity,status,single_price,sub_freight_price,goods_name,file,goods_name_style,attr'
                    );
                },
            ]
        )
            ->order(['create_time' => 'desc', 'status' => 'asc'])
            ->where($where)
            ->field($field)->append(['order_operation'])->paginate(4, FALSE);
        return ['code' => 0, 'message' => 'ok', 'data' => $data];
    }

    /**
     * 获得聊天记录
     * @param MongoDb $db
     * @param Request $request
     * @param Goods $goods
     * @return array
     */
    public function get_chat_log(MongoDb $db, Request $request, Goods $goods)
    {
        $res = $request::post();
        try
        {
            $_rul = [
                'limit'              => '记录数不能为空',
                'store_id'           => '店铺id不能为空',
                'last_id'            => '最后一条消息id不能为空',
                'first_message_time' => '第一条消息时间戳不能为空',
            ];
            foreach ($_rul as $k => $v)
            {
                if (!isset($res[$k]))
                {
                    exception($v, 100);
                }
            }
            $member_id = (string)Session::get('member_info')['member_id'];
            $where = ['member_id' => $member_id, 'store_id' => (string)$res['store_id']];
            $last_id = (int)$res['last_id'];
            $first_message_time = (int)$res['first_message_time'];
            if ($last_id !== 0)
            {
                $where['id'] = ['$lt' => $last_id];
            }
            if ($first_message_time !== 0)
            {
                $where['message.MESSAGE_ID'] = ['$lt' => (string)$first_message_time];
            }
            $_data = $db->field(
                [
                    'type',
                    'message.MESSAGE_ID',
                    'message.MESSAGE_TYPE',
                    'message.MESSAGE_DATA',
                    'message.VOICE_TIME',
                    'message.FROM_ID',
                    'id',
                ],
                TRUE
            )->where($where)->limit(
                (int)($res['limit'] > 20 ? 20 : $res['limit'])
            )->sort(['id' => 'desc'])->query('message_log')->toArray();
            $_goods = [];
            foreach ($_data as $k => $v)
            {
                unset($v->_id);
                //判断消息类型

                switch ($v->message->MESSAGE_TYPE)
                {
                    case 'TEXT':
                        break;
                    case 'IMAGE':
                        break;
                    case 'VOICE':
                        break;
                    case 'GOODS':
                        //查询商品信息
                        $_goods[] = ['key' => $k, 'goods_id' => $v->message->MESSAGE_DATA];
                        break;
                    case 'ORDER':
                        break;
                }

            }
            //查询商品信息
            if (!empty($_goods))
            {
                $_goods_info = $goods->where([['goods_id', 'in', array_column($_goods, 'goods_id')]])->field(
                    'goods_id,goods_name,file,shop_price,time_limit_price,cut_price,group_price,is_limit,is_bargain,is_group'
                )->append(['goods_price'])->hidden(
                    ['cut_price', 'group_price', 'is_bargain', 'is_group', 'is_limit', 'time_limit_price', 'shop_price']
                )->select();
                foreach ($_goods as $g_v)
                {
                    foreach ($_goods_info as $_g_i_v)
                    {
                        if ($g_v['goods_id'] == $_g_i_v['goods_id'])
                        {
                            $_data[$g_v['key']]->message->GOODS_DATA = $_g_i_v;
                        }
                    }
                }
            }
            return ['code' => 0, 'store_id' => $res['store_id'], 'data' => array_reverse($_data)];
        } catch (\Exception $e)
        {
            $code = 500;
            if (in_array($e->getCode(), [100]))
            {
                $code = $e->getCode();
                $message = $e->getMessage();
            }
            return ['code' => $code, 'data' => $message ?? '系统错误'];
        }
    }

    /**
     * 获得商品信息
     * @param Request $request
     * @param Goods $goods
     * @return array
     * @throws \Exception
     */
    public function get_goods_info(Request $request, Goods $goods)
    {
        $goods_id = $request::post('goods_id', NULL) ?? exception('商品id不能为空', 401);
        $_goods_info = $goods->where([['goods_id', '=', $goods_id]])->field(
                'goods_id,goods_name,file,shop_price,time_limit_price,cut_price,group_price,is_limit,is_bargain,is_group'
            )->append(['goods_price'])->hidden(
                ['cut_price', 'group_price', 'is_bargain', 'is_group', 'is_limit', 'time_limit_price', 'shop_price']
            )->find() ?? exception('商品不存在', 401);
        return ['code' => 0, 'data' => $_goods_info];

    }

    /**
     * 获得最近浏览记录
     * @param Request $request
     * @param Goods $goods
     * @return array
     * @throws \think\exception\DbException
     */
    public function goods_browse_log(Request $request, Goods $goods)
    {
        $param = $request::post();
        $where = [['member_id', '=', Session::get('member_info')['member_id']]];
        if (!empty($param['store_id']))
        {
            $where[] = ['g.store_id', '=', $param['store_id']];
        }
        $data = $goods->alias('g')->join('record_goods r_g', 'g.goods_id=r_g.goods_id')->where($where)->field(
            'g.goods_id,g.goods_name,g.file,g.shop_price,g.time_limit_price,g.cut_price,g.group_price,g.is_limit,g.is_bargain,is_group'
        )->append(['goods_price'])
            ->hidden(
                ['cut_price', 'group_price', 'is_bargain', 'is_group', 'is_limit', 'time_limit_price', 'shop_price']
            )->paginate(10, FALSE);
        return ['code' => 0, 'store_id' => $param['store_id'] ?? 0, 'message' => 'ok', 'data' => $data];
    }

    /**
     * 获得店铺推荐商品
     * @param Request $request
     * @param Goods $goods
     * @return array
     * @throws \think\exception\DbException
     */
    public function store_recommend_goods(Request $request, Goods $goods)
    {
        $store_id = $request::post('store_id', 0);
        $data = $goods->where([['store_id', '=', $store_id], ['store_particularly_recommend', '=', 1]])->field(
            'goods_id,goods_name,file,shop_price,time_limit_price,cut_price,group_price,is_limit,is_bargain,is_group'
        )->append(['goods_price'])->hidden(
            ['cut_price', 'group_price', 'is_bargain', 'is_group', 'is_limit', 'time_limit_price', 'shop_price']
        )->paginate(10, FALSE);
        return ['code' => 0, 'store_id' => $store_id, 'message' => 'ok', 'data' => $data];
    }
}