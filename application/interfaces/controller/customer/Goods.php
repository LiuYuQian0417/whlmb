<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-03-20
 * Time: 08:49
 */

namespace app\interfaces\controller\customer;


use think\Controller;

class Goods extends Controller
{
    /**
     * 商品详情
     */
    public function info()
    {
        return $this->fetch('index');
    }
}