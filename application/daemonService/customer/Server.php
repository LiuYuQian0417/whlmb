<?php
/**
 * Created by PhpStorm.
 * User: malson
 * Date: 2019-01-02
 * Time: 16:00
 */

namespace app\daemonService\customer;


use Redis;

class Server
{
    /**
     * @var Redis
     */
    public $redis;

    /**
     * 判断WebSocket客户端是否存在，并且状态为Active状态
     *
     * @param int $fd
     * @return bool
     */
    function exist(int $fd)
    {
        return FALSE;
    }


    /**
     * 发送数据
     *
     * @param int $fd 客户端连接的ID
     * @param mixed $data 要发送的数据内容
     * @param int $opcode 指定发送数据内容的格式，默认为文本。发送二进制内容 WEBSOCKET_OPCODE_BINARY
     * @param bool $finish
     */
    function push(int $fd, $data, int $opcode = 1, bool $finish = true)
    {
    }


    function pack(string $data, int $opcode = 1, bool $finish = true, bool $mask = false): string
    {
    }
}