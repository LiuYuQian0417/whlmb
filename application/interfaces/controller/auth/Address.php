<?php
declare(strict_types=1);

namespace app\interfaces\controller\auth;

use app\common\model\MemberAddress;
use app\common\model\Area;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 收货地址 - Joy
 * Class Register
 * @package app\interfaces\controller\auth
 */
class Address extends BaseController
{

    /**
     * 地址列表
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          MemberAddress $memberAddress)
    {
        $member_id = request(0)->mid;
        $result = $memberAddress
            ->where([
                ['member_id', '=', $member_id],
            ])
            ->field('member_id,create_time,update_time,delete_time', TRUE)
            ->order(['is_default' => 'desc', 'create_time' => 'desc'])
            ->paginate($memberAddress->pageLimits, FALSE);
        return $crypt->response([
            'code'    => 0,
            'message' => '查询成功',
            'result'  => $result,
        ], TRUE);
    }

    /**
     * 新增地址
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(RSACrypt $crypt,
                           MemberAddress $memberAddress)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $memberAddress->valid($param, 'interfaces_create');
        // 检测当前用户含有地址数量
        $curNum = $memberAddress->getCurNum($param['member_id']);
        if ($curNum >= 20) {
            return $crypt->response([
                'code'    => -1,
                'message' => "当前收货地址已达上限20个,无法新增",
            ], TRUE);
        }
//        $memberAddress
//            ->allowField(true)
//            ->isUpdate(false)
//            ->save($param);

        $_result = $memberAddress::create($param);

        return $crypt->response([
            'code'    => 0,
            'message' => '新增成功',
            'data'    => [
                'address_id' => $_result->member_address_id,
            ],
        ], TRUE);
    }

    /**
     * 编辑地址
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update(RSACrypt $crypt,
                           MemberAddress $memberAddress)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $memberAddress->valid($param, 'interfaces_update');
        $memberAddress
            ->allowField(TRUE)
            ->isUpdate(TRUE)
            ->save($param);
        return $crypt->response([
            'code'    => 0,
            'message' => '修改成功',
        ], TRUE);
    }

    /**
     * 地址读取
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function find(RSACrypt $crypt,
                         MemberAddress $memberAddress)
    {
        $param = $crypt->request();
        $memberAddress->valid($param, 'find');
        $result = $memberAddress
            ->where([
                ['member_address_id', '=', $param['member_address_id']],
            ])
            ->field('create_time,update_time,delete_time', TRUE)
            ->append(['address_info'])
            ->find();
        if (!is_null($result) && $result['address_info']) {
            $result = array_merge($result->toArray(), array_filter($result['address_info']));
        }
        return $crypt->response([
            'code'    => 0,
            'message' => '查询成功',
            'result'  => $result,
        ], TRUE);
    }

    /**
     * 省市区街道 - 地区联动
     * @param RSACrypt $crypt
     * @param Area $area
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function linkage(RSACrypt $crypt,
                            Area $area)
    {
        $param = $crypt->request();
        $param['parent_id'] = empty($param['parent_id']) ? 0 : $param['parent_id'];
        $result = $area
            ->where([
                ['parent_id', '=', $param['parent_id']],
            ])
            ->field('area_id,area_name')
            ->select();
        return $crypt->response([
            'code'    => 0,
            'message' => '查询成功',
            'result'  => $result,
        ], TRUE);
    }

    /**
     * 删除地址
     * @param RSACrypt $crypt
     * @param MemberAddress $memberAddress
     * @return mixed
     */
    public function destroy(RSACrypt $crypt,
                            MemberAddress $memberAddress)
    {
        $param = $crypt->request();
        $memberAddress->valid($param, 'destroy');
        $memberAddress->destroy($param['member_address_id']);
        return $crypt->response([
            'code'    => 0,
            'message' => '删除成功',
        ], TRUE);
    }
}