<?php
declare(strict_types = 1);

namespace app\interfaces\controller\applet;

use app\common\model\Distribution;
use app\common\model\IntegralTask;
use app\common\model\Member;
use app\common\model\MemberTask;
use app\common\push\PubNum;
use app\interfaces\controller\BaseController;
use EasyWeChat\Factory;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;

/**
 * 小程序 - Joy
 * Class Search
 * @package app\interfaces\controller\goods
 */
class My extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    /**
     * 小程序登录or注册
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\DecryptException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function login(RSACrypt $crypt,
                          Member $memberModel)
    {
        $param = $crypt->request();
        $memberModel->valid($param, 'applet_login');
        $app = Factory::miniProgram(config('wechat.')['applet']);
        $find = $app->auth->session($param['code']);
        // 检测是否使用unionId来统一
        $flagId = ['openid', 'unionid'][config('user.common.wx.use_unionId')];
        if (empty($find[$flagId])) {
            $find['session_key'] = preg_replace('/\s*/', '', $find['session_key']);
            $find = $app->encryptor->decryptData($find['session_key'], $param['iv'], $param['encryptedData']);
            $flagId = ['openId', 'unionId'][config('user.common.wx.use_unionId')];
            if (empty($find[$flagId])) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '请求微信服务器失败',
                ], true);
            }
            $find['openid'] = $find['openId'];
        }
        $member = $memberModel
            ->where([
                ['wechat_open_id', '=', $find[$flagId]],
            ])
            ->field('member_id,phone,avatar,nickname,status,micro_open_id')
            ->with(['distributionRecord'])
            ->find();
        if (!is_null($member) && !$member['status']) {
            return $crypt->response([
                'code' => -1,
                'message' => '该账号已被注销或禁用',
            ], true);
        }
        if (!is_null($member) && is_null($member->micro_open_id)) {
            // 解绑操作会清除micro_open_id,若绑定了app或公众号,此处需要重新赋值
            $member->micro_open_id = $find['openid'];
            $member->save();
        }
        // dump(Cache::get($find[$flagId]));
        // halt($member);
        // 用户不存在执行注册
        if (is_null($member) && empty(Cache::get($find[$flagId]))) {
            Cache::set($find[$flagId], $find[$flagId], 180);
            $data = [
                'nickname' => $param['nickName'],
                'avatar' => $this->applet_upload($param['avatarUrl']),
                'distribution_superior' => array_key_exists('sup_id', $param) && $param['sup_id'] ? $param['sup_id'] : 0,
                'wechat_open_id' => $find[$flagId],
                'web_open_id' => null,
                'subscribe_time' => null,
                'micro_open_id' => $find['openid'],
                'phone' => '',
            ];
            Db::startTrans();
            // 公共注册
            $member = default_generated($memberModel, $data, $param['member_id'], 1);
            // 注册即成为分销商检测
            $regToBe = [
                'member_id' => $member['member_id'],
                'nickname' => $member['nickname'],
                'phone' => $member['phone'],
                'sex' => 0,     //默认女
                'web_open_id' => $member['web_open_id'],
                'subscribe_time' => $member['subscribe_time'],
                'micro_open_id' => $member['micro_open_id'],
                'distribution_superior' => $param['sup_id'] ?? 0,
                'bType' => 2,   //成为分销商途径注册自动成为分销商
                'text' => 2,    //注册即成为分销商
            ];
            $regDis = Hook::exec(['app\\interfaces\\behavior\\Distribution', 'regToBe'], $regToBe);
            Db::commit();
            $member['distribution_record'] = null;
            if (!empty($regDis)) {
                $member['distribution_record']['distribution_id'] = $regDis['distribution_id'];
                $member['distribution_record']['audit_status'] = $regDis['audit_status'];
            }
        }
        if (($param['sup_id'] ?? '') && $member['distribution_record']['distribution_id']) {
            $bindData = [
                'distribution_id' => $member['distribution_record']['distribution_id'],
                'distribution_superior' => $param['sup_id'],
                'nickname' => $member['nickname'],
            ];
            Hook::exec(['app\\interfaces\\behavior\\Distribution', 'bindExisted'], $bindData);
        }
        // 返回token
        $jwt = app('app\\common\\service\\JwtManage', [
            'param' => [
                'mid' => $member['member_id'],
                'dev_type' => $param['dev_type'],
            ]])->issueToken();
        header("token:" . $jwt);
        $ret = [
            'code' => 0,
            'message' => '登录成功',
            'member' => $member,
            'openid' => $find['openid'],
            'unionId' => $find[$flagId],
            'info' => [],
        ];
        if (!is_null($member['distribution_record'])) {
            $ret['info'] = [
                'distribution_id' => $member['distribution_record']['distribution_id'],
                'audit_status' => $member['distribution_record']['audit_status'],
            ];
        }
        return $crypt->response($ret, true);
    }
    
    /**
     * 设置form_id
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function saveFormId(RSACrypt $crypt)
    {
        $args = $crypt->request();
        if ($args['micro_form_id'] == 'the formId is a mock one') {
            return $crypt->response([
                'code' => -1,
                'message' => '不可使用模拟器发送form_id',
            ], true);
        }
        $args['member_id'] = request(0)->mid;
        $pushServer = app('app\\interfaces\\behavior\\Push');
        $pushServer->setFormId(Request::param('micro_open_id'), explode(',', $args['micro_form_id']));
        return $crypt->response([
            'code' => 0,
            'message' => '设置成功',
        ], true);
    }
    
    /**
     * 手机端微信登录or注册
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function mobile_login(Request $request,
                                 RSACrypt $crypt,
                                 Member $memberModel,
                                 Distribution $distribution)
    {
        $param = $request::get();
        $memberModel->valid($param, 'mobile_login');
        $app = Factory::officialAccount(config('wechat.')['mobile']);
        // 获取 OAuth 授权结果用户信息
        $result = $app->oauth->user();
        // 检测是否使用unionId来统一
        $flagId = ['openid', 'unionid'][config('user.common.wx.use_unionId')];
        if (!isset($result['original'][$flagId])) {
            return $crypt->response([
                'code' => -1,
                'message' => '请求微信服务器失败',
            ], true);
        }
        $member = $memberModel
            ->where([
                ['wechat_open_id', '=', $result['original'][$flagId]],
            ])
            ->field('member_id,phone,avatar,nickname,status,web_open_id,subscribe_time')
            ->find();
        if (!is_null($member) && !$member['status']) {
            return $crypt->response([
                'code' => -1,
                'message' => '该账号已被注销或禁用',
            ], true);
        }
        if (!is_null($member) && is_null($member->web_open_id)) {
            $member->web_open_id = $result['original']['openid'];
            $pubInfo = (new PubNum(['open_id' => $result['original']['openid']]))->getInfo();
            if ($pubInfo['subscribe'] == 1) {
                // 用户已关注公众号
                $member->subscribe_time = date('Y-m-d H:i:s', $pubInfo['subscribe_time']);
            }
            $member->save();
        }
        // 用户不存在则执行注册
        if (is_null($member) && empty(Cache::get($result['original'][$flagId]))) {
            Cache::set($result['original'][$flagId], $result['original'][$flagId], 8);
            $data = [
                'nickname' => $result['original']['nickname'],
                'avatar' => $this->applet_upload($result['original']['headimgurl']),
                'wechat_open_id' => $result['original'][$flagId],
                'web_open_id' => $result['original']['openid'],
                'subscribe_time' => null,
                'micro_open_id' => null,
                'phone' => '',
            ];
            $info = (new PubNum(['open_id' => $result['original']['openid']]))->getInfo();
            if ($info['subscribe'] == 1) {
                // 用户已关注公众号
                $data['subscribe_time'] = date('Y-m-d H:i:s', $info['subscribe_time']);
            }
            Db::startTrans();
            // 公共注册
            $member = default_generated($memberModel, $data, $param['member_id'], 1);
            // 注册即成为分销商检测
            $regToBe = [
                'member_id' => $member['member_id'],
                'nickname' => $member['nickname'],
                'phone' => $member['phone'],
                'sex' => 0,     //默认女
                'web_open_id' => $member['web_open_id'],
                'subscribe_time' => $member['subscribe_time'],
                'micro_open_id' => $member['micro_open_id'],
                'distribution_superior' => array_key_exists('sup_id', $param) && $param['sup_id'] ? $param['sup_id'] : 0,
                'bType' => 2,   //成为分销商途径注册自动成为分销商
                'text' => 2,    //注册即成为分销商
            ];
            $rb = Hook::exec(['app\\interfaces\\behavior\\Distribution', 'regToBe'], $regToBe);
            Db::commit();
            $member['distribution_id'] = '';
            if (!empty($rb)) {
                $member['distribution_id'] = $rb['distribution_id'];
            }
        } else {
            $did = $distribution
                ->where([
                    ['member_id', '=', $member['member_id']],
                    ['audit_status', '=', 1],
                ])
                ->value('distribution_id');
            $member['distribution_id'] = '';
            if ($did) {
                $member['distribution_id'] = $did;
            }
        }
        // 返回token
        $jwt = app('app\\common\\service\\JwtManage', [
            'param' => [
                'mid' => $member['member_id'],
                'dev_type' => $param['dev_type'],
            ]])->issueToken();
        header("token:" . $jwt);
        return $crypt->response([
            'code' => 0,
            'message' => '登录成功',
            'member' => $member,
            'openid' => $result['original']['openid'],
            'unionId' => $result['original'][$flagId],
        ], true);
    }
    
    /**
     * APP微信登录or注册
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function app_login(RSACrypt $crypt,
                              Member $memberModel,
                              Distribution $distribution)
    {
        $param = $crypt->request();
        $memberModel->valid($param, 'app_login');
        // 检测是否使用unionId来统一
        $flagId = ['open_id', 'unionId'][config('user.common.wx.use_unionId')];
        $member = $memberModel
            ->where([
                ['wechat_open_id', '=', $param[$flagId]],
            ])
            ->field('member_id,phone,avatar,nickname,status,app_open_id')
            ->find();
        if (!is_null($member) && !$member['status']) {
            return $crypt->response([
                'code' => -1,
                'message' => '该账号已被注销或禁用',
            ], true);
        }
        if (!is_null($member) && is_null($member->app_open_id)) {
            $member->app_open_id = $param['open_id'];
            $member->save();
        }
        // 不存在用户则执行新增
        if (is_null($member) && empty(Cache::get($param[$flagId]))) {
            Cache::set($param[$flagId], $param[$flagId], 8);
            $data = [
                'nickname' => $param['nickname'],
                'avatar' => $this->applet_upload($param['avatarUrl']),
                'wechat_open_id' => $param[$flagId],
                'app_open_id' => $param['open_id'],
                'subscribe_time' => null,
                'micro_open_id' => null,
                'web_open_id' => null,
                'phone' => '',
            ];
            Db::startTrans();
            // 注册用户默认生成表
            $member = default_generated($memberModel, $data, 0, 1);
            // 注册即成为分销商检测
            $regToBe = [
                'member_id' => $member['member_id'],
                'nickname' => $member['nickname'],
                'phone' => $member['phone'],
                'sex' => 0,     //默认女
                'web_open_id' => $member['web_open_id'],
                'subscribe_time' => $member['subscribe_time'],
                'micro_open_id' => $member['micro_open_id'],
                'distribution_superior' => array_key_exists('superior', $param) && $param['superior'] ? $param['superior'] : 0,
                'bType' => 2,   //成为分销商途径注册自动成为分销商
                'text' => 2,    //注册即成为分销商
            ];
            $rb = Hook::exec(['app\\interfaces\\behavior\\Distribution', 'regToBe'], $regToBe);
            Db::commit();
            $member['distribution_id'] = '';
            if ($rb) {
                $member['distribution_id'] = $rb['distribution_id'];
            }
        } else {
            $did = $distribution
                ->where([
                    ['member_id', '=', $member['member_id']],
                    ['audit_status', '=', 1],
                ])
                ->value('distribution_id');
            $member['distribution_id'] = '';
            if ($did) {
                $member['distribution_id'] = $did;
            }
        }
        // 返回token
        $jwt = app('app\\common\\service\\JwtManage', [
            'param' => [
                'mid' => $member['member_id'],
                'dev_type' => $param['dev_type'],
            ]])->issueToken();
        header("token:" . $jwt);
        return $crypt->response([
            'code' => 0,
            'message' => '登录成功',
            'member' => $member,
            'openid' => $param['open_id'],
            'unionId' => $param['unionId'],
        ], true);
    }
    
    /**
     * 绑定手机号
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function info(RSACrypt $crypt,
                         Member $memberModel)
    {
        $param = $crypt->request();
        $memberModel->valid($param, 'applet_info');
        //检测短信(10绑定手机号)
        $sms = app('app\\interfaces\\controller\\auth\\Sms');
        $checkCode = $sms->getCache($param['phone'], 10, $param['code']);
        if (!$checkCode) {
            return $crypt->response([
                'code' => -1,
                'message' => '验证码不正确',
            ], true);
        }
        $wxMember = $memberModel
            ->where([
                ['wechat_open_id', '=', $param['unionId']],
            ])
            ->field('member_id,wechat_open_id,app_open_id,micro_open_id,
            web_open_id,status,phone,avatar,pay_points,avatar,delete_time')
            ->find();
        if (is_null($wxMember) || !$wxMember['status']) {
            return $crypt->response([
                'code' => -1,
                'message' => '该用户不存在或已被注销',
            ], true);
        }
        if ($wxMember['phone']) {
            return $crypt->response([
                'code' => -2,
                'message' => '该账号已绑定手机号,不可再次绑定',
            ], true);
        }
        $phoneMember = $memberModel
            ->where([
                ['phone', '=', $param['phone']],
            ])
            ->field('member_id,wechat_open_id,avatar,pay_points')
            ->find();
        if ($phoneMember['wechat_open_id'] && $phoneMember['wechat_open_id'] != $param['unionId']) {
            return $crypt->response([
                'code' => -2,
                'message' => '该手机号已绑定其他微信号,不可再次绑定',
            ], true);
        }
        Db::startTrans();
        if (is_null($phoneMember)) {
            // 更新微信账号手机号码
            $wxMember->phone = $param['phone'];
            $wxMember->save();
            // 更新积分和成长值
            taskOver($member_id = $wxMember['member_id'], 0, 0);
        } else {
            // 将刚注册的微信账号绑定到手机账号上
            $phoneMember->wechat_open_id = $wxMember->wechat_open_id;
            $phoneMember->avatar = $wxMember->getData('avatar');
            $phoneMember->app_open_id = $wxMember->app_open_id;
            $phoneMember->micro_open_id = $wxMember->micro_open_id;
            $phoneMember->web_open_id = $wxMember->web_open_id;
            if (!$phoneMember->avatar) {
                $phoneMember->avatar = $wxMember->getData('avatar');
            }
            $phoneMember->save();
            $wxMember->delete();
            // 更新积分和成长值
            taskOver($member_id = $phoneMember['member_id'], 1, 0);
            // 返回token
            $jwt = app('app\\common\\service\\JwtManage', [
                'param' => [
                    'mid' => $phoneMember['member_id'],
                    'dev_type' => Request::param('dev_type'),
                ],
            ], true)->issueToken();
            header("token:" . $jwt);
        }
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '绑定成功',
            'member_id' => $member_id,
        ]);
    }
    
    /**
     * 绑定微信
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function binding_wechat(RSACrypt $crypt,
                                   Member $memberModel)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        //        halt($param);
        // 主账号信息
        $phoneMember = $memberModel
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->field('member_id,status,wechat_open_id,app_open_id,web_open_id')
            ->find();
        if (is_null($phoneMember) || !$phoneMember['status']) {
            return $crypt->response([
                'code' => -1,
                'message' => '该用户不存在或已被注销',
            ], true);
        }
        if ($phoneMember['wechat_open_id']) {
            return $crypt->response([
                'code' => -2,
                'message' => '该账号已绑定微信,不可再次绑定',
            ], true);
        }
        $idType = [
            1 => 'app_open_id',
            2 => 'app_open_id',
            4 => 'web_open_id',
        ][Request::param('dev_type')];
        $wxMember = $memberModel
            ->where([
                ['wechat_open_id', '=', $param['wechat_open_id']],
            ])
            ->field('member_id,phone,delete_time')
            ->find();
        if (!is_null($wxMember) && $wxMember['phone']) {
            return $crypt->response([
                'code' => -3,
                'message' => '该微信已绑定手机,不可再次绑定',
            ], true);
        }
        if (Request::param('dev_type') == 4) {  // 手机站
            $info = (new PubNum(['open_id' => $param['open_id']]))->getInfo();
            if ($info['subscribe'] == 1) {
                // 用户已关注公众号
                $phoneMember->subscribe_time = date('Y-m-d H:i:s', $info['subscribe_time']);
            }
        }
        // 更新手机账号
        $phoneMember->wechat_open_id = $param['wechat_open_id'];
        $phoneMember->$idType = $param['open_id'];
        Db::startTrans();
        $phoneMember->save();
        if (!is_null($wxMember)) {
            // 删除微信账号
            $wxMember->delete();
        }
        // 更新积分和成长值
        taskOver($phoneMember['member_id'], 1, 0);
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '绑定成功',
        ], true);
    }
    
    /**
     * 解除绑定微信
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @param MemberTask $memberTask
     * @param IntegralTask $integralTask
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function relieve_binding_wechat(RSACrypt $crypt,
                                           Member $memberModel,
                                           MemberTask $memberTask,
                                           IntegralTask $integralTask)
    {
        $param['member_id'] = request(0)->mid;
        $info = $memberModel
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->field('wechat_open_id,status,subscribe_time,
            app_open_id,micro_open_id,web_open_id')
            ->find();
        if (is_null($info) || !$info['status']) {
            return $crypt->response([
                'code' => -1,
                'message' => '该用户不存在或已被注销',
            ], true);
        }
        $info->wechat_open_id = null;
        $info->subscribe_time = null;
        $info->app_open_id = null;
        $info->micro_open_id = null;
        $info->web_open_id = null;
        Db::startTrans();
        $info->save();
        $memberTask
            ->allowField(true)
            ->isUpdate(true)
            ->save([
                'third_party_state' => 0,
            ], [
                'member_id' => $param['member_id'],
            ]);
        $integralTask
            ->allowField(true)
            ->isUpdate(true)
            ->save([
                'third_party_state' => 0,
            ], [
                'member_id' => $param['member_id'],
            ]);
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '解除成功',
        ], true);
    }
    
    /**
     * 是否绑定微信
     * @param RSACrypt $crypt
     * @param Member $memberModel
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function judge_binding_wechat(RSACrypt $crypt,
                                         Member $memberModel)
    {
        $param['member_id'] = request(0)->mid;
        $info = $memberModel
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->value('wechat_open_id');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => !$info ? 0 : 1,
        ], true);
    }
    
    /**
     * 面对面扫码 - 小程序
     * @param RSACrypt $crypt
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function face_code(RSACrypt $crypt)
    {
        $param['member_id'] = request(0)->mid;
        $file = 'static/img/interfaces/qr_code/face/face_' . $param['member_id'] . '.png';
        self::aplCode([
            'file_path' => $file,
            'scene' => 'token,' . $param['member_id'],
        ], [
            'width' => 600,
            'page' => 'nearby_shops/invitation_web/invitation_web',
        ]);
        return $crypt->response([
            'code' => 0,
            'applet_face_code_file' => Request::domain() . '/' . $file,
        ], true);
    }
    
    /**
     * 生成小程序二维码
     * @param $args
     * @param $wh
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     */
    public static function aplCode($args, $wh)
    {
        $file_path_prefix = Env::get('root_path') . 'public/';
        if (!file_exists($file_path_prefix . $args['file_path'])) {
            $app = Factory::miniProgram(config('wechat.')['applet']);
            $response = $app->app_code->getUnlimit($args['scene'], $wh);
            if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
                $pathInfo = pathinfo($file_path_prefix . $args['file_path']);
                $response->saveAs($pathInfo['dirname'], $pathInfo['basename']);
            }
        }
    }
    
    
    /**
     * 小程序上传文件
     * @param $url
     * @return bool|string
     */
    protected static function applet_upload($url)
    {
        if (!$url) {
            return false;
        }
        // 设置运行时间为无限制
        set_time_limit(0);
        $url = trim($url);
        $curl = curl_init();
        // 设置你需要抓取的URL
        curl_setopt($curl, CURLOPT_URL, $url);
        // 设置header
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // https请求 不验证证书 其实只用这个就可以了
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        //https请求 不验证HOST
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行速度慢，强制进行ip4解析
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        // 运行cURL，请求网页
        $file = curl_exec($curl);
        // 关闭URL请求
        curl_close($curl);
        // 将文件写入获得的数据
        $filename = './avatar/' . time() . rand() . ".jpg";
        $dir = iconv("UTF-8", "GBK", './avatar/' . date('Ymd'));
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $write = @fopen($filename, "w");
        fwrite($write, $file);
        fclose($write);
        $oss = app('app\\common\\service\\OSS');
        // 上传 oss
        $ossCode = $oss->fileUpload('avatar/file/' . date('Ymd') . '/' . substr($filename, 9), Env::get('root_path') . 'public/' . substr($filename, 2));
        if ($ossCode['code'] == 0) {
            // 删除本地文件
            unlink(Env::get('root_path') . 'public/' . substr($filename, 2));
            return 'avatar/file/' . date('Ymd') . '/' . substr($filename, 9);
        } else {
            return false;
        }
    }
    
    /**
     * 小程序一级菜单
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function navigation(RSACrypt $crypt)
    {
        $arg = $crypt->request();
        if (!array_key_exists('type', $arg)) {
            $arg['type'] = 'more_1';
        }
        // more_1 多店第1套模板   only_0 单店第一套模板
        $svg = [
            'home' => [
                'front' => [
                    'more_0' => '<svg t="1554701298526" id="home_front_more_0" class="icon" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4702" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M956.4 530.4c2.7-5.6 14.2-35.6-24.3-72.8l-4.5-4.4-337.1-325.9c-20.8-20.1-48.6-31.2-78.5-31.2-29.8 0-57.7 11.1-78.5 31.2L91.9 457.6c-38.4 37.2-27 67.2-24.4 72.8 5.5 11.7 20.3 31.4 56.5 31.4h52.4v256.6c0 52.7 40.4 109.5 105.7 109.5h143.1V661.5c0-3.5-0.1-6.7-0.1-9.9-0.3-13.3-0.5-23.8 5.4-30.3 4.5-5 11.9-7.2 24-7.2h115c12.1 0 19.5 2.2 24 7.2 5.9 6.6 5.7 17 5.4 30.3-0.1 3.2-0.1 6.4-0.1 9.9v266.4h143.1c65.3 0 105.7-56.9 105.7-109.5V561.8H900c36.1 0 50.8-19.7 56.4-31.4z m-160.5-15.9v303.9c0 29.9-20.7 62.2-54 62.2h-91.4V661.5c0-64.6-25.7-94.6-81-94.6h-115c-55.2 0-81 30.1-81 94.6v219.1h-91.4c-33.4 0-54-32.3-54-62.2V514.5h-104c-1.9 0-2.8-1.8-3.5-3.3-0.1-0.1-0.1-0.3-0.2-0.4h-3.2l2.3-3.8c0.4-0.7 0.9-1.6 1.4-2.7 2.1-4 5.2-10 9.9-14.4l341.7-330.1c10.8-10.4 25.8-16.5 40.1-16.4 14.2 0 28.4 6 39.2 16.4l341.7 330.1c4.5 4.3 7.6 10.2 9.7 14.1 0.6 1.2 1.2 2.2 1.6 2.9l2.4 3.9h-3.4c-0.1 0.2-0.2 0.3-0.2 0.4-0.7 1.4-1.6 3.2-3.5 3.2H795.9z" p-id="4703"></path></svg>',
                    'more_1' => '<svg version="1.1" id="home_front_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M945.9,550.4L546,150.5c-13.5-13-34.8-13-48.3,0L98.6,550.4c-13.3,13.3-13.3,34.9,0,48.3c13.3,13.3,34.9,13.3,48.3,0l375.4-376.2L897.8,598c13.3,13.3,34.9,13.3,48.3,0c13.3-13.3,13.3-34.9,0-48.3L945.9,550.4L945.9,550.4z"/><path class="st0" d="M776.9,559.2c-18.8,0-34,15.2-34,34v222H635.7v-112c0-18.8-15.2-34-34-34H442.9c-18.8,0-34,15.2-34,34v112H301.6v-222c0-18.8-15.2-34-34-34c-18.8,0-34,15.2-34,34v255.9c0,18.8,15.2,34,34,34h175.1c18.8,0,34-15.2,34-34V737.2h90.9v112c0,18.8,15.2,34,34,34h175.1c18.8,0,34-15.2,34-34V593.3C810.8,574.5,795.7,559.2,776.9,559.2L776.9,559.2z"/></g></svg>',
                    'only_0' => '<svg version="1.1" id="home_front_only_0" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}.st1{fill:#7f7f7f;stroke:#7f7f7f;stroke-width:4;stroke-miterlimit:10;}</style><g><g><defs><rect id="SVGID_1_" x="111.1" y="107.6" width="770.6" height="770.6"/></defs><clipPath id="SVGID_2_"><use xlink:href="#SVGID_1_"  style="overflow:visible;"/></clipPath></g></g><g><g><defs><rect id="SVGID_3_" x="92" y="90" width="770.6" height="770.6"/></defs><clipPath id="SVGID_4_"><use xlink:href="#SVGID_3_"  style="overflow:visible;"/></clipPath></g></g><path class="st0" d="M546.1,726.3h-68.3V593.2c0-13.7,10.2-27.3,27.3-27.3h13.7c13.7,0,27.3,10.2,27.3,27.3V726.3z M546.1,726.3"/><path class="st1" d="M782.1,845H241.9c-31.4,0-56.9-25.5-56.9-56.9V404.7c0-16.2,6.9-31.6,18.9-42.4l270.5-242.1c21.7-19.4,54.3-19.3,75.9,0.1l269.6,242c12,10.8,18.9,26.2,18.9,42.3v383.4C838.9,819.4,813.4,845,782.1,845L782.1,845z M512.5,162.6L241.9,404.7v383.4h540.2V404.7L512.5,162.6L512.5,162.6z M493.5,141.4h0.1H493.5z M493.5,141.4"/></svg>',
                    'only_1' => '<svg version="1.1" id="home_front_only_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:url(#SVGID_1_);}</style><linearGradient id="SVGID_1_" gradientUnits="userSpaceOnUse" x1="139.4009" y1="512" x2="884.5991" y2="512"><stop  offset="0" style="stop-color:#E2E2E2"/><stop  offset="1" style="stop-color:#D6D6D6"/></linearGradient><path class="st0" d="M878,476.6L581.1,170.3c-18.1-18.8-43-29.5-69.1-29.5c-26.1,0-51.1,10.6-69.1,29.5L146,476.6c-5.8,5.9-8,14.5-5.7,22.5c2.3,8,8.6,14.1,16.7,16.1c8,2,16.5-0.5,22.3-6.5l45-46.4v349.1c0,39.6,34.4,71.8,76.8,71.8h422	c42.4,0,76.8-32.1,76.8-71.8V462.3l45,46.4c5.7,6,14.2,8.5,22.3,6.5c8-2,14.4-8.1,16.7-16.1C886,491.1,883.8,482.6,878,476.6zM603.7,652.8c0,9.1-3.6,17.9-10.1,24.3c-6.5,6.4-15.2,10.1-24.3,10.1H454.7c-9.1,0-17.9-3.6-24.3-10.1c-6.4-6.4-10.1-15.2-10.1-24.3V538.1c0-9.1,3.6-17.9,10.1-24.3c6.4-6.5,15.2-10.1,24.3-10.1h114.6c9.1,0,17.9,3.6,24.3,10.1c6.4,6.4,10.1,15.2,10.1,24.3V652.8z"/></svg>',
                    'only_2' => '<svg version="1.1" id="home_front_only_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><g>	<path class="st0" d="M821.2,398L551.3,155.7c-22.1-19.8-55.4-19.9-77.6-0.1L202.9,398c-12.3,11-19.3,26.8-19.3,43.3v383.8		c0,32,26.1,58.1,58.1,58.1h540.8c32,0,58.1-26.1,58.1-58.1V441.3C840.5,424.8,833.4,409,821.2,398z M781.2,441.8v382.2H242.8V441.8		l269.7-241.3L781.2,441.8z"/>	<path class="st0" d="M433.7,729.1h163.7c16.3,0,29.6-13.3,29.6-29.6v-14.2c0-16.3-13.3-29.6-29.6-29.6H433.7		c-20.3,0-36.7,16.5-36.7,36.7S413.5,729.1,433.7,729.1z"/></g></svg>',
                ],
                'back' => [
                    'more_0' => '<svg t="1554701113344" id="home_back_more_0" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4220" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M956.4 530.4c2.7-5.6 14.2-35.6-24.3-72.8l-4.5-4.4-337.1-325.9c-20.8-20.1-48.6-31.2-78.5-31.2-29.8 0-57.7 11.1-78.5 31.2L91.9 457.6c-38.4 37.2-27 67.2-24.4 72.8 5.5 11.7 20.3 31.4 56.5 31.4h52.4v256.6c0 52.7 40.4 109.5 105.7 109.5h143.1V661.5c0-3.5-0.1-6.7-0.1-9.9-0.3-13.3-0.5-23.8 5.4-30.3 4.5-5 11.9-7.2 24-7.2h115c12.1 0 19.5 2.2 24 7.2 5.9 6.6 5.7 17 5.4 30.3-0.1 3.2-0.1 6.4-0.1 9.9v266.4h143.1c65.3 0 105.7-56.9 105.7-109.5V561.8H900c36.1 0 50.8-19.7 56.4-31.4z" p-id="4221"></path></svg>',
                    'more_1' => '<svg version="1.1" id="home_back_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M945.9,550.4L546,150.5c-13.5-13-34.8-13-48.3,0L98.6,550.4c-13.3,13.3-13.3,34.9,0,48.3c13.3,13.3,34.9,13.3,48.3,0l375.4-376.2L897.8,598c13.3,13.3,34.9,13.3,48.3,0c13.3-13.3,13.3-34.9,0-48.3L945.9,550.4L945.9,550.4z"/><path class="st0" d="M776.9,559.2c-18.8,0-34,15.2-34,34v222H635.7v-112c0-18.8-15.2-34-34-34H442.9c-18.8,0-34,15.2-34,34v112H301.6v-222c0-18.8-15.2-34-34-34c-18.8,0-34,15.2-34,34v255.9c0,18.8,15.2,34,34,34h175.1c18.8,0,34-15.2,34-34V737.2h90.9v112c0,18.8,15.2,34,34,34h175.1c18.8,0,34-15.2,34-34V593.3C810.8,574.5,795.7,559.2,776.9,559.2L776.9,559.2z"/></g></svg>',
                    'only_0' => '<svg version="1.1" id="home_back_only_0" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><g><defs><rect id="SVGID_1_" x="111.1" y="107.6" width="770.6" height="770.6"/></defs><clipPath id="SVGID_2_"><use xlink:href="#SVGID_1_"  style="overflow:visible;"/></clipPath></g></g><g><g><defs><rect id="SVGID_3_" x="92" y="90" width="770.6" height="770.6"/></defs><clipPath id="SVGID_4_"><use xlink:href="#SVGID_3_"  style="overflow:visible;"/></clipPath></g></g><path class="st0" d="M820.1,362.4l-269.6-242c-21.6-19.4-54.2-19.5-75.9-0.1L204,362.4c-12,10.8-18.9,26.2-18.9,42.4v383.4c0,31.4,25.5,56.9,56.9,56.9h540.2c31.4,0,56.9-25.5,56.9-56.9V404.7C838.9,388.6,832.1,373.2,820.1,362.4z M546.1,726.3h-68.3V593.2c0-13.7,10.2-27.3,27.3-27.3h13.7c13.7,0,27.3,10.2,27.3,27.3V726.3z"/></svg>',
                    'only_1' => '<svg version="1.1" id="home_back_only_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><path class="st0" d="M878,476.6L581.1,170.3c-18.1-18.8-43-29.5-69.1-29.5c-26.1,0-51.1,10.6-69.1,29.5L146,476.6	c-5.8,5.9-8,14.5-5.7,22.5c2.3,8,8.6,14.1,16.7,16.1c8,2,16.5-0.5,22.3-6.5l45-46.4v349.1c0,39.6,34.4,71.8,76.8,71.8h422	c42.4,0,76.8-32.1,76.8-71.8V462.3l45,46.4c5.7,6,14.2,8.5,22.3,6.5c8-2,14.4-8.1,16.7-16.1C886,491.1,883.8,482.6,878,476.6z	 M603.7,652.8c0,9.1-3.6,17.9-10.1,24.3c-6.5,6.4-15.2,10.1-24.3,10.1H454.7c-9.1,0-17.9-3.6-24.3-10.1	c-6.4-6.4-10.1-15.2-10.1-24.3V538.1c0-9.1,3.6-17.9,10.1-24.3c6.4-6.5,15.2-10.1,24.3-10.1h114.6c9.1,0,17.9,3.6,24.3,10.1	c6.4,6.4,10.1,15.2,10.1,24.3V652.8z"/></svg>',
                    'only_2' => '<svg version="1.1" id="home_back_only_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><path class="st0" d="M821.2,398L551.3,155.7c-22.1-19.8-55.4-19.9-77.6-0.1L202.9,398c-12.3,11-19.3,26.8-19.3,43.3v383.8	c0,32,26.1,58.1,58.1,58.1h540.8c32,0,58.1-26.1,58.1-58.1V441.3C840.5,424.8,833.4,409,821.2,398z M624.7,686v14.2	c0,15-12.2,27.3-27.3,27.3H433.7c-19,0-34.4-15.4-34.4-34.4c0-19,15.4-34.4,34.4-34.4h163.7C612.4,658.7,624.7,670.9,624.7,686z"/></svg>',
                ],
            ],
            'cate' => [
                'front' => [
                    'more_0' => '<svg t="1554701310830" id="cate_front_more_0" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4848" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M602.4 126.1C562.8 166 541 219 541 275.4v188.1c0 12.6 10.2 22.9 22.7 22.9h186.7c55.9 0 108.5-22 148.1-61.9 39.6-39.9 61.3-92.8 61.3-149.1 0-54.2-20.4-105.8-57.5-145.1l-4.1-4.4c-39.5-39.7-92-61.5-147.8-61.5-55.9-0.1-108.5 21.9-148 61.7z m264 32.4l1.2 1.1c30.2 31.2 46.8 72.2 46.8 115.7 0 44.2-17 85.7-47.9 116.9-31 31.1-72.2 48.3-116 48.3h-164V275.4c0-44.2 17-85.7 48-116.8 31-31.2 72.1-48.4 116-48.4 43.8 0 85 17.2 115.9 48.3zM460.2 537.6H273.5c-56 0-108.6 21.9-148.1 61.8s-61.3 92.8-61.3 149.2C64.1 805 85.9 858 125.6 898l1.1 1c39.6 39.1 91.7 60.6 146.9 60.6 55.9 0 108.5-22 148.1-61.9C461.2 857.9 483 805 483 748.6V560.5c0-12.6-10.2-22.9-22.8-22.9z m-22.7 211c0 44.2-17 85.7-47.9 116.9-31 31.1-72.2 48.3-116 48.3-43.2 0-84-16.7-114.7-47l-1.4-1.4c-30.8-31.1-47.9-72.5-47.9-116.7s17-85.7 48-116.8c30.9-31.2 72.1-48.4 116-48.4h163.9v165.1zM898.6 599.4c-39.6-39.8-92.2-61.8-148.1-61.8H563.8c-12.5 0-22.7 10.3-22.7 22.9v188.1c0 56.3 21.8 109.3 61.3 149.1 39.6 39.9 92.2 61.9 148.1 61.9 55.2 0 107.3-21.5 146.7-60.5l1.2-1.1c39.7-40 61.5-93.1 61.5-149.4 0-56.3-21.8-109.3-61.3-149.2z m-32.2 266.1l-1.1 1.2c-30.9 30.5-71.6 47.2-114.9 47.2-43.9 0-85.1-17.1-116-48.3-30.9-31.2-47.9-72.7-47.9-116.9V583.5h163.9c43.8 0 85 17.2 116 48.4 30.9 31.2 48 72.6 48 116.8 0 44.1-17 85.6-48 116.8zM125.4 126.1C85.9 166 64.1 219 64.1 275.4c0 56.4 21.8 109.4 61.3 149.2l1.3 1.3 2.5 2.3c39 37.5 90.3 58.2 144.3 58.2h186.7c12.5 0 22.7-10.3 22.7-22.9V275.4c0-55.6-21.2-108-59.8-147.6l-1.6-1.8c-39.5-39.8-92.1-61.7-148-61.7-55.9 0-108.5 22-148.1 61.8z m265.2 33.5c30.2 31.1 46.8 72.2 46.8 115.7v165.2H273.5c-42.9 0-83.5-16.5-114.2-46.6l-1.8-1.8c-30.9-31.1-47.9-72.6-47.9-116.8s17-85.7 48-116.8c31-31.2 72.1-48.3 116-48.3 43.8 0 85 17.2 116.1 48.5l0.9 0.9z" p-id="4849"></path></svg>',
                    'more_1' => '<svg version="1.1" id="cate_front_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M490.8,661.4c0-33.6-13.2-65.2-37.1-89.2s-55.7-37.1-89.2-37.1H269c-33.6,0-65.2,13.2-89.2,37.1s-37.1,55.7-37.1,89.2v95.5c0,33.6,13.2,65.2,37.1,89.2c23.9,23.9,55.7,37.1,89.2,37.1h95.4c35.1,0,67.6-13.6,91.6-38.3c23.2-23.8,35.6-55.2,34.7-88.1L490.8,661.4L490.8,661.4z M432.6,755.2c0,37.6-30.6,68.2-68.2,68.2h-95.5c-37.6,0-68.2-30.6-68.2-68.2v-95.5c0-37.6,30.6-68.2,68.2-68.2h95.5c37.6,0,68.2,30.6,68.2,68.2V755.2z"/><path class="st0" d="M268.9,490.8h95.5c34.9,0,67.3-13.5,91.2-38.1c23.5-24.1,36-56.2,35.2-90.1v-95.5c0-33.6-13.2-65.2-37.1-89.2s-55.7-37.1-89.2-37.1H269c-33.6,0-65.2,13.2-89.2,37.1s-37.1,55.7-37.1,89.2v97.3c0,33.6,13.2,65.2,37.1,89.2C203.8,477.5,235.3,490.8,268.9,490.8z M200.8,267.2c0-37.6,30.6-68.2,68.2-68.2h95.5c37.6,0,68.2,30.6,68.2,68.2v95.5c0,37.6-30.6,68.2-68.2,68.2h-95.5c-37.6,0-68.2-30.6-68.2-68.2L200.8,267.2L200.8,267.2z"/><path class="st0" d="M836.9,573.3H588.5c-15.5,0-29.1,13.6-29.1,29.1c0,15.5,13.6,29.1,29.1,29.1h246.6c16.1,0,30.9-13.9,30.9-29.1C866,586.8,852.3,573.3,836.9,573.3z"/><path class="st0" d="M691,807.7H588.5c-15.5,0-29.1,13.6-29.1,29.1c0,15.5,13.6,29.1,29.1,29.1H691c15.5,0,29.1-13.6,29.1-29.1C720.1,821.2,706.5,807.7,691,807.7z"/><path class="st0" d="M793.4,689.5H588.5c-15.5,0-29.1,13.6-29.1,29.1s13.6,29.1,29.1,29.1h204.9c15.5,0,29.1-13.6,29.1-29.1C822.5,703.1,809,689.5,793.4,689.5z"/><path class="st0" d="M850.1,349.2h-0.6c-13.3,0-24.5,9.4-27.2,22.4c-5.3,26.3-15.8,63.6-65.4,59.3h-95.5c-37.6,0-68.2-30.5-68.2-68.2v-95.5c0-37.6,30.5-68.2,68.2-68.2h95.5c49.2,0,63.2,38.6,67,65c2.1,14.2,14.3,24.5,28.5,24.5l0,0c17.6,0,31.2-15.6,28.5-33c-3.6-23.8-12.6-55.5-34.8-77.6c-23.9-23.9-55.7-37.1-89.2-37.1h-95.5c-33.6,0-65.2,13.2-89.2,37.1c-23.9,23.9-37.1,55.7-37.1,89.2v97.3c0,33.6,13.2,65.2,37.1,89.2c23.9,23.9,55.7,37.1,89.2,37.1H757c84.4,4.6,112.9-56.1,119.9-107.1C879.5,365.8,868.1,349.2,850.1,349.2z"/></g></svg>',
                    'only_0' => '<svg version="1.1" id="cate_front_only_0" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><path class="st0" d="M511.7,557.6c-9.2,0-18.3-1.9-26.8-5.6L162.7,410c-22.6-9.9-36.8-30.9-37.1-54.7c-0.3-23.7,13.3-45,35.5-55.5l322.3-152.7c17.8-8.4,39-8.4,56.8,0l321.9,152.7c22.2,10.5,35.8,31.8,35.4,55.5c-0.3,23.8-14.5,44.7-37.1,54.6L538.5,552C530.1,555.7,521,557.6,511.7,557.6L511.7,557.6z M190,354.4l319.9,141c1.1,0.5,2.6,0.5,3.8,0l319.5-140.9L513.7,202.9v0c-1.1-0.5-2.7-0.6-3.9,0L190,354.4z M836.3,356.1h0.2H836.3z M511.9,883.3c-8.9,0-18-2-26.4-6L173.8,742.4c-15.6-6.8-22.9-24.9-16.1-40.6c6.8-15.7,24.9-22.9,40.6-16.1l312.8,135.3L809.5,686c15.5-7.1,33.8-0.2,40.9,15.3c7.1,15.5,0.2,33.8-15.3,40.9L537.4,877.5C529.4,881.4,520.7,883.3,511.9,883.3L511.9,883.3z M511.9,883.3"/><path class="st0" d="M822.2,515L511.4,650.1L200.8,515.3v63.6c0,11.8,7.1,22.5,17.9,27.2l260.5,111.6c10.2,4.9,21.2,7.4,32.1,7.4c11,0,21.9-2.5,32.1-7.4l260.8-111.9c10.9-4.7,17.9-15.4,17.9-27.2V515z M822.2,515"/></svg>',
                    'only_1' => '<svg version="1.1" id="cate_front_only_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:url(#SVGID_1_);}	.st1{fill:url(#SVGID_2_);}	.st2{fill:url(#SVGID_3_);}	.st3{fill:url(#SVGID_4_);}</style><g>	<linearGradient id="SVGID_1_" gradientUnits="userSpaceOnUse" x1="140.6546" y1="309.5284" x2="478.4317" y2="309.5284">		<stop  offset="0" style="stop-color:#E2E2E2"/>		<stop  offset="1" style="stop-color:#D6D6D6"/>	</linearGradient>	<path class="st0" d="M398,140.8H229.2c-50.6,0-88.6,38-88.6,84.4v168.7c0,46.4,38,84.4,84.4,84.4h168.7c46.4,0,84.4-38,84.4-84.4		V225.2C482.3,178.8,444.4,140.8,398,140.8z"/>	<linearGradient id="SVGID_2_" gradientUnits="userSpaceOnUse" x1="545.6006" y1="309.5284" x2="883.3455" y2="309.5284">		<stop  offset="0" style="stop-color:#E2E2E2"/>		<stop  offset="1" style="stop-color:#D6D6D6"/>	</linearGradient>	<path class="st1" d="M798.7,140.8H630c-46.4,0-84.4,38-84.4,84.4v168.7c0,46.4,38,84.4,84.4,84.4h168.7c46.4,0,84.4-38,84.4-84.4		V225.2C887.3,178.8,845.1,140.8,798.7,140.8z M836.7,389.7c0,25.3-21.1,42.2-42.2,42.2H642.6c-25.3,0-42.2-21.1-42.2-42.2V233.6		c0-25.3,21.1-42.2,42.2-42.2h151.9c25.3,0,42.2,21.1,42.2,42.2V389.7z"/>	<linearGradient id="SVGID_3_" gradientUnits="userSpaceOnUse" x1="144.8735" y1="714.4735" x2="482.3267" y2="714.4735">		<stop  offset="0" style="stop-color:#E2E2E2"/>		<stop  offset="1" style="stop-color:#D6D6D6"/>	</linearGradient>	<path class="st2" d="M398,545.7H229.2c-46.4,0-84.4,38-84.4,84.4v168.7c0,46.4,38,84.4,84.4,84.4H398c46.4,0,84.4-38,84.4-84.4		V630.1C482.3,583.7,444.4,545.7,398,545.7z"/>	<linearGradient id="SVGID_4_" gradientUnits="userSpaceOnUse" x1="545.6006" y1="714.4735" x2="883.3455" y2="714.4735">		<stop  offset="0" style="stop-color:#E2E2E2"/>		<stop  offset="1" style="stop-color:#D6D6D6"/>	</linearGradient>	<path class="st3" d="M798.7,545.7H630c-46.4,0-84.4,38-84.4,84.4v168.7c0,46.4,38,84.4,84.4,84.4h168.7c46.4,0,84.4-38,84.4-84.4		V630.1C887.3,583.7,845.1,545.7,798.7,545.7z"/></g></svg>',
                    'only_2' => '<svg version="1.1" id="cate_front_only_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><g>	<path class="st0" d="M870.1,467.3c14.7-12.8,22.8-29.9,22.8-48.1c0-18.2-8.1-35.3-22.8-48.1l-1.4-1.2L561,157.2l-0.2-0.2		c-13.3-10.5-30.6-16.2-48.8-16.2c-18.2,0-35.5,5.8-48.8,16.2l-0.4,0.3L155.3,369.9l-1.4,1.2c-14.7,12.8-22.8,29.9-22.8,48.1		c0,18.2,8.1,35.3,22.8,48.1l1.4,1.2L463,681.3l0.2,0.2c13.3,10.5,30.6,16.2,48.8,16.2c18.2,0,35.5-5.8,48.8-16.2l0.4-0.3		l307.6-212.7L870.1,467.3z M512.1,638.6l-1.9-1.3L194.6,419.1l317.5-219.5l317.5,219.5L512.1,638.6z"/>	<path class="st0" d="M883.8,631.6c1-8-1.6-15.8-7.3-22c-11.8-12.9-33.5-15.1-48.3-4.8l-316,219.4l-1.9-1.3L186.9,600.5		c-6.1-4.2-13.5-6.4-21.3-6.4c-10.6,0-20.5,4.1-27,11.4c-5.7,6.3-8.2,14.1-7.2,22.1c1.1,8,5.6,15.1,12.8,20L463,866.8l0.2,0.2		c13.3,10.5,30.6,16.2,48.8,16.2c18.2,0,35.6-5.8,49-16.3l0.4-0.3L871,651.7C878.2,646.7,882.7,639.6,883.8,631.6z"/>	<path class="st0" d="M456.1,451.1H568c20-0.1,36.4-14.4,36.5-31.9c0-8.5-3.8-16.5-10.7-22.5c-6.8-5.9-16.2-9.3-25.8-9.3H456		c-20,0.1-36.4,14.4-36.5,32C419.6,436.7,436,451,456.1,451.1z"/></g></svg>',
                ],
                'back' => [
                    'more_0' => '<svg t="1554701318533" id="cate_back_more_0" class="icon" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4994" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M602.4 126.1C562.8 166 541 219 541 275.4v188.1c0 12.6 10.2 22.9 22.7 22.9h186.7c55.9 0 108.5-22 148.1-61.9 39.6-39.9 61.3-92.8 61.3-149.1 0-54.2-20.4-105.8-57.5-145.1l-4.1-4.4c-39.5-39.7-92-61.5-147.8-61.5-55.9-0.1-108.5 21.9-148 61.7zM460.2 537.6H273.5c-56 0-108.6 21.9-148.1 61.8s-61.3 92.8-61.3 149.2C64.1 805 85.9 858 125.6 898l1.1 1c39.6 39.1 91.7 60.6 146.9 60.6 55.9 0 108.5-22 148.1-61.9C461.2 857.9 483 805 483 748.6V560.5c0-12.6-10.2-22.9-22.8-22.9zM898.6 599.4c-39.6-39.8-92.2-61.8-148.1-61.8H563.8c-12.5 0-22.7 10.3-22.7 22.9v188.1c0 56.3 21.8 109.3 61.3 149.1 39.6 39.9 92.2 61.9 148.1 61.9 55.2 0 107.3-21.5 146.7-60.5l1.2-1.1c39.7-40 61.5-93.1 61.5-149.4 0-56.3-21.8-109.3-61.3-149.2zM125.4 126.1C85.9 166 64.1 219 64.1 275.4c0 56.4 21.8 109.4 61.3 149.2l1.3 1.3 2.5 2.3c39 37.5 90.3 58.2 144.3 58.2h186.7c12.5 0 22.7-10.3 22.7-22.9V275.4c0-55.6-21.2-108-59.8-147.6l-1.6-1.8c-39.5-39.8-92.1-61.7-148-61.7-55.9 0-108.5 22-148.1 61.8z" p-id="4995"></path></svg>',
                    'more_1' => '<svg version="1.1" id="cate_back_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M490.8,661.4c0-33.6-13.2-65.2-37.1-89.2s-55.7-37.1-89.2-37.1H269c-33.6,0-65.2,13.2-89.2,37.1s-37.1,55.7-37.1,89.2v95.5c0,33.6,13.2,65.2,37.1,89.2c23.9,23.9,55.7,37.1,89.2,37.1h95.4c35.1,0,67.6-13.6,91.6-38.3c23.2-23.8,35.6-55.2,34.7-88.1L490.8,661.4L490.8,661.4z M432.6,755.2c0,37.6-30.6,68.2-68.2,68.2h-95.5c-37.6,0-68.2-30.6-68.2-68.2v-95.5c0-37.6,30.6-68.2,68.2-68.2h95.5c37.6,0,68.2,30.6,68.2,68.2V755.2z"/><path class="st0" d="M268.9,490.8h95.5c34.9,0,67.3-13.5,91.2-38.1c23.5-24.1,36-56.2,35.2-90.1v-95.5c0-33.6-13.2-65.2-37.1-89.2s-55.7-37.1-89.2-37.1H269c-33.6,0-65.2,13.2-89.2,37.1s-37.1,55.7-37.1,89.2v97.3c0,33.6,13.2,65.2,37.1,89.2C203.8,477.5,235.3,490.8,268.9,490.8z M200.8,267.2c0-37.6,30.6-68.2,68.2-68.2h95.5c37.6,0,68.2,30.6,68.2,68.2v95.5c0,37.6-30.6,68.2-68.2,68.2h-95.5c-37.6,0-68.2-30.6-68.2-68.2L200.8,267.2L200.8,267.2z"/><path class="st0" d="M836.9,573.3H588.5c-15.5,0-29.1,13.6-29.1,29.1c0,15.5,13.6,29.1,29.1,29.1h246.6c16.1,0,30.9-13.9,30.9-29.1C866,586.8,852.3,573.3,836.9,573.3z"/><path class="st0" d="M691,807.7H588.5c-15.5,0-29.1,13.6-29.1,29.1c0,15.5,13.6,29.1,29.1,29.1H691c15.5,0,29.1-13.6,29.1-29.1C720.1,821.2,706.5,807.7,691,807.7z"/><path class="st0" d="M793.4,689.5H588.5c-15.5,0-29.1,13.6-29.1,29.1s13.6,29.1,29.1,29.1h204.9c15.5,0,29.1-13.6,29.1-29.1C822.5,703.1,809,689.5,793.4,689.5z"/><path class="st0" d="M850.1,349.2h-0.6c-13.3,0-24.5,9.4-27.2,22.4c-5.3,26.3-15.8,63.6-65.4,59.3h-95.5c-37.6,0-68.2-30.5-68.2-68.2v-95.5c0-37.6,30.5-68.2,68.2-68.2h95.5c49.2,0,63.2,38.6,67,65c2.1,14.2,14.3,24.5,28.5,24.5l0,0c17.6,0,31.2-15.6,28.5-33c-3.6-23.8-12.6-55.5-34.8-77.6c-23.9-23.9-55.7-37.1-89.2-37.1h-95.5c-33.6,0-65.2,13.2-89.2,37.1c-23.9,23.9-37.1,55.7-37.1,89.2v97.3c0,33.6,13.2,65.2,37.1,89.2c23.9,23.9,55.7,37.1,89.2,37.1H757c84.4,4.6,112.9-56.1,119.9-107.1C879.5,365.8,868.1,349.2,850.1,349.2z"/></g></svg>',
                    'only_0' => '<svg version="1.1" id="cate_back_only_0" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M479.3,717.7c10.2,4.9,21.2,7.4,32.1,7.4c11,0,21.9-2.5,32.1-7.4l260.8-111.9c10.9-4.7,17.9-15.4,17.9-27.2V515L511.4,650.1L200.8,515.3v63.6c0,11.8,7.1,22.5,17.9,27.2L479.3,717.7z"/><path class="st0" d="M809.5,686L511.1,821.1L198.3,685.7c-15.7-6.8-33.8,0.4-40.6,16.1c-6.8,15.6,0.4,33.8,16.1,40.6l311.7,134.8c8.4,4,17.4,6,26.4,6c8.8,0,17.5-1.9,25.5-5.8L835,742.2c15.5-7.1,22.4-25.4,15.3-40.9C843.3,685.8,824.9,678.9,809.5,686z"/><path class="st0" d="M862.1,299.8L540.2,147.1c-17.8-8.4-39-8.4-56.8,0L161.1,299.8c-22.2,10.5-35.8,31.8-35.5,55.5c0.3,23.8,14.5,44.7,37.1,54.7L485,552c8.4,3.7,17.5,5.6,26.8,5.6c9.2,0,18.4-1.9,26.8-5.6l321.9-142c22.5-9.9,36.7-30.9,37.1-54.6C897.8,331.6,884.3,310.4,862.1,299.8z"/></g></svg>',
                    'only_1' => '<svg version="1.1" id="cate_back_only_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><g>	<path class="st0" d="M398,140.8H229.2c-50.6,0-88.6,38-88.6,84.4v168.7c0,46.4,38,84.4,84.4,84.4h168.7c46.4,0,84.4-38,84.4-84.4		V225.2C482.3,178.8,444.4,140.8,398,140.8z"/>	<path class="st0" d="M798.7,140.8H630c-46.4,0-84.4,38-84.4,84.4v168.7c0,46.4,38,84.4,84.4,84.4h168.7c46.4,0,84.4-38,84.4-84.4		V225.2C887.3,178.8,845.1,140.8,798.7,140.8z M836.7,389.7c0,25.3-21.1,42.2-42.2,42.2H642.6c-25.3,0-42.2-21.1-42.2-42.2V233.6		c0-25.3,21.1-42.2,42.2-42.2h151.9c25.3,0,42.2,21.1,42.2,42.2V389.7z"/>	<path class="st0" d="M398,545.7H229.2c-46.4,0-84.4,38-84.4,84.4v168.7c0,46.4,38,84.4,84.4,84.4H398c46.4,0,84.4-38,84.4-84.4		V630.1C482.3,583.7,444.4,545.7,398,545.7z"/>	<path class="st0" d="M798.7,545.7H630c-46.4,0-84.4,38-84.4,84.4v168.7c0,46.4,38,84.4,84.4,84.4h168.7c46.4,0,84.4-38,84.4-84.4		V630.1C887.3,583.7,845.1,545.7,798.7,545.7z"/></g></svg>',
                    'only_2' => '<svg version="1.1" id="cate_back_only_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><path class="st0" d="M883.8,631.6c1-8-1.6-15.8-7.3-22c-11.8-12.9-33.5-15.1-48.3-4.8l-316,219.4l-1.9-1.3L186.9,600.5	c-6.1-4.2-13.5-6.4-21.3-6.4c-10.6,0-20.5,4.1-27,11.4c-5.7,6.3-8.2,14.1-7.2,22.1c1.1,8,5.6,15.1,12.8,20L463,866.8l0.2,0.2	c13.3,10.5,30.6,16.2,48.8,16.2c18.2,0,35.6-5.8,49-16.3l0.4-0.3L871,651.7C878.2,646.7,882.7,639.6,883.8,631.6z"/><path class="st0" d="M870.1,371.1l-1.4-1.2L561,157.2l-0.2-0.2c-13.3-10.5-30.6-16.2-48.8-16.2c-18.2,0-35.5,5.8-48.8,16.2l-0.4,0.3	L155.3,369.9l-1.4,1.2c-14.7,12.8-22.8,29.9-22.8,48.1c0,18.2,8.1,35.3,22.8,48.1l1.4,1.2L463,681.3l0.2,0.2	c13.3,10.5,30.6,16.2,48.8,16.2c18.2,0,35.5-5.8,48.8-16.2l0.4-0.3l307.6-212.7l1.4-1.2c14.7-12.8,22.8-29.9,22.8-48.1	C892.9,401,884.8,384,870.1,371.1z M568,451.1H456.1c-20.1-0.1-36.5-14.4-36.6-31.7c0.1-17.5,16.5-31.9,36.5-32h111.9	c9.6,0,19,3.4,25.8,9.3c6.9,6,10.7,14,10.7,22.5C604.4,436.7,588,451,568,451.1z"/></svg>',
                ],
            ],
            'shop' => [
                'front' => [
                    'more_0' => '<svg t="1554701385813" id="shop_front_more_0" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5873" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M829.5 194.8c-39-39-87-71.3-142.7-96.1-54.9-23.1-113.6-34.9-174.5-34.9-60.8 0-119.6 11.7-174.6 34.9l-0.2 0.1c-54.3 21.1-103.4 54.2-142.2 95.8l-0.2 0.2c-41.8 41.8-73.2 88.5-96 142.6-23.2 55-34.9 113.8-34.9 174.6s11.7 119.6 34.9 174.6c24.7 55.6 57 103.6 96 142.6s87 71.3 142.7 96.1c54.9 23.1 113.6 34.9 174.5 34.9 60.8 0 119.6-11.7 174.6-34.9 55.6-24.7 103.6-57 142.6-96s71.3-87 96.1-142.7c23.1-54.9 34.9-113.6 34.9-174.5 0-60.8-11.7-119.5-34.9-174.5-24.8-55.8-57.2-103.8-96.1-142.8z m-30.2 603.6C723.1 875.5 621.2 918 512.3 918s-211.6-42.4-289.1-119.5c-37.6-37.4-67.1-80.8-87.7-129.1-21.4-50-32.2-102.9-32.2-157.4 0-50.4 10.7-104.9 30.1-153.3 29.9-73.4 80.9-136.2 147.4-181.6 68-46.5 147-71 228.6-71H539c5 0 9.8 0.8 14.5 1.6 4.3 0.7 8.3 1.4 12.1 1.4h6.7c27.4 3.1 54.4 12.1 77.3 20.6h3l1.2 0.4c44.6 16.7 86.8 42.3 129.2 78.1l0.2 0.2c33.8 31 64.2 71.4 90.1 120.1l0.8 1.6v1.4c0.2 0.4 0.6 1 0.9 1.3l1.2 1.2 0.5 1.5c1.3 4 3.4 8 5.5 12.4 2.2 4.4 4.5 9 6.1 13.8 1.5 3 2.3 5.9 2.9 8.3 0.4 1.5 1 3.7 1.5 4.2l0.8 0.8 0.5 1c1.9 3.8 6.3 13.7 6.6 25.2 1.4 3.4 2.1 7 2.8 10.5 0.6 3.2 1.3 6.5 2.4 8.6l0.7 1.5v1.7c0 2.1 0.6 4.6 1.3 7.2 0.8 3.2 1.7 6.8 1.7 10.6 0 3.9 0.7 7.9 1.4 12.1 0.8 4.6 1.6 9.4 1.6 14.5v3c0 5.5 0.7 12.1 1.4 18.4 0.7 6.7 1.5 13.7 1.5 20 1.4 108.9-39.8 211.7-116.1 289.1z" p-id="5874"></path><path d="M759.4 251.1c-6.4-6.2-16.8-7.1-26.3-2.5l-307.8 153c-5.8 3-10.6 7.7-13.7 13.4L256.4 716c-4.7 9.3-3.6 19.4 2.8 25.5 6.3 6.2 16.6 7.2 26 2.6L594 591.9c5.9-2.9 10.8-7.7 13.7-13.5l154.6-301.8c4.6-9.3 3.5-19.4-2.9-25.5zM509.3 548.2c-28.6 0-51.8-23.2-51.8-51.8s23.2-51.8 51.8-51.8 51.8 23.2 51.8 51.8-23.1 51.8-51.8 51.8z" p-id="5875"></path></svg>',
                    'more_1' => '<svg version="1.1" id="shop_front_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M387.7,450.7c0,68.6,55.7,124.3,124.3,124.3s124.3-55.7,124.3-124.3c0-68.6-55.7-124.3-124.3-124.3S387.7,382,387.7,450.7L387.7,450.7z M573.3,450.7c0,33.8-27.5,61.4-61.3,61.4s-61.3-27.5-61.3-61.4c0-33.8,27.5-61.3,61.3-61.3S573.3,416.7,573.3,450.7L573.3,450.7z M573.3,450.7"/><path class="st0" d="M780.5,299.2c-0.2-0.3-0.3-0.6-0.5-0.8c0-0.2-0.2-0.2-0.2-0.3c-5.5-9.4-15.6-15.7-27.2-15.7c-17.3,0-31.5,14.2-31.5,31.5c0,5.2,1.3,10.1,3.5,14.3c19.8,35.2,31.1,76,31.1,119.4c0,57.1-19.7,109.5-52.4,151c-1.3,1.6-2.7,3.3-3.9,4.9l-14.8,16.2l-47.5,52.1L512,807.6L386.8,671.7l-47.5-52.2l-14.8-16.2c-1.3-1.6-2.7-3.3-3.9-4.9c-32.9-41.5-52.4-93.9-52.4-151c0-134.7,109.2-243.8,243.8-243.8c48.8,0,94.1,14.3,132.1,38.9c5,3.3,10.9,5.2,17.3,5.2c17.3,0,31.5-14.2,31.5-31.5c0-11.5-6.1-21.6-15.4-27.1C629.8,158.6,573,140.8,512,140.8c-169.4,0-306.8,137.3-306.8,306.8c0,82.4,32.6,157.3,85.4,212.4c0.6,0.8,1.1,1.4,1.7,2L487,873.1c6.6,7.2,15.9,10.5,25,10.1c9.1,0.5,18.4-2.8,25-10.1L731.6,662c0.6-0.6,1.3-1.4,1.7-2c52.9-55.1,85.4-129.9,85.4-212.4C818.8,393.8,804.9,343.3,780.5,299.2L780.5,299.2z M780.5,299.2"/></g></svg>',
                    'only_0' => '',
                    'only_1' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 500 500"><linearGradient id="a" gradientUnits="userSpaceOnUse" x1="157.353" y1="106.928" x2="232.898" y2="106.928"><stop offset="0" stop-color="#f29550"/><stop offset="1" stop-color="#f2c733"/></linearGradient><path d="M189 67c-2.3-3.9-6.1-6.6-10.5-7.6-4.6-1-9.4-.1-13.1 2.4-3.9 2.3-6.6 6.1-7.6 10.5-1 4.6-.1 9.4 2.4 13.3l41.1 61.7c3.1 4.6 8.7 7.6 14.3 7.6 3.5 0 6.8-.9 9.2-2.5 3.9-2.3 6.6-6.1 7.6-10.5 1-4.6.1-9.4-2.4-13.3L189 67z" fill="url(#a)"/><linearGradient id="b" gradientUnits="userSpaceOnUse" x1="267.236" y1="107.001" x2="342.456" y2="107.001"><stop offset="0" stop-color="#f29550"/><stop offset="1" stop-color="#f2c733"/></linearGradient><path d="M334.7 61.9c-7.6-5.1-18.5-2.8-23.7 5l-41.1 61.7c-2.4 3.6-3.3 8.3-2.3 12.9 1 4.4 3.6 8.4 7.3 10.8 2.5 1.7 5.8 2.6 9.3 2.6 5.6 0 11.3-3 14.3-7.6l41.1-61.7c5.2-7.7 2.9-18.5-4.9-23.7z" fill="url(#b)"/><linearGradient id="c" gradientUnits="userSpaceOnUse" x1="64.853" y1="281.898" x2="435.1" y2="281.898"><stop offset="0" stop-color="#f25b50"/><stop offset="1" stop-color="#f27f50"/></linearGradient><path d="M369.7 124.2H130.2c-36 0-65.4 29.4-65.4 65.4v184.7c0 36 29.4 65.4 65.4 65.4h239.5c35.9 0 65.4-29.4 65.4-65.4V189.6c0-36-29.4-65.4-65.4-65.4z" fill="url(#c)"/><g><image width="169" height="170" xlink:href="A36367D4.png" transform="translate(174 200)" overflow="visible" opacity=".5"/><path d="M326.4 277.3c-1.8-6.1-6.1-11-12.3-14.1l-89-44.8c-3.7-1.8-7.4-2.5-11-2.5-13.5 0-24.5 11-24.5 24.5V330c0 3.7.6 7.4 2.5 11 3.1 6.1 8 10.4 14.1 12.3 2.5.6 5.5 1.2 8 1.2 3.7 0 7.4-1.2 11-2.5l89-44.8c4.9-2.5 8.6-6.1 11-11 3-6 3-12.7 1.2-18.9z" fill="#fff"/></g></svg>',
                ],
                'back' => [
                    'more_0' => '<svg id="shop_back_more_0" t="1554701379440" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5726" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M925.5 337.5c-24.7-55.7-57.1-103.7-96-142.6-39-39-87-71.3-142.7-96.1-54.9-23.1-113.6-34.9-174.5-34.9-60.8 0-119.6 11.7-174.6 34.9l-0.2 0.1c-54.3 21.1-103.4 54.2-142.2 95.8l-0.2 0.2c-41.8 41.8-73.2 88.5-96 142.6-23.2 55-34.9 113.8-34.9 174.6s11.7 119.6 34.9 174.6c24.7 55.6 57 103.6 96 142.6s87 71.3 142.7 96.1c54.9 23.1 113.6 34.9 174.5 34.9 60.8 0 119.6-11.7 174.6-34.9 55.6-24.7 103.6-57 142.6-96s71.3-87 96.1-142.7c23.1-54.9 34.9-113.6 34.9-174.5-0.1-61-11.8-119.7-35-174.7z m-163.3-60.9L607.6 578.4c-2.9 5.8-7.8 10.6-13.7 13.5L285.3 744.1c-9.4 4.6-19.7 3.6-26-2.6-6.4-6.2-7.5-16.3-2.8-25.5l155.2-301c3-5.6 7.9-10.4 13.7-13.4l307.8-153c9.5-4.7 19.9-3.7 26.3 2.5 6.3 6.1 7.4 16.2 2.7 25.5z" p-id="5727"></path><path d="M509.3 496.4m-51.8 0a51.8 51.8 0 1 0 103.6 0 51.8 51.8 0 1 0-103.6 0Z" p-id="5728"></path></svg>',
                    'more_1' => '<svg version="1.1" id="shop_back_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M387.7,450.7c0,68.6,55.7,124.3,124.3,124.3s124.3-55.7,124.3-124.3c0-68.6-55.7-124.3-124.3-124.3S387.7,382,387.7,450.7L387.7,450.7z M573.3,450.7c0,33.8-27.5,61.4-61.3,61.4s-61.3-27.5-61.3-61.4c0-33.8,27.5-61.3,61.3-61.3S573.3,416.7,573.3,450.7L573.3,450.7z M573.3,450.7"/><path class="st0" d="M780.5,299.2c-0.2-0.3-0.3-0.6-0.5-0.8c0-0.2-0.2-0.2-0.2-0.3c-5.5-9.4-15.6-15.7-27.2-15.7c-17.3,0-31.5,14.2-31.5,31.5c0,5.2,1.3,10.1,3.5,14.3c19.8,35.2,31.1,76,31.1,119.4c0,57.1-19.7,109.5-52.4,151c-1.3,1.6-2.7,3.3-3.9,4.9l-14.8,16.2l-47.5,52.1L512,807.6L386.8,671.7l-47.5-52.2l-14.8-16.2c-1.3-1.6-2.7-3.3-3.9-4.9c-32.9-41.5-52.4-93.9-52.4-151c0-134.7,109.2-243.8,243.8-243.8c48.8,0,94.1,14.3,132.1,38.9c5,3.3,10.9,5.2,17.3,5.2c17.3,0,31.5-14.2,31.5-31.5c0-11.5-6.1-21.6-15.4-27.1C629.8,158.6,573,140.8,512,140.8c-169.4,0-306.8,137.3-306.8,306.8c0,82.4,32.6,157.3,85.4,212.4c0.6,0.8,1.1,1.4,1.7,2L487,873.1c6.6,7.2,15.9,10.5,25,10.1c9.1,0.5,18.4-2.8,25-10.1L731.6,662c0.6-0.6,1.3-1.4,1.7-2c52.9-55.1,85.4-129.9,85.4-212.4C818.8,393.8,804.9,343.3,780.5,299.2L780.5,299.2z M780.5,299.2"/></g></svg>',
                    'only_0' => '',
                    'only_1' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 500 500"><linearGradient id="a" gradientUnits="userSpaceOnUse" x1="157.353" y1="106.928" x2="232.898" y2="106.928"><stop offset="0" stop-color="#f29550"/><stop offset="1" stop-color="#f2c733"/></linearGradient><path d="M189 67c-2.3-3.9-6.1-6.6-10.5-7.6-4.6-1-9.4-.1-13.1 2.4-3.9 2.3-6.6 6.1-7.6 10.5-1 4.6-.1 9.4 2.4 13.3l41.1 61.7c3.1 4.6 8.7 7.6 14.3 7.6 3.5 0 6.8-.9 9.2-2.5 3.9-2.3 6.6-6.1 7.6-10.5 1-4.6.1-9.4-2.4-13.3L189 67z" fill="url(#a)"/><linearGradient id="b" gradientUnits="userSpaceOnUse" x1="267.236" y1="107.001" x2="342.456" y2="107.001"><stop offset="0" stop-color="#f29550"/><stop offset="1" stop-color="#f2c733"/></linearGradient><path d="M334.7 61.9c-7.6-5.1-18.5-2.8-23.7 5l-41.1 61.7c-2.4 3.6-3.3 8.3-2.3 12.9 1 4.4 3.6 8.4 7.3 10.8 2.5 1.7 5.8 2.6 9.3 2.6 5.6 0 11.3-3 14.3-7.6l41.1-61.7c5.2-7.7 2.9-18.5-4.9-23.7z" fill="url(#b)"/><linearGradient id="c" gradientUnits="userSpaceOnUse" x1="64.853" y1="281.898" x2="435.1" y2="281.898"><stop offset="0" stop-color="#f25b50"/><stop offset="1" stop-color="#f27f50"/></linearGradient><path d="M369.7 124.2H130.2c-36 0-65.4 29.4-65.4 65.4v184.7c0 36 29.4 65.4 65.4 65.4h239.5c35.9 0 65.4-29.4 65.4-65.4V189.6c0-36-29.4-65.4-65.4-65.4z" fill="url(#c)"/><g><image width="169" height="170" xlink:href="A36367D4.png" transform="translate(174 200)" overflow="visible" opacity=".5"/><path d="M326.4 277.3c-1.8-6.1-6.1-11-12.3-14.1l-89-44.8c-3.7-1.8-7.4-2.5-11-2.5-13.5 0-24.5 11-24.5 24.5V330c0 3.7.6 7.4 2.5 11 3.1 6.1 8 10.4 14.1 12.3 2.5.6 5.5 1.2 8 1.2 3.7 0 7.4-1.2 11-2.5l89-44.8c4.9-2.5 8.6-6.1 11-11 3-6 3-12.7 1.2-18.9z" fill="#fff"/></g></svg>',
                ],
            ],
            'cart' => [
                'front' => [
                    'more_0' => '<svg t="1554701329633" id="cart_front_more_0" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5140" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M328.4 784.7c-88.6 0-160.7-72.1-160.7-160.7 0 0 0.1-272.4 0.1-374.9 0-35.6-8.6-61.8-25.6-78-21.3-20.3-50.6-19.2-50.7-19.2-14.4 0.9-26.7-9.9-27.7-24.2-1-14.3 9.9-26.7 24.2-27.7 2.1-0.2 51.1-3 89.2 32.7 28.2 26.4 42.5 65.6 42.5 116.4 0 102.5-0.1 374.9-0.1 374.9 0 59.9 48.8 108.7 108.7 108.7H786c59.9 0 108.7-48.8 108.7-108.7V398.2c0-59.9-48.8-108.7-108.7-108.7H368.9c-14.4 0-26-11.6-26-26s11.7-26 26-26H786c88.6 0 160.7 72.1 160.7 160.7V624c0 88.6-72.1 160.7-160.7 160.7H328.4z m0 0" p-id="5141"></path><path d="M368.3 454.1c-14.4 0-26-11.6-26-26s11.6-26 26-26h377c14.4 0 26 11.6 26 26s-11.6 26-26 26h-377z m0 0M368.3 618.8c-14.4 0-26-11.6-26-26s11.6-26 26-26h377c14.4 0 26 11.6 26 26s-11.6 26-26 26h-377z m0 0M246.7 872.1c0-18.6-9.9-35.8-26-45.1-16.1-9.3-35.9-9.3-52 0s-26 26.5-26 45.1 9.9 35.8 26 45.1c16.1 9.3 35.9 9.3 52 0s26-26.5 26-45.1z m0 0M960.1 872.1c0-18.6-9.9-35.8-26-45.1-16.1-9.3-35.9-9.3-52 0s-26 26.5-26 45.1c0 28.7 23.3 52 52 52s52-23.3 52-52z m0 0" p-id="5142"></path></svg>',
                    'more_1' => '<svg version="1.1" id="cart_front_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><g><path class="st0" d="M890.2,282.7c-7-8.6-17.5-12.1-28.7-12.1H503.7c-20.4,0-36.9,12.1-36.9,32.5c0,20.4,16.5,32.5,36.9,32.5h320.4l-62.4,308H389.2L371.8,522h260.6c20.4,0,36.9-16.2,36.9-36.6c0-20.4-16.5-36.6-36.9-36.6h-271l-39.8-278.1c-2.6-18.2-18.2-29.9-36.5-29.9H162.5c-20.4,0-36.9,16.2-36.9,36.6c0,20.4,16.5,36.6,36.9,36.6h98.8l67.6,464.6c2.6,18.1,18.2,29.8,36.5,29.8h418.1c17.4,0,32.5-10.9,36.1-27.9l78-367.6C899.9,302,897.2,291.4,890.2,282.7L890.2,282.7z M356.1,754.1c-35.7,0-64.6,28.9-64.6,64.6c0,35.7,28.9,64.6,64.6,64.6c35.6,0,64.6-28.9,64.6-64.6C420.7,783,391.8,754.1,356.1,754.1L356.1,754.1z M734.3,754.1c-35.7,0-64.6,28.9-64.6,64.6c0,35.7,28.9,64.6,64.6,64.6c35.6,0,64.6-28.9,64.6-64.6C798.9,783,769.9,754.1,734.3,754.1L734.3,754.1z M734.3,754.1"/></g></g></svg>',
                    'only_0' => '<svg version="1.1" id="cart_front_only_0" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;stroke:#7f7f7f;stroke-width:6;stroke-miterlimit:10;}.st1{fill:#7f7f7f;}</style><g><g><defs><rect id="SVGID_1_" x="200.8" y="8.8" width="742.5" height="742.5"/></defs><clipPath id="SVGID_2_"><use xlink:href="#SVGID_1_"  style="overflow:visible;"/></clipPath></g></g><path class="st0" d="M369.8,777c-29,0-52.5,23.7-52.5,53c0,29.3,23.5,53,52.5,53c29,0,52.5-23.7,52.5-53C422.3,800.7,398.8,777,369.8,777 M737.3,777c-29,0-52.5,23.7-52.5,53c0,29.3,23.5,53,52.5,53c29,0,52.5-23.7,52.5-53C789.8,800.7,766.3,777,737.3,777 M789.4,750.5H363c-37.8,0-71.1-30.2-75.7-68.8l-44.5-316.6l-25.3-148c-1.6-12.9-13.2-23.5-24.6-23.5h-33.4c-14.5,0-26.3-11.9-26.3-26.5c0-14.6,11.8-26.5,26.3-26.5h33.4c38.3,0,71.9,30.2,76.6,68.8l25.3,147.4l44.7,318c1.5,12.3,12.5,22.7,23.6,22.7h426.4c14.5,0,26.3,11.9,26.3,26.5C815.7,738.6,803.9,750.5,789.4,750.5 M395.8,644.4c-13.6,0-25.2-10.6-26.2-24.6c-1.1-14.6,9.8-27.3,24.2-28.4L750,565c13.1-0.1,24.1-10.4,25.5-22.2l41.3-239c1.1-8.9-1.4-18.7-6.7-24.7c-3.4-3.9-7.6-5.8-12.6-5.8H343.1c-14.5,0-26.3-11.9-26.3-26.5c0-14.6,11.8-26.5,26.3-26.5h454.5c20.1,0,38.5,8.4,51.9,23.7c15.3,17.5,22.4,42.1,19.3,67.6l-41.4,239c-4.5,37.2-37.7,67.4-75.5,67.4l-354.2,26.4C397.1,644.4,396.4,644.4,395.8,644.4"/><path class="st1" d="M684.8,435.1v6c0,10.9-9,19.8-20,19.8H446.7c-11,0-20-8.9-20-19.8v-6"/><rect x="426.7" y="407" class="st1" width="258.2" height="29.9"/></svg>',
                    'only_1' => '<svg version="1.1" id="cart_front_only_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:url(#SVGID_1_);}	.st1{fill:url(#SVGID_2_);}</style><g>	<linearGradient id="SVGID_1_" gradientUnits="userSpaceOnUse" x1="140.1357" y1="512" x2="828.3669" y2="512">		<stop  offset="0" style="stop-color:#E2E2E2"/>		<stop  offset="1" style="stop-color:#D6D6D6"/>	</linearGradient>	<path class="st0" d="M379,777.7c-29.2,0-52.8,23.5-52.8,52.8c0,29.2,23.5,52.8,52.8,52.8c29.2,0,52.8-23.5,52.8-52.8		C431.8,801.2,408.3,777.7,379,777.7L379,777.7z M749.9,777.7c-29.2,0-52.8,23.5-52.8,52.8c0,29.2,23.5,52.8,52.8,52.8		c29.2,0,52.8-23.5,52.8-52.8C803.4,801.2,779.1,777.7,749.9,777.7L749.9,777.7z M802.7,751.3H371.9c-38.5,0-72-30-76.3-69.2		l-44.9-316.6L225,217.1c-1.4-12.8-13.6-23.5-25-23.5h-33.5c-15,0-26.4-12.1-26.4-26.4c0-15,12.1-26.4,26.4-26.4H200		c38.5,0,72.7,30,77,69.2l25.7,147.6l44.9,318.1c1.4,12.1,12.8,22.8,23.5,22.8H802c15,0,26.4,12.1,26.4,26.4		C829.1,739.1,817.6,751.3,802.7,751.3L802.7,751.3z M802.7,751.3"/>	<linearGradient id="SVGID_2_" gradientUnits="userSpaceOnUse" x1="325.5578" y1="432.4833" x2="883.8643" y2="432.4833">		<stop  offset="0" style="stop-color:#E2E2E2"/>		<stop  offset="1" style="stop-color:#D6D6D6"/>	</linearGradient>	<path class="st1" d="M863.3,244.2c-13.5-15-32.1-23.5-52.8-23.5H351.9c-15,0-26.4,12.1-26.4,26.4l52.8,373		c0.7,14.3,12.8,24.3,26.4,24.3h2.1l358-26.4c38.5,0,72-30,76.3-67.7l42.1-238.9C886.1,286.3,879,262,863.3,244.2L863.3,244.2z		 M654.3,366.2l-20.7,130.5c-2.1,12.8-14.3,21.4-27.1,20c-12.8-2.1-21.4-14.3-20-27.1L607.3,359c2.1-12.8,14.3-21.4,27.1-20		C647.9,341.2,656.5,353.3,654.3,366.2L654.3,366.2z M766.3,366.2l-20.7,130.5c-2.1,12.8-14.3,21.4-27.1,20		c-12.8-2.1-21.4-14.3-20-27.1L719.2,359c2.1-12.8,14.3-21.4,27.1-20C759.2,341.2,768.4,353.3,766.3,366.2L766.3,366.2z		 M766.3,366.2"/></g></svg>',
                    'only_2' => '<svg version="1.1" id="cart_front_only_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><g>	<path class="st0" d="M905.3,310.9c-13.1-15.1-31.9-24-51.8-24.5H329.8c-7.2,0-14.3,1.1-21,3.2l-10.5-87		c-4.8-35.2-34.7-61.6-70.3-61.9h-97.6c-16.6,0-30.1,13.5-30.1,30.1c0,16.6,13.5,30.1,30.1,30.1H228c5.3,0.2,9.8,4.2,10.6,9.4		l36,297.7c0,0.2,0.1,0.5,0.1,0.7L291,658.6c3.8,36.2,34.1,63.8,70.5,64.1h459.2c36.5-0.1,67-27.8,70.7-64.1l31.9-291.5		C925.6,346.7,919,326.3,905.3,310.9z M831.6,652.4c-0.4,5.7-5.1,10.2-10.8,10.2H361.9c-5.6-0.2-10.2-4.6-10.6-10.2L319,359.7		c-0.4-3.4,0.7-6.9,3-9.4c2-2.3,4.8-3.6,7.8-3.6h522.9c3,0,5.9,1.3,7.8,3.6c2.2,2.6,3.3,6,3,9.4L831.6,652.4z"/>	<path class="st0" d="M465.2,814.1c-7.8-18.7-25.9-30.9-46.2-30.9c-27.6,0-50,22.4-50,50c0,20.3,12.1,38.4,30.9,46.2		c6.2,2.6,12.7,3.8,19.2,3.8c13,0,25.7-5.1,35.3-14.7C468.7,854.2,472.9,832.8,465.2,814.1z"/>	<path class="st0" d="M806.6,814.1c-7.8-18.7-25.9-30.9-46.2-30.9c-27.6,0-50,22.4-50,50c0,20.3,12.1,38.4,30.9,46.2		c6.2,2.6,12.7,3.8,19.2,3.8c13,0,25.7-5.1,35.3-14.7C810.2,854.2,814.4,832.8,806.6,814.1z"/>	<path class="st0" d="M760.5,732.8"/>	<path class="st0" d="M516.4,437.9h-94.8c-18.6,0.1-33.8,15.3-33.9,34c0,9,3.5,17.5,9.9,23.9c6.3,6.3,15.1,9.9,24,9.9h94.9		c18.6-0.1,33.8-15.4,33.9-34C550.3,453.2,535,438,516.4,437.9z"/></g></svg>',
                ],
                'back' => [
                    'more_0' => '<svg t="1554701336639" id="cart_back_more_0" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5287" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M786 237.5H219.6c-2-45.3-16.2-80.5-42.2-104.9C139.3 97 90.3 99.8 88.2 100 73.9 101 63 113.3 64 127.7c0.9 14.3 13.3 25.2 27.7 24.2 0.1 0 29.3-1.1 50.7 19.2 17 16.2 25.6 42.4 25.6 78 0 102.5-0.1 374.9-0.1 374.9 0 88.6 72.1 160.7 160.7 160.7H786c88.6 0 160.7-72.1 160.7-160.7V398.2c0-88.6-72.1-160.7-160.7-160.7z m-40.7 381.3h-377c-14.4 0-26-11.6-26-26s11.6-26 26-26h377c14.4 0 26 11.6 26 26s-11.6 26-26 26z m0-164.7h-377c-14.4 0-26-11.6-26-26s11.6-26 26-26h377c14.4 0 26 11.6 26 26s-11.6 26-26 26zM246.7 872.1c0-18.6-9.9-35.8-26-45.1-16.1-9.3-35.9-9.3-52 0s-26 26.5-26 45.1 9.9 35.8 26 45.1c16.1 9.3 35.9 9.3 52 0s26-26.5 26-45.1z m0 0M960.1 872.1c0-18.6-9.9-35.8-26-45.1-16.1-9.3-35.9-9.3-52 0s-26 26.5-26 45.1c0 28.7 23.3 52 52 52s52-23.3 52-52z m0 0" p-id="5288"></path></svg>',
                    'more_1' => '<svg version="1.1" id="cart_back_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><g><path class="st0" d="M890.2,282.7c-7-8.6-17.5-12.1-28.7-12.1H503.7c-20.4,0-36.9,12.1-36.9,32.5c0,20.4,16.5,32.5,36.9,32.5h320.4l-62.4,308H389.2L371.8,522h260.6c20.4,0,36.9-16.2,36.9-36.6c0-20.4-16.5-36.6-36.9-36.6h-271l-39.8-278.1c-2.6-18.2-18.2-29.9-36.5-29.9H162.5c-20.4,0-36.9,16.2-36.9,36.6c0,20.4,16.5,36.6,36.9,36.6h98.8l67.6,464.6c2.6,18.1,18.2,29.8,36.5,29.8h418.1c17.4,0,32.5-10.9,36.1-27.9l78-367.6C899.9,302,897.2,291.4,890.2,282.7L890.2,282.7z M356.1,754.1c-35.7,0-64.6,28.9-64.6,64.6c0,35.7,28.9,64.6,64.6,64.6c35.6,0,64.6-28.9,64.6-64.6C420.7,783,391.8,754.1,356.1,754.1L356.1,754.1z M734.3,754.1c-35.7,0-64.6,28.9-64.6,64.6c0,35.7,28.9,64.6,64.6,64.6c35.6,0,64.6-28.9,64.6-64.6C798.9,783,769.9,754.1,734.3,754.1L734.3,754.1z M734.3,754.1"/></g></g></svg>',
                    'only_0' => '<svg version="1.1" id="cart_back_only_0" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;stroke:#7f7f7f;stroke-width:6;stroke-miterlimit:10;}.st1{fill:#7f7f7f;}</style><g><g><defs><rect id="SVGID_1_" x="200.8" y="8.8" width="742.5" height="742.5"/></defs><clipPath id="SVGID_2_"><use xlink:href="#SVGID_1_"  style="overflow:visible;"/></clipPath></g></g><path class="st0" d="M369.8,777c-29,0-52.5,23.7-52.5,53c0,29.3,23.5,53,52.5,53c29,0,52.5-23.7,52.5-53C422.3,800.7,398.8,777,369.8,777 M737.3,777c-29,0-52.5,23.7-52.5,53c0,29.3,23.5,53,52.5,53c29,0,52.5-23.7,52.5-53C789.8,800.7,766.3,777,737.3,777 M789.4,750.5H363c-37.8,0-71.1-30.2-75.7-68.8l-44.5-316.6l-25.3-148c-1.6-12.9-13.2-23.5-24.6-23.5h-33.4c-14.5,0-26.3-11.9-26.3-26.5c0-14.6,11.8-26.5,26.3-26.5h33.4c38.3,0,71.9,30.2,76.6,68.8l25.3,147.4l44.7,318c1.5,12.3,12.5,22.7,23.6,22.7h426.4c14.5,0,26.3,11.9,26.3,26.5C815.7,738.6,803.9,750.5,789.4,750.5M395.8,644.4c-13.6,0-25.2-10.6-26.2-24.6c-1.1-14.6,9.8-27.3,24.2-28.4L750,565c13.1-0.1,24.1-10.4,25.5-22.2l41.3-239c1.1-8.9-1.4-18.7-6.7-24.7c-3.4-3.9-7.6-5.8-12.6-5.8H343.1c-14.5,0-26.3-11.9-26.3-26.5c0-14.6,11.8-26.5,26.3-26.5h454.5c20.1,0,38.5,8.4,51.9,23.7c15.3,17.5,22.4,42.1,19.3,67.6l-41.4,239c-4.5,37.2-37.7,67.4-75.5,67.4l-354.2,26.4C397.1,644.4,396.4,644.4,395.8,644.4"/><path class="st1" d="M684.8,435.1v6c0,10.9-9,19.8-20,19.8H446.7c-11,0-20-8.9-20-19.8v-6"/><rect x="426.7" y="407" class="st1" width="258.2" height="29.9"/><rect x="262" y="217.2" class="st1" width="216" height="55.8"/><polygon class="st1" points="452.3,643 326.3,653 255,273 377,245.1 815.7,258 840,355 789.8,556 732,600 "/></svg>',
                    'only_1' => '<svg version="1.1" id="cart_back_only_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><g>	<path class="st0" d="M379,777.7c-29.2,0-52.8,23.5-52.8,52.8c0,29.2,23.5,52.8,52.8,52.8c29.2,0,52.8-23.5,52.8-52.8		C431.8,801.2,408.3,777.7,379,777.7L379,777.7z M749.9,777.7c-29.2,0-52.8,23.5-52.8,52.8c0,29.2,23.5,52.8,52.8,52.8		c29.2,0,52.8-23.5,52.8-52.8C803.4,801.2,779.1,777.7,749.9,777.7L749.9,777.7z M802.7,751.3H371.9c-38.5,0-72-30-76.3-69.2		l-44.9-316.6L225,217.1c-1.4-12.8-13.6-23.5-25-23.5h-33.5c-15,0-26.4-12.1-26.4-26.4c0-15,12.1-26.4,26.4-26.4H200		c38.5,0,72.7,30,77,69.2l25.7,147.6l44.9,318.1c1.4,12.1,12.8,22.8,23.5,22.8H802c15,0,26.4,12.1,26.4,26.4		C829.1,739.1,817.6,751.3,802.7,751.3L802.7,751.3z M802.7,751.3"/>	<path class="st0" d="M863.3,244.2c-13.5-15-32.1-23.5-52.8-23.5H351.9c-15,0-26.4,12.1-26.4,26.4l52.8,373		c0.7,14.3,12.8,24.3,26.4,24.3h2.1l358-26.4c38.5,0,72-30,76.3-67.7l42.1-238.9C886.1,286.3,879,262,863.3,244.2L863.3,244.2z		 M654.3,366.2l-20.7,130.5c-2.1,12.8-14.3,21.4-27.1,20c-12.8-2.1-21.4-14.3-20-27.1L607.3,359c2.1-12.8,14.3-21.4,27.1-20		C647.9,341.2,656.5,353.3,654.3,366.2L654.3,366.2z M766.3,366.2l-20.7,130.5c-2.1,12.8-14.3,21.4-27.1,20		c-12.8-2.1-21.4-14.3-20-27.1L719.2,359c2.1-12.8,14.3-21.4,27.1-20C759.2,341.2,768.4,353.3,766.3,366.2L766.3,366.2z		 M766.3,366.2"/></g></svg>',
                    'only_2' => '<svg version="1.1" id="cart_back_only_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><path class="st0" d="M465.2,814.1c-7.8-18.7-25.9-30.9-46.2-30.9c-27.6,0-50,22.4-50,50c0,20.3,12.1,38.4,30.9,46.2	c6.2,2.6,12.7,3.8,19.2,3.8c13,0,25.7-5.1,35.3-14.7C468.7,854.2,472.9,832.8,465.2,814.1z"/><path class="st0" d="M806.6,814.1c-7.8-18.7-25.9-30.9-46.2-30.9c-27.6,0-50,22.4-50,50c0,20.3,12.1,38.4,30.9,46.2	c6.2,2.6,12.7,3.8,19.2,3.8c13,0,25.7-5.1,35.3-14.7C810.2,854.2,814.4,832.8,806.6,814.1z"/><path class="st0" d="M760.5,732.8"/><path class="st0" d="M905.3,310.9c-13.1-15.1-31.9-24-51.8-24.5H329.8c-7.2,0-14.3,1.1-21,3.2l-10.5-87	c-4.8-35.2-34.7-61.6-70.3-61.9h-97.6c-16.6,0-30.1,13.5-30.1,30.1c0,16.6,13.5,30.1,30.1,30.1H228c5.3,0.2,9.8,4.2,10.6,9.4	l36,297.7c0,0.2,0.1,0.5,0.1,0.7L291,658.6c3.8,36.2,34.1,63.8,70.5,64.1h459.2c36.5-0.1,67-27.8,70.7-64.1l31.9-291.5	C925.6,346.7,919,326.3,905.3,310.9z M516.4,505.7h-94.9c-8.9,0-17.7-3.6-24-9.9c-6.4-6.4-9.9-14.9-9.9-23.9	c0.1-18.6,15.3-33.9,33.9-34h94.8c18.6,0.1,33.9,15.3,34,33.8C550.3,490.4,535,505.6,516.4,505.7z"/></svg>',
                ],
            ],
            'my' => [
                'front' => [
                    'more_0' => '<svg t="1554701349418" id="my_front_more_0" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5580" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M512 64.4c-140.9 0-255.5 117-255.5 260.8 0 104.1 60.3 194 146.9 235.7-160.2 48-277.7 199.2-277.7 378.1 0 11.4 9 20.6 20.2 20.6 11.1 0 20.2-9.2 20.2-20.6 0-194.6 155.2-353 345.9-353 140.9 0 255.5-117 255.5-260.8S652.9 64.4 512 64.4z m0 480.4c-118.6 0-215.2-98.5-215.2-219.6S393.3 105.6 512 105.6s215.2 98.5 215.2 219.6S630.7 544.8 512 544.8z m234.3 80.8c-8.9-6.9-21.5-5.2-28.3 3.9-6.8 9.1-5.1 22 3.8 28.9 86.5 67.5 136.1 169.8 136.1 280.7 0 11.4 9 20.6 20.2 20.6s20.2-9.2 20.2-20.6c0-124-55.4-238.2-152-313.5z m0 0" p-id="5581"></path></svg>',
                    'more_1' => '<svg version="1.1" id="my_front_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M716.5,603.7H307.4c-77.1,0-139.8,62.7-139.8,139.7c0,77.1,62.7,139.8,139.8,139.8h409.3c77.1,0,139.8-62.7,139.8-139.8C856.3,666.4,793.5,603.7,716.5,603.7z M794.5,743.5c0,43-35,78-78,78H307.4c-43,0-78-35-78-78c0-43,35-78,78-78h409.2C759.5,665.6,794.5,700.5,794.5,743.5z"/><g><path class="st0" d="M512,608.8"/></g><path class="st0" d="M614.3,449.6c-27.3,27.3-63.6,42.3-102.3,42.3c-38.6,0-74.9-15-102.3-42.3c-27.3-27.3-42.3-63.6-42.3-102.3c0-38.6,15-74.9,42.3-102.3c27.4-27.3,63.7-42.3,102.3-42.3s74.9,15,102.3,42.3c8.4,8.4,15.6,17.7,21.6,27.6c5.7,9.4,15.7,15.3,26.7,15.3h0c24.1,0,38.8-26.3,26.3-46.9c-37.3-61.8-106-102.7-183.9-100c-107.6,3.6-195,90.6-199.2,198.1c-4.6,117.6,89.7,214.6,206.3,214.6c91.5,0,169.3-59.8,196.3-142.5c6.5-19.9-8.4-40.4-29.4-40.4h0c-13.5,0-25.3,8.9-29.5,21.7C642.4,413.8,630.5,433.3,614.3,449.6z"/></g></svg>',
                    'only_0' => '<svg version="1.1" id="my_front_only_0" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><g><defs><rect id="SVGID_1_" x="111.1" y="107.6" width="770.6" height="770.6"/></defs><clipPath id="SVGID_2_"><use xlink:href="#SVGID_1_"  style="overflow:visible;"/></clipPath></g></g><g><g><defs><rect id="SVGID_3_" x="92" y="90" width="770.6" height="770.6"/></defs><clipPath id="SVGID_4_"><use xlink:href="#SVGID_3_"  style="overflow:visible;"/></clipPath></g></g><path class="st0" d="M768.6,895.5H224.3c-33.6,0-55.9-27.6-55.9-59.1v-126c0-15.8,7.5-35.4,18.6-47.2l160.3-149.6c-33.6-43.3-52.2-90.6-59.6-145.7c-7.5-70.9,7.5-141.7,52.2-196.9c37.3-43.3,96.9-66.9,156.6-63c63.4-3.9,126.8,19.7,167.8,70.9c41,55.1,55.9,126,48.5,196.9c-7.5,55.1-29.8,106.3-63.4,145.7l156.6,145.7c11.2,11.8,18.6,27.6,18.6,47.2v126C824.5,867.9,798.4,895.5,768.6,895.5L768.6,895.5z M496.4,167.1c-44.7-3.9-85.7,11.8-119.3,47.2c-29.8,43.3-44.7,98.4-37.3,149.6c7.5,51.2,29.8,98.4,63.4,129.9c11.2,11.8,14.9,27.6,3.7,43.3l-3.7,3.9L220.6,710.4v126h544.3v-126L589.6,541.1 c-11.2-11.8-14.9-31.5-3.7-43.3l3.7-3.9c37.3-31.5,59.6-78.7,67.1-129.9c7.5-51.2-3.7-106.3-33.6-149.6 C589.6,182.9,541.2,163.2,496.4,167.1L496.4,167.1z M496.4,167.1"/><path class="st0" d="M529.4,787.8h-68.3V654.7c0-13.7,10.2-27.3,27.3-27.3h13.7c13.7,0,27.3,10.2,27.3,27.3V787.8z M529.4,787.8"/></svg>',
                    'only_1' => '<svg version="1.1" id="my_front_only_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:url(#SVGID_1_);}	.st1{fill:url(#SVGID_2_);}</style><g>	<linearGradient id="SVGID_1_" gradientUnits="userSpaceOnUse" x1="326.6068" y1="335.6793" x2="697.8091" y2="335.6793">		<stop  offset="0" style="stop-color:#E2E2E2"/>		<stop  offset="1" style="stop-color:#D6D6D6"/>	</linearGradient>	<ellipse class="st0" cx="512.2" cy="335.7" rx="185.6" ry="194.9"/>	<linearGradient id="SVGID_2_" gradientUnits="userSpaceOnUse" x1="196.6912" y1="707.9482" x2="827.3088" y2="707.9482">		<stop  offset="0" style="stop-color:#E2E2E2"/>		<stop  offset="1" style="stop-color:#D6D6D6"/>	</linearGradient>	<path class="st1" d="M800.8,693.8C746,586.9,679.2,532.7,602.2,532.7H421.8c-76.9,0-143.7,54.2-198.5,161.1		c-49.4,96.3-18.9,146.8,3.6,168.3c14.1,13.5,32.8,21.1,52.4,21h465.7c19.5,0,38.3-7.5,52.4-21C819.7,840.6,850.1,790.1,800.8,693.8		z"/></g></svg>',
                    'only_2' => '<svg version="1.1" id="my_front_only_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><g>	<path class="st0" d="M827.7,732.8"/>	<path class="st0" d="M714.6,229.7c-57.3-57.3-133.6-88.9-214.7-88.9c-167.4,0-303.6,136.2-303.6,303.6		c0,167.4,136.2,303.6,303.6,303.6c167.4,0,303.6-136.2,303.6-303.6C803.5,363.3,771.9,287.1,714.6,229.7z M499.9,685.4		c-132.9,0-241-108.1-241-241c0.1-132.8,108.2-240.9,241-241c132.9,0,241,108.1,241,241C740.9,577.3,632.8,685.4,499.9,685.4z"/>	<path class="st0" d="M758.5,849.6c0-8.1-3.2-15.7-8.9-21.5c-5.7-5.7-13.4-8.9-21.5-8.9H271.6c-8,0-15.8,3.2-21.5,8.9		c-5.7,5.7-8.9,13.4-8.9,21.5v3.2c0,8.1,3.2,15.7,8.9,21.5c5.7,5.7,13.4,8.9,21.5,8.9h456.5c8.1,0,15.7-3.2,21.5-8.9		c5.7-5.7,8.9-13.4,8.9-21.5V849.6z"/>	<path class="st0" d="M545.2,541.6h-90.7c-19.7,0.1-35.9,16.3-36,36c0,9.6,3.8,18.6,10.5,25.4c6.7,6.7,16,10.5,25.4,10.5h90.8		c19.7-0.1,35.9-16.3,36-36.1C581.2,557.8,565,541.7,545.2,541.6z"/></g></svg>',
                ],
                'back' => [
                    'more_0' => '<svg t="1554701344022" id="my_back_more_0" style="fill:#7f7f7f" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5433" xmlns:xlink="http://www.w3.org/1999/xlink" width="50" height="50"><defs><style type="text/css"></style></defs><path d="M512 64.2c-145.7 0-263.7 120.1-263.7 268.3 0 148.2 118 268.3 263.7 268.3s263.7-120.1 263.7-268.3c0-148.2-118.1-268.3-263.7-268.3z" p-id="5434"></path><path d="M910.7 959.8c0-224.1-178.5-405.7-398.7-405.7S113.3 735.7 113.3 959.8" p-id="5435"></path></svg>',
                    'more_1' => '<svg version="1.1" id="my_back_more_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><path class="st0" d="M716.5,603.7H307.4c-77.1,0-139.8,62.7-139.8,139.7c0,77.1,62.7,139.8,139.8,139.8h409.3c77.1,0,139.8-62.7,139.8-139.8C856.3,666.4,793.5,603.7,716.5,603.7z M794.5,743.5c0,43-35,78-78,78H307.4c-43,0-78-35-78-78c0-43,35-78,78-78h409.2C759.5,665.6,794.5,700.5,794.5,743.5z"/><g><path class="st0" d="M512,608.8"/></g><path class="st0" d="M614.3,449.6c-27.3,27.3-63.6,42.3-102.3,42.3c-38.6,0-74.9-15-102.3-42.3c-27.3-27.3-42.3-63.6-42.3-102.3c0-38.6,15-74.9,42.3-102.3c27.4-27.3,63.7-42.3,102.3-42.3s74.9,15,102.3,42.3c8.4,8.4,15.6,17.7,21.6,27.6c5.7,9.4,15.7,15.3,26.7,15.3h0c24.1,0,38.8-26.3,26.3-46.9c-37.3-61.8-106-102.7-183.9-100c-107.6,3.6-195,90.6-199.2,198.1c-4.6,117.6,89.7,214.6,206.3,214.6c91.5,0,169.3-59.8,196.3-142.5c6.5-19.9-8.4-40.4-29.4-40.4h0c-13.5,0-25.3,8.9-29.5,21.7C642.4,413.8,630.5,433.3,614.3,449.6z"/></g></svg>',
                    'only_0' => '<svg version="1.1" id="my_back_only_0" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">.st0{fill:#7f7f7f;}</style><g><g><defs><rect id="SVGID_1_" x="111.1" y="107.6" width="770.6" height="770.6"/></defs><clipPath id="SVGID_2_"><use xlink:href="#SVGID_1_"  style="overflow:visible;"/></clipPath></g></g><g><g><defs><rect id="SVGID_3_" x="92" y="90" width="770.6" height="770.6"/></defs><clipPath id="SVGID_4_"><use xlink:href="#SVGID_3_"  style="overflow:visible;"/></clipPath></g></g><path class="st0" d="M529.4,787.8h-68.3V654.7c0-13.7,10.2-27.3,27.3-27.3h13.7c13.7,0,27.3,10.2,27.3,27.3V787.8z M529.4,787.8"/><path class="st0" d="M805.9,667.1L649.3,521.5c33.6-39.4,55.9-90.6,63.4-145.7c7.5-70.9-7.5-141.7-48.5-196.9c-41-51.2-104.4-74.8-167.8-70.9c-59.6-3.9-119.3,19.7-156.6,63c-44.7,55.1-59.6,126-52.2,196.9c7.5,55.1,26.1,102.4,59.6,145.7L187,663.2c-11.2,11.8-18.6,31.5-18.6,47.2v126c0,31.5,22.4,59.1,55.9,59.1h544.3c29.8,0,55.9-27.6,55.9-55.1v-126C824.5,694.7,817.1,678.9,805.9,667.1z"/></svg>',
                    'only_1' => '<svg version="1.1" id="my_back_only_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><g>	<ellipse class="st0" cx="512.2" cy="335.7" rx="185.6" ry="194.9"/>	<path class="st0" d="M800.8,693.8C746,586.9,679.2,532.7,602.2,532.7H421.8c-76.9,0-143.7,54.2-198.5,161.1		c-49.4,96.3-18.9,146.8,3.6,168.3c14.1,13.5,32.8,21.1,52.4,21h465.7c19.5,0,38.3-7.5,52.4-21C819.7,840.6,850.1,790.1,800.8,693.8		z"/></g></svg>',
                    'only_2' => '<svg version="1.1" id="my_back_only_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1024 1024" style="enable-background:new 0 0 1024 1024;" xml:space="preserve"><style type="text/css">	.st0{fill:#7f7f7f;}</style><path class="st0" d="M827.7,732.8"/><path class="st0" d="M758.5,849.6c0-8.1-3.2-15.7-8.9-21.5c-5.7-5.7-13.4-8.9-21.5-8.9H271.6c-8,0-15.8,3.2-21.5,8.9	c-5.7,5.7-8.9,13.4-8.9,21.5v3.2c0,8.1,3.2,15.7,8.9,21.5c5.7,5.7,13.4,8.9,21.5,8.9h456.5c8.1,0,15.7-3.2,21.5-8.9	c5.7-5.7,8.9-13.4,8.9-21.5V849.6z"/><path class="st0" d="M714.6,229.7c-57.3-57.3-133.6-88.9-214.7-88.9c-167.4,0-303.6,136.2-303.6,303.6	c0,167.4,136.2,303.6,303.6,303.6c167.4,0,303.6-136.2,303.6-303.6C803.5,363.3,771.9,287.1,714.6,229.7z M545.3,613.6h-90.8	c-9.5,0-18.7-3.8-25.4-10.5c-6.8-6.8-10.5-15.8-10.5-25.4c0.1-19.8,16.3-35.9,36-36h90.7c19.8,0.1,35.9,16.2,36,35.9	C581.2,597.3,565,613.5,545.3,613.6z"/></svg>',
                ],
            ],
        ];
        $set = [
            ['id' => 1, 'title' => '首页', 'route' => '/pages/home/home', 'current' => 0, 'iconPath' => $svg['home']['front'][$arg['type']],
                'st_img' => 'mobile/small/image/sy2.png', 'img' => 'mobile/small/image/sy1.png', 'selectedIconPath' => $svg['home']['back'][$arg['type']],
            ],
            ['id' => 2, 'title' => '分类', 'route' => '/pages/classify/classify', 'current' => 0, 'iconPath' => $svg['cate']['front'][$arg['type']],
                'st_img' => 'mobile/small/image/fl2.png', 'img' => 'mobile/small/image/fl1.png', 'selectedIconPath' => $svg['cate']['back'][$arg['type']],
            ],
            [
                'id' => 3,
                'title' => self::$oneOrMore ? '附近门店' : '直播',
                'route' => '/pages/common_page/common_page',
                'current' => 0, 'iconPath' => $svg['shop']['front'][$arg['type']],
                'st_img' => 'mobile/small/image/fj2.png', 'img' => 'mobile/small/image/fj1.png', 'selectedIconPath' => $svg['shop']['back'][$arg['type']],
            ],
            ['id' => 4, 'title' => '购物车', 'route' => '/pages/cart/cart', 'current' => 0, 'iconPath' => $svg['cart']['front'][$arg['type']],
                'st_img' => 'mobile/small/image/gwc2.png', 'img' => 'mobile/small/image/gwc1.png', 'selectedIconPath' => $svg['cart']['back'][$arg['type']],
            ],
            ['id' => 5, 'title' => '我的', 'route' => '/pages/my/my', 'current' => 0, 'iconPath' => $svg['my']['front'][$arg['type']],
                'st_img' => 'mobile/small/image/wd2.png', 'img' => 'mobile/small/image/wd1.png', 'selectedIconPath' => $svg['my']['back'][$arg['type']],
            ],
        ];
        if (!self::$oneOrMore && !Env::get('is_live')) {
            unset($set[2]); // 是否隐藏直播
            $set = array_values($set);
        }
        // 1显示 2隐藏
        return $crypt->response([
            'code' => 0,
            'message' => '获取成功',
            'data' => $set,
            'flag' => 1,
        ], true);
    }
    
    /**
     * post url
     * @param $url
     * @param null $data
     * @return mixed
     */
    protected static function http_requests($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return json_decode($output, true);
    }
}