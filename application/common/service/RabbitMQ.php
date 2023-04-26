<?php
declare(strict_types=1);

namespace app\common\service;

use think\Exception;
use think\facade\Config;

/**
 * 消息队列
 * Class RabbitMQ
 * @package app\common\service
 */
class RabbitMQ
{
    /**
     * 配置
     * @var array|mixed
     */
    private $config = [];
    /**
     * 交换机名称
     * @var
     */
    private $exchange_name;
    /**
     * 当前队列名称
     * @var
     */
    private $queue_name;
    /**
     * 是否持久化
     * @var bool
     */
    private $is_durable = true;
    /**
     * 是否自动删除[交换机和队列]
     * @var bool
     */
    private $auto_delete = false;
    /**
     * 是否延迟化交换机[延迟消息时间(毫秒级)]
     * @var int
     */
    private $delayedAndTime = 0;
    /**
     * rabbitMQ连接对象,信道对象,交换机对象,队列对象
     * @var null
     */
    private $_con = null;
    private $_channel = null;
    private $_exchange = null;
    private $_queue = null;

    public function __construct($queue_name = '', $delayedAndTime = 1, $cnf = 'default')
    {
        $this->config = is_array($cnf) ? $cnf : Config::get('mq.' . $cnf);
        $this->delayedAndTime = strval($delayedAndTime * 1000);
        $this->exchange_name = $this->config['exchange_name'] . ($this->delayedAndTime ? '_delay' : '_now');
        // 既为队列名称,也是路由名称
        $this->queue_name = $this->exchange_name . '_' . $queue_name;
    }

    /**
     * 设置持久化(默认true)
     * @param $durable
     */
    public function setDurable(bool $durable)
    {
        $this->is_durable = $durable;
    }

    /**
     * 设置自动删除(默认false)
     * @param bool $autoDelete
     */
    public function autoDelete(bool $autoDelete)
    {
        $this->auto_delete = $autoDelete;
    }

    /**
     * 打开amqp连接
     * @param $goOn int 是否继续进行配置
     * @throws Exception
     */
    private function open($goOn = 1)
    {
        if (is_null($this->_con)) {
            try {
                $this->_con = new \AMQPConnection($this->config);
                $this->_con->connect();
            } catch (\AMQPConnectionException $e) {
                throw new Exception('不能连接rabbitMQ', 500);
            }
        }
        if ($goOn) $this->initConnect();
    }

    /**
     * rabbitMQ连接不变,重置交换机,队列,路由
     * @param $exchange_name
     * @param $queue_name
     * @throws Exception
     */
    public function reset($exchange_name, $queue_name)
    {
        $this->exchange_name = $exchange_name;
        $this->queue_name = $queue_name;
        $this->open();
    }

    /**
     * 初始化rabbitMQ连接配置
     * @throws Exception
     */
    private function initConnect()
    {
        if (!$this->exchange_name || !$this->queue_name)
            throw new Exception('配置参数异常', 500);
        try {
            // 创建信道
            $this->_channel = new \AMQPChannel($this->_con);
            // QOS吞吐量限制
            $this->_channel->setPrefetchCount(1);
            // 创建交换机
            self::setUpExchange();
            // 创建队列并绑定交换机
            self::setUpQueue();
            // 将队列绑定到交换机的路由上(路由名称使用队列名称)
            $this->_queue->bind($this->exchange_name, $this->queue_name);
        } catch (\AMQPException $e) {
            throw new Exception('初始化rabbitMQ连接配置失败', 500);
        }
    }

    /**
     * 创建交换机
     */
    private function setUpExchange()
    {
        // 在此信道上创建交换机
        $this->_exchange = new \AMQPExchange($this->_channel);
        // 设置交换机名称
        $this->_exchange->setName($this->exchange_name);
        // 设置交换机类型
        if ($this->delayedAndTime) {
            // 延迟型
            $this->_exchange->setType('x-delayed-message');
            $this->_exchange->setArgument('x-delayed-type', 'direct');
        } else {
            $this->_exchange->setType(AMQP_EX_TYPE_DIRECT);
        }
        if ($this->is_durable) $this->_exchange->setFlags(AMQP_DURABLE);
        if ($this->auto_delete) $this->_exchange->setFlags(AMQP_AUTODELETE);
        // 声明此交换机
        $this->_exchange->declareExchange();
    }

    /**
     * 创建队列
     */
    private function setUpQueue()
    {
        // 在此信道上创建队列
        $this->_queue = new \AMQPQueue($this->_channel);
        // 设置操作队列名称
        $this->_queue->setName($this->queue_name);
        if ($this->is_durable) $this->_queue->setFlags(AMQP_DURABLE);
        if ($this->auto_delete) $this->_queue->setFlags(AMQP_AUTODELETE);
        // 声明此队列
        $this->_queue->declareQueue();
    }

    /**
     * 删除当前或指定转换机
     * @param string $exchange_name
     * @return bool
     * @throws Exception
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function delExchange($exchange_name = '')
    {
        $this->open(0);
        // 创建信道
        $this->_channel = new \AMQPChannel($this->_con);
        // 在此信道上创建交换机
        $this->_exchange = new \AMQPExchange($this->_channel);
        // 删除当前货指定交换机
        return $this->_exchange->delete($exchange_name ?: $this->exchange_name);
    }

    /**
     * 删除队列
     * @param int $unUsed
     * @return mixed
     * @throws Exception
     * @throws \AMQPConnectionException
     */
    public function delQueue($unUsed = 0)
    {
        $this->open(0);
        // 创建信道
        $this->_channel = new \AMQPChannel($this->_con);
        // 在此信道上创建队列
        self::setUpQueue();
        // 删除当前指定队列
        return $this->_queue->delete($unUsed ? 512 : 0);
    }

    /**
     * 生产者发送消息
     * @param $msg
     * @return mixed
     * @throws Exception
     */
    public function send($msg)
    {
        $this->open();
        if (is_array($msg)) {
            $msg = json_encode($msg);
        } else {
            $msg = trim(strval($msg));
        }
        $attr = [];
        // 设置消息延迟时长
        if ($this->delayedAndTime) {
            $attr = ['headers' => ['x-delay' => $this->delayedAndTime]];
        }
        // 发送msg
        return $this->_exchange->publish($msg, $this->queue_name, AMQP_NOPARAM, $attr);
    }

    /**
     * 消费者接收消息
     * @param $func
     * @param bool $auto_ack
     * @return bool
     * @throws Exception
     */
    public function receive($func, $auto_ack = false)
    {
        $this->open();
        if (!$func || !$this->_queue) return false;
        while (true) {
            if ($auto_ack)
                $this->_queue->consume($func, AMQP_AUTOACK);
            else
                $this->_queue->consume($func);
        }
    }

    public function __destruct()
    {
        if ($this->_con) $this->_con->disconnect();
    }

}