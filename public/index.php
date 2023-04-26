<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;

//$fi = new \finfo(FILEINFO_MIME_TYPE);
//// 判断是否上传文件
//if (count($_FILES) > 0)
//{
//    foreach ($_FILES as $key => $file)
//    {
//        // 判断文件的 mime 是否在允许的范围内
//        if (!in_array(
//            $fi->file($file['tmp_name']),
//            [
//                'image/png',        // png
//                'image/gif',        // gif
//                'image/jpg',        // jpg
//                'image/jpeg',        // jpeg
//                'image/pjpeg',      // jpg|jpe|jpeg
//                'application/x-bmp',// bmp
//            ]
//        ))
//        {
//            // 如果不符合则卸载数组中的文件
//            unset($_FILES[$key]);
//        }
//    }
//}
//unset($fi);

// [ 应用入口文件 ]
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

$allow_origin = [
    'http://localhost:8082',
    'http://127.0.0.1:8082',
    'https://58yhy.com',
    'http://58yhy.com',
];

if (in_array($origin, $allow_origin))
{
    header("Access-Control-Allow-Credentials: true");
    header('Access-Control-Allow-Origin:' . $origin);
    //    header('Access-Control-Allow-Origin:*');
    header('Access-Control-Allow-Methods:POST,GET');
    header('Access-Control-Allow-Headers:x-requested-with,content-type,token');
    header('Access-Control-Expose-Headers:token');
}
// 加载基础文件
require __DIR__ . '/../thinkphp/base.php';

// 支持事先使用静态方法设置Request对象和Config对象

// 执行应用并响应
Container::get('app')->run()->send();
