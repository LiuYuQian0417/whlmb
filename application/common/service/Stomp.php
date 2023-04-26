<?php
declare(strict_types = 1);
namespace app\common\service;


use Stomp\Client;
use Stomp\Exception\StompException;
use Stomp\Network\Connection;
use Stomp\StatefulStomp;
use Stomp\Transport\Message;
use think\facade\Config;

class Stomp
{
    private $config;
    private $stomp;

    public function __construct()
    {
        $this->config = Config::get('mq.web');
        self::conn();
    }

    /**
     * 建立连接
     * @throws StompException
     */
    private function conn()
    {
        try {
            $this->stomp = new Client(new Connection('tcp://' . $this->config['host'] . ':' . $this->config['port']));
            $this->stomp->setLogin($this->config['login'], $this->config['password']);
            $this->stomp->setVhostname($this->config['vHost']);
            $this->stomp->setClientId('111');
            // 异步
            $this->stomp->setSync(false);
        } catch (StompException $stompException) {
            throw new StompException($stompException->getMessage());
        }
    }

    /**
     * 发送消息
     * @param array $message
     * @return string
     */
    public function send($message = [])
    {
        try {
            if (!empty($message) && !empty($message['data'])) {
                foreach ($message['data'] as $item) {
                    $msg = array_merge($message['base'], $item);
                    $msg = new Message(json_encode($msg), []);
                    $destinations = $this->config['type'] . $this->config['exchange_name'] . '/' . $item['dist'];
                    (new StatefulStomp($this->stomp))->send($destinations, $msg);
                }
            }
            return '';
        } catch (StompException $stompException) {
            return $stompException->getMessage();
        }
    }
}