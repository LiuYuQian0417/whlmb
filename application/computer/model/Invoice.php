<?php
/**
 * 发票主表.
 * User: Heng
 * Date: 2019/2/1
 * Time: 14:02
 */

namespace app\computer\model;

use \app\common\model\Invoice as InvoiceModel;

class Invoice extends InvoiceModel
{
    public function getModifyRiseAttr(){
        $_rise=['1'=>'个人','2'=>'公司'];
        return $_rise[$this->rise]??'';
    }
}