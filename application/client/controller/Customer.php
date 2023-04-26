<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18 0018
 * Time: 14:52
 */

namespace app\client\controller;

use app\common\model\Customer as CustomerModel;
use app\common\model\CustomerGroup;
use app\common\model\Store;
use app\common\model\Member;
use app\common\service\OSS;
use app\daemonService\customer\MongoDb;
use think\Controller;
use think\Db;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Request;
use think\facade\Session;

class Customer extends Controller
{
    /**
     * 客服管理
     */
    public function index()
    {
        $_GET = $this->request->get();

        $_where = [
            ['store_id', '=', Session::get('client_store_id')],
        ];

        if (isset($_GET['customer_group_id']) && $_GET['customer_group_id'] != 0) {
            $_where[] = ['customer_group_id', '=', $_GET['customer_group_id']];
        }

        if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
            $_where[] = ['account|name|nickname', 'like', "%{$_GET['keyword']}%"];
        }

        if (isset($_GET['type'])) {
            switch ($_GET['type']) {
                // 启用客服
                case '1':
                    $_where[] = ['enabled', '=', 1];
                    break;
                // 禁用客服
                case '2':
                    $_where[] = ['enabled', '=', 2];
                    break;
            }
        }

        $_data = CustomerModel::where($_where)->paginate();

        // 获取客服组
        $_group = CustomerGroup::_GetList();

        $this->assign('data', $_data);
        $this->assign('group', $_group);

        return $this->fetch();
    }

    /**
     * 创建客服
     *
     * @param CustomerModel $customer
     *
     * @param Store $store
     * @return mixed
     * @throws \think\Exception\DbException
     */
    public function create(CustomerModel $customer, Store $store)
    {
        if ($this->request->isPost()) {
            $_postData = $this->request->post();

            // 验证POST数据
            $customer->valid($_postData, 'create');

            // 密码加密
            $_postData['password'] = md5($_postData['password']);
            // 标识店铺ID
            $_postData['store_id'] = Session::get('client_store_id');

            try {
                // 搜索系统是否存在此账号的客服
                $_searchSavedCustomer = $customer->field(
                    [
                        'account',
                    ]
                )->where(
                    [
                        ['account', '=', $_postData['account']],
                    ]
                )->find();

                if (isset($_searchSavedCustomer)) {
                    // 如果系统存在此账号的客服 则返回错误信息
                    throw new \Exception('当前客服账号已存在');
                }

                $customer->allowField(TRUE)->save($_postData);
                return ['code' => 0, 'message' => '添加成功', 'url' => 'client/customer/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $_storeLogo = Store::field([
            'logo',
        ])->where([
            ['store_id', '=', Session::get('client_store_id')],
        ])->find();

        $this->assign('store_logo', $_storeLogo['logo']);
        $this->assign('store_logo_data', $_storeLogo->getData('logo'));


        // 客服组列表
        $_group = CustomerGroup::_GetList();
        $_store = $store->get(Session::get('client_store_id'));
        return $this->fetch('', [
            'group' => $_group,
            'store' => $_store,
        ]);
    }

    /**
     * 编辑客服
     *
     * @param CustomerModel $customer
     *
     * @return mixed
     */
    public function update(CustomerModel $customer)
    {

        if ($this->request->isPost()) {
            $_postData = $this->request->post();
            $_customer_id = $this->request->get('customer_id',NULL);

            try {
                // 验证POST数据
                $customer->valid($_postData, 'update');

                if (!$_customer_id){
                    throw new Exception('缺少关键字段');
                }

                $_postData['customer_id'] = $_customer_id;

                // 如果没有输入密码
                if (empty($_postData['password'])) {
                    unset($_postData['password']);
                    unset($_postData['repassword']);
                } else {
                    // 密码加密
                    $_postData['password'] = md5($_postData['password']);
                }

                $customer->allowField(TRUE)->isUpdate(TRUE)->save($_postData);

                return ['code' => 0, 'message' => '保存成功', 'url' => 'client/customer/index'];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => "保存失败,请稍后再试"];
            }

        }

        // 获取客服组
        $_group = CustomerGroup::_GetList();

        // 客服ID
        $_customerId = $this->request->get('customer_id', NULL);

        // 判断用户是否输入客服ID
        if (!isset($_customerId)) {
            $this->error('当前客服不存在');
            return FALSE;
        }

        // 获取客服信息
        try {
            $_customer = CustomerModel::with('groupBlt')->where(['customer_id' => $_customerId])->find();
        } catch (\Exception $e) {
            $this->error('当前客服不存在');
            return FALSE;
        }

        // 判断客服是否存在
        if (is_null($_customer)) {
            $this->error('当前客服不存在');
        }

//        $_oss = new OSS();
//
//        try {
//            $_img = $_oss->getSignUrlForGet($_customer['img'])['url'];
//        } catch (\Exception $e) {
//            $_img = NULL;
//        }


        $_storeLogo = Store::field([
            'logo',
        ])->where([
            ['store_id', '=', Session::get('client_store_id')],
        ])->find();

        $this->assign('store_logo', $_storeLogo['logo']);
        $this->assign('store_logo_data', $_storeLogo->getData('logo'));


        $_customer['img_data'] = $_customer->getData('img');


        $this->assign('customer', $_customer);
        $this->assign('group', $_group);

        return $this->fetch('create');
    }

    public function destroy(CustomerModel $customer)
    {
        try {
            $_postData = $this->request->post();

            $_valid = $customer->valid($_postData, 'destroy');

            if ($_valid['code'] != 0) {
                return $_valid;
            }

            $_id = explode(',', $_postData['id']);

            $_updateData = [];

            $_date = date('Y-m-d H:i:s');

            foreach ($_id as $value) {
                $_updateData[] = [
                    'customer_id' => $value,
                    'delete_time' => $_date,
                ];
            }
            $customer->saveAll($_updateData);
            return ['code' => 0, '删除成功'];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    public function enabled(CustomerModel $customer)
    {
        try {
            $_postData = $this->request->post();

            $_valid = $customer->valid($_postData, 'destroy');

            if ($_valid['code'] != 0) {
                return $_valid;
            }

            $customer->save(
                [
                    'enabled' => 1,
                ],
                ['customer_id' => $_postData['id']]
            );

            return ['code' => 0, '启用成功'];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }

    public function disabled(CustomerModel $customer)
    {
        try {
            $_postData = $this->request->post();

            $_valid = $customer->valid($_postData, 'destroy');

            if ($_valid['code'] != 0) {
                return $_valid;
            }

            $customer->save(
                [
                    'enabled' => 2,
                ],
                ['customer_id' => $_postData['id']]
            );

            return ['code' => 0, '禁用成功'];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
    }


    /**
     * 消息列表
     *
     * @param CustomerGroup $customerGroup
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index3(CustomerGroup $customerGroup)
    {
        $data = $customerGroup->with(
            [
                'customerRel' => function ($query) {
                    $query->field('customer_id,customer_group_id,name,img,account,nickname');
                },
            ]
        )->where(
            [['store_id', '=', Session::get('client_store_id')]]
        )->field('name,customer_group_id')->select();
        return $this->fetch('', ['data' => $data]);
    }

    /**
     * 获得客服会员列表
     *
     * @param Request $request
     * @param MongoDb $db
     *
     * @return array
     */
    public function get_member_list(Request $request, MongoDb $db)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $dbData = $db
                    ->field(
                        ['_id', 'after_chat_time', 'message.FROM_ID', 'message.MESSAGE_ID', 'message.FROM_TYPE'],
                        FALSE
                    )
                    ->where(['customer_id' => (string)$param['customer_id'], 'name' => ['$ne' => 'count']])
                    ->query('member_customer')->toArray();
                $data = $_member_avatar = $_member_id = [];

//                dump($dbData->toArray());
                foreach ($dbData as $v) {
                    $data[] = $v;
                    $_member_id[] = $v->member_id;
                }
                $_prefix = config()['database']['prefix'];
                $_str_member_id = implode(',', $_member_id);
                if (!empty($_str_member_id)) {
                    $sql = "select member_id,avatar,nickname from {$_prefix}member where member_id in ({$_str_member_id}) order by field(member_id";
                    foreach ($_member_id as $v) {
                        $sql .= ',\'' . $v . '\'';
                    }
                    $sql .= ')';
                    $_member_avatar = Db::query($sql);
                }

                // 存在的用户的聊天信息
                $extisMemberMessageData = [];

                foreach ($_member_avatar as $value){
                    foreach ($dbData as $dbValue){
                        if ($value['member_id'] == $dbValue->member_id){
                            $dbValue->avatar = !empty($value['avatar'])?$this->getOss($value['avatar']):"/static/img/user_pic.png";
                            $dbValue->nickname = $value['nickname'];
                            $extisMemberMessageData[] = $dbValue;
                        }
                    }
                }
                return json(['code' => 0, 'data' => $extisMemberMessageData]);
            } catch (\Exception $e) {
                return json(['code' => -100, 'message' => $e->getMessage()]);
            }
        }
    }

    /**
     * 获得客服消息列表
     *
     * @param Request $request
     * @param MongoDb $db
     *
     * @return array
     */
    public function get_message_list(Request $request, MongoDb $db)
    {
        if ($request::isPost()) {
            try {
                $param = Request::post();
                $where = ['member_id' => (string)$param['member_id'], 'customer_id' => (string)$param['customer_id']];
                $last_id = (int)$param['last_id'];
                if ($last_id !== 0) {
                    $where['id'] = ['$lt' => $last_id];
                }
                //查询出当前用户聊天记录的日期范围
                $message_data = $db->aggregate(
                        'message_log',
                        [
                            [
                                '$match' => [
                                    'member_id'   => ['$eq' => (string)$param['member_id']],
                                    'customer_id' => ['$eq' => (string)$param['customer_id']],
                                ],
                            ],
                            [
                                '$group' => [
                                    '_id'      => '$time',
                                    'min_data' => ['$min' => '$time'],
                                    'max_data' => ['$max' => '$time'],
                                ],
                            ],
                            ['$project' => ['_id' => 0]],
                        ]
                    )->toarray()[0] ?? [];
                if (!empty($param['start_time']) && !empty($param['end_time'])) {
                    $where['time'] = ['$gte' => $param['start_time'], '$lte' => $param['end_time']];
                }
                $dbData = $db
                    ->field(
                        ['_id', 'store_id', 'message.FROM_TYPE', 'message.FROM_ID', 'time'],
                        FALSE
                    )
                    ->limit(10)
                    ->where($where)->sort(['id' => 'desc'])->query('message_log');
                $data = [];
                $avatar = Member::get($param['member_id'])->getData('avatar') ?? '';
                $avatar = !empty($avatar)?$this->getOss($avatar):"/static/img/user_pic.png";
                $_last_id = -1;
                foreach ($dbData as $v) {
                    $_last_id = $v->id;
                    $v->avatar = $avatar;
                    $v->message->MESSAGE_ID = (int)$v->message->MESSAGE_ID;
                    array_unshift($data, $v);
                }
                return json(['code' => 0, 'data' => $data, 'message_data' => $message_data, 'last_id' => $_last_id]);
            } catch (\Exception $e) {
                return json(['code' => -100, 'message' => $e->getMessage()]);
            }
        }
    }

    private function getOss($value)
    {
        $ossManage = app('app\\common\\service\\OSS');
        $res = $ossManage->getSignUrlForGet($value . config('oss.')['style'][0]);
        return ($res['code'] === 0) ? $res['url'] : '';
    }
}