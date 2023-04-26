<?php
// 店铺管理
declare(strict_types=1);

namespace app\master\controller;

use app\common\model\Area;
use app\common\model\Area as AreaModel;
use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\StoreGoodsClassify as StoreGoodsClassifyModel;
use app\common\service\TemplateMessage;
use app\master\validate\Store as StoreValid;
use think\Db;
use think\Controller;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Request;
use app\common\model\Store as StoreModel;
use app\common\model\StoreTypeChange as StoreTypeChangeModel;
use app\common\model\Member as MemberModel;
use app\common\model\StoreClassify;
use app\common\model\StoreAuth as StoreAuthModel;
use app\common\model\StoreOperation;
use think\facade\Session;
use app\common\model\StoreCosts;

class Store extends Controller
{

    /**
     * 店铺概况
     * @param StoreModel $store
     * @return array|mixed
     */
    public function general(StoreModel $store)
    {
        try {
            // 获取数据
            $data = $store::withTrashed()
                ->field(
                    'IFNULL(sum(case when shop = 0 and status in (1,3,4) then 1 else 0 end),0) as self_store,
                IFNULL(sum(case when shop in (1,2) and status in (1,3,4) then 1 else 0 end),0) as enter_store,
                IFNULL(sum(case when status = 0 then 1 else 0 end),0) as enter_apply,
                IFNULL(sum(case when status = 1 and shop in (1,2) then 1 else 0 end),0) as enter_apply_yes,
                IFNULL(sum(case when status = 2 then 1 else 0 end),0) as enter_apply_no,
                IFNULL(sum(case when status = 3 and shop in (1,2) then 1 else 0 end),0) as approve,
                IFNULL(sum(case when status = 4 and shop in (1,2) then 1 else 0 end),0) as approve_yes,
                IFNULL(sum(case when status = 5 and shop in (1,2) then 1 else 0 end),0) as approve_no,
                IFNULL(sum(case when status = 3 and shop = 0 then 1 else 0 end),0) as self_approve,
                IFNULL(sum(case when status = 4 and shop = 0 then 1 else 0 end),0) as self_approve_yes,
                IFNULL(sum(case when status = 5 and shop = 0 then 1 else 0 end),0) as self_approve_no,
                IFNULL(sum(case when is_city = 1 and shop = 0 and status = 4 then 1 else 0 end),0) as is_city_count,
                IFNULL(sum(case when is_pay_delivery = 1 and shop = 0 and status = 4 then 1 else 0 end),0) as is_pay_delivery_count,
                IFNULL(sum(case when is_shop = 1 and shop = 0 and status = 4 then 1 else 0 end),0) as is_shop_count,
                IFNULL(sum(case when is_express = 1 and shop = 0 and status = 4 then 1 else 0 end),0) as is_express_count,
                IFNULL(sum(case when brand_classify_id is not NULL and shop = 0 and status = 4 then 1 else 0 end),0) as is_brand_count,
                IFNULL(sum(case when is_good = 1 and shop = 0 and status = 4 then 1 else 0 end),0) as is_good_count'
                )
                ->cache(TRUE, 60)
                ->find();

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch(
            '',
            [
                'item' => $data,
            ]
        );
    }

    /**
     * 店铺列表
     * @param Request $request
     * @param StoreModel $storeModel
     * @param StoreClassify $storeClassify
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, StoreModel $storeModel, StoreClassify $storeClassify)
    {
        try {

            // 获取参数
            $param = $request::get();

            // 条件定义
            $condition[] = ['store_id', '>', 0];
            $condition[0] = ['shop', '=', 0];
            // 只查询 申请通过 认证中 认证通过 认证不通过的店铺
            $condition[] = ['a.status', 'in', [1, 3, 4, 5]];

            // 如果查询了入住时间
            if (!empty($param['date'])) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                // 添加查询时间到where语句中
                array_push($condition, ['a.create_time', 'between time', [$begin, $end]]);
            }

            if (!empty($param['keywords'])) {
                $condition[] = ['store_name|member.phone', 'like', '%' . $param['keywords'] . '%'];
            }  // 关键词

            if (array_key_exists('category', $param) && $param['category'] != -1) {
                $condition[] = ['category', 'eq', $param['category']];
            }// 主营类目
            if (array_key_exists('status', $param) && $param['status'] != -1) {
                $condition[] = ['a.status', 'eq', $param['status']];
            }// 入驻申请状态（店铺概况传入）
            // 店铺类型 0自营 1,2入驻店铺
            if (array_key_exists('type', $param)) {
                if ($param['type'] == 0) {
                    $condition[0] = ['shop', '=', $param['type']];
                } else {
                    $condition[0] = ['shop', 'in', '1,2'];
                    // 个人  公司
                    if (array_key_exists('shop', $param) && $param['shop'] != -1) {
                        $condition[0] = ['shop', '=', $param['shop']];
                    }
                    // 申请认证时间
                    if (array_key_exists('date1', $param) && $param['date1']) {
                        list($begin, $end) = explode(' - ', $param['date1']);
                        $end = $end . ' 23:59:59';
                        array_push($condition, ['store_auth.create_time', 'between', [$begin, $end]]);
                    }
                    if (array_key_exists('status', $param) && $param['status'] != -1) {
                        $condition[] = ['a.status', 'eq', $param['status']];
                    }// 认证审核状态
                }
            }
            // 店铺特色 同城配送 货到付款 门店自提 全国包邮 品牌甄选 发现好店
            $whereOr = [];
            if (array_key_exists('conditions', $param) && strpos(implode(',', $param['conditions']), '0') === FALSE) {
                if (strpos(implode(',', $param['conditions']), 'is_brand') !== FALSE && count(
                        $param['conditions']
                    ) == 1) {
                    // 只有品牌甄选情况
                    $condition[] = ['brand_classify_id', 'exp', Db::raw('is not null')];
                } else {
                    if (strpos(implode(',', $param['conditions']), 'is_brand') !== FALSE && count($param['conditions']) > 1) {
                        // 多选含品牌甄选情况
                        $whereOr[] = ['brand_classify_id', 'exp', Db::raw('is not null')];
                        unset($param['conditions'][array_search("is_brand", $param['conditions'])]);
                        if (!empty($param['conditions'])) {
                            $condition[] = [join($param['conditions'], '|'), '=', 1];
                        }
                    } else {
                        // 不含品牌甄选情况
                        $condition[] = [join($param['conditions'], '|'), '=', 1];
                    }
                }
            }
            $data = $storeModel::withTrashed()
                ->alias('a')
                ->join('member member', 'a.member_id = member.member_id', 'left')
                ->join('store_auth store_auth', 'a.store_id = store_auth.store_id and isnull(store_auth.delete_time)', 'left')
                ->where($condition)
                ->where(function ($e) use ($condition, $whereOr) {
                    $e->whereOr($whereOr);
                })
                ->field('a.*,member.phone,a.status as status_name,store_auth_id')
                ->order(['store_id' => 'desc'])
                ->paginate(15, FALSE, ['query' => $param]);

            if (array_key_exists('is_stop', $param) && $param['is_stop'] != -1) {
                if ($param['is_stop'] == 1) {
                    // 获取数据
                    $data = $storeModel
                        ->alias('a')
                        ->join('member member', 'a.member_id = member.member_id', 'left')
                        ->join('store_auth store_auth', 'a.store_id = store_auth.store_id', 'left')
                        ->where($condition)
                        ->where(function ($e) use ($condition, $whereOr) {
                            $e->whereOr($whereOr);
                        })
                        ->field('a.*,member.phone,a.status as status_name,store_auth_id')
                        ->order(['store_id' => 'desc'])
                        ->paginate(15, FALSE, ['query' => $param]);
                } else {
                    // 获取数据
                    $data = $storeModel::withTrashed()
                        ->alias('a')
                        ->join('member member', 'a.member_id = member.member_id', 'left')
                        ->join('store_auth store_auth', 'a.store_id = store_auth.store_id', 'left')
                        ->where($condition)
                        ->where(function ($e) use ($condition, $whereOr) {
                            $e->whereOr($whereOr);
                        })
                        ->where('a.delete_time', 'not null')
                        ->field('a.*,member.phone,a.status as status_name,store_auth_id')
                        ->order(['store_id' => 'desc'])
                        ->paginate(15, FALSE, ['query' => $param]);
                }
            }


        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch(
            '',
            [
                'data'          => $data,
                'count'         => $storeModel->getCount(),
                'classify_list' => $storeClassify->where('status', 1)->select(),
                'conditions'    => implode(',', Request::instance()->param('conditions', [])),
            ]
        );
    }


    /**
     * 店铺新增
     * @param Request $request
     * @param StoreModel $storeModel
     * @param StoreClassify $storeClassify
     * @param StoreValid $storeValid
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, StoreClassify $storeClassify, StoreValid $storeValid)
    {

        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();

                $_memberId = $request::get('member_id', '');

                if (!$storeValid->scene('create_store')->check($param)) {
                    return ['code' => -100, 'message' => $storeValid->getError()];
                };

                $_storeData = array_merge(Cache::get("MASTER_CREATE_STORE_{$_memberId}", []), $param);

                Cache::set("MASTER_CREATE_STORE_{$param['member_id']}", $_storeData, 86400);

                return [
                    'code'    => 0,
                    'message' => config('message.')[0],
                    'url'     => "/store/storeAuth?member_id={$param['member_id']}",
                ];

            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $_item = [];
        // 用户ID
        $_memberId = $request::get('member_id', '');

        // 如果用户ID 不为空的话
        if (!empty($_memberId)) {
            // 从缓存获取 店铺上传的信息
            $_item = Cache::get("MASTER_CREATE_STORE_{$_memberId}");

            // 会员账户信息
            $_item['member_name'] = Member::where('member_id', $_memberId)->value('phone');
            $_item['logo_data'] = $_item['logo'];
            $_ossConfig = config('oss.');
            $_item['logo'] = $_ossConfig['prefix'] . $_item['logo'];


        }

        return $this->fetch(
            '',
            [
                'rand_str'      => md5(microtime()),
                'classify_list' => $storeClassify->where('status', 1)->select(),
                'store_id'      => Request::instance()->param('store_id', '0'),
                'item'          => $_item,
            ]
        );
    }


    /**
     * 店铺新增选择会员信息
     * @param Request $request
     * @param MemberModel $memberModel
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function member_search(Request $request, MemberModel $memberModel)
    {

        // 获取参数
        $param = $request::get();

        // 条件定义
        $condition[] = ['member.member_id', '>', 0];
        $condition[] = ['member.phone', '>', 0];

        // 创建店铺
        if (isset($param['create_store'])) {
            // 会员不能有店铺
            $condition[] = ['store.store_id', 'exp', Db::raw('IS NULL')];
        }

        // 条件搜索
        if (!empty($param['keyword'])) {
            $condition[] = ['member.phone', 'like', '%' . $param['keyword'] . '%'];
        }

        $data = $memberModel->alias('member')
            ->join('store store', 'store.member_id = member.member_id', 'left')
            ->where($condition)
            ->field('member.member_id,member.phone,member.nickname')
            ->order(['member_id' => 'asc'])
            ->group('member.member_id')
            ->paginate(15, FALSE, ['query' => $param]);

        return $this->fetch(
            '',
            [
                'data' => $data,
            ]
        );
    }

    /**
     * 店铺编辑
     * @param Request $request
     * @param StoreModel $storeModel
     * @param StoreClassify $storeClassify
     * @param StoreAuthModel $storeAuth
     * @param MemberModel $member
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, StoreModel $storeModel, StoreClassify $storeClassify, StoreAuthModel $storeAuth, Member $member)
    {
        // 获取店铺信息
        $item = $storeModel::withTrashed()->find($request::get('store_id'));

        // 判断店铺是否存在
        if (!$item) {
            // 店铺不存在
            $this->error('店铺不存在');
        }

        if ($request::isPost()) {
            try {
                // 启动事务
                Db::startTrans();
                // 获取参数
                $param = $request::post();

                $storeModel->valid($param, 'master_edit_store');

                if (!isset($param['end_time']) || empty($param['end_time'])) {
                    $param['end_time'] = NULL;
                }

                // 拼接省市区信息
                $_areaCondition = "{$param['province']},{$param['city']},{$param['area']}";

                // 修改店铺所在地 为汉字
                $_areaList = Area::where([
                    ['area_id', 'in', $_areaCondition],
                ])->orderRaw("field(area_id,{$param['province']},{$param['city']},{$param['area']})")->column('area_name');

                $param['province'] = $_areaList[0];
                $param['city'] = $_areaList[1];
                $param['area'] = $_areaList[2];

                unset($param['member_id']);

                if (in_array($item['status'], [4, 5])) {
                    $param['status'] = 3;
                }

                // 数据 - 更新 withTrashed 修复 删除的店铺不能更新信息的问题
//                dump($param);
                $storeModel::withTrashed()
                    ->where('store_id', $item['store_id'])
                    ->update($param);

                // 提交事务
                Db::commit();
                //清除店铺对应导航条缓存

                foreach ([
                             'flatClient_mainNav_' . $item['store_id'],
                             'flatClient_sideNav_' . $item['store_id'],
                             'flatClient_allAuth_' . $item['store_id'],
                             'flatClient_allAuthTree_' . $item['store_id'],
                             'flatClient_breadcrumb',
                         ] as $v) {
                    Cache::rm($v);
                }

                return [
                    'code'    => 0,
                    'message' => config('message.')[0],
                    'url'     => "/store/storeAuth?store_id={$item['store_id']}",
                ];

            } catch (ValidateException $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }
//
        // 拼接省市区信息
        $_areaCondition = "{$item['province']},{$item['city']},{$item['area']}";

        // 修改店铺所在地 为汉字
        $_areaList = Area::where([
            ['area_name', 'in', $_areaCondition],
        ])->orderRaw("field(area_name,'{$item['province']}','{$item['city']}','{$item['area']}')")->column('area_id');

        $item['province'] = $_areaList[0];
        $item['city'] = $_areaList[1];
        $item['area'] = $_areaList[2];

        $item['logo_data'] = $item->getData('logo');

        return $this->fetch(
            'create',
            [
                'classify_list' => $storeClassify->where('status', 1)->select(),
                'item'          => $item,
                'store_id'      => $item['store_id'],
            ]
        );
    }

    // 审核 开始

    /**
     * 审核店铺信息
     */
    public function auditStoreInfo()
    {
        $_storeId = $this->request->get('store_id', NULL);

        $_store = StoreModel::withTrashed()->find($_storeId);

        if (!$_store) {
            $this->error('店铺不存在');
        }

        $_classify = StoreClassify::field([
            'store_classify_id',
            'title',
        ])->where('status', 1)->select();

        // 渲染到页面的数据
        $_data = [
            'store_id'    => $_store['store_id'],          // 店铺Id
            'member_id'   => $_store['member_id'],        // 店铺管理者ID
            'member_name' => $_store->member->phone,    // 店铺管理者手机号
            'category'    => $_store['category'],          // 店铺主营项目
            'store_name'  => $_store['store_name'],      // 店铺名称
            'logo'        => $_store['logo'],                  // 店铺LOGO
            'phone'       => $_store['phone'],                // 客服电话
            'province'    => $_store['province'],          // 省
            'city'        => $_store['city'],                  // 市
            'area'        => $_store['area'],                  // 区
            'address'     => $_store['address'],            // 店铺详细地址
            'lng'         => $_store['lng'],                    // 经度
            'lat'         => $_store['lat'],                    // 维度
            'sort'        => $_store['sort'],                  // 排序
            'keywords'    => $_store['keywords'],          // 关键字
            'describe'    => $_store['describe'],          // 描述
            'end_time'    => $_store['end_time'],          // 到期时间
            'type'        => $_store['type'],                  // 店铺类型
            'shop'        => $_store['shop'],                  // 店铺类型
        ];

        // 店铺信息
        $this->assign('data', $_data);
        // 商品分类
        $this->assign('classify_list', $_classify);
        // 店铺ID
        $this->assign('store_id', $_store['store_id']);

        return $this->fetch();
    }

    /**
     * 审核认证信息
     */
    public function auditAuthInfo()
    {
        $_storeId = $this->request->get('store_id', '');

        if ($this->request->isPost()) {

            try {

                Db::startTrans();

                // 获取店铺信息
                $_store = StoreModel::withTrashed()->find($_storeId);

                // 判断店铺是否存在
                if (!$_store) {
                    return ['code' => -100, 'message' => '店铺不存在'];
                }

                $_param = $this->request->post();

                // 验证参数

                (new StoreAuthModel())->valid($_param, 'master_audit_store');


                $_storeData = [
                    'status' => 0,
                ];

                $_operationData = [
                    'type'      => 4,
                    'manage_id' => Session::get('manage_id'),
                    'nickname'  => Session::get('manageName'),
                    'store_id'  => $_store['store_id'],
                    'reason'    => '',
                ];

                // 判断认证审核状态
                if ($_param['status'] == 1) {
                    // 通过
                    $_storeData['status'] = 4;
                } else {
                    // 未通过
                    $_storeData['status'] = 5;
                    $_operationData['reason'] = $_param['reason'];
                }

                StoreModel::withTrashed()->where('store_id', $_storeId)->update($_storeData);
                StoreOperation::create($_operationData);
                Db::commit();
                return [
                    'code'    => 0,
                    'message' => config('message.')[0],
                    'url'     => 'store/index',
                ];
            } catch (ValidateException $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }


        if (empty($_storeId)) {
            $this->error('店铺不存在');
        }


        $_auditLog = StoreOperation::withTrashed()
            ->where('store_id', $_storeId)
            ->order('create_time DESC')
            ->find();

        $_storeAuth = StoreAuthModel::withTrashed()
            ->where('store_id', $_storeId)
            ->find();

        if (!$_storeAuth) {
            $this->error('店铺不存在');
        }

        $_data = [
            'type'               => $_storeAuth['type'],
            'auth_name'          => $_storeAuth['auth_name'],
            'tel'                => $_storeAuth['tel'],
            'ID_type'            => $_storeAuth['ID_type'],
            'auth_number'        => $_storeAuth['auth_number'],
            'ID_front_file'      => $_storeAuth['ID_front_file'],
            'ID_back_file'       => $_storeAuth['ID_back_file'],
            'company_number'     => $_storeAuth['company_number'],
            'file1'              => $_storeAuth['file1'],
            'file'               => $_storeAuth['file'],
            'company_name'       => $_storeAuth['company_name'],
            'bank_file'          => $_storeAuth['bank_file'],
            'bank_province'      => $_storeAuth['bank_province'],
            'bank_city'          => $_storeAuth['bank_city'],
            'bank_area'          => $_storeAuth['bank_area'],
            'account_name'       => $_storeAuth['account_name'],
            'account_bank_name'  => $_storeAuth['account_bank_name'],
            'account_sub_branch' => $_storeAuth['account_sub_branch'],
            'bank_number'        => $_storeAuth['bank_number'],
        ];

        $_storeAuthStatus = StoreModel::withTrashed()
            ->where('store_id', $_storeId)
            ->value('status');


        // 开户行的地址
        $_area = [
            'province' => [],
            'city'     => [],
            'area'     => [],
        ];

        $_addressIdList = [
            'province' => 0,
            'city'     => 0,
            'area'     => 0,
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

        $_areaList = Area::field(
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
        $this->assign('data', $_data);
        $this->assign('areas', $_area);
        $this->assign('audit_log', $_auditLog);
        $this->assign('store_auth_status', $_storeAuthStatus);
        return $this->fetch();
    }

    /*联系我们*/
    public function contact(Request $request, StoreModel $store, StoreClassify $storeClassify)
    {
        $item = $store->get($request::get('store_id'));

        if ($request::isPost()) {

            // 获取参数
            $param = $request::post();

            try {
                // 验证
                $check = $store->valid($param, 'contact');
                if ($check['code']) {
                    return $check;
                }

                // 数据 - 编辑
                $item->allowField(TRUE)->isUpdate(TRUE)->save($param);

                // 提交事务
                Db::commit();

                return [
                    'code'    => 0,
                    'message' => config('message.')[0],
                    'url'     => 'store/contact?store_id=' . $request::get('store_id'),
                ];

            } catch (ValidateException $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch(
            '',
            [
                'item'          => $item,
                'classify_list' => $storeClassify->where('status', 1)->select(),
                'store_id'      => Request::instance()->param('store_id', '0'),
            ]
        );
    }

    /*图片信息*/
    public function images(Request $request, StoreModel $store, StoreClassify $storeClassify)
    {
        $item = $store->get($request::get('store_id'));

        if ($request::isPost()) {

            // 获取参数
            $param = $request::post();

            try {
                // 验证
                $check = $store->valid($param, 'shipping_instructions');
                if ($check['code']) {
                    return $check;
                }

                // 数据 - 编辑
                $item->allowField(TRUE)->isUpdate(TRUE)->save($param);

                // 提交事务
                Db::commit();

                return [
                    'code'    => 0,
                    'message' => config('message.')[0],
                    'url'     => 'store/images?store_id=' . $request::get('store_id'),
                ];

            } catch (ValidateException $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch(
            '',
            [
                'item'          => $item,
                'classify_list' => $storeClassify->where('status', 1)->select(),
                'store_id'      => Request::instance()->param('store_id', '0'),
            ]
        );
    }

    /*认证信息*/
    public function storeAuth(Request $request, StoreAuthModel $storeAuth, AreaModel $area, StoreClassify $storeClassify, StoreModel $store)
    {
        $_memberId = $request::get('member_id', '');

        if ($request::isPost()) {
            $_param = $request::post();

            Db::startTrans();
            try {
                // 验证发送过来的参数
                $storeAuth->valid($_param, 'master_store_auth_post');

                // 如果 memberId 不为空的话 说明是 创建
                if ($_memberId !== '') {
                    // 如果 member_id 不为空的话 说明是在创建
                    $_storeData = Cache::get("MASTER_CREATE_STORE_{$_memberId}");

                    // 拼接省市区信息
                    $_areaCondition = "{$_storeData['province']},{$_storeData['city']},{$_storeData['area']}";

                    // 转换店铺所在地 为汉字
                    $_areaList = Area::where([
                        ['area_id', 'in', $_areaCondition],
                    ])->orderRaw("field(area_id,{$_storeData['province']},{$_storeData['city']},{$_storeData['area']})")->column('area_name');

                    $_storeData['province'] = $_areaList[0];
                    $_storeData['city'] = $_areaList[1];
                    $_storeData['area'] = $_areaList[2];

                    // 创建的自营店铺 状态直接为待审核
                    $_storeData['status'] = 3;

                    // 店铺认证数据
                    $_authData = $_param;

                    // 创建店铺
                    $_store = $store::create($_storeData);
                    // 店铺ID
                    $_authData['store_id'] = $_store['store_id'];
                    // 用户ID
                    $_authData['member_id'] = $_store['member_id'];

                    // 自动写入 银行信息
                    // 银行信息 1 对公 2 对私
                    // 认证类型 2 企业 1 个人
                    // 需要把数字反过来
                    $_authData['bank_type'] = $_authData['type'] == 1 ? 2 : 1;
                    // 创建店铺审核表
                    $storeAuth::create($_authData);
                    // 为店铺创建一个默认的店铺商品分类
                    StoreGoodsClassifyModel::create([
                        'store_id'  => $_store['store_id'],
                        'parent_id' => 0,
                        'title'     => '默认分类',
                        'count'     => 1,
                    ]);
                    // 清理缓存
                    Cache::rm("MASTER_CREATE_STORE_{$_memberId}");
                } else {
                    // 保存
                    $_storeAuth = $storeAuth->get(
                        [
                            'store_id' => $request::get('store_id'),
                        ]
                    );

                    $_store = $store->where([
                        ['store_id', '=', $request::get('store_id')],
                    ])->find();


                    if (in_array($_store['status'], [4, 5])) {
                        $_store->isUpdate(TRUE)->save([
                            'status' => 3,
                        ], [
                            'store_id' => $_store['store_id'],
                        ]);
                    }

                    // 自动写入 银行信息
                    // 银行信息 1 对公 2 对私
                    // 认证类型 2 企业 1 个人
                    // 需要把数字反过来
                    $_param['bank_type'] = $_param['type'] == 1 ? 2 : 1;

                    $_storeAuth->allowField(
                        [
                            'store_auth_id',
                            'type',
                            'member_id',
                            'auth_name',
                            'tel',
                            'ID_type',
                            'auth_number',
                            'ID_front_file',
                            'ID_back_file',
                            'company_name',
                            'company_number',
                            'file',
                            'file1',
                            'bank_file',
                            'licence_file',
                            'qualification_file',
                            'bank_province',
                            'bank_city',
                            'bank_area',
                            'bank_type',
                            'account_name',
                            'account_bank_name',
                            'account_sub_branch',
                            'bank_number',
                        ]
                    )->save($_param);
                }

                Db::commit();
                return [
                    'code'    => 0,
                    'message' => config('message.')[0],
                    'url'     => "/store/index",
                ];
            } catch (ValidateException $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];

            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, config('message.')[-1]];
            }

        }

        if ($_memberId === '') {
            $_storeAuth = $storeAuth
                ->alias('StoreAuth')
                ->field(
                    [
                        'StoreAuth.type'               => 'type',
                        'StoreAuth.store_auth_id'      => 'store_auth_id',
                        'StoreAuth.member_id'          => 'member_id',
                        'StoreAuth.auth_name'          => 'auth_name',
                        'StoreAuth.tel'                => 'tel',
                        'StoreAuth.ID_type'            => 'ID_type',
                        'StoreAuth.auth_number'        => 'auth_number',
                        'StoreAuth.ID_front_file'      => 'ID_front_file',
                        'StoreAuth.ID_back_file'       => 'ID_back_file',
                        'StoreAuth.company_name'       => 'company_name',
                        'StoreAuth.company_number'     => 'company_number',
                        'StoreAuth.file'               => 'file',
                        'StoreAuth.file1'              => 'file1',
                        'StoreAuth.bank_file'          => 'bank_file',
                        'StoreAuth.licence_file'       => 'licence_file',
                        'StoreAuth.qualification_file' => 'qualification_file',
                        'StoreAuth.bank_province'      => 'bank_province',
                        'StoreAuth.bank_city'          => 'bank_city',
                        'StoreAuth.bank_area'          => 'bank_area',
                        'StoreAuth.bank_type'          => 'bank_type',
                        'StoreAuth.account_name'       => 'account_name',
                        'StoreAuth.account_bank_name'  => 'account_bank_name',
                        'StoreAuth.account_sub_branch' => 'account_sub_branch',
                        'StoreAuth.bank_number'        => 'bank_number',
                        'Store.status'                 => 'status',
                        'Store.bank_status'            => 'bank_status',
                    ]
                )
                ->join('Store Store', 'Store.store_id = StoreAuth.store_id')
                ->where(
                    [
                        'StoreAuth.store_id' => $request::get('store_id'),
                    ]
                )->find();

            if (!$_storeAuth) {
                $this->error('不存在的店铺');
            }

            $_storeAuth['ID_front_file_data'] = $_storeAuth->getData('ID_front_file');
            $_storeAuth['ID_back_file_data'] = $_storeAuth->getData('ID_back_file');
            $_storeAuth['file1_data'] = $_storeAuth->getData('file1');
            $_storeAuth['file_data'] = $_storeAuth->getData('file');
            $_storeAuth['bank_file_data'] = $_storeAuth->getData('bank_file');
        } else {

            $_storeAuth = Cache::get("MASTER_CREATE_STORE_AUTH_{$_memberId}");
            // 获取店铺信息
            $_ossConfig = config('oss.');

            // 身份证正面照片
            if (isset($_storeAuth['ID_front_file'])) {
                $_storeAuth['ID_front_file_data'] = $_storeAuth['ID_front_file'];
                $_storeAuth['ID_front_file'] = $_ossConfig['prefix'] . $_storeAuth['ID_front_file'];
            }

            // 身份证正面照片
            if (isset($_storeAuth['ID_back_file'])) {
                $_storeAuth['ID_back_file_data'] = $_storeAuth['ID_back_file'];
                $_storeAuth['ID_back_file'] = $_ossConfig['prefix'] . $_storeAuth['ID_back_file'];
            }


            // 营业执照照片
            if (isset($_storeAuth['file1'])) {
                $_storeAuth['file1_data'] = $_storeAuth['file1'];
                $_storeAuth['file1'] = $_ossConfig['prefix'] . $_storeAuth['file1'];
            }

            // 获取店铺中的店铺类型和分类 赋值到页面
            switch ($_storeAuth['type']) {
                case '1':   // 个人店铺
                    // 手持身份证照片
                    if (isset($_storeAuth['file'])) {
                        $_storeAuth['file_data'] = $_storeAuth['file'];
                        $_storeAuth['file'] = $_ossConfig['prefix'] . $_storeAuth['file'];
                    }
                    break;
                case'2':    // 企业店铺
                    // 银行开户证明
                    if (isset($_storeAuth['bank_file'])) {
                        $_storeAuth['bank_file_data'] = $_storeAuth['bank_file'];
                        $_storeAuth['bank_file'] = $_ossConfig['prefix'] . $_storeAuth['bank_file'];
                    }
                    break;
            }
        }

        // 开户行的地址
        $_area = [
            'province' => [],
            'city'     => [],
            'area'     => [],
        ];

        $_addressIdList = [
            'province' => 0,
            'city'     => 0,
            'area'     => 0,
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


        return $this->fetch(
            '',
            [
                'item'          => $_storeAuth ?? [],
                'areas'         => $_area,
                'classify_list' => $storeClassify->where('status', 1)->select(),
                'store_id'      => Request::instance()->param('store_id', '0'),
            ]
        );
    }

    public function setting(StoreModel $store, Request $request)
    {
        $_memberId = $request::get('member_id', '');

        if ($request::isPost()) {

            $_param = $request::post();

            try {
                $store->valid($_param, 'master_create_store_setting');

                // member_id 不等于空 说明在创建设置
                if ($_memberId !== '') {
                    $_storeCreateInfo = Cache::get("MASTER_CREATE_STORE_{$_memberId}");


                    // 将店铺信息保存到缓存里面
                    Cache::set("MASTER_CREATE_STORE_{$_memberId}", array_merge($_storeCreateInfo, $_param), 86400);

                    return [
                        'code'    => 0,
                        'message' => config('message.')[0],
                        'url'     => "/store/fitment?member_id={$_memberId}",
                    ];
                }

                // 没有 member_id 说明在保存
                $_storeId = $request::get('store_id', '');

                $_saveData = $this->request->only([
                    'is_city',
                    'is_pay_delivery',
                    'is_shop',
                    'is_express',
                    'is_good',
                    'good_image',
                    'brand_image',
                ]);

                $store::withTrashed()
                    ->where('store_id', $_storeId)
                    ->update(
                        $_saveData
                    );

                return [
                    'code'    => 0,
                    'message' => config('message.')[0],
                    'url'     => "/store/index",
                ];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {

                return [
                    'code'    => -100,
                    'message' => config('message.')[-1],
                ];
            }

        }


        // 新建
        if (!empty($_memberId)) {

            $_store = Cache::get("MASTER_CREATE_STORE_{$_memberId}");

            $_ossConfig = config('oss.');

            if (isset($_store['good_image'])) {
                $_store['good_image_data'] = $_store['good_image'];
                $_store['good_image'] = $_ossConfig['prefix'] . $_store['good_image'];
            }

        } else {

            // 更新或查看
            $_storeId = $this->request->get('store_id', -1);

            if ($_storeId === -1) {
                $this->error('店铺不存在');
            }

            $_store = $store::withTrashed()
                ->field(
                    [
                        'is_city',  // 同城配送
                        'is_pay_delivery',  // 货到付款
                        'is_shop',//门店自提
                        'is_express',//全国包邮
                        'is_good',//发现好店
                        'good_image',//发现好店展示图
                        'brand_image',//发现好店展示图
                        'shop',
                    ]
                )->where(
                    [
                        ['store_id', '=', $_storeId],
                    ]
                )->find();

            // 如果 店铺 存在的话
            if ($_store) {
                // 获取原始图片信息
                $_store['good_image_data'] = $_store->getData('good_image');
                $_store['brand_image_data'] = $_store->getData('brand_image');
            }
        }

        return $this->fetch(
            'store_setting',
            [
                'item' => $_store,
            ]
        );

    }

    /*店铺装修*/
    public function fitment(StoreModel $store, StoreAuthModel $storeAuth, Request $request)
    {

        if ($request::isPost()) {

            $_memberId = $request::get('member_id', '');
            $_storeId = $request::get('store_id', '');

            $_param = $request::post();


            try {
                $store->valid($_param, 'master_create_store_fitment');

                $_saveData = Request::only([
                    'back_image',
                    'goods_style',
                ]);

                $store::withTrashed()->where([
                    ['store_id', '=', $_storeId],
                ])->update(
                    $_saveData
                );

                return [
                    'code'    => 0,
                    'message' => config('message.')[0],
                    'url'     => 'store/index',
                ];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return [
                    'code'    => -100,
                    'message' => config('message.')[-1],
                ];
            }

        }

        $_store = $store::withTrashed()
            ->where(['store_id' => $request::get('store_id')])
            ->find();

        if ($_store) {
            // 店铺背景图原始信息
            $_store['back_image_data'] = $_store->getData('back_image');
        }

        return $this->fetch(
            '',
            [
                'item'     => $_store,
                'store_id' => Request::instance()->param('store_id', '0'),
            ]
        );
    }

    /**
     * 注销店铺
     * @param Request $request
     * @param StoreModel $storeModel
     * @param StoreOperation $storeOperation
     * @return array|mixed
     */
    public function destroy(Request $request, StoreModel $storeModel, StoreOperation $storeOperation, Goods $goods)
    {
        $param = $request::post();
        if ($request::isPost()) {
            Db::startTrans();
            try {
                $check = $storeModel->valid($param, 'log_out');
                if ($check['code']) {
                    return $check;
                }

                $param['manage_id'] = Session::get("manage_id");
                $param['nickname'] = Session::get("manageName");
                $storeOperation->allowField(TRUE)->save($param);
                // 获取店铺用户信息
                $info = $storeModel
                    ->alias('s')
                    ->where([
                        ['s.store_id', '=', $param['store_id']],
                    ])
                    ->join('member m', 'm.member_id = s.member_id')
                    ->field('m.member_id,m.web_open_id,m.micro_open_id,m.subscribe_time,s.store_name,
                    m.phone')
                    ->find();

                // 下架商品
                $goods->where('store_id', $param['store_id'])->update(['is_putaway' => 0]);

                // 注销店铺
                $param['id'] = $param['store_id'];
                $storeModel::destroy($param['id']);
                // 推送消息[不推小程序][注销店铺]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey'         => 'shop_state',
                    'openId'         => $info['web_open_id'],
                    'subscribe_time' => $info['subscribe_time'],
                    'microId'        => '',
                    'phone'          => $info['phone'],
                    'data'           => [0, $info['store_name'], $param['reason']],
                    'inside_data'    => [
                        'member_id'  => $info['member_id'],
                        'type'       => 0,
                        'jump_state' => '-1',
                        'file'       => 'image/cuo.png',
                    ],
                    'sms_data'       => [],
                ]);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch(
            '',
            [
                'store_id' => $request::get('id'),
            ]
        );
    }

    /**
     * 启用店铺
     * @param Request $request
     * @param StoreModel $storeModel
     * @param StoreOperation $storeOperation
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function open_store(Request $request, StoreModel $storeModel, StoreOperation $storeOperation)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $store = $storeModel::onlyTrashed()->find($param['store_id']);
                Db::startTrans();
                $store->restore();
                // 获取店铺用户信息 恢复软删 在查找数据
                $info = $storeModel
                    ->alias('s')
                    ->where([
                        ['s.store_id', '=', $param['store_id']],
                    ])
                    ->join('member m', 'm.member_id = s.member_id')
                    ->field('m.member_id,m.web_open_id,m.subscribe_time,m.micro_open_id,s.store_name,
                    m.phone')
                    ->find();
                $param['manage_id'] = Session::get("manage_id");
                $param['nickname'] = Session::get("manageName");
                $param['type'] = 2;
                $storeOperation->allowField(TRUE)->save($param);
                // 推送消息[不推小程序][启用店铺]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey'         => 'shop_state',
                    'openId'         => $info['web_open_id'],
                    'microId'        => '',
                    'subscribe_time' => $info['subscribe_time'],
                    'phone'          => $info['phone'],
                    'data'           => [1, $info['store_name']],
                    'inside_data'    => [
                        'member_id'  => $info['member_id'],
                        'type'       => 0,
                        'jump_state' => '-1',
                        'file'       => 'image/dui.png',
                    ],
                    'sms_data'       => [],
                ]);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $data = $storeOperation->where(
            [
                ['store_id', '=', $request::get('id')],
                ['type', '=', 1],
            ]
        )
            ->order('create_time', 'desc')
            ->find();

        return $this->fetch(
            '',
            [
                'store_id' => $request::get('id'),
                'item'     => $data,
            ]
        );
    }

    /**
     * 店铺分类排序更新
     * @param Request $request
     * @param StoreModel $storeModel
     * @return array
     */
    public function text_update(Request $request, StoreModel $storeModel)
    {

        if ($request::isPost()) {
            try {
                $storeModel->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 入驻审核列表
     * @param Request $request
     * @param StoreModel $storeModel
     * @param StoreClassify $storeClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checked(Request $request, StoreModel $storeModel, StoreClassify $storeClassify)
    {
        $param = $request::get();

        $condition[] = ['store_id', '>', 0];
        $condition[0] = ['a.status', 'in', '0,2'];

        // 入驻时间
        if (array_key_exists('date', $param) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            // 如果存在 结束时间 则时间结束为 当前日期的晚上 23时59分59秒
            if (isset($end)) {
                $end = $end . ' 23:59:59';
            }
            array_push($condition, ['a.create_time', 'between time', [$begin, $end]]);
        }

        if (!empty($param['keywords'])) {
            $condition[] = ['member.phone|a.store_name', 'like', '%' . $param['keywords'] . '%'];
        }  // 关键词
        if (array_key_exists('category', $param) && $param['category'] != -1) {
            $condition[] = ['category', 'eq', $param['category']];
        }// 主营类目
        if (array_key_exists('status', $param) && $param['status'] != -1) {
            $condition[0] = ['a.status', 'eq', $param['status']];
        }// 入驻申请状态
        $data = $storeModel
            ->alias('a')
            ->join('member member', 'member.member_id = a.member_id', 'left')
            ->where($condition)
            ->field(
                'store_id,store_name,category,province,city,area,address,member.phone,a.create_time,a.status,a.status as status_name'
            )
            ->order('create_time', 'desc')
            ->paginate(10, FALSE, ['query' => $param]);

        return $this->fetch(
            '',
            [
                'data'          => $data,
                'classify_list' => $storeClassify->where('status', 1)->select(),
            ]
        );
    }

    /**
     * 入驻审核操作
     * @param Request $request
     * @param StoreModel $storeModel
     * @param StoreOperation $storeOperation
     * @param StoreAuthModel $storeAuth
     * @return array|mixed
     */
    public function is_checked(Request $request, StoreModel $storeModel, StoreOperation $storeOperation, StoreAuthModel $storeAuth)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                // 获取店铺用户信息
                $info = $storeModel
                    ->alias('s')
                    ->where([
                        ['s.store_id', '=', $param['store_id']],
                    ])
                    ->join('member m', 'm.member_id = s.member_id')
                    ->field('m.member_id,m.web_open_id,m.subscribe_time,s.store_name,m.micro_open_id,
                    m.phone,m.password')
                    ->find();
                Db::startTrans();
                $pwdMsg = '';
                if ($param['status'] == 1) {
                    unset($param['reason']);
                    $storeModel->valid($param, 'head');
                    if ($info['password']) {
                        $pwdMsg = '您的会员登录密码';
                    } else {
                        $pwdMsg = chr(mt_rand(33, 126)) . mt_rand(100000, 999999);
                        // 保存为用户密码
                        (new Member())->allowField(TRUE)->isUpdate(TRUE)->save([
                            'member_id' => $info['member_id'],
                            'password'  => (string)$pwdMsg,
                        ]);
                    }
                } else {
                    $storeModel->valid($param, 'log_out');
                }
                // 更改店铺状态
                $storeModel->where('store_id', $param['store_id'])->update(['status' => $param['status']]);
                // 写入操作店铺纪录
                $param['manage_id'] = Session::get("manage_id");
                $param['nickname'] = Session::get("manageName");
                $param['type'] = 3;
                // 保存
                $storeOperation->allowField(TRUE)->save($param);

                // 如果店铺下的认证表不存在 直接给他创建一个
                if (!$storeAuth->where(
                    [
                        'store_id' => $param['store_id'],
                    ]
                )->find()) {
                    $storeAuth::create(
                        [
                            'store_id'  => $param['store_id'],
                            'member_id' => $info['member_id'],
                        ]
                    );
                };
                // 推送消息[入驻审核`成功`|`失败`]
                $pushServer = app('app\\interfaces\\behavior\\Push');
                $pushServer->send([
                    'tplKey'         => 'reside_apply_state',
                    'openId'         => $info['web_open_id'],
                    'subscribe_time' => $info['subscribe_time'],
                    'microId'        => $info['micro_open_id'],
                    'phone'          => $info['phone'],  // 1 审核通过 2审核未通过
                    'data'           => [$param['status'], $info['store_name'],
                                         $param['status'] - 1 ? $param['reason'] : config('user.common.client_url'), $pwdMsg],
                    'inside_data'    => [
                        'member_id'  => $info['member_id'],
                        'type'       => 0,
                        'jump_state' => $param['status'] - 1 ? '9' : '16',
                        'file'       => ['image/dui.png', 'image/cuo.png'][$param['status'] - 1],
                    ],
                    'sms_data'       => [],
                ]);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (ValidateException $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch(
            '',
            [
                'store_id' => $request::get('store_id'),
            ]
        );
    }

    /**
     * 入驻审核（未通过信息）
     * @param Request $request
     * @param StoreOperation $storeOperation
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checked_info(Request $request, StoreOperation $storeOperation)
    {
        $data = $storeOperation
            ->where(
                [
                    ['store_id', '=', $request::get('store_id')],
                    ['type', '=', 3],
                ]
            )
            ->order('store_operation_id', 'desc')
            ->limit(1)
            ->find();

        return $this->fetch(
            '',
            [
                'item' => $data,
            ]
        );
    }

    /**
     * 费用列表
     * @param Request $request
     * @param StoreClassify $storeClassify
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function costs_set(Request $request, StoreClassify $storeClassify)
    {
        // 获取参数
        $param = $request::get();
        // 父ID
        $data = $storeClassify
            ->relation('store_costs')
            ->where('status', 1)
            ->field('store_classify_id,title,sort,is_recommend,status')
            ->order(['store_classify_id' => 'asc'])
            ->paginate(15, FALSE, ['query' => $param]);
        return $this->fetch(
            '',
            [
                'data' => $data,
            ]
        );
    }

    /**
     * 添加店铺费用
     * @param Request $request
     * @param StoreCosts $storeCosts
     * @return array
     */
    public function add_costs(Request $request, StoreCosts $storeCosts)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $check = $storeCosts->valid($param, 'create');
                if ($check['code']) {
                    return $check;
                }

                $_result = $storeCosts::create($param);

                return ['code' => 0, 'message' => config('message.')[0], 'id' => $_result->store_costs_id];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 删除费用信息
     * @param Request $request
     * @param StoreCosts $storeCosts
     * @return array
     */
    public function subtract_costs(Request $request, StoreCosts $storeCosts)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

                $storeCosts::destroy($param['store_costs_id']);

                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    public function edit_costs(Request $request, StoreCosts $storeCosts)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                if (isset($param['turnover'])) {
                    $storeCosts->valid($param, 'turnover');
                } else {
                    $storeCosts->valid($param, 'percent');
                }
                $storeCosts->allowField(TRUE)->isUpdate(TRUE)->save($param);

                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (ValidateException $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    public function type_change(Request $request, StoreModel $storeModel, StoreTypeChangeModel $storeTypeChangeModel)
    {
        $param = $request::get();
        $store = $storeModel::get($param['store_id']);
        $change = $storeTypeChangeModel->where('store_id', $param['store_id'])->order('change_id desc')->find();

        $is_apply = 0;
        if (isset($change['apply_type']) && $change['apply_type'] == 1) {
            $is_apply = 1;
        }
        if ($request::isPost()) {
            $data = $request::post();
            //  验证是否更改类型
            if ($store['shop'] == $data['store_type']) {
                return ['code' => -100, 'message' => '未做更改处理'];
            }
            if ($store['shop'] != 0 && $data['store_type'] != 0) {
                return ['code' => -100, 'message' => '未做更改处理'];
            }
//            halt($data);
            //  更改类型
            try {
                Db::startTrans();
                //   更新店铺类型  店铺状态改为认证中
                $storeModel->where('store_id', $param['store_id'])->update(['status' => 3, 'shop' => $data['store_type']]);
                //   添加更改记录
                $_data['deal_time'] = date('Y-m-d H:i:s');
                $_data['deal_person'] = session('manageName');
                $_data['remark'] = $data['remark'];
                $_data['deal_status'] = 1;
//                if ($is_apply == 0) {
                //  平台发起转换
                $_data['apply_time'] = date('Y-m-d H:i:s');
                $_data['apply_type'] = 2;
                $_data['store_id'] = $data['store_id'];

                $storeTypeChangeModel->save($_data);
//                } else {
//                    //
//                    $storeTypeChangeModel->where(['change_id', '=', $change['change_id']])->update($_data);
//                }
                Db::commit();
                return ['code' => 0, 'message' => '操作成功'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }


        $record = $storeTypeChangeModel->where('store_id', $param['store_id'])->order('change_id desc')->select();

        return $this->fetch(
            '',
            [
                'type'     => $param['type'] ?? 1,
                'store'    => $store,
                'is_apply' => $is_apply,
                'record'   => $record,
            ]
        );
    }
}