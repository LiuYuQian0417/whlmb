<?php
/**
 * Created by PhpStorm.
 * User: Faith
 * Date: 2019/4/28
 * Time: 13:19
 */


/**
 * 字符串url编码
 * @param $string
 * @return mixed|string
 */
function urlsafe_b64encode($string) {
    $data = base64_encode($string);
    $data = str_replace(array('+','/','='),array('-','_',''),$data);
    return $data;
}

/**字符串url解码
 * @param $string
 * @return bool|string
 */
function urlsafe_b64decode($string) {
    $data = str_replace(array('-','_'),array('+','/'),$string);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    return base64_decode($data);
}