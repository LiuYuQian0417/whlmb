<?php
// 店铺管理
namespace app\client\controller;

use app\client\validate\Store as StoreValid;
use app\client\validate\StoreAuth;
use app\common\model\Area as AreaModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\Member;
use app\common\model\Store as StoreModel;
use app\common\model\StoreAuth as StoreAuthModel;
use app\common\model\StoreClassify;
use think\Controller;
use think\Db;
use think\facade\Request;
use think\facade\Session;

class Store extends Controller
{
    /**
     * 店铺设置
     *
     * @param Request $request
     * @param StoreModel $store
     * @param AreaModel $area
     *
     * @optimization Malson 2019-03-12 09:57
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, StoreModel $store, AreaModel $area)
    {
        // 根据商户ID 获取商户详细信息
        $item = $store->get(Session::get('client_store_id'));
        if ($request::isPost()) {
            
            // 获取参数
            $param = $request::post();
            
            Db::startTrans();
            try {
                $_valid = new StoreValid;
                
                $_storeAuth = StoreAuthModel::get([
                    'store_id' => Session::get('client_store_id'),
                ]);
                // 验证
                if (!$_valid->scene('update_info')->check($param)) {
                    return ['code' => -100, 'message' => $_valid->getError()];
                };
                
                // 将 area_id 换成 文字
                $_areaList = $area->where([
                    ['area_id', 'in', "{$param['province']},{$param['city']},{$param['area']}"],
                ])->orderRaw("field(area_id,{$param['province']},{$param['city']},{$param['area']})")
                    ->column('area_name');
                
                // 赋值
                $param['province'] = $_areaList[0];
                $param['city'] = $_areaList[1];
                $param['area'] = $_areaList[2];
                
                $_storeData['filled'] = 1;
                
                if ($item['status'] > 3) {
                    $_storeData['status'] = 3;
                }
                
                // 如果店铺的审核状态为 申请通过 同时 店铺信息均填写完毕 则 使店铺进入待审核状态
                if ($item['status'] == 1 && $_storeAuth['filled'] == 1) {
                    $_storeData['status'] = 3;
                }
                
                // 如果店铺数据不为空 则 更新
                $item->save($_storeData);
                
                $store::update($param, [
                    'store_id' => Session::get('client_store_id'),
                ], [
                    'category',
                    'store_name',
                    'logo',
                    'phone',
                    'province',
                    'city',
                    'area',
                    'address',
                    'lng',
                    'lat',
                    'keywords',
                    'describe',
                    'filled',
                ]);
                Session::set('client_storeName', $param['store_name']);
                
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/store/storeAuth'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
        
        try {
            // 获取店铺所有主营项目
            $_storeClassify = StoreClassify::where('status', 1)->order(['sort' => 'desc', 'store_classify_id' => 'asc'])->select();
            
            if (isset($item['logo'])) {
                $item['logo_data'] = $item->getData('logo');
            }
            
            $_area = getAreaListFromText($item['province'], $item['city'], $item['area']);
            
            $this->assign('item', $item);
            $this->assign('areas', $_area);
            $this->assign('store_classify_list', $_storeClassify ?? []);
            
            return $this->fetch();
        } catch (\Exception $e) {
            $this->error(config('message.')['-1']);
        }
    }
    
    /**
     * 联系我们
     *
     * @param Request $request
     * @param StoreModel $store
     *
     * @optimization Malson 2019-03-12 10:03
     *
     * @return array|mixed
     * @throws \think\Exception\DbException
     */
    public function contact(Request $request, StoreModel $store)
    {
        // 根据商户ID 获取商户详细信息
        $item = $store->get(Session::get('client_store_id'));
        
        if ($request::isPost()) {
            $param = $request::post();
            
            try {
                Db::startTrans();
                // 验证
                $check = $store->valid($param, 'client_update_contact');
                if ($check['code']) {
                    return $check;
                }
                
                // 数据 - 编辑
                $item->allowField(
                    [
                        'phone',
                        'province',
                        'city',
                        'area',
                        'address',
                        'lng',
                        'lat',
                    ]
                )->isUpdate(true)->save($param);
                
                Session::set('client_storeLogo', $item['logo']);
                
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/store/contact'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
        
        return $this->fetch(
            '', [
                'item' => $item,
            ]
        );
    }
    
    /**
     * 图片信息
     *
     * @param Request $request
     * @param StoreModel $store
     *
     * @optimization Malson 2019-03-12 10:06
     *
     * @return array|mixed
     * @throws \think\Exception\DbException
     */
    public function images(Request $request, StoreModel $store)
    {
        // 根据商户ID 获取商户详细信息
        $item = $store->get(Session::get('client_store_id'));
        if ($request::isPost()) {
            $param = $request::post();
            Db::startTrans();
            try {
                // 验证
                $check = $store->valid($param, 'client_update_images');
                if ($check['code']) {
                    return $check;
                }
                $item->allowField(true)->isUpdate(true)->save($param);
                
                Session::set('client_storeLogo', $item['logo']);
                
                // 提交事务
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/store/images'];
                
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                halt($e->getMessage());
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
        
        return $this->fetch(
            '', [
                'item' => $item,
            ]
        );
    }
    
    /**
     * 店铺认证
     *
     * @param Request $request
     * @param StoreAuthModel $storeAuth
     * @param AreaModel $area
     * @param StoreModel $store
     *
     * @optimization Malson 2019-03-12 10:48
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function storeAuth(Request $request, StoreAuthModel $storeAuth, AreaModel $area, StoreModel $store)
    {
        // 提交信息
        if ($request::isPost()) {
            // 获取参数
            $_POST = $request::post();
            
            $_storeAuthValid = new StoreAuth();
            
            if (!$_storeAuthValid->scene('update_auth')->check($_POST)) {
                return ['code' => -100, 'message' => $_storeAuthValid->getError()];
            }
            
            Db::startTrans();
            try {
                $_store = $store::get(Session::get('client_store_id'));
                
                // 如果店铺审核状态为 认证通过 或 认证不通过 则将其状态修改回 认证中
                $_storeData = [];
                
                if ($_store['status'] > 3) {
                    $_storeData = ['status' => 3];
                }
                
                // 如果店铺的审核状态为 申请通过 同时 店铺信息均填写完毕 则 使店铺进入待审核状态
                if ($_store['status'] == 1 && $_store['filled'] == 1) {
                    $_storeData = ['status' => 3];
                }
                
                // 如果店铺数据不为空 则 更新
                if (!empty($_storeData)) {
                    $_store->save($_storeData);
                }
                
                // 设置审核信息填充完成
                $_POST['filled'] = 1;
                
                // 自动写入 银行信息
                // 银行信息 1 对公 2 对私
                // 认证类型 2 企业 1 个人
                // 需要把数字反过来
                $_POST['bank_type'] = $_POST['type'] == 1 ? 2 : 1;
                
                // 更新
                $storeAuth::update($_POST, [
                    'store_id' => Session::get('client_store_id'),
                    'member_id' => Session::get('client_member_id'),
                ], [
                    'type',
                    'auth_name',
                    'auth_number',
                    'ID_front_file',
                    'ID_back_file',
                    'company_number',
                    'file1',
                    'ID_type',
                    'file',
                    'tel',
                    'company_name',
                    'bank_file',
                    'bank_province',
                    'bank_city',
                    'bank_area',
                    'bank_type',
                    'account_name',
                    'account_bank_name',
                    'account_sub_branch',
                    'bank_number',
                    'filled',
                ]);
                
                Db::commit();
                return ['code' => 0, 'message' => '您的认证信息已修改 请耐心等待平台审核', 'url' => '/client/store/storeAuth'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }
        
        $_storeAuth = $storeAuth
            ->alias('StoreAuth')
            ->field(
                [
                    'StoreAuth.store_auth_id' => 'store_auth_id',
                    'StoreAuth.type' => 'type',
                    'StoreAuth.member_id' => 'member_id',
                    'StoreAuth.auth_name' => 'auth_name',
                    'StoreAuth.tel' => 'tel',
                    'StoreAuth.ID_type' => 'ID_type',
                    'StoreAuth.auth_number' => 'auth_number',
                    'StoreAuth.ID_front_file' => 'ID_front_file',
                    'StoreAuth.ID_back_file' => 'ID_back_file',
                    'StoreAuth.company_name' => 'company_name',
                    'StoreAuth.company_number' => 'company_number',
                    'StoreAuth.file' => 'file',
                    'StoreAuth.file1' => 'file1',
                    'StoreAuth.bank_file' => 'bank_file',
                    'StoreAuth.licence_file' => 'licence_file',
                    'StoreAuth.qualification_file' => 'qualification_file',
                    'StoreAuth.bank_province' => 'bank_province',
                    'StoreAuth.bank_city' => 'bank_city',
                    'StoreAuth.bank_area' => 'bank_area',
                    'StoreAuth.bank_type' => 'bank_type',
                    'StoreAuth.account_name' => 'account_name',
                    'StoreAuth.account_bank_name' => 'account_bank_name',
                    'StoreAuth.account_sub_branch' => 'account_sub_branch',
                    'StoreAuth.bank_number' => 'bank_number',
                    'Store.status' => 'status',
                ]
            )
            ->join('Store Store', 'Store.store_id = StoreAuth.store_id')
            ->where(
                [
                    'StoreAuth.store_id' => Session::get('client_store_id'),
                ]
            )->find();
        
        if (!$_storeAuth) {
            $this->error('您并没有店铺');
        }
        
        // 开户行的地址
        $_area = [
            'province' => [],
            'city' => [],
            'area' => [],
        ];
        
        $_addressIdList = [
            'province' => 0,
            'city' => 0,
            'area' => 0,
        ];
        
        
        // 如果没有设置省 则省为北京 市为北京
        if (
            empty($_storeAuth['bank_province']) ||
            empty($_storeAuth['bank_city']) ||
            empty($_storeAuth['bank_area'])
        ) {
            $_addressIdList['province'] = '110000';
            $_addressIdList['city'] = '110100';
            $_addressIdList['area'] = '110101';
        } else {   // 如果有设置省的话 则使用 设置的省
            $_addressIdList['province'] = $_storeAuth['bank_province'];
            $_addressIdList['city'] = $_storeAuth['bank_city'];
            $_addressIdList['area'] = $_storeAuth['bank_area'];
        }
        
        $_areaList = $area
            ->field(
                'group_concat(area_id) area_id,group_concat(area_name) area_name'
            )
            ->where(
                [
                    [
                        'parent_id',
                        'in',
                        "0,{$_addressIdList['province']},{$_addressIdList['city']}",
                    ],
                ]
            )->group('parent_id')->select();
        
        for ($i = 0; $i < count($_areaList); $i++) {
            $_areaList[$i]['area_id'] = explode(',', $_areaList[$i]['area_id']);
            $_areaList[$i]['area_name'] = explode(',', $_areaList[$i]['area_name']);
            
            for ($k = 0; $k < count($_areaList[$i]['area_id']); $k++) {
                switch ($i) {
                    case 0:
                        $_area['province'][$k]['area_id'] = $_areaList[$i]['area_id'][$k];
                        $_area['province'][$k]['area_name'] = $_areaList[$i]['area_name'][$k];
                        break;
                    case 1:
                        $_area['city'][$k]['area_id'] = $_areaList[$i]['area_id'][$k];
                        $_area['city'][$k]['area_name'] = $_areaList[$i]['area_name'][$k];
                        break;
                    case 2:
                        $_area['area'][$k]['area_id'] = $_areaList[$i]['area_id'][$k];
                        $_area['area'][$k]['area_name'] = $_areaList[$i]['area_name'][$k];
                        break;
                }
            }
        }
        
        $_storeAuth['ID_front_file_data'] = $_storeAuth->getData('ID_front_file');
        $_storeAuth['ID_back_file_data'] = $_storeAuth->getData('ID_back_file');
        $_storeAuth['file1_data'] = $_storeAuth->getData('file1');
        $_storeAuth['file_data'] = $_storeAuth->getData('file');
        $_storeAuth['bank_file_data'] = $_storeAuth->getData('bank_file');
        $_storeAuth['ID_front_file_data'] = $_storeAuth->getData('ID_front_file');
        
        return $this->fetch(
            '', [
                'item' => $_storeAuth,
                'areas' => $_area,
            ]
        );
    }
    
    /**
     * 店铺装修
     *
     * @param StoreModel $store
     * @param Request $request
     *
     * @optimization Malson 2019-03-12 11:03
     *
     * @return array|mixed
     */
    public function fitment(StoreModel $store, Request $request)
    {
        try {
            $data = $store
                ->where(['store_id' => Session::get('client_store_id')])
                ->field([
                    'goods_style',
                    'back_image',
                    'pc_back_image'
                ])
                ->find();
            $data['back_image_data'] = $data->getData('back_image');
            $data['pc_back_image_data'] = $data->getData('pc_back_image');
            if ($request::isPost()) {
                
                // 获取参数
                $param = $request::post();
                Db::startTrans();
                try {
                    // 验证
                    $param['store_id'] = Session::get('client_store_id');
                    $_valid = new StoreValid;
                    
                    if (!$_valid->scene('fitment')->check($param)) {
                        return [
                            'code' => -100,
                            'message' => $_valid->getError(),
                        ];
                    }
                    $store
                        ->allowField([
                            'goods_style',
                            'back_image',
                            'pc_back_image',
                        ])
                        ->isUpdate(true)
                        ->save($param);
                    Db::commit();
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/store/fitment'];
                    
                } catch (\Exception $e) {
                    Db::rollback();
                    return ['code' => -100, 'message' => config('message.')['-1']];
                }
            }
            
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => config('message.')['-1']];
        }

        return $this->fetch(
            '', [
                'item' => $data,
            ]
        );
    }
    
    /**
     * 修改店铺状态
     *
     * @param StoreModel $store
     *
     * is_shop      tinyint(1) unsigned [1] 是否开启门店自提 0 否 1 是
     * is_express    tinyint(1) unsigned [1]    是否开启全国包邮 0 否 1 是
     * is_city        tinyint(1) unsigned [1]    是否开启同城配送 0 否 1 是
     * @optimization Malson 2019-03-12 11:21
     *
     * @return array
     */
    function switchEnabled(StoreModel $store)
    {
        $_param = $this->request->post();
        try {
            if (
                !isset($_param['type']) ||
                !isset($_param['enable']) ||
                !isset($_param['store_id']) ||
                empty($_param['type']) ||
                empty($_param['store_id'])
            ) {
                return ['code' => -1, 'message' => '参数错误'];
            }
            
            if ($_param['enable'] != 1) {
                switch ($_param['type']) {
                    case 'is_express':  // 快递配送
                        // 判断是否存在快递配送的商品
                        if (GoodsModel::where([
                                ['express', '=', 1],
                                ['store_id', '=', Session::get('client_store_id')],
                            ])->count() > 0) {
                            return ['code' => -1, 'message' => '您的店铺下存在快递配送的商品,无法关闭'];
                        }
                        break;
                    case 'is_shop':     // 门店自提
                        // 判断是否存在门店自提的商品
                        if (GoodsModel::where([
                                ['express_self_lifting', '=', 1],
                                ['store_id', '=', Session::get('client_store_id')],
                            ])->count() > 0) {
                            return ['code' => -1, 'message' => '您的店铺下存在到店自提的商品,无法关闭'];
                        }
                        
                        break;
                    case 'is_city':     // 同城配送
                        // 判断是否存在同城配送的商品
                        if (GoodsModel::where([
                                ['express_one_city', '=', 1],
                                ['store_id', '=', Session::get('client_store_id')],
                            ])->count() > 0) {
                            return ['code' => -1, 'message' => '您的店铺下存在同城配送的商品,无法关闭'];
                        }
                        break;
                }
            }
            $store->allowField(
                [
                    'is_shop',   // 门店自提
                    'is_express',// 全国包邮
                    'is_city',   // 同城配送
                    'is_delivery',  // 全国快递
                ]
            )->isUpdate(true)
                ->save(
                    [
                        $_param['type'] => $_param['enable'] === '1' ?? '0',
                    ], [
                        'store_id' => Session::get('client_store_id'),
                    ]
                );
            return ['code' => 0];
        } catch (\Exception $e) {
            return ['code' => -1, 'message' => config('message.')['-1']];
        }
    }
    
    /**
     * 店铺设置
     */
    public function setting()
    {
        if ($this->request->isPost()) {
            $_POST = $this->request->post();
            
            $_valid = new StoreValid();
            
            if (!$_valid->scene('setting')->check($_POST)) {
                return [
                    'code' => -100,
                    'message' => $_valid->getError(),
                ];
            }
            
            try {
                // 更新数据
                StoreModel::update($_POST, [
                    'store_id' => Session::get('client_store_id'),
                ], [
                    'is_city',          // 同城配送
                    //                    'is_pay_delivery',  // 货到付款
                    'is_shop',          // 门店自提
                    //                    'is_express',       // 全国包邮
                    'is_delivery',       // 全国快递
                    //                    'is_good',          // 发现好店
                    //                    'good_image',       // 发现好店图片
                    'brand_image',       // 发现好店图片
                    'pc_head_back_image',       // pc公共头部图片
                ]);
                
                return [
                    'code' => 0,
                    'message' => config('message.')[0],
                ];
            } catch (\Exception $e) {
                return [
                    'code' => -100,
                    'message' => config('message.')[-1],
                ];
            }
        }
        
        // 获取店铺信息
        $_store = StoreModel::field([
            'is_city',                  // 同城配送
            'is_pay_delivery',          // 货到付款
            'is_shop',                  // 门店自提
            'is_express',               // 全国包邮
            'is_delivery',              // 全国快递
            'is_good',                  // 发现好店
            'good_image',               // 发现好店图片
            'brand_image',              // 品牌甄选展示图
            'pc_head_back_image',       // pc公共头部图片
            'is_added_value_tax',//增值税专用发票
        ])->find(Session::get('client_store_id'));
        
        $_store['good_image_data'] = $_store->getData('good_image');
        $_store['brand_image_data'] = $_store->getData('brand_image');
        $_store['pc_head_back_image_data'] = $_store->getData('pc_head_back_image');
        $pc = config('user.')['pc']['is_include'] ? 1 : 0; //判断是否含有pc
        $this->assign('store', $_store);
        $this->assign('pc', $pc);
        
        return $this->fetch();
    }
    
    /**
     * 处理融云名字
     * @param Member $member
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rongInfo(Member $member)
    {
        $param = file_get_contents("php://input");
        $info = json_decode($param, true) ?? [];
        
        $data = $member
            ->where('member_id', 'in', array_column($info, 'targetId'))
            ->field('member_id,nickname,avatar')
            ->select()->toArray();
        foreach ($info as $k => &$datum) {
            $datum['username'] = $datum['targetId'];
            $datum['nickname'] = $datum['targetId'];
            $datum['avatar'] = config('rongyun.default_avatar');
            foreach ($data as $item) {
                if ($item['member_id'] == $datum['targetId']) {
                    $datum['username'] = $item['member_id'];
                    $datum['nickname'] = $item['nickname'];
                    $datum['avatar'] = $item['avatar'];
                }
            }
        }
        return json_encode($info);
    }
}