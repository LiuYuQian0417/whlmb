<?php
declare(strict_types = 1);

namespace app\computer\controller\auth;

use app\common\model\Member;
use app\computer\controller\BaseController;
use EasyWeChat\Factory;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;
use think\facade\Session;

/**
 * 会员登录
 * Class Login
 * @package app\computer\controller\auth
 */
class Login extends BaseController
{
    /**
     * 登录
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Session $session
     * @return mixed
     */
    public function index(Request $request, RSACrypt $crypt, Member $member, Session $session)
    {
        //判断是否登录
        if (!empty(Session::get('member_info')['member_id'])) {
            $this->redirect('/pc2.0/my/index');
        }
        if ($request::isPost()) {
            try {
                $param = $crypt->request();
                Db::startTrans();
                $ret = $member->login([
                    'phone' => trim($param['phone']),
                    'password' => trim($param['password']),
                    'dev_type' => 5,
                ]);
                if ($ret['code'] !== 0) {
                    return $crypt->response($ret, true);
                }
                Db::commit();
                $session::set('member_info', $ret['data']);
                unset($ret['data']);
                //设置登录成功之后回跳页面
                return $crypt->response($ret, true);
            } catch (\Exception $e) {
                Db::rollback();
                return $crypt->response(['code' => -100, 'message' => $e->getMessage()], true);
            }
        }
        return $this->fetch();
        
    }
    
    /**
     * 短信验证码登录
     * @param Request $request
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Session $session
     * @return mixed
     */
    public function sms(Request $request, RSACrypt $crypt, Member $member, Session $session)
    {
        if ($request::isPost()) {
            try {
                $param = $crypt->request();
                //检测短信
                $sms = app('app\\interfaces\\controller\\auth\\Sms');
                $checkCode = $sms->getCache($param['phone'], 9, $param['code']);
                // 验证码不正确
                if (!$checkCode) {
                    return $crypt->response(['code' => -1, 'message' => config('message.')[-1][-3]], true);
                }
                Db::startTrans();
                $ret = $member->login([
                    'phone' => $param['phone'],
                    'distribution_superior' => array_key_exists('superior', $param) && $param['superior'] ? $param['superior'] : 0,
                    'dev_type' => 5,
                ], 2);
                Db::commit();
                // 检测当前会员是否入驻
                $ret['data']['in_state'] = Hook::exec(['app\\interfaces\\behavior\\StoreCheck', 'isSettledIn'], $ret['data']);
                $session::set('member_info', $ret['data']);
                return $crypt->response($ret, true);
            } catch (\Exception $e) {
                Db::rollback();
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }
    
    
    /**
     * 微信扫码登录
     * @param Request $request
     * @param Member $member
     * @param Session $session
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function we_chat_login(Request $request, Member $member, Session $session)
    {
        $param = $request::param();
        //如果code不存在
        $app = Factory::officialAccount(config('wechat.')['pc_login']);
        // 获取 OAuth 授权结果用户信息
        try {
            $result = $app->oauth->user();
        } catch (\Exception $e) {
            $this->redirect('/pc2.0/login/index');
        }
        // 检测是否使用unionId来统一
        $flagId = ['openid', 'unionid'][config('user.common.wx.use_unionId')];
        if (!isset($result['original'][$flagId])) {
            exception('微信授权失败');
        }
        $_wx_id = $result['original'][$flagId];
        $member_info = $member
            ->where([
                ['wechat_open_id', '=', $_wx_id],
            ])
            ->field('member_id,phone,status')
            ->find();
        if (!is_null($member_info) && !$member_info['status']) {
            exception('用户账号已经被禁用');
        }
        //如果用户存在并且绑定手机号则跳转商城首页
        if (!empty($member_info) and !empty($member_info['phone'])) {
            $session::set('member_info', $member_info);
            $this->redirect('/pc2.0/index/index');
        }
        //用户不存在执行注册
        if (empty($member_info) && empty(Cache::get($_wx_id))) {
            Cache::set($_wx_id, $_wx_id, 8);
            $data = [
                'nickname' => $result['nickName'],
                'avatar' => $this->applet_upload($result['original']['headimgurl']),
                'sex' => $result['sex'] == 1 ? 1 : 0,//微信1男2女
                'wechat_open_id' => $_wx_id,
                'micro_open_id' => $result['openId'],
                'phone' => '',
            ];
            Db::startTrans();
            //distribution_superior  上级分销商id
            // 公共注册
            $member_info = default_generated($member, $data, $param['distribution_superior'] ?? 0, 1);
            // 注册即成为分销商检测
            $regToBe = [
                'member_id' => $member_info['member_id'],
                'nickname' => $member_info['nickname'],
                'distribution_superior' => $param['distribution_superior'] ?? 0,
                'phone' => $member_info['phone'],
                'sex' => $result['sex'] == 1 ? 1 : 0,//微信1男2女
                'bType' => 2,   //成为分销商途径注册自动成为分销商
                'text' => 2,    //注册即成为分销商
            ];
            Hook::exec(['app\\interfaces\\behavior\\Distribution', 'regToBe'], $regToBe);
            Db::commit();
        }
        return $this->fetch('', [
            'unionId' => $_wx_id
        ]);
    }
    
    /**
     * 绑定手机号
     * @param Request $request
     * @param Member $memberModel
     * @param Session $session
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function info(Request $request,
                         Member $memberModel, Session $session)
    {
        $param = $request::param();
        
        //检测短信(10绑定手机号)
        $sms = app('app\\interfaces\\controller\\auth\\Sms');
        $checkCode = $sms->getCache($param['phone'], 10, $param['code']);
        if (!$checkCode) {
            return ['code' => -1,
                'message' => '验证码不正确',
            ];
        }
        $wxMember = $memberModel
            ->where([
                ['wechat_open_id', '=', $param['unionId']],
            ])
            ->field('member_id,wechat_open_id,status,phone,avatar,pay_points,delete_time')
            ->find();
        if (is_null($wxMember) || !$wxMember['status']) {
            return [
                'code' => -1,
                'message' => '该用户不存在或已被注销',
            ];
        }
        if ($wxMember['phone']) {
            return [
                'code' => -2,
                'message' => '该账号已绑定手机号,不可再次绑定',
            ];
        }
        $phoneMember = $memberModel
            ->where([
                ['phone', '=', $param['phone']],
            ])
            ->field('member_id,wechat_open_id,avatar,pay_points')
            ->find();
        if ($phoneMember['wechat_open_id'] && $phoneMember['wechat_open_id'] != $param['unionId']) {
            return [
                'code' => -2,
                'message' => '该手机号已绑定其他微信号,不可再次绑定',
            ];
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
            $phoneMember->wechat_open_id = $param['unionId'];
            if (!$phoneMember->avatar) {
                $phoneMember->avatar = $wxMember->avatar;
            }
            $phoneMember->save();
            $wxMember->delete();
            // 更新积分和成长值
            taskOver($member_id = $phoneMember['member_id'], 1, 0);
            // 返回token
        }
        Db::commit();
        $member_id = (empty($phoneMember) ? $wxMember : $phoneMember)['member_id'];
        $session::set('member_info', ['member_id' => $member_id]);
        //返回token
        (new My())->get_token();
        return [
            'code' => 0,
            'message' => '绑定成功',
            'member_id' => $member_id,
        ];
    }
    
    
    /**
     * 上传文件
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
    
    
}