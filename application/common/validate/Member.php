<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Member extends Validate
{
    protected $rule = [
        'member_id|会员信息' => 'require',
        'phone|手机号' => ['require', 'unique:member', 'mobile'],
        'old_password|原密码' => 'require',
        'password|密码' => ['require', 'length:6,20', 'check_password'],
        'pay_password|支付密码' => ['require', 'length:6', 'number'],
        'sex|性别' => 'require',
        'add_money|最大可增加余额' => 'elt:max_add_money',
        'add_integral|最大可增加积分' => 'elt:max_add_integral',
        'nickname|昵称' => 'require',
        'status|禁用状态' => 'require',
        'code|三方数据' => 'require',
        'message_code|短信验证码' => 'require',
        'encryptedData|三方数据' => 'require',
        'iv|三方数据' => 'require',
        'token|token' => 'require',
        'open_id|openId' => 'require',
        'wechat_open_id|openId' => 'require',
        'out_trade_no|订单号' => 'require',
        'body|数据信息' => 'require',
        'total_fee|金额' => 'require',
        'integral_id|积分商品信息' => 'require',
        'member_address_id|地址信息' => 'require',
        'parameter|参数信息' => 'require',
        'url|URL信息' => 'require',
        'attach|额外自定义信息' => 'require',
        'reason|加入黑名单原因'              => 'require',
    ];

    protected $message = [
        'member_id.require' => '不可为空',
        'add_money.elt' => '超出限制',
        'add_integral.elt' => '超出限制',
        'phone.require' => '不可为空',
        'phone.unique' => '已经注册,请勿重复操作',
        'phone.mobile' => '格式不正确',
        'phone.regex' => '不可为空',
        'old_password.require' => '不可为空',
        'password.require' => '不可为空',
        'password.length' => '长度为6到20位数',
        'password.confirm' => '和确认密码不一致',
        'pay_password.require' => '不可为空',
        'pay_password.length' => '长度为6位',
        'pay_password.number' => '必须是纯数字',
        'sex.require' => '不可为空',
        'nickname.require' => '不可为空',
        'status.require' => '不可为空',
        'code.require' => '不可为空',
        'message_code.require' => '不可为空',
        'encryptedData.require' => '不可为空',
        'iv.require' => '不可为空',
        'token.require' => '不可为空',
        'open_id.require' => '不可为空',
        'wechat_open_id.require' => '不可为空',
        'out_trade_no.require' => '不可为空',
        'body.require' => '不可为空',
        'total_fee.require' => '不可为空',
        'integral_id.require' => '不可为空',
        'member_address_id.require' => '不可为空',
        'parameter.require' => '不可为空',
        'url.require' => '不可为空',
        'attach.require' => '不可为空',
        'reason.require' => '不可为空',
    ];

    protected $scene = [
        'create' => ['phone', 'password', 'sex', 'status'],
        'nickname' => ['member_id', 'nickname'],
        'sex' => ['member_id', 'sex'],
        'set_password' => ['password'],
        'forget_password' => ['phone' => ['require', 'mobile'], 'password'],
//        'update_password'     => ['old_password', 'password'],
        'binding_phone' => ['phone', 'code'],
        'set_pay_password' => ['pay_password'],
        'forget_pay_password' => ['phone' => ['require', 'mobile'], 'pay_password'],
//        'update_pay_password' => ['old_password', 'pay_password'],
        'telCreate' => ['phone', 'password', 'code'],
        'invite' => ['phone', 'message_code', 'token'],
        'mobile_login' => ['code'],
        'applet_login' => ['code', 'encryptedData', 'iv'],
        'app_login' => ['open_id', 'unionId', 'nickName', 'avatarUrl'],
        'applet_info' => ['phone' => ['require', 'mobile']],
        'binding_wechat' => ['wechat_open_id'],
        'applet_payment' => ['open_id', 'out_trade_no', 'body', 'attach'],
        'applet_recharge' => ['open_id', 'out_trade_no', 'body', 'total_fee'],
        'mobile_recharge' => ['open_id', 'out_trade_no', 'body', 'total_fee'],
        'set' => ['parameter', 'url'],
        'client' => ['phone' => ['require'], 'password'],
        'check' => ['phone' => ['require', 'mobile']],
        'log_out' => ['reason', 'member_id'],
    ];

    //view 验证场景
    public function sceneView()
    {
        return $this->only(['member_id', 'sex', 'add_money', 'add_integral', 'password'])
            ->remove('password', 'require')
            ->append('password', 'check_password|confirm:confirm_password');
    }


    public function sceneConfirm()
    {
        return $this->only(['phone', 'password'])
            ->append('password', 'check_password|confirm:confirm_password');
    }

    public function sceneUpdatePassword()
    {
        return $this->only(['old_password', 'password'])
            ->append('password', 'check_password|confirm:confirm_password');
    }

    public function sceneUpdatePayPassword()
    {
        return $this->only(['old_password', 'pay_password'])
            ->append('pay_password', 'confirm:confirm_password');
    }

    //验证密码是否为数字字母组合
    public function check_password($value)
    {
        return preg_match('/^(?![^a-zA-Z]+$)(?!\D+$)/', $value) ? true : '必须为数字字母组合';
    }


}