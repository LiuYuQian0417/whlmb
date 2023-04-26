<?php
/**
 * Created by PhpStorm.
 * User: malson
 * Date: 2019-01-02
 * Time: 17:26
 */


$_redis = new Redis();

$_redis->connect('127.0.0.1', 6380);

$_redis->auth('K00095B1B386CCab');

$_redis->select(0);

$_keys = $_redis->keys('DEV_TEST_*');

foreach ($_keys as $key)
{

    if ($_redis->del($key)){
        echo "++++++ $key 删除成功 ++++++\n";
    }else{
        echo "------ $key 删除失败 ------\n";
    };
}
