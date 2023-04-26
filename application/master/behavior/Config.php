<?php
declare(strict_types = 1);
namespace app\master\behavior;

use think\facade\Request;
use think\facade\Session;

class Config
{
    public function run($params)
    {
        if (Request::get('YO',0) !== 0){
            Session::set('YO',true);
        }
        url_logs('master_logs'); //传入参数区分 手机端还是电脑端避免混淆

    }
}