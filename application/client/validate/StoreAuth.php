<?php


namespace app\client\validate;


use app\common\validate\BaseValidate;

class StoreAuth extends BaseValidate
{
    protected $rule = [
        'type|' => 'require|in:1,2',
        'auth_name|' => 'require|max:6',
        'auth_number|' => 'require|IdCard',
        'ID_front_file|' => 'require',
        'ID_back_file|' => 'require',
        'company_number|' => 'require|max:100',
        'file1|' => 'require',
        'ID_type|' => 'requireIf:type,1|in:1,2',
        'file|' => 'requireIf:type,1',
        'tel|' => 'requireIf:type,1|max:11',
        'company_name|' => 'requireIf:type,2',
        'bank_file|' => 'requireIf:type,2',
        'bank_province|' => 'require|number|areaExist',
        'bank_city|' => 'require|number|areaExist',
        'bank_area|' => 'require|number|areaExist',
        'account_name|' => 'require|max:100',
        'account_bank_name|' => 'require|max:100',
        'account_sub_branch|' => 'require|max:100',
        'bank_number|' => 'require|regex:(^([1-9]{1})(\d{15,18})$)',
    ];

    protected $message = [
        'type.require' => '请选择认证类型',
        'type.in' => '认证类型格式错误',
        'auth_name.require' => '请填写姓名',
        'auth_name.max' => '姓名不能超过6个字符',
        'auth_number.require' => '请填写身份证号码',
        'auth_number.IdCard' => '身份证号码不合法',
        'ID_front_file.require' => '请上传身份证正面照片',
        'ID_back_file.require' => '请上传身份证反面照片',
        'company_number.require' => '请填写营业执照号码',
        'company_number.max' => '营业执照号码不能超过100个字符',
        'file1.require' => '请上传营业执照',
        'ID_type.requireIf' => '请选择身份证类型',
        'ID_type.in' => '身份证类型格式错误',
        'file.requireIf' => '请上传手持身份证照片',
        'tel.requireIf' => '请输入联系电话',
        'company_name.requireIf' => '请输入企业名称',
        'bank_file.requireIf' => '请上传银行开户证明',
        'bank_province.require' => '请选择开户信息-省',
        'bank_province.number' => '开户信息-省-格式错误',
        'bank_province.areaExist' => '开户信息-省-不存在',
        'bank_city.require' => '请选择开户信息-市',
        'bank_city.number' => '开户信息-市-格式错误',
        'bank_city.areaExist' => '开户信息-市-不存在',
        'bank_area.require' => '请选择开户信息-区',
        'bank_area.number' => '开户信息-区-格式错误',
        'bank_area.areaExist' => '开户信息-区-不存在',
        'account_name.require' => '请填写开户名称',
        'account_name.max' => '开户名称不能超过100个字符',
        'account_bank_name.require' => '请填写开户行',
        'account_bank_name.max' => '开户行不能超过100个字符',
        'account_sub_branch.require' => '请填写开户支行',
        'account_sub_branch.max' => '开户支行不能超过100个字符',
        'bank_number.require' => '请填写银行账号',
        'bank_number.regex' => '银行账号格式错误',
    ];

    protected $scene = [
      'update_auth'=>[
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
      ]
    ];

}