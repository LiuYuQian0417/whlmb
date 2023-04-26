<?php
/**
 * 关于我的资料.
 * User: Heng
 * Date: 2019/2/21
 * Time: 13:57
 */

namespace app\computer\controller\auth;

use EasyWeChat\Factory;
use think\Db;
use think\facade\Cache;
use think\facade\Request;
use mrmiao\encryption\RSACrypt;
use app\computer\model\Member as MemberModel;
use app\computer\controller\auth\Sms;
use app\computer\model\ArticleClassify as ArticleClassifyModel;
use app\computer\model\Feedback as FeedbackModel;
use app\computer\controller\BaseController;
use app\computer\model\Member;
use think\facade\Env;
use think\facade\Session;
use app\computer\model\ArticleClassify;
use app\computer\model\Article;

class Setting extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    protected $beforeActionList = [
        //检查是否登录
        'is_login'=>['except' => 'help_center']
    ];


    /**
     * 个人信息
     * @return mixed
     */
    public function index()
    {
        return $this->fetch('');
    }

    /**
     * 账户与安全
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     */
    public function safety(RSACrypt $crypt, Member $member)
    {
        try {
            $member_id = Session::get('member_info')['member_id'] ?? 0;
            // 读取个人信息
            $find = $member->where('member_id', $member_id)
                ->field('password,phone,pay_password')
                ->find();

            // 创建参数
            $result['password_state'] = empty($find['password']) ? 0 : 1;
            $result['phone_state'] = empty($find['phone']) ? 0 : 1;
            $result['pay_state'] = empty($find['pay_password']) ? 0 : 1;
            $result['phone'] = empty($find['phone']) ? '' : $find['phone'];

        } catch (\Exception $e)
        {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
        }

        return  $this->fetch('',['result' => $result]);
    }

    /**
     * 个人信息编辑
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @return mixed
     */
    public function editInformation(Request $request, RSACrypt $crypt, MemberModel $member)
    {
        if ($request::isPost()) {
            try {
                // 接参
                $param = $crypt->request();
                $param['member_id'] = request()->mid ?? '';

                // 更新
                $member->allowField(true)
                    ->isUpdate(true)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0],'url' =>'index'], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }

        }
    }

    /**
     * 设置登录密码
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @return mixed
     */
    public function setPassword(Request $request, RSACrypt $crypt, MemberModel $member)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();
                $param['member_id'] = Session::get('member_info')['member_id'];
                // 验证
                $check = $member->valid($param, 'set_password');
                if ($check['code']) return $crypt->response($check);

                $member->allowField(true)->isUpdate(true)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0],'url' => 'safety'], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
        return $this->fetch('');
    }

    /**
     * 修改登录密码
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @return mixed
     */
    public function updatePassword(Request $request, RSACrypt $crypt, MemberModel $member)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();
                $param['member_id'] = Session::get('member_info')['member_id'] ?? 0;

                // 验证
                $check = $member->valid($param, 'UpdatePassword');
                if ($check['code']) return $crypt->response($check);

                // 读取个人信息
                $member_id = $member->where(['member_id' => $param['member_id'], 'password' => passEnc($param['old_password'])])
                    ->value('member_id');

                // 如果不存在
                if (!$member_id) return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-1]], true);

                if ($param['password'] == $param['old_password']) return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-4]], true);

                $member->allowField(true)->isUpdate(true)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0],'url' =>'safety'], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
        return $this->fetch();
    }

    /**
     * 设置支付密码
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @return mixed
     */
    public function setPayPassword(Request $request, RSACrypt $crypt, MemberModel $member)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();
                $param['member_id'] = Session::get('member_info')['member_id'];

                // 验证
                $check = $member->valid($param, 'set_pay_password');
                if ($check['code']) return $crypt->response($check);

                $member->allowField(true)->isUpdate(true)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
        return $this->fetch();
    }

    /**
     * 编辑密码
     * @return mixed
     */
    public function edit_password()
    {
        return $this->fetch('');
    }

    /**
     * 修改支付密码
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @return mixed
     */
    public function updatePayPassword(Request $request, RSACrypt $crypt, MemberModel $member)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();
                $param['member_id'] = Session::get('member_info')['member_id'];

                // 验证
                $check = $member->valid($param, 'UpdatePayPassword');
                if ($check['code']) return $crypt->response($check);

                // 读取个人信息
                $member_id = $member->where(['member_id' => $param['member_id'], 'pay_password' => passEnc($param['old_password'])])
                    ->value('member_id');

                // 如果不存在
                if (!$member_id) return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-1]], true);

                $member->allowField(true)->isUpdate(true)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }

        return  $this->fetch('');
    }

    /**
     * 忘记支付密码
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @return mixed
     */
    public function forgetPayPassword(Request $request, RSACrypt $crypt, MemberModel $member)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();

                // 验证
                $check = $member->valid($param, 'forget_pay_password');
                if ($check['code']) return $crypt->response($check);

                // 读取个人信息
                $member_id = $member->where('phone', $param['phone'])->value('member_id');

                // 如果不存在
                if (!$member_id) return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-2]], true);

                $member->allowField(true)->isUpdate(true)->save($param, ['phone' => $param['phone']]);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
        $member_id = Session::get('member_info')['member_id'];
        $phone = $member->where([['member_id','=',$member_id]])->value('phone');
        return  $this->fetch('',[
            'phone' => $phone
        ]);
    }

    /**
     * 修改手机号
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $member
     * @param Sms $sms
     * @return mixed
     */
    public function updatePhone(Request $request, RSACrypt $crypt, MemberModel $member,Sms $sms)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();
                $param['member_id'] = Session::get('member_info')['member_id'];
                // 验证
                $check = $member->valid($param, 'binding_phone');
                if ($check['code']) return $crypt->response($check);

                $code = $sms->getCache($param['phone'], 1, $param['code']);

                // 验证码不正确
                if ($code === false) return $crypt->response(['code' => -1, 'message' => config('message.')[-1][-3]], true);

                $member->allowField(true)->isUpdate(true)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0],'url' => 'safety'], true);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
        $member_id = Session::get('member_info')['member_id'];
        $phone = $member->where([['member_id','=',$member_id]])->value('phone');
        return $this->fetch('',[
            'phone' => $phone
        ]);
    }

    /**
     * 意见反馈
     * @param Request $request
     * @param RSACrypt $crypt
     * @param FeedbackModel $feedback
     * @return mixed
     */
    public function feedback(Request $request, RSACrypt $crypt, FeedbackModel $feedback)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();

                // 验证
                $check = $feedback->valid($param, 'create');
                if ($check['code']) return $crypt->response($check);

//                if (Cache::get($request::ip())) return $crypt->response(['code' => -100, 'message' => config('message.')[-2][-3]], true);

                $feedback->allowField(true)->save($param);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }

    /**
     * 帮助中心
     * @param Article $article
     * @param ArticleClassify $articleClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function help_center( Article $article,ArticleClassify $articleClassify)
    {
        $article_id = Request::instance()->param('article_id',0);
        $status = Request::instance()->param('status');


        $result = $articleClassify
            ->with('article')
            ->field('article_classify_id,title')
            ->where([
                        ['parent_id', '=', 16],     //帮助中心
                        ['status', '=', 1],
                    ])
            ->order('sort', 'desc')
            ->select();

        if (empty($article_id)) $article_id = $result[0]['article'][0]['article_id'];

        $info =  $article
            ->where('article_id', $article_id)
            ->field('title,content')
            ->find();

//        halt($status);
        return $this->fetch('',[
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'info' => $info,
            'article_id' => $article_id,
            'status' => $status,
        ]);
    }

    /**
     * 账户关联
     * @param Member $member
     * @return mixed
     */
    public function relevance( Member $member)
    {

        $data = $member->where('member_id',Session::get('member_info')['member_id'])->value('wechat_open_id');
        return $this->fetch('',[
            'data' => $data
        ]);
    }

    /**
     * 微信绑定
     * @param Request $request
     * @param Member $member
     * @return mixed
     */
    public function bind_wx(Request $request,Member $member){
        $code=$request::get('code',NULL);
        //是否显示二维码
        $is_qr_code = TRUE;
        //不显示二维码时内容
        $content = '';
        if(!empty($code)){
            //如果code不存在
            $app = Factory::officialAccount(config('wechat.')['pc_login']);
            // 获取 OAuth 授权结果用户信息
            try{
                $result = $app->oauth->user();
                // 检测是否使用unionId来统一
                $flagId = ['openid', 'unionid'][config('user.common.wx.use_unionId')];
                if (!isset($result['original'][$flagId])) {
                    exception('微信授权失败');
                }
                $_wx_id= $result['original'][$flagId];
                $member_info = $member
                    ->where([
                                ['wechat_open_id', '=', $_wx_id],
                            ])
                    ->count();
                if($member_info>0){
                    $is_qr_code = FALSE;
                    $content='<div style="text-align: center">该微信号已绑定过</div>
                              <div style="text-align: center"><a href="javascript:self_reload()">刷新</a></div>';
                }else{
                    $member->save(['wechat_open_id'=>$_wx_id],[['member_id','=',Session::get('member_info')['member_id']]]);
                    $is_qr_code = FALSE;
                    $content="<script>layer.msg('绑定成功',{time:1000,end:function(){top.window.location.reload()}});</script>";
                }

            }catch (\Exception $e){
            }
        }
        return $this->fetch('',
                            ['content'=>$content,'is_qr_code'=>$is_qr_code]
        );
    }

/***** 废弃****/

//    public function check(Request $request, RSACrypt $crypt, MemberModel $member)
//    {
//        if ($request::isPost()) {
//            try {
//
//                $param = $crypt->request();
//
//                // 验证
//                $check = $member->valid($param, 'check');
//                if ($check['code']) return $crypt->response($check);
//
//                // 读取个人信息
//                $member_id = $member->where('phone', $param['phone'])->value('member_id');
//
//                // 如果不存在
//                if (!$member_id) return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-2]], true);
//
//
//                return $crypt->response(['code' => 0, 'message' => config('message.')[0][3]], true);
//
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//            }
//        }
//    }
//
//    /**
//     * 忘记密码
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param MemberModel $member
//     * @return mixed
//     */
//    public function forgetPassword(Request $request, RSACrypt $crypt, MemberModel $member)
//    {
//        if ($request::isPost()) {
//            try {
//
//                $param = $crypt->request();
//
//                // 验证
//                $check = $member->valid($param, 'forget_password');
//                if ($check['code']) return $crypt->response($check);
//
//                // 读取个人信息
//                $member_id = $member->where('phone', $param['phone'])->value('member_id');
//
//                // 如果不存在
//                if (!$member_id) return $crypt->response(['code' => -1, 'message' => config('message.')[-2][-2]], true);
//
//                $member->allowField(true)->isUpdate(true)->save($param, ['phone' => $param['phone']]);
//
//                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], true);
//
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
//            }
//        }
//    }
//
//    /**
//     * 更新头像
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param MemberModel $member
//     * @return mixed
//     */
//    public function avatar(Request $request, RSACrypt $crypt, MemberModel $member)
//    {
//        if ($request::isPost()) {
//            try {
//                $param = $crypt->request();
//                $param['member_id'] = Session::get('member_info')['member_id'];
//                // 处理
//                $member->allowField(true)->isUpdate(true)->save($param);
//                return $crypt->response([
//                                            'code'    => 0,
//                                            'message' => config('message.')[0][0],
//                                            'avatar'  => $member->avatar
//                                        ], true);
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
//            }
//        }
//    }


}