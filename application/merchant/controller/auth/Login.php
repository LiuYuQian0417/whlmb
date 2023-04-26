<?php
declare(strict_types=1);

namespace app\merchant\controller\auth;

use app\common\model\Store as StoreModel;
use app\common\service\Inform;
use think\Controller;
use think\Db;
use app\common\model\Member;
use app\common\model\Manage;
use mrmiao\encryption\RSACrypt;
use think\facade\Hook;
use think\facade\Session;
use think\Request;


/**
 * 登录
 * Class Login
 * @package app\merchant\controller\auth
 */
class Login extends Controller
{
    /**
     * 密码登录
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Manage $manage
     * @return array|mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,Member $member, Manage $manage)
    {
        $param = $crypt->request();
        Db::startTrans();
        if($param['m_type'] == 1)
        {

            // 加密密码替换原数组中 password 字段中的内容
            $param['password'] = passEnc($param['password']);
            // 查找对应的用户
            $find = $member
                ->where(['phone' => $param['phone'], 'password' => $param['password']])
                ->field('member_id,nickname,avatar,status,store_login_time,phone')
                ->find();

            // 用户不存在或者密码错误
            if (!$find) {
                $result['code'] = -1;
                $result['message'] = '账号或密码错误';

                return json($result);
            }

            // 用户被禁用
            if ($find['status'] === 0) {
                $result['code'] = -2;
                $result['message'] = '此账号登录受限';

                return json($result);
            }
            // 判断店铺是否存在
            $store = (new StoreModel())->where(['member_id' => $find['member_id']])
                ->field('store_name,shop,store_grade_id,status,logo,store_id')
                ->find();

            if (!$store) {
                return ['code' => -1, 'message' => '该店铺不存在'];
            }
            // 判断店铺的审核状态
            switch ($store['status']) {
                case 0:
                    // 入驻申请中
                    return ['code' => -2, 'message' => '您的入驻申请正在审核中,请耐心等待平台审核'];
                    break;
                case 2:
                    // 入驻审核被拒绝
                    return ['code' => -2, 'message' => '很抱歉,您的店铺入驻申请审核被拒绝'];
                    break;
            }

            //更改登录时间,IP等信息
            $update = [
                // 用户iD
                'member_id' => $find['member_id'],
                // 最后一次登录时间
                'store_login_time' => date('Y-m-d H:i:s'),
                // 上一次登录的时间
                'store_last_login_time' => $find['store_login_time'],
            ];
            // 更新数据
            $member->allowField(TRUE)->isUpdate(TRUE)->save($update);

            $ret = [
                'member_id' => $store['store_id'],
                'avatar' => $store['logo'],
                'nickname' => $store['store_name'],
                'phone' => $find['phone'],
                'type'  => 1,
            ];

            $result =  [
                'code' => 0,
                'message' => '登录成功',
                'data' => $ret,
            ];

        }
        else
        {
            $where = ['phone' => $param['phone']];
            $where['password'] = passEnc($param['password']);

            $find = $manage
                ->where($where)
                ->field('manage_id,nickname,phone,avatar,status,login_time,login_ip')
                ->find();

            // 用户不存在或帐号密码错误
            if (is_null($find)) {
                $result['code'] = -1;
                $result['message'] = '账号或密码错误';

                return json($result);
            }
            // 用户被禁用
            if (!$find['status']) {
                $result['code'] = -2;
                $result['message'] = '账号已禁用，请联系客服';

                return json($result);
            }
            //更改登录时间,IP等信息
            $update = [
                'manage_id' => $find['manage_id'],
                'login_num' => Db::raw('login_num + 1'),
                'login_time' => date('Y-m-d H:i:s'),
                'login_ip' => \think\facade\Request::ip(),
                'last_login_time' => array_key_exists('login_time', $find) ? $find['login_time'] : date('Y-m-d H:i:s'),
                'last_login_ip' => array_key_exists('login_ip', $find) ? $find['login_ip'] : '',
            ];

            $manage->allowField(true)->isUpdate(true)->save($update);
            // 返回token
            $jwt = app('app\\common\\service\\JwtManage', ['param' => ['mid' => $find['manage_id'],['type' => 2],['types' => 2]]], true)->issueToken();
            header("token:" . $jwt);
            $ret = [
                'member_id' => $find['manage_id'],
                'avatar' => $find['avatar'],
                'nickname' => $find['nickname'],
                'phone' => $find['phone'],
                'type'  => 2,
            ];
            if (array_key_exists('distribution_id', $find) && $find['distribution_id']) {
                $ret['distribution_id'] = $find['distribution_id'];
            }
            $result =  [
                'code' => 0,
                'message' => '登录成功',
                'data' => $ret,
            ];
        }

        Db::commit();
        return $crypt->response($result,true);
    }

    /**
     * 短信验证码登录
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function sms(RSACrypt $crypt, Member $member, Manage $manage)
    {
        $param = $crypt->request();
//        检测短信
        $sms = app('app\\merchant\\controller\\auth\\Sms');
        if (empty($param['code']))
        {
            return $crypt->response([
                'code' => -1,
                'message' => '请输入验证码',
            ], true);
        }
        $checkCode = $sms->getCache($param['phone'], 9, $param['code']);
        // 验证码不正确
        if (!$checkCode) {
            return $crypt->response([
                'code' => -1,
                'message' => '验证码不正确',
            ], true);
        }
        Db::startTrans();
        if($param['m_type'] == 1)
        {

            // 查找对应的用户
            $find = $member
                ->where(['phone' => $param['phone']])
                ->field('member_id,nickname,avatar,status,store_login_time,phone')
                ->find();

            // 用户不存在或者密码错误
            if (!$find) {
                $result['code'] = -1;
                $result['message'] = '账号或密码错误';

                return json($result);
            }

            // 用户被禁用
            if ($find['status'] === 0) {
                $result['code'] = -2;
                $result['message'] = '此账号登录受限';

                return json($result);
            }
            // 判断店铺是否存在
            $store = (new StoreModel())->where(['member_id' => $find['member_id']])
                ->field('store_name,shop,store_grade_id,status,logo,store_id')
                ->find();

            if (!$store) {
                return ['code' => -1, 'message' => '该店铺不存在'];
            }
            // 判断店铺的审核状态
            switch ($store['status']) {
                case 0:
                    // 入驻申请中
                    return ['code' => -2, 'message' => '您的入驻申请正在审核中,请耐心等待平台审核'];
                    break;
                case 2:
                    // 入驻审核被拒绝
                    return ['code' => -2, 'message' => '很抱歉,您的店铺入驻申请审核被拒绝'];
                    break;
            }

            //更改登录时间,IP等信息
            $update = [
                // 用户iD
                'member_id' => $find['member_id'],
                // 最后一次登录时间
                'store_login_time' => date('Y-m-d H:i:s'),
                // 上一次登录的时间
                'store_last_login_time' => $find['store_login_time'],
            ];
            // 更新数据
            $member->allowField(TRUE)->isUpdate(TRUE)->save($update);

            $ret = [
                'member_id' => $store['store_id'],
                'avatar' => $store['logo'],
                'nickname' => $store['store_name'],
                'phone' => $find['phone'],
                'type'  => 1,
            ];

            $result =  [
                'code' => 0,
                'message' => '登录成功',
                'data' => $ret,
            ];

        }
        else
        {
            $where = ['phone' => $param['phone']];

            $find = $manage
                ->where($where)
                ->field('manage_id,nickname,phone,avatar,status,login_time,login_ip')
                ->find();

            // 用户不存在或帐号密码错误
            if (is_null($find)) {
                $result['code'] = -1;
                $result['message'] = '账号或密码错误';

                return json($result);
            }
            // 用户被禁用
            if (!$find['status']) {
                $result['code'] = -2;
                $result['message'] = '账号已禁用，请联系客服';

                return json($result);
            }
            //更改登录时间,IP等信息
            $update = [
                'manage_id' => $find['manage_id'],
                'login_num' => Db::raw('login_num + 1'),
                'login_time' => date('Y-m-d H:i:s'),
                'login_ip' => \think\facade\Request::ip(),
                'last_login_time' => array_key_exists('login_time', $find) ? $find['login_time'] : date('Y-m-d H:i:s'),
                'last_login_ip' => array_key_exists('login_ip', $find) ? $find['login_ip'] : '',
            ];

            $manage->allowField(true)->isUpdate(true)->save($update);
            // 返回token
            $jwt = app('app\\common\\service\\JwtManage', ['param' => ['mid' => $find['manage_id'],['type' => 2],['types' => 2]]], true)->issueToken();
            header("token:" . $jwt);
            $ret = [
                'member_id' => $find['manage_id'],
                'avatar' => $find['avatar'],
                'nickname' => $find['nickname'],
                'phone' => $find['phone'],
                'type'  => 2,
            ];
            if (array_key_exists('distribution_id', $find) && $find['distribution_id']) {
                $ret['distribution_id'] = $find['distribution_id'];
            }
            $result =  [
                'code' => 0,
                'message' => '登录成功',
                'data' => $ret,
            ];
        }
        Db::commit();
        return $crypt->response($result, true);
    }

    public function set_password(RSACrypt $crypt, Member $member, Manage $manage)
    {
        $param = $crypt->request();
//        检测短信
        $sms = app('app\\merchant\\controller\\auth\\Sms');
        if (empty($param['code']))
        {
            return $crypt->response([
                'code' => -1,
                'message' => '请输入验证码',
            ], true);
        }
        $checkCode = $sms->getCache($param['phone'], 4, $param['code']);
        // 验证码不正确
        if (!$checkCode) {
            return $crypt->response([
                'code' => -1,
                'message' => '验证码不正确',
            ], true);
        }
        if ($param['password'] != $param['retype_password'])
        {
            return $crypt->response([
                'code' => -2,
                'message' => '密码不一致',
            ], true);
        }
        $member->valid($param, 'forget_password');

        Db::startTrans();
        if($param['m_type'] == 1)
        {
            $info = $member
                ->where([
                    ['phone', '=', $param['phone']],
                    ['status', '=', 1],
                ])
                ->field('member_id,password')
                ->find();
            if (is_null($info)) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '账号不存在',
                ], true);
            }
            if (passEnc($param['password']) == $info['password']) {
                return $crypt->response([
                    'code' => -2,
                    'message' => '新密码不可与旧密码一致',
                ], true);
            }
            $param['member_id'] = $info['member_id'];
           $member
                ->allowField(true)
                ->isUpdate(true)
                ->save($param);

        }
        else
        {
            $info = $manage
                ->where([
                    ['phone', '=', $param['phone']],
                    ['status', '=', 1],
                ])
                ->field('manage_id,password')
                ->find();
            if (is_null($info)) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '账号不存在',
                ], true);
            }
            if (passEnc($param['password']) == $info['password']) {
                return $crypt->response([
                    'code' => -2,
                    'message' => '新密码不可与旧密码一致',
                ], true);
            }
            $param['manage_id'] = $info['manage_id'];
            $manage
                ->allowField(true)
                ->isUpdate(true)
                ->save($param);
        }
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '设置成功',
        ], true);
    }
}





