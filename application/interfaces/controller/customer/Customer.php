<?php
declare(strict_types=1);

namespace app\interfaces\controller\customer;


use app\common\model\AlterPriceLog;
use app\common\model\Cart;
use app\common\model\CollectGoods;
use app\common\model\Customer as CustomerModel;
use app\common\model\CustomerDiversion;
use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\OrderAttach;
use app\common\model\OrderGoods;
use app\common\model\RecordGoods;
use app\common\model\Store;
use app\common\service\IntegratingCloud;
use app\common\service\OSS;
use app\daemonService\customer\MongoDb;
use mrmiao\encryption\RSACrypt;
use think\Controller;
use think\Db;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;

class Customer extends Controller
{
    public function login(RSACrypt $crypt, CustomerModel $customer)
    {
        try {
            $_POST = $this->request->post();

            $customer->valid($_POST, 'login');


            $_user = $customer->where(
                [
                    ['account', '=', $_POST['account']],
                    ['password', '=', md5($_POST['password'])],
                ]
            )->find();

            if (!$_user) {
                return $crypt->response(
                    [
                        'code'    => 1000,
                        'message' => '用户名或密码错误',
                    ]
                );
            }

            // 判断客服是否被禁用
            if ($_user['enabled'] != 1) {
                return $crypt->response(
                    [
                        'code'    => 1000,
                        'message' => '客服已被停用,请联系您的店铺管理员',
                    ],
                    TRUE
                );
            }


            // 客服组信息
            $_customerDiversionId = CustomerDiversion::where(
                [
                    'store_id'          => $_user['store_id'],
                    'customer_group_id' => $_user['customer_group_id'],
                ]
            )->column('diversion_id');

            // 写入最后登录时间
            CustomerModel::update([
                'last_login_time' => date('Y-m-d H:i:s'),
            ], [
                'customer_id' => $_user['customer_id'],
            ]);

            Env::load(Env::get('APP_PATH') . 'common/ini/.config');
            $_storeLogo =config('user.')['one_more'] == 0 ? Env::get('logo') : Store::where([
                                                                                                ['store_id', '=', $_user['store_id']],
                                                                                            ])->value('logo');
            $_storeLogo = $_user->getOssUrl($_storeLogo);

            $_returnData = [
                // 店铺ID
                'store_id'     => (string)$_user['store_id'],
                // 客服ID
                'customer_id'  => (string)$_user['customer_id'],
                // 客服昵称
                'nickname'     => (string)$_user['nickname'],
                // 头像
                'head_img'     => (string)$_storeLogo,
                // 分流ID
                'diversion_id' => join('|', $_customerDiversionId),
            ];


            return $crypt->response(
                [
                    'code' => 0,
                    'data' => $_returnData,
                ],
                TRUE
            );

        } catch (ValidateException $e) {
            return $crypt->response(
                [
                    'code'    => -100,
                    'message' => $e->getMessage(),
                    'level'   => Env::get('navigation_status', 0),
                ],
                TRUE
            );
        }
    }


    /**
     * 获得聊天记录
     * @param RSACrypt $crypt
     * @param MongoDb $db
     * @return mixed
     */
    public function getChatLog(RSACrypt $crypt, MongoDb $db)
    {
        $res = $this->request->post();
        try {
            $_rul = [
                'limit'              => '记录数不能为空',
                'member_id'          => '会员id不能为空',
                'store_id'           => '店铺id不能为空',
                'last_id'            => '最后一条消息id不能为空',
                'first_message_time' => '第一条消息时间戳不能为空',
            ];
            foreach ($_rul as $k => $v) {
                if (!isset($res[$k])) {
                    exception($v, 100);
                }
            }
            $where = ['member_id' => (string)$res['member_id'], 'store_id' => (string)$res['store_id']];
            $last_id = (int)$res['last_id'];
            $first_message_time = (int)$res['first_message_time'];
            if ($last_id !== 0) {
                $where['id'] = ['$lt' => $last_id];
            }
            if ($first_message_time !== 0) {
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
            )->sort(['id' => 'desc'])->query('message_log');
            $data = [];
            foreach ($_data as $v) {
                unset($v->_id);
                array_unshift($data, $v);
            }
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => $data,
                ]
            );
        } catch (\Exception $e) {
            $code = 500;
            if (in_array($e->getCode(), [100])) {
                $code = $e->getCode();
                $message = $e->getMessage();
            }
            return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
        }
    }

    /**
     * 获得店铺列表
     * @param RSACrypt $crypt
     * @param MongoDb $db
     * @return mixed
     */
    public function getCustomerList(RSACrypt $crypt, MongoDb $db)
    {
        $member_id = $this->request->post('member_id', FALSE);
        try {
            if (!$member_id) {
                exception('会员id不能为空', '100');
            }
            $_data = $db
                ->field(
                    [
                        'customer_id',
                        'after_chat_time',
                        'message.MESSAGE_TYPE',
                        'message.MESSAGE_DATA',
                        'store_id',
                        'type',
                    ],
                    TRUE
                )
                ->where(['member_id' => (string)$member_id, 'name' => ['$ne' => 'count']])
                ->sort(
                    ['after_chat_time' => 'asc']
                )->query(
                    'member_store'
                );
            $data = [];
            $_store_id = [];
            foreach ($_data as $v) {
                unset($v->_id);
                array_unshift($data, $v);
                array_unshift($_store_id, $v->store_id);
            }
            $_prefix = config()['database']['prefix'];
            $_new_store_id = implode(',', $_store_id);
            //根据店铺id查出店铺信息
            if (!empty($_new_store_id)) {
                $sql = "select logo,store_name from {$_prefix}store where store_id in ({$_new_store_id}) order by field(store_id";
                foreach ($_store_id as $v) {
                    $sql .= ',\'' . $v . '\'';
                }
                $sql .= ')';
                $_store_logo = Db::query($sql);
            }
            //判断当前记录是否有平台客服记录
            $_terrace_id_index = array_search(0, $_store_id);
            if ($_terrace_id_index !== FALSE) {
                //读取平台信息
                Env::load(Env::get('APP_PATH') . 'common/ini/.config');
                $_store_logo = $_store_logo ?? [];
                array_splice(
                    $_store_logo,
                    $_terrace_id_index,
                    0,
                    [['logo' => Env::get('logo'), 'store_name' => Env::get('title')]]
                );
            }
            foreach ($data as $k => $v) {
                $v->logo = $this->getOss($_store_logo[$k]['logo']);
                $v->store_name = $_store_logo[$k]['store_name'];

            }
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => $data,
                ]
            );
        } catch (\Exception $e) {

            return $crypt->response(
                [
                    'code' => 0,
                    'data' => [],
                ]
            );
//            $code = 500;
//            if (in_array($e->getCode(), [100]) && Config::get('app_debug')) {
//                $code = $e->getCode();
//                $message = $e->getMessage();
//            }
//            return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
        }
    }

    /**
     * 获得商品信息
     * @param RSACrypt $crypt
     * @param Goods $goods
     * @param Request $request
     * @return mixed
     */
    public function getGoodsInfo(RSACrypt $crypt, Goods $goods, Request $request)
    {
        try {
            $goods_id = $request::post('goods_id', NULL) ?? exception('商品id不能为空', 401);
            $goods_info = $goods->where([['goods_id', '=', (int)$goods_id]])->field(
                    'goods_id,goods_name,file,shop_price,group_price,is_group,cut_price,is_bargain,time_limit_price,is_limit'
                )->find() ?? exception('商品不存在', 401);


            $val = $goods_info['is_group'] . $goods_info['is_bargain'] . $goods_info['is_limit'];
            $data = [
                'goods_id'   => $goods_info['goods_id'],
                'goods_name' => $goods_info['goods_name'],
                'file'       => $goods_info['file'],
            ];
            switch (bindec($val)) {
                case 0:
                    $data['price'] = $goods_info['shop_price'];
                    break;
                case 1:
                    $data['price'] = $goods_info['time_limit_price'];
                    break;
                case 2:
                    $data['price'] = $goods_info['cut_price'];
                    break;
                case 4:
                    $data['price'] = $goods_info['group_price'];
                    break;
                default:
                    $data['price'] = $goods_info['shop_price'];
            }
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => $data,
                ]
            );
        } catch (\Exception $e) {
            $code = 500;
            if (in_array($e->getCode(), ['401']) && Config::get('app_debug')) {
                $code = $e->getCode();
                $message = $e->getMessage();
            }
            return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
        }
    }

    /**
     * 获得商品信息
     * @param RSACrypt $crypt
     * @param OrderGoods $orderGoods
     * @param Request $request
     * @return mixed
     */
    public function getOrderInfo(RSACrypt $crypt, OrderGoods $orderGoods, Request $request)
    {
        try {
            $order_id = $request::post('order_id', NULL) ?? exception('订单id不能为空', 401);
            $goods_id = $request::post('goods_id', NULL) ?? exception('商品id不能为空', 401);
            $goods_info = $orderGoods->where(
                    [['order_attach_id', '=', (int)$order_id], ['goods_id', '=', $goods_id]]
                )->field(
                    'goods_id,attr,status,file,single_price,goods_name,quantity'
                )->find() ?? exception('订单不存在', 401);
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => $goods_info,
                ]
            );
        } catch (\Exception $e) {
            $code = 500;
            if (in_array($e->getCode(), ['401']) && Config::get('app_debug')) {
                $code = $e->getCode();
                $message = $e->getMessage();
            }
            return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
        }
    }

    /**
     * 获得店铺信息
     * @param RSACrypt $crypt
     * @param Store $store
     * @param Request $request
     * @return mixed
     */
    public function getStoreInfo(RSACrypt $crypt, Store $store, Request $request)
    {
        try {
            $store_id = $request::post('store_id', NULL) ?? exception('店铺id不能为空', 401);
            if(config('user.')['one_more'] == 0)$store_id=0;
            Env::load(Env::get('APP_PATH') . 'common/ini/.config');
            $store_info = $store_id == 0 ? [
                'store_id'   => 0,
                'logo'       => Env::get('logo'),
                'store_name' => Env::get('title'),
            ] : $store::where([['store_id', '=', (int)$store_id]])->field(
                'store_id,logo,store_name'
            )->find();
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => $store_info,
                ]
            );
        } catch (\Exception $e) {
            $code = 500;
            if (in_array($e->getCode(), ['401']) && Config::get('app_debug')) {
                $code = $e->getCode();
            }
            $message = $e->getMessage();
            return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
        }
    }

    /**
     * 获得店铺商品列表
     * @param RSACrypt $crypt
     * @param Goods $goods
     * @param Request $request
     * @return mixed
     */
    public function getStoreGoodsList(RSACrypt $crypt, Goods $goods, Request $request)
    {
        try {
            $store_id = $request::post('store_id', NULL) ?? exception('店铺id不能为空', 401);
            $where = [
                ['is_putaway', '=', 1],
                ['forever_del_status', '=', 0],
            ];
            if ($store_id != 0) {
                $where[] = ['store_id', '=', (int)$store_id];
            }
            $goods_list = $goods
                ->where($where)
                ->field(
                    'goods_id,goods_name,file,shop_price,group_price,is_group,cut_price,is_bargain,time_limit_price,is_limit'
                )->paginate(10);
            $data = [];
            foreach ($goods_list as $key => $goods_info) {
                $val = $goods_info['is_group'] . $goods_info['is_bargain'] . $goods_info['is_limit'];
                $data [$key] = [
                    'goods_id'   => $goods_info['goods_id'],
                    'goods_name' => $goods_info['goods_name'],
                    'file'       => $goods_info['file'],
                ];
                switch (bindec($val)) {
                    //没参加活动
                    case 0:
                        $data[$key]['price'] = $goods_info['shop_price'];
                        $data[$key]['activity_type'] = 1;
                        break;
                    //限时抢购
                    case 1:
                        $data[$key]['price'] = $goods_info['time_limit_price'];
                        $data[$key]['activity_type'] = 2;
                        break;
                    //砍价
                    case 2:
                        $data[$key]['price'] = $goods_info['cut_price'];
                        $data[$key]['activity_type'] = 3;
                        break;
                    //拼团
                    case 4:
                        $data[$key]['price'] = $goods_info['group_price'];
                        $data[$key]['activity_type'] = 4;
                        break;
                    default:
                        $data[$key]['activity_type'] = 1;
                        $data[$key]['price'] = $goods_info['shop_price'];
                }

            }
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => [
                        'data' => $data,
                        'page' => [
                            'current_page' => (int)$goods_list->currentPage(),
                            'total_page'   => $goods_list->lastPage(),
                        ],
                    ],
                ]
            );
        } catch (\Exception $e) {
            $code = 500;
            if (in_array($e->getCode(), [401]) && Config::get('app_debug')) {
                $code = $e->getCode();
                $message = $e->getMessage();
            }
            return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
        }
    }

    /**
     * 获得店铺订单列表
     * @param RSACrypt $crypt
     * @param OrderAttach $orderAttach
     * @param Request $request
     * @return mixed
     */

    public function getStoreOrderList(RSACrypt $crypt, OrderAttach $orderAttach, Request $request)
    {
        try {
            $par = $request::post();
            $orderAttach->valid($par, 'customerList');
            $where = [['member_id', '=', $par['member_id']]];
            if ($par['store_id'] != 0) {
                array_push($where, ['store_id', '=', $par['store_id']]);
            }
            $field = 'order_attach_id,order_attach_number,subtotal_price,
            number,subtotal_freight_price,store_id,status';
            $data = $orderAttach::with(
                [
                    'orderGoods' => function ($query) {
                        $query->field(
                            'order_attach_id,goods_id,quantity,status,single_price,sub_freight_price,goods_name,file,goods_name_style,attr'
                        );
                    },
                    'store'      => function ($query) {
                        $query->field('store_id,store_name');
                    },
                ]
            )
                ->order(['create_time' => 'desc', 'status' => 'asc'])
                ->where($where)
                ->field($field)->paginate(10, FALSE);
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => $data,
                ]
            );
        } catch (ValidateException $e) {
            return $crypt->response(
                [
                    'code'    => 0,
                    'message' => $e->getMessage(),
                ]
            );
        } catch (\Exception $e) {
            $code = 500;
            if (in_array($e->getCode(), [401]) && Config::get('app_debug')) {
                $code = $e->getCode();
            }
            $message = $e->getMessage();
            return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
        }
    }


    public function getGoodsList(RSACrypt $crypt, OrderGoods $orderGoods, Request $request, RecordGoods $recordGoods, CollectGoods $collectGoods, Cart $cart)
    {
        try {
            $par = $request::post();
            $field = 'g.store_id,g.goods_id,g.file,g.goods_name,g.shop_price,g.group_price,g.is_group,g.cut_price,g.is_bargain,g.time_limit_price,g.is_limit';
            switch ($par['list_type']) {
                //已购买订单列表
                case 1:
                    $baseModel = $orderGoods->alias('o')->join('goods g', 'o.goods_id=g.goods_id')
                        ->order(['o.create_time' => 'desc'])
                        ->where([['o.member_id', '=', $par['member_id']]])
                        ->field($field);
                    break;
                //浏览商品
                case 2:
                    $baseModel = $recordGoods
                        ->alias('r')
                        ->join('goods g', 'r.goods_id=g.goods_id')
                        ->where([['r.member_id', '=', $par['member_id']]])->order(
                            'r.create_time',
                            'desc'
                        )->field($field);
                    break;
                //关注商品
                case 3:
                    $baseModel = $collectGoods
                        ->alias('c')
                        ->join('goods g', 'c.goods_id=c.goods_id')
                        ->where([['c.member_id', '=', $par['member_id']]])
                        ->order('c.create_time', 'desc')->field($field);
                    break;
                //购物车商品
                case 4:
                    $baseModel = $cart
                        ->alias('r')
                        ->join('goods g', 'r.goods_id=g.goods_id')
                        ->where([['r.member_id', '=', $par['member_id']]])
                        ->order('r.create_time', 'desc')->field($field);
                    break;
            }
            $goods_list = $baseModel->paginate(10, FALSE);
            foreach ($goods_list as $key => $goods_info) {
                $val = $goods_info['is_group'] . $goods_info['is_bargain'] . $goods_info['is_limit'];
                switch (bindec($val)) {
                    //没参加活动
                    case 0:
                        $goods_info->price = $goods_info['shop_price'];
                        $goods_info->activity_type = 1;
                        break;
                    //限时抢购
                    case 1:
                        $goods_info->price = $goods_info['time_limit_price'];
                        $goods_info->activity_type = 2;
                        break;
                    //砍价
                    case 2:
                        $goods_info->price = $goods_info['cut_price'];
                        $goods_info->activity_type = 3;
                        break;
                    //拼团
                    case 4:
                        $goods_info->price = $goods_info['group_price'];
                        $goods_info->activity_type = 4;
                        break;
                    default:
                        $goods_info->price = $goods_info['shop_price'];
                        $goods_info->activity_type = 1;
                }
                //删除多余数据
                foreach ([
                             'shop_price',
                             'group_price',
                             'is_group',
                             'cut_price',
                             'is_bargain',
                             'time_limit_price',
                             'is_limit',
                         ] as $v) {
                    if (isset($goods_info->$v)) {
                        unset($goods_info->$v);
                    }
                }


            }
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => $goods_list,
                ]
            );
        } catch (\Exception $e) {
            $code = 500;
            if (in_array($e->getCode(), [401]) && Config::get('app_debug')) {
                $code = $e->getCode();
            }
            $message = $e->getMessage();
            return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
        }
    }

    /**
     *
     * 修改订单商品金额
     * @param RSACrypt $crypt
     * @param Request $request
     * @param AlterPriceLog $alterPriceLog
     * @return mixed
     */
    public function updateOrderPrice(RSACrypt $crypt, Request $request, AlterPriceLog $alterPriceLog)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $alterPriceLog->valid($param, 'customer');
                Db::startTrans();
                $alterPriceLog->customerUpdateOrderPrice($param);
                Db::commit();
                return $crypt->response(
                    [
                        'code' => 0,
                    ]
                );
            } catch (ValidateException $e) {
                return $crypt->response(
                    [
                        'code'    => 0,
                        'message' => $e->getMessage(),
                    ]
                );
            } catch (\Exception $e) {
                Db::rollback();
                $code = 500;
                if (in_array($e->getCode(), [401]) && Config::get('app_debug')) {
                    $code = $e->getCode();
                }
                $message = $e->getMessage();
                return $crypt->response(['code' => $code, 'data' => $message ?? '系统错误']);
            }
        }
    }

    /**
     * 获取用户的基本信息
     *
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     */
    public function getUserInfo(RSACrypt $crypt, Member $member)
    {
        $_id = $this->request->post('id', NULL);

        try {
            if (!isset($_id)) {
                return $crypt->response(['code' => 100, 'data' => '参数错误']);
            }

            $_memberInfo = $member->field(
                [
                    'member_id',
                    'nickname',
                    'avatar',
                ]
            )->find($_id);

            if ($_memberInfo === NULL) {
                return $crypt->response(['code' => 404, 'data' => '用户不存在']);
            }

            return $crypt->response(
                [
                    'code' => 0,
                    'data' => [
                        'member_id' => (string)$_memberInfo['member_id'],
                        'nickname'  => (string)$_memberInfo['nickname'],
                        'head_img'  => (string)(!empty($_memberInfo['avatar']) ? $_memberInfo['avatar'] : $this->request->domain() . '/static/img/user_pic.png'),
                    ],
                ]
            );
        } catch (\Exception $e) {
            return $crypt->response(['code' => 500, 'data' => '系统错误']);
        }

    }

    public function uploadFile(RSACrypt $crypt)
    {
        $file = request()->file('file');

        $info = $file ? $file->getInfo() : [];
        if (!is_null($file)) {
            $ossConfig = config('oss.');
            $ossFileName = 'customer-img/' . date('Ymd') . '/' . md5(microtime()) . strtolower(
                    strrchr($_FILES['file']['name'], '.')
                );
            $ossManage = app('app\\common\\service\\OSS');
            $res = $ossManage->fileUpload($ossFileName, $info['tmp_name']);
            if ($res['code'] === 0) {
                $info['ossUrl'] = $ossFileName;
            }
            // 重写文件读写权限(默认公有读,私有写)
            $ossManage->putObjectAcl($ossFileName, 'public-read');
            $info['ossUrl'] = $ossConfig['prefix'] . $ossFileName;

        }
        return $crypt->response($info);
    }

    public function getCustomerInfo(RSACrypt $crypt, CustomerModel $customer)
    {
        $_customer_id = $this->request->post('customer_id', NULL);
        try {
            if (!isset($_customer_id)) {
                return $crypt->response(['code' => 100, 'data' => '参数错误']);
            }
            $_customer = $customer->field('customer_id,img,nickname')->find($_customer_id);
            if (!$_customer) {
                return $crypt->response(
                    [
                        'code'    => 401,
                        'message' => '用户不存在',
                    ]
                );
            }
            return $crypt->response(
                [
                    'code' => 0,
                    'data' => [
                        $_customer,
                    ],
                ]
            );
        } catch (\Exception $e) {
            return $crypt->response(['code' => 500, 'data' => '系统错误']);
        }
    }


    private function getOss($value)
    {
        $ossManage = app('app\\common\\service\\OSS');
        $res = $ossManage->getSignUrlForGet($value . config('oss.')['style'][0]);
        return ($res['code'] === 0) ? $res['url'] : '';
    }

    /**
     * 获取融云Token
     * @param RSACrypt $crypt
     * @param IntegratingCloud $integratingCloud
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getToken(RSACrypt $crypt, IntegratingCloud $integratingCloud, Member $member)
    {
        $member_id = request()->mid;

        $user = $member->where('member_id', $member_id)->field('nickname,avatar')->find();
        $avatar = !empty($user['avatar']) ? $user['avatar'] : config('rongyun.default_avatar');
        $token = $integratingCloud->getToken($member_id, $user['nickname'] ?? '', $avatar);

        return $crypt->response(apiShow($token));
    }

    /**
     * 获取融云用户信息
     * @param RSACrypt $crypt
     * @param Store $store
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rongInfo(RSACrypt $crypt, Store $store)
    {
        $param = $crypt->request();

        $data = $store
            ->where('member_id', $param['rong_id'])
            ->field('store_id,shop,store_name,logo')
            ->find();

        return $crypt->response(apiShow($data));
    }
}