<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
$dec_name = config('beanstalk.tube');
return [
    "think\\queue\\command\\Work",
    "think\\queue\\command\\Restart",
    "think\\queue\\command\\Listen",
    "think\\queue\\command\\Subscribe",
    $dec_name => "app\\common\\command\\Consumer",
    "app\\common\\command\\DaemonBase",
    "app\\common\\command\\DaemonCustomer",
    "app\\common\\command\\DaemonNotify"
];
