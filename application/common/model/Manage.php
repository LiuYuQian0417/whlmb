<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Request;
use think\facade\Session;

/**
 * 管理后台管理员模型
 * Class Manage
 * @package app\common\model
 */
class Manage extends BaseModel
{
    protected $pk = 'manage_id';
    //上次登录时间距离现在时间的时间差,符合则记录一次登录次数
    protected $diffTime = 3600 * 3;

    public static function init()
    {
        self::beforeWrite(function ($e) {
            if (!empty($e->password)) {
                $e->password = passEnc($e->password);
            }
            $avatarPath = self::upload('image', 'manage/avatar/');
            if ($avatarPath) {
                $e->avatar = $avatarPath;
            }
            $e->status = 1;
        });
    }

    /**
     * 登录处理
     * @param $param array 登录参数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function login($param)
    {
        $where = [
            'nickname' => $param['nickname'],
            'password' => passEnc($param['password']),
        ];
        $info = $this
            ->where($where)
            ->field('password,create_time,update_time,delete_time', true)
            ->with('authGroup')
            ->find();
        if (is_null($info)) {
            return [
                'code' => -2,
                'message' => '账号或密码错误',
            ];
        }
        //登录状态异常
        if ($info['status'] != 1) {
            return [
                'code' => -3,
                'message' => '此账号登录受限',
            ];
        }
        //权限组状态异常
        if (is_null($info['auth_group'])) {
            return [
                'code' => -4,
                'message' => '此账号权限组受限',
            ];
        }
        //执行更新管理员信息
        $this->recordInfo($info);
        //设置Session
        Session::set("manage_id", $info['manage_id']);
        Session::set("manageName", $info['nickname']);
        Session::set("manageAvatar", $info['avatar']);
        Session::set("auth_group_id", $info['auth_group_id']);
        Session::set("manageAuthGroupTitle", $info['auth_group']['title']);
        //登录成功,打包数据返回
        return [
            'code' => 0,
            'message' => '登录成功',
            'url' => '/index/index',
        ];
    }

    /**
     * 更新管理员登录信息
     * @param $info array 查询出来的管理员数据
     */
    protected function recordInfo($info)
    {
        $update = [
            'manage_id' => $info['manage_id'],
            'login_ip' => Request::ip(),
            'last_login_ip' => $info['login_ip'],
            'login_time' => date('Y-m-d H:i:s'),
            'last_login_time' => $info['login_time'],
        ];
        if (abs(time() - strtotime($update['login_time'])) > $this->diffTime) {
            $update = array_merge($update, ['login_num' => ['inc', 1]]);
        }
        $this->isUpdate(true)->save($update);
    }


    /** 关联模型 **/
    public function authGroup()
    {
        return $this
            ->belongsTo('AuthGroup', 'auth_group_id', 'auth_group_id')
            ->where([
                ['status', '=', 1],
            ])
            ->field('delete_time,update_time,create_time', true);
    }

}