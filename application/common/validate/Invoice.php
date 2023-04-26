<?php
/**
 * 关于发票.
 * User: Heng
 * Date: 2019/2/12
 * Time: 17:57
 */

namespace app\common\validate;

use think\Validate;

/**
 * 发票
 * Class Invoice
 * @package app\common\validate
 */
class Invoice extends Validate
{
    protected $rule = [
        'invoice_id|发票id' => 'require',
        'invoice_type|发票类型' => 'require',
        'rise|抬头类型' => 'require',
        'rise_name|抬头内容' => 'require',
        'detail_type|内容明细类型' => 'require',
    ];

    protected $message = [
        'invoice_id.require' => '不能为空',
        'invoice_type.require' => '不能为空',
        'rise.require' => '不能为空',
        'rise_name.require' => '不能为空',
        'detail_type.require' => '不能为空',
    ];

    protected $scene = [
        'create' => ['invoice_id', 'invoice_type', 'rise', 'rise_name', 'detail_type'],
    ];
}