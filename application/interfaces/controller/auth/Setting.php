<?php
declare(strict_types=1);

namespace app\interfaces\controller\auth;

use app\common\model\ArticleClassify;
use app\common\model\Feedback;
use app\common\model\Member;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Env;

/**
 * 账户设置 - Joy
 * Class Register
 * @package app\interfaces\controller\auth
 */
class Setting extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    /**
     * 账户设置
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 读取个人信息
        $result = $member
            ->where([
                ['member_id', '=', $param['member_id']],
                ['status', '=', 1],
            ])
            ->field('avatar,nickname,username')
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -1,
                'message' => '用户不存在',
            ], true);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }

    /**
     * 账户与安全
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function safety(RSACrypt $crypt,
                           Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $find = $member
            ->where([
                ['member_id', '=', $param['member_id']],
                ['status', '=', 1],
            ])
            ->field('password,phone,pay_password')
            ->find();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => [
                'password_state' => empty($find['password']) ? 0 : 1,
                'phone_state' => empty($find['phone']) ? 0 : 1,
                'pay_state' => empty($find['pay_password']) ? 0 : 1,
                'phone' => empty($find['phone']) ? '' : $find['phone'],
            ],
        ], true);
    }

    /**
     * 设置密码
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function set_password(RSACrypt $crypt,
                                 Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $member->valid($param, 'set_password');
        $member
            ->allowField(true)
            ->isUpdate(true)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '设置成功',
        ], true);
    }

    /**
     * 修改密码
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update_password(RSACrypt $crypt,
                                    Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $param['confirm_password'] = $param['password'];
        $member->valid($param, 'UpdatePassword');
        // 读取个人信息
        $member_id = $member
            ->where([
                ['member_id', '=', $param['member_id']],
                ['password', '=', passEnc($param['old_password'])],
            ])
            ->value('member_id');
        if (!$member_id) {
            return $crypt->response([
                'code' => -1,
                'message' => '旧密码错误',
            ], true);
        }
        $member
            ->allowField(true)
            ->isUpdate(true)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '修改成功',
        ], true);
    }

    /**
     * 忘记密码
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function forget_password(RSACrypt $crypt,
                                    Member $member)
    {
        $param = $crypt->request();
        $member->valid($param, 'forget_password');
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
        return $crypt->response([
            'code' => 0,
            'message' => '设置成功',
        ], true);
    }

    /**
     * 修改手机号
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Sms $sms
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update_phone(RSACrypt $crypt,
                                 Member $member,
                                 Sms $sms)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $member->valid($param, 'binding_phone');
        $code = $sms->getCache($param['phone'], 1, $param['code']);
        if ($code === false) {
            return $crypt->response([
                'code' => -1,
                'message' => '验证码不正确',
            ], true);
        }
        $member
            ->allowField(true)
            ->isUpdate(true)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '修改成功',
        ], true);
    }

    /**
     * 设置支付密码
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function set_pay_password(RSACrypt $crypt,
                                     Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $member->valid($param, 'set_pay_password');
        $member
            ->allowField(true)
            ->isUpdate(true)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '设置成功',
        ], true);
    }

    /**
     * 修改支付密码
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update_pay_password(RSACrypt $crypt,
                                        Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $param['confirm_password'] = $param['pay_password'];
        $member->valid($param, 'UpdatePayPassword');
        // 读取个人信息
        $member_id = $member
            ->where([
                ['member_id', '=', $param['member_id']],
                ['pay_password', '=', passEnc($param['old_password'])],
            ])
            ->value('member_id');
        if (!$member_id) {
            return $crypt->response([
                'code' => -1,
                'message' => '旧密码不正确',
            ], true);
        }
        $member
            ->allowField(true)
            ->isUpdate(true)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '修改成功',
        ], true);
    }

    /**
     * 忘记支付密码
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     */
    public function forget_pay_password(RSACrypt $crypt,
                                        Member $member)
    {
        $param = $crypt->request();
        $member->valid($param, 'forget_pay_password');
        // 读取个人信息
        $member_id = $member
            ->where([
                ['phone', '=', $param['phone']],
                ['status', '=', 1],
            ])
            ->value('member_id');
        if (!$member_id) {
            return $crypt->response([
                'code' => -1,
                'message' => '用户不存在',
            ], true);
        }
        $member
            ->allowField(true)
            ->isUpdate(true)
            ->save($param, ['phone' => $param['phone']]);
        return $crypt->response([
            'code' => 0,
            'message' => '修改成功',
        ], true);
    }

    /**
     * 问题反馈
     * @param RSACrypt $crypt
     * @param Feedback $feedback
     * @return mixed
     */
    public function feedback(RSACrypt $crypt,
                             Feedback $feedback)
    {
        $param = $crypt->request();
        $feedback->valid($param, 'create');
        if (Cache::get(Request::ip())) {
            return $crypt->response([
                'code' => -100,
                'message' => '5分钟之内您只能提交一次',
            ], true);
        }
        $feedback
            ->allowField(true)
            ->isUpdate(false)
            ->save($param);
        return $crypt->response([
            'code' => 0,
            'message' => '提交成功',
        ], true);
    }

    /**
     * 帮助中心
     * @param RSACrypt $crypt
     * @param ArticleClassify $articleClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function help_center(RSACrypt $crypt,
                                ArticleClassify $articleClassify)
    {
        $result = $articleClassify
            ->with('article')
            ->field('article_classify_id,title')
            ->where([
                ['parent_id', '=', 16],     //帮助中心
                ['status', '=', 1],
            ])
            ->order('sort', 'desc')
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }

    /**
     * 客服热线
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function hotline(RSACrypt $crypt)
    {
        $phone = Env::get('phone', '未设置客服热线');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $phone,
        ]);
    }

}