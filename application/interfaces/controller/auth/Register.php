<?php
declare(strict_types = 1);

namespace app\interfaces\controller\auth;

use app\common\model\Member;
use app\common\service\Inform;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;

/**
 * 平台注册
 * Class Register
 * @package app\interfaces\controller\auth
 */
class Register extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    /**
     * 手机号注册
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Inform $inform
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tel(RSACrypt $crypt,
                        Member $member,
                        Inform $inform)
    {
        $param = $crypt->request();
        $member->valid($param, 'telCreate');
        // 验证码二次验证
        // $smsApi = app('app\\interfaces\\controller\\auth\\Sms');
        // $codeCheck = $smsApi->getCache($param['phone'], 1, $param['code']);
        // if (!$codeCheck) {
        //     return $crypt->response([
        //         'code' => -1,
        //         'message' => '验证码不正确',
        //     ], true);
        // }
        $param['distribution_superior'] = array_key_exists('superior', $param) && $param['superior'] ? $param['superior'] : 0;
        $account = $member
            ->where([
                ['phone', '=', $param['phone']],
            ])
            ->field('member_id,status')
            ->find();
        if (!is_null($account)) {
            return $crypt->response([
                'code' => -1,
                'message' => '该账号已注册',
            ], true);
        }
        Db::startTrans();
        // 进行注册
        
        $find = self::common($member, $param, $inform, 0);


        // 注册即成为分销商检测
        $regToBe = [
            'member_id' => $find['member_id'],
            'nickname' => $find['nickname'],
            'phone' => $find['phone'],
            'sex' => 0,     //默认女
            'web_open_id' => $find['web_open_id'],
            'subscribe_time' => $find['subscribe_time'],
            'micro_open_id' => $find['micro_open_id'],
            'distribution_superior' => $param['distribution_superior'],
            'bType' => 2,   //成为分销商途径注册自动成为分销商
            'text' => 2,    //注册即成为分销商
        ];
        $find['distribution_id'] = '';
        $rb = Hook::exec(['app\\interfaces\\behavior\\Distribution', 'regToBe'], $regToBe);
        Db::commit();
        if ($rb) {
            $find['distribution_id'] = $rb['distribution_id'];
        }
        // 返回token
        $jwt = app('app\\common\\service\\JwtManage', [
            'param' => [
                'mid' => $find['member_id'],
                'dev_type' => $param['dev_type'],
            ],
        ], true)->issueToken();
        header("token:" . $jwt);
        // 注册成功返回用户信息
        return $crypt->response([
            'code' => 0,
            'message' => '注册成功',
            'data' => $find,
        ], true);
    }
    
    /**
     * 邀请好友注册页面
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Inform $inform
     * @return array|mixed|\think\response\View
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function invite(Request $request,
                           RSACrypt $crypt,
                           Member $member,
                           Inform $inform)
    {
        if ($request::isPost()) {
            $param = $crypt->request();
            $member->valid($param, 'invite');
            $smsApi = app('app\\interfaces\\controller\\auth\\Sms');
            $codeCheck = $smsApi->getCache($param['phone'], 1, $param['message_code']);
            if (!$codeCheck) {
                return [
                    'code' => -1,
                    'message' => '验证码不正确',
                ];
            }
            if ($param['type'] == 1 && strlen($param['token']) < 20) {
                $param['token'] = ['member_id' => $param['token']];
            } else {
                $param['token'] = $crypt->singleDec($param['token']);
                if ($param['token'] == NULL) {
                    return [
                        'code' => -1,
                        'message' => 'token解析失败',
                    ];
                }
            }
            Db::startTrans();
            // 进行注册[手机号]
            $find = self::common($member, $param, $inform, 0);
            Db::commit();
            return [
                'code' => 0,
                'message' => '注册成功',
                'url' => Request::instance()->domain() . '/download/index.html',
                'mobile_url' => config('user.mobile.mobile_domain'),
                'data' => $member->retData($find),
            ];
        }
        return view('invite');
    }
    
    /**
     * 公共注册方法
     * @param Member $memberModel
     * @param $data
     * @param Inform $inform
     * @param int $type 0 手机注册 1 三方注册
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common(Member $memberModel,
                           $data,
                           Inform $inform,
                           $type = 0)
    {
        $upId = '';
        if (isset($data['token']['member_id'])) {
            $upId = $data['token']['member_id'];
        } elseif (isset($data['member_id'])) {
            $upId = $data['member_id'];
            unset($data['member_id']);
        }
        // 注册用户默认生成表
        $member = default_generated($memberModel, $data, $upId, $type);
        // 积分推送
    
        // 积分推送
        $inform->integral_inform(0, '11', Env::get('integral_phone'), [
            'member_id' => $member['member_id'],
            'web_open_id' => $member['web_open_id'],
            'subscribe_time' => $member['subscribe_time'],
            'micro_open_id' => $member['micro_open_id'],
            'phone' => $member['phone'],
        ], 0);

        return $member;
    }
    
}