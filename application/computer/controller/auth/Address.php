<?php
/**
 * 关于收货地址.
 * User: Heng
 * Date: 2019/2/21
 * Time: 15:57
 */

namespace app\computer\controller\auth;

use app\computer\controller\BaseController;

use think\facade\Request;
use mrmiao\encryption\RSACrypt;
use app\computer\model\Area;
use app\computer\model\MemberAddress;
use think\facade\Session;

class Address extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login' => ['except' => 'linkage'],
    ];

    /**
     * 收货地址列表
     * @param MemberAddress $memberAddress
     * @param Area $area
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(MemberAddress $memberAddress, Area $area)
    {
        // 接参
        $param['member_id'] = Session::get('member_info')['member_id'];
        // 获取数据
        $result = $memberAddress->where([['member_id', 'eq', $param['member_id']]])
            ->field('member_id,create_time,update_time,delete_time', TRUE)
            ->select();

        // 操作
        $province = $area->where('parent_id', 0)
            ->field('area_id,area_name')
            ->select();

        return $this->fetch('', ['result' => $result, 'province' => $province]);
    }


    /**
     * 添加收货地址
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @return mixed
     */
    public function create(Request $request, RSACrypt $crypt, MemberAddress $memberAddress)
    {
        if ($request::isPost())
        {
            // 接参
            $param = $crypt->request();
            $param['member_id'] = Session::get('member_info')['member_id'];
            // 验证
            $memberAddress->valid($param, 'interfaces_create');
            if ($memberAddress->where([['member_id', '=', $param['member_id']]])->count() > 20)
            {
                return $crypt->response(['code' => -401, 'message' => '最多可创建20个收货地址']);
            }
            // 操作
            $memberAddress->allowField(TRUE)->save($param);
            $data = [
                'address_id' => $memberAddress['member_address_id'],
                'name'       => $param['name'],
                'phone'      => $param['phone'],
                'is_default' => $param['is_default'] ?? 0,
                'address'    => "{$param['province']}&nbsp{$param['city']}&nbsp{$param['area']}&nbsp{$param['street']}",
            ];
            return $crypt->response(
                ['code' => 0, 'message' => config('message.')[0][0], 'type' => 'create', 'data' => $data]
            );
        }
    }


    /**
     * 更新收货地址
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @return mixed
     */
    public function update(Request $request, RSACrypt $crypt, MemberAddress $memberAddress)
    {
        if ($request::isPost())
        {
            try
            {
                // 接参
                $param = $crypt->request();
                $param['member_id'] = Session::get('member_info')['member_id'];

                // 验证
                $check = $memberAddress->valid($param, 'interfaces_update');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                // 操作
                $memberAddress->allowField(TRUE)->isUpdate(TRUE)->save($param);
                $data = [
                    'address_id' => $memberAddress['member_address_id'],
                    'name'       => $memberAddress['name'],
                    'phone'      => $memberAddress['phone'],
                    'is_default' => $memberAddress['is_default'] ?? 0,
                    'address'    => "{$memberAddress['province']}&nbsp{$memberAddress['city']}&nbsp{$memberAddress['area']}&nbsp{$memberAddress['street']}",
                ];

                return $crypt->response(
                    ['code' => 0, 'message' => config('message.')[0][0], 'type' => 'update', 'data' => $data]
                );

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
            }
        }
    }


    /**
     * 收货信息查看
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @param Area $area
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function find(Request $request, RSACrypt $crypt, MemberAddress $memberAddress, Area $area)
    {
        if ($request::isPost())
        {
            // 接参
            $param = $crypt->request();
            // 验证
            $memberAddress->valid($param, 'find');
            // 操作
            $result = $memberAddress->where('member_address_id', $param['member_address_id'])
                ->field('create_time,update_time,delete_time', TRUE)
                ->find();
            //TODO  如果此处接口不好使  检查其他设置收货地址地方功能传参是否有问题
            $where_data = implode(',', [$result->province, $result->city, $result->area]);
            $_area_id = $area->where([['area_name', 'in', $where_data]])->orderRaw(
                'field(area_name,"' . $where_data . '")'
            )->column('area_id');
            $result['province_array'] = $area->where([['parent_id', '=', 0]])->field('area_id,area_name')->select();
            foreach (['city_array', 'area_array', 'street_array'] as $k => $v)
            {
                $result[$v] = $area->where([['parent_id', '=', $_area_id[$k]]])->field('area_id,area_name')->select();
            }
            return $crypt->response(['code' => 0, 'result' => $result]);
        }
    }


    /**
     * 删除收货地址
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @return mixed
     */
    public function destroy(Request $request, RSACrypt $crypt, MemberAddress $memberAddress)
    {
        if ($request::isPost())
        {
            try
            {
                // 接参
                $param = $crypt->request();
                $param['member_id'] = Session::get('member_info')['member_id'];

                // 验证
                $check = $memberAddress->valid($param, 'destroy');
                if ($check['code'])
                {
                    return $crypt->response($check);
                }

                // 操作
                $memberAddress->destroy($param['member_address_id']);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
            }
        }
    }


    /**
     * 设置默认收货地址
     * @param Request $request
     * @param MemberAddress $memberAddress
     * @return \think\response\Json
     * @throws \Exception
     */
    public function default_address(Request $request, MemberAddress $memberAddress)
    {
        if ($request::isPost())
        {
            $member_address_id = $request::post('member_address_id', NULL) ?? exception('地址不存在');
            $memberAddress->save(
                ['is_default' => '1', 'member_id' => Session::get('member_info')['member_id']],
                [
                    ['member_address_id', '=', $member_address_id],
                    ['member_id', '=', Session::get('member_info')['member_id']],
                ]
            );
            return json(['code' => 0, 'message' => '修改成功']);
        }
    }

    /**
     * 省市区街道 - 地区联动
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Area $area
     * @return mixed
     */
    public function linkage(Request $request, RSACrypt $crypt, Area $area)
    {
        if ($request::isPost())
        {
            try
            {

                $param = $crypt->request();

                $param['parent_id'] = empty($param['parent_id']) ? 0 : $param['parent_id'];

                // 操作
                $result = $area->where('parent_id', $param['parent_id'])
                    ->field('area_id,area_name')
                    ->select();

                return $crypt->response(['code' => 0, 'result' => $result]);

            } catch (\Exception $e)
            {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
            }
        }
    }
}