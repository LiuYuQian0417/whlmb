<?php
declare(strict_types=1);

namespace app\common\validate;

use  think\Validate;

class Attr extends Validate
{
    protected $rule = [
        'attr_id|类型信息'         => 'require',
        'attr_type_id|分类类型信息'  => 'require',
        'attr_name|属性名称'       => 'require',
        'attr_input_type|录入方式' => 'require',
        'attr_value|可选值列表'     => 'requireIf:attr_input_type,1',
    ];

    protected $message = [
        'attr_type_id.require'    => '不可为空',
        'attr_name.require'       => '不可为空',
        'attr_input_type.require' => '不可为空',
        'attr_value.requireIf'    => '不可为空',
        'attr_name.unique'        => '不能重复',
    ];
//    // 用户名验证
//    protected function checkValue($value, $rule, $data)
//    {
//        if($data['attr_input_type']==0 && $data['attr_value']==''){
//            return '手动输入属性值不可为空！';
//        }
//    }
    protected $scene = [
        'create'      => ['attr_type_id', 'attr_name', 'attr_input_type', 'attr_value'],
        'edit'        => ['attr_type_id', 'type_name', 'attr_input_type', 'store_id', 'attr_id', 'attr_value'],
        'create_hand' => ['attr_type_id', 'attr_name', 'attr_input_type'],
        'edit_hand'   => ['attr_type_id', 'type_name', 'attr_input_type', 'store_id', 'attr_id'],
    ];
}