<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-01-15
 * Time: 10:54
 */

namespace app\master\controller;

use think\Controller;

/**
 * 客服设置
 *
 * Class CustomerReceptionSettings
 * @package app\client\controller
 */
class CustomerSettings extends Controller
{

    /**
     * 接待设置
     */
    public function reception()
    {
        return $this->fetch();
    }


}