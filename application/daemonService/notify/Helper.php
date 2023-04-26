<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-03-25
 * Time: 11:30
 */

namespace app\daemonService\notify;


class Helper
{

    /**
     * 数据解码
     *
     * @param $data
     *
     * @return mixed
     */
    function decode($data)
    {
        return json_decode(
            $data,
            TRUE
        );
    }

    /**
     * 发送成功消息
     *
     * @param Server $server
     * @param        $fd
     * @param        $data
     */
    function sendSuccess($server, $fd, $data)
    {
        $this->send(
            $server,
            $fd,
            [
                'TYPE' => 'SUCCESS',
                'DATA' => $data,
            ]
        );
    }

    /**
     * 普通的发送
     *
     * @param Server $server
     * @param        $fd
     * @param        $data
     */
    function send($server, $fd, $data)
    {
        if ($server->isEstablished($fd))
        {
            $server->push($fd, $this->encode($data));
        }
    }

    /**
     * 数据编码
     *
     * @param $data
     *
     * @return false|string
     */
    function encode($data)
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * 发送警告消息
     *
     * @param Server $server
     * @param        $fd
     * @param        $data
     */
    function sendWarning($server, $fd, $data)
    {
        $this->send(
            $server
            ,
            $fd,
            [
                'TYPE' => 'WARNING',
                'DATA' => $data,
            ]
        );
    }

    /**
     * 返回错误消息
     *
     * @param Server $server
     * @param        $fd
     * @param        $data
     */
    function sendError($server, $fd, $data)
    {
        $this->send(
            $server,
            $fd,
            [
                'TYPE' => 'ERROR',
                'DATA' => $data,
            ]
        );
    }


}