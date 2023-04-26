<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-04-03
 * Time: 14:55
 */

namespace app\common\validate;


use think\Validate;

class StoreAuth extends Validate
{
    protected $rule = [
        'type|认证类型' => 'require|in:1,2',
        'auth_name|姓名' => 'require|chs|length:2,6',
        'auth_number|身份证号码' => 'require|IdCard',
        'ID_front_file|' => 'require',  // 身份证正面照片
        'ID_back_file|' => 'require',   // 身份证反面照片
        'company_number|营业执照号码' => 'require',
        'file1|' => 'require', // 营业执照

        // 店铺类型 个人店铺
        'ID_type|身份证类型' => 'requireIf:type,1|in:1,2',
        'file|' => 'requireIf:type,1',  // 手持身份证照片
        'tel|联系电话' => 'requireIf:store_shop,1',

        // 店铺类型 企业店铺
        'company_name|企业名称' => 'requireIf:type,2',
        'bank_file|' => 'requireIf:type,2', // 银行开户证明

        'bank_province|开户所在省' => 'require|number',
        'bank_city|开户所在市' => 'require|number',
        'bank_area|开户所在区' => 'require|number',
        'account_name|开户名称' => 'require',
        'account_bank_name|开户行' => 'require',
        'account_sub_branch|开户支行' => 'require',
        'bank_number|银行账号' => 'require',

        'status|认证审核状态' => 'require|in:1,2',
        'reason|认证审核拒绝原因' => 'requireIf:status,2',
    ];

    protected $message = [
        'auth_name.require' => '不可为空',
        'auth_name.chs' => '只能是汉字',
        'auth_name.length' => '长度错误',
        'auth_number.require' => '不可为空',
        'auth_number.IdCard' => '错误',
        'ID_front_file.require' => '请上传身份证正面照',
        'ID_back_file.require' => '请上传身份证反面照',
        'company_number.require' => '不可为空',
        'file1.require' => '请上传营业执照',
        'ID_type.requireIf' => '必须选择',
        'ID_type.in' => '格式错误',
        'file.requireIf' => '请上传手持身份证照片',
        'tel.requireIf' => '不可为空',
        'company_name.requireIf' => '不可为空',
        'bank_file.requireIf' => '请上传银行开户证明',
        'bank_province.require' => '必须选择',
        'bank_province.number' => '格式错误',
        'bank_city.require' => '必须选择',
        'bank_city.number' => '格式错误',
        'bank_area.require' => '必须选择',
        'bank_area.number' => '格式错误',
        'account_name.require' => '不可为空',
        'account_bank_name.require' => '不可为空',
        'account_sub_branch.require' => '不可为空',
        'bank_number.require' => '不可为空',
        'type.require' => '格式错误',
        'type.in' => '格式错误',
        'status.require' => '不可为空',
        'status.in' => '格式错误',
        'reason.requireIf' => '不可为空',
    ];

    protected $scene = [
        'client_store_auth_post' => [
            'type',
            'auth_name',
            'auth_number',
            'ID_front_file',
            'ID_back_file',
            'company_number',
            'file1',
            'ID_type',
            'file',
            'tel',
            'company_name',
            'bank_file',
            'bank_province',
            'bank_city',
            'bank_area',
            'account_name',
            'account_bank_name',
            'account_sub_branch',
            'bank_number',
        ],
        'master_store_auth_post' => [
            'type',
            'auth_name',
            'auth_number',
            'ID_front_file',
            'ID_back_file',
            'company_number',
            'file1',
            'ID_type',
            'file',
            'tel',
            'company_name',
            'bank_file',
            'bank_province',
            'bank_city',
            'bank_area',
            'account_name',
            'account_bank_name',
            'account_sub_branch',
            'bank_number',
        ],
        'master_audit_store' => [
            'status',
            'reason'
        ]
    ];

    function IdCard($idCard)
    {
        //15位和18位身份证号码的正则表达式
        $_regIdCard = '/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])((\d{4})|\d{3}[Xx])$/';

        //如果通过该验证，说明身份证格式正确，但准确性还需计算
        //if($_regIdCard.test(idCard)){
        if (!preg_match($_regIdCard, $idCard)) {
            return FALSE;
        }


        if (strlen($idCard) !== 18) {
            return FALSE;
        }

        $_idCardWi = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2]; //将前17位加权因子保存在数组里
        $_idCardY = [1, 0, 10, 9, 8, 7, 6, 5, 4, 3, 2]; //这是除以11后，可能产生的11位余数、验证码，也保存成数组
        $_idCardWiSum = 0; //用来保存前17位各自乖以加权因子后的总和

        for ($i = 0; $i < 17; $i++) {
            $_idCardWiSum += substr($idCard, $i, 1) * $_idCardWi[$i];
        }

        $_idCardMod = $_idCardWiSum % 11;//计算出校验码所在数组的位置

        $_idCardLast = substr($idCard, -1);//得到最后一位身份证号码

        //如果等于2，则说明校验码是10，身份证号码最后一位应该是X
        if ($_idCardMod === 2) {
            if ($_idCardLast === "X" || $_idCardLast === "x") {
                return TRUE;
            }
        } else {
            //用计算出的验证码与最后一位身份证号码匹配，如果一致，说明通过，否则是无效的身份证号码
            if ($_idCardLast == $_idCardY[$_idCardMod]) {
                return TRUE;
            }
        }

        return FALSE;
    }
}

