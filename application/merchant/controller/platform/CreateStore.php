<?php
declare(strict_types=1);

namespace app\merchant\controller\platform;

use app\common\model\Goods;
use app\common\model\Store as StoreModel;
use app\common\model\StoreAuth;
use app\common\model\Store;
use app\common\model\StoreOperation;
use think\Controller;
use think\Db;
use think\exception\ValidateException;
use think\facade\Session;
use think\Request;
use app\common\model\Member;
use app\common\model\StoreClassify;

class CreateStore extends Controller
{

    /**
     * 会员列表
     * @param Request $request
     * @param Member $member
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function member_list(Request $request, Member $member){
        $param = $request->param();
        if(!empty($param['keys'])) $condition[] = ['nickname|phone','like','%'.$param['keys'].'%'];
        $condition[] = ['status','eq',1];
        $result = $member->where($condition)->field('member_id,phone,nickname,register_time')->paginate(10);
        $data['code'] = 0;
        $data['message'] = '成功';
        $data['data'] = $result;
        return json($data);
    }

    /**
     * 新增店铺
     * @param Request $request
     * @param Store $store
     * @param StoreAuth $storeAuth
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, Store $store, StoreAuth $storeAuth)
    {
        $param = $request->param();
        //查询用户是否已有店铺
        $member_status = $store->where('member_id',$param['member_id'])->find();
        if(!empty($member_status))
        {
            if ($member_status['status'] == 0)
            {
                $data['code'] = -1;
                $data['message'] = '该账户已申请入驻，请选择其他账号再试';
                return json($data);
            }
            elseif ($member_status['status'] == 1)
            {
                $data['code'] = -1;
                $data['message'] = '该账户已申请入驻，请选择其他账号再试';
                return json($data);
            }
            elseif ($member_status['status'] == 3)
            {
                $data['code'] = -1;
                $data['message'] = '该账户的申请店铺正在认证，请选择其他账号再试';
                return json($data);
            }
            elseif ($member_status['status'] == 4)
            {
                $data['code'] = -1;
                $data['message'] = '该账户下已拥有店铺，请选择其他账号再试';
                return json($data);
            }
        }
        // 检测店铺名称是否有重复
        $checkWhere = [
            ['store_name', '=', $param['store_name']],
        ];
        $check = $store
            ->where($checkWhere)
            ->find();
        if (!is_null($check)) {
            $data['code'] = -1;
            $data['message'] = '系统检测到店铺名称已存在,请更换名称再提交';
            return json($data);
        }
        try {
            Db::startTrans();
        //将接到的参数重组，分别存入两张表
        //店铺表添加信息
        $store_data['type']             = $param['type'];                           //分类（公司店铺 | 自营店铺） 0 普通店 1 旗舰店 2 专卖店 3 直营店
        $store_data['member_id']        = $param['member_id'];                      //会员ID
        $store_data['store_name']       = $param['store_name'];                     //店铺名称
        $store_data['category']         = $param['category'];                       //主营类目
        $store_data['logo']             = $param['logo'];                           //店铺logo
        $store_data['phone']            = $param['phone'];                          //服务电话
        $store_data['province']         = $param['province'];                       //所在省份
        $store_data['city']             = $param['city'];                           //所在城市
        $store_data['area']             = $param['area'];                           //所在地区
        $store_data['address']          = $param['address'];                        //详细地址
        $store_data['end_time']         = $param['end_time'];                       //到期时间
        $store_data['shop']             = $param['shop'];                           //类型 0 自营店铺 1 入驻店铺
        $store_data['is_delivery']      = $param['is_delivery'];                    //是否开启全国快递 0 否 1 是
        $store_data['is_city']          = $param['is_city'];                        //是否开启同城配送 0 否 1 是
        $store_data['is_shop']          = $param['is_shop'];                        //是否开启门店自提 0 否 1 是
        $store_data['back_image']       = $param['back_image'];                     //店铺首页背景图
        $store_data['goods_style']      = $param['goods_style'];                    //商品列表样式  0 正常样式 1 新零售样式
        $store_data['brand_image']      = $param['brand_image'];                    //店铺品牌甄选展示图
        $store_data['is_good']          = $param['is_good'];                        //发现好店 0 否 1 是
        $store_data['good_image']       = $param['good_image'];                     //店铺发现好店展示图
        $store_data['status']           = 3;

            if (empty($param['store_id']))
       {
                $store->allowField(TRUE)->isUpdate(FALSE)->save($store_data);
                $store_id = $store->store_id;
       }
       else
       {
           $store_data['store_id'] = $param['store_id'];
           $store->allowField(TRUE)->isUpdate(TRUE)->save($store_data);
           $store_id = $param['store_id'];
       }
//        halt($store_id);
        //店铺认证表添加信息
        $store_auth_data['auth_name']           = $param['auth_name'];                      //真实姓名
        $store_auth_data['store_id']            = $store_id;
        $store_auth_data['member_id']           = $param['member_id'];
        $store_auth_data['auth_number']         = $param['auth_number'];                    //身份证号码
        $store_auth_data['tel']                 = $param['tel'];                            //联系电话
        $store_auth_data['type']                = $param['types'];                          //认证类型：1个人  2企业个体
        $store_auth_data['ID_front_file']       = $param['ID_front_file'];                  //身份证正面照
        $store_auth_data['ID_back_file']        = $param['ID_back_file'];                   //身份证反面照
        $store_auth_data['file']                = $param['file'];                           //手持身份证照片
        $store_auth_data['company_number']      = $param['company_number'];                 //营业执照号
        $store_auth_data['file1']               = $param['file1'];                          //营业执照照片
        $store_auth_data['bank_province']       = $param['bank_province'];                  //开户省
        $store_auth_data['bank_city']           = $param['bank_city'];                      //开户市
        $store_auth_data['bank_area']           = $param['bank_area'];                      //开户区
        $store_auth_data['account_name']        = $param['account_name'];                   //开户名称
        $store_auth_data['account_bank_name']   = $param['account_bank_name'];              //开户行
        $store_auth_data['account_sub_branch']  = $param['account_sub_branch'];             //开户支行
        $store_auth_data['bank_number']         = $param['bank_number'];                    //卡号

            if (empty($param['store_id']))
            {
                $storeAuth->allowField(TRUE)->isUpdate(FALSE)->save($store_auth_data);

            }
            else
            {

                $store_auth_data['store_auth_id'] = $storeAuth->where('store_id',$param['store_id'])->value('store_auth_id');
                $storeAuth->allowField(TRUE)->isUpdate(TRUE)->save($store_auth_data);

            }
            Db::commit();

            $result['code'] = 0;
            $result['message'] = '成功';
            return json($result);
        } catch (\Exception $e) {
            $result['code'] = -1;
            $result['message'] = $e->getMessage();
            return json($result);
        }

    }

    /**
     * 店铺列表
     * @param Request $request
     * @param Store $store
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function store_list(Request $request, Store $store)
    {
        $param = $request->param();

        if (!empty($param['shop'])) $condition[] = ['shop','eq',$param['shop']];
        if (!empty($param['start_time']) && !empty($param['end_time'])) $condition[] = ['create_time','between time',[$param['start_time'],$param['end_time']]];
        if (!empty($param['status'])) $condition[] = ['status','eq',$param['status']];
        if (!empty($param['category'])) $condition[] = ['category','like','%'.$param['category'].'%'];
        $condition[] = ['store_id','>',0];
        $result['store_list'] = $store->where($condition)->field('store_id,shop,store_name,status,category')->paginate(10);
        foreach ($result['store_list'] as $value)
        {
            $value['categroy_name'] = $value->CategoryName;
        }
        $data['code'] = 0;
        $data['message'] = '成功';
        $data['data'] = $result;
        return json($data);
    }

    /**
     * 主营类目
     * @param StoreClassify $storeClassify
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function main_business(StoreClassify $storeClassify)
    {
        $result['classify_list'] = $storeClassify->where('status',1)->field('store_classify_id,title')->select();
        $data['code'] = 0;
        $data['message'] = '成功';
        $data['data'] = $result;
        return json($data);
    }

    /**
     * 获取店铺信息（编辑时）
     * @param Request $request
     * @param Store $store
     * @param StoreAuth $storeAuth
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, Store $store, StoreAuth $storeAuth)
    {

        $param = $request->param();
        //店铺表信息
        $result['store_data'] = $store->where('store_id',$param['store_id'])
            ->field('store_id,type,member_id,store_name,category,logo,phone,province,city,area,address,end_time,shop,is_delivery,is_city,is_shop,back_image,goods_style,brand_image,is_good,good_image')->find();
        $result['store_data']['category_name'] = $result['store_data']->CategoryName;
        //店铺认证表信息
        $result['store_auth_data'] = $storeAuth->where('store_id',$param['store_id'])
            ->field('auth_name,auth_number,tel,type,ID_front_file,ID_back_file,file,company_number,file1,company_number,file1,bank_province,bank_city,bank_area,account_name,account_bank_name,account_sub_branch,bank_number')->find();
        $result['store_auth_data']['bank_province_name'] = $result['store_auth_data']->BankProvinceName;
        $result['store_auth_data']['bank_city_name'] = $result['store_auth_data']->BankCityName;
        $result['store_auth_data']['bank_area_name'] = $result['store_auth_data']->BankAreaName;

        $data['code'] = 0;
        $data['message'] = '成功';
        $data['data'] = $result;
        return json($data);
    }

    /**
     * 权重
     * @param Request $request
     * @param Store $store
     * @return \think\response\Json
     */
    public function weight(Request $request, Store $store)
    {
        $param = $request->param();
        if (empty($param['store_id']))
        {
            $data['code'] = -1;
            $data['message'] = '请选择店铺';
        }
        if (empty($param['sort']))
        {
            $data['code'] = -1;
            $data['message'] = '请输入店铺权重';
        }
        $result = $store->allowField(TRUE)->isUpdate(TRUE)->save($param);
        if($result)
        {
            $data['code'] = 0;
            $data['message'] = '成功';
        }
        else
        {
            $data['code'] = -1;
            $data['message'] = '网络繁忙，请稍后再试';
        }
        return json($data);
    }

    /**
     * 注销店铺
     * @param \think\facade\Request $request
     * @param StoreModel $storeModel
     * @param StoreOperation $storeOperation
     * @return array|mixed
     */
    public function destroy(Request $request, StoreModel $storeModel, StoreOperation $storeOperation, Goods $goods)
    {
        $param = $request->post();
        if ($request->isPost()) {
            Db::startTrans();
            try {
                $check = $storeModel->valid($param, 'log_out');
                if ($check['code']) {
                    return $check;
                }

                $param['type'] = 1;
                if(empty($param['manage_id']))
                {
                    $result['code'] = -100;
                    $result['message'] = '系统繁忙，请稍后再试';
                    return json($result);
                }
                if(empty($param['nickname']))
                {
                    $result['code'] = -100;
                    $result['message'] = '系统繁忙，请稍后再试';
                    return json($result);
                }
                $storeOperation->allowField(TRUE)->save($param);
                // 获取店铺用户信息
                $info = $storeModel
                    ->alias('s')
                    ->where([
                        ['s.store_id', '=', $param['store_id']],
                    ])
                    ->join('member m', 'm.member_id = s.member_id')
                    ->field('m.member_id,m.web_open_id,m.micro_open_id,m.subscribe_time,s.store_name')
                    ->find();

                // 下架商品
                $goods->where('store_id', $param['store_id'])->update(['is_putaway' => 0]);

                // 注销店铺
                $param['id'] = $param['store_id'];
                $storeModel::destroy($param['id']);
                Db::commit();
                $result['code'] = 0;
                $result['message'] = '成功';
                return json($result);
            } catch (ValidateException $e) {
                $result['code'] = -100;
                $result['message'] = $e->getMessage();
                return json($result);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $result['code'] = -100;
                $result['message'] = $e->getMessage();
                return json($result);            }
        }

    }


    /**
     * 启用店铺
     * @param \think\facade\Request $request
     * @param StoreModel $storeModel
     * @param StoreOperation $storeOperation
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function open_store(Request $request, StoreModel $storeModel, StoreOperation $storeOperation)
    {
        if ($request->isPost()) {
            try {
                $param = $request->post();
                if(empty($param['manage_id']))
                {
                    $result['code'] = -100;
                    $result['message'] = '系统繁忙，请稍后再试';
                    return json($result);
                }
                if(empty($param['nickname']))
                {
                    $result['code'] = -100;
                    $result['message'] = '系统繁忙，请稍后再试';
                    return json($result);
                }
                // 获取店铺用户信息
                $info = $storeModel
                    ->alias('s')
                    ->where([
                        ['s.store_id', '=', $param['store_id']],
                    ])
                    ->join('member m', 'm.member_id = s.member_id')
                    ->field('m.member_id,m.web_open_id,m.subscribe_time,m.micro_open_id,s.store_name')
                    ->find();
                $store = $storeModel::onlyTrashed()->find($param['store_id']);
                Db::startTrans();
                $store->restore();
//                $param['manage_id'] = Session::get("manage_id");
//                $param['nickname'] = Session::get("manageName");
                $param['type'] = 2;
                $storeOperation->allowField(TRUE)->save($param);
                Db::commit();
                $result['code'] = 0;
                $result['message'] = '成功';
                return json($result);
            } catch (\Exception $e) {
                $result['code'] = -100;
                $result['message'] = $e->getMessage();
                return json($result);
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

    }


    public function store_audit(Request $request, StoreModel $storeModel, StoreOperation $storeOperation)
    {
        $param = $request->param();
        Db::startTrans();
        try {
        // 判断认证审核状态
                if ($param['status'] == 1) {
                    // 通过
                    $_storeData['status'] = 4;
                } else {
                    // 未通过
                    $_storeData['status'] = 5;
                    $_operationData['reason'] = $param['reason'];
                }
                $_operationData = [
                    'type' => 4,
                    'manage_id' => $param['manage_id'],
                    'nickname' => $param['nickname'],
                    'store_id' => $param['store_id'],
                    'reason' => ''
                ];
            $aa = $storeModel->withTrashed()->where('store_id', $param['store_id'])->update($_storeData);
            $storeOperation->create($_operationData);
            Db::commit();

            $result['code'] = 0;
            $result['message'] = '成功';
            return json($result);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $result['code'] = -100;
            $result['message'] = $e->getMessage();
            return json($result);
    }
    }
}