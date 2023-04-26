<?php

namespace app\common\service;

use think\facade\Cache;
use think\facade\Config;

/**
 * redis分布式锁
 * Class Lock
 * @package app\common\service
 */
class Lock
{
    private $retryDelay;
    private $_id;
    private $retryCount;
    private $clockDriftFactor = 0.01;
    private $quorum;
    private $servers = [];
    private $instances = [];
    static $retData = [];
    
    function __construct($retryDelay = 200, $retryCount = 3)
    {
        $this->servers = [[config('cache.default')['host'], config('cache.default')['port'], $this->clockDriftFactor]];
        $this->retryDelay = $retryDelay;
        $this->retryCount = $retryCount;
        $this->quorum = min(count($this->servers), (count($this->servers) / 2 + 1));
        $this->instances[] = Cache::handler();
        $this->_id = spl_object_id($this);
        self::$retData[$this->_id] = [];
    }
    
    /**
     * 加锁
     * @param $keyArr array 锁名
     * @param $ttl    int 过期时间
     * @param int $retryDelay
     * @return array|bool
     */
    public function lock($keyArr = [], $ttl = 10000, $retryDelay = 0)
    {
        if (empty($keyArr)) {
            return true;
        }
        if ($retryDelay) {
            $this->retryDelay = $retryDelay;
        }
        $retry = $this->retryCount;
        do {
            $n = 0;
            $startTime = microtime(true) * 1000;
            $drift = ($ttl * $this->clockDriftFactor) + 2; //102
            $prefix = Config::get('cache.default')['prefix'];
            foreach ($this->instances as $instance) {
                foreach ($keyArr as $_key) {
                    $token = uniqid();
                    $validityTime = $ttl - (microtime(
                                true
                            ) * 1000 - $startTime) - $drift; //10000 - (1536396705580.1-1536396705578.9) - 102
                    if ($validityTime <= 0) {
                        continue;
                    }
                    if (self::set($instance, $prefix, $_key, $token, $ttl)) {
                        array_push(
                            self::$retData[$this->_id],
                            [
                                'validity' => $validityTime,
                                'resource' => $prefix . $_key,
                                'token' => $token,
                            ]
                        );
                    }
                    $n++;
                }
            }
            if ($n >= $this->quorum) {
                return self::$retData[$this->_id];
            }
            $delay = mt_rand(floor($this->retryDelay / 2), $this->retryDelay);
            usleep($delay * 1000);
            $retry--;
        } while ($retry > 0);
        return false;
    }
    
    protected function set($instance, $prefix, $_key, $token, $ttl, $n = 0)
    {
        if ($n == 6) {
            return false;
        }
        $ret = $instance->set($prefix . $_key, $token, ['NX', 'PX' => $ttl]);
        if ($ret) {    // 重新加入
            return true;
        }
        $n++;
        usleep(100 * $n);
        return $this->set($instance, $prefix, $_key, $token, $ttl, $n);
    }
    
    /**
     * 解锁
     * @param $lockData
     */
    public function unlock($lockData = [])
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';
        if (empty($lockData)) {
            $lockData = self::$retData;
            self::$retData = [];
        }
        foreach ($this->instances as $instance) {
            foreach ($lockData as $key => $value) {
                if ($value) {
                    $instance->eval($script, [$value['resource'], $value['token']], 1);
                }
            }
        }
        
    }
}