<?php
declare(strict_types = 1);

namespace app\common\service;


use Pheanstalk\Exception;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use think\facade\Config;
use think\facade\Env;

class Beanstalk
{
    protected static $config = [];
    protected $_con = null;
    
    public function __construct()
    {
        self::$config = Config::get('beanstalk.');
    }
    
    /**
     * 向管道写入消息
     * @param $msg
     * @param $delay
     * @param $pri
     * @return object|\Pheanstalk\Response|string
     */
    public function put($msg, $delay = 0, $pri = 0)
    {
        if ($msg) {
            $msg = is_array($msg) ? json_encode($msg) : $msg;
            $beanService = new Pheanstalk(self::$config['host'], self::$config['port']);
            $id = $beanService->putInTube(self::$config['tube'], json_encode($msg), $pri, $delay);
            $beanService->getConnection()->disconnect();
            $msg = null;
            $beanService = null;
            return $id;
        }
        return '';
    }
    
    /**
     * 从管道中读取消息并转发给对应方法
     */
    public function receive()
    {
        $beanService = new Pheanstalk(self::$config['host'], self::$config['port']);
        while (true) {
            $active = $beanService->getConnection()->isServiceListening();
            if (!$active) {
                $beanService = new Pheanstalk(self::$config['host'], self::$config['port']);
            }
            // 监听指定管道并忽略默认管道
            $job = $beanService->watch(self::$config['tube'])->ignore('default')->reserve();
            // 获取数据
            if ($job instanceof Job && $dataStr = $job->getData()) {
                $msg = str_repeat('-', 25) . date('Y-m-d H:i:s') . "[" .
                    (microtime(true) - time()) . "]" . str_repeat('-', 25) . PHP_EOL;
                // 获取消息标识id
                $msg .= 'msgId：' . $job->getId() . PHP_EOL;
                // 获取消息体
                $msg .= 'content：' . $dataStr . PHP_EOL;
                $dataArr = json_decode(json_decode($dataStr, true), true);
                if (is_array($dataArr) && array_key_exists('queue', $dataArr)) {
                    // 获取队列名称
                    $queue_name = $dataArr['queue'];
                    $msg .= 'queue：' . $queue_name . PHP_EOL;
                    $msg .= 'state：msg received' . PHP_EOL;
                    $dir_base = Env::get('root_path') . 'public/msg/' . $queue_name . '/' . date('Y-m');
                    if (!is_dir($dir_base)) {
                        mkdir($dir_base, 0755, true);
                    }
                    $log_file = $dir_base . DIRECTORY_SEPARATOR . date('d') . '.log';
                    $planTaskService = (new PlanTask($dataArr, $log_file, $msg));
                    try {
                        if (method_exists($planTaskService, $queue_name)) {
                            $ret = $planTaskService->$queue_name();
                            if ($ret === true) {
                                // 成功删除此任务
                                $beanService->delete($job);
                            } else {
                                // 失败重新发布
                                // $beanService->release($job);
                            }
                        } else {
                            // 请求异常删除此任务
                            $beanService->delete($job);
                        }
                    } catch (\Exception $e) {
                        // 请求异常删除此任务
                        $beanService->delete($job);
                    }
                    $planTaskService = null;
                } else {
                    $log_file = Env::get('root_path') . 'public/msg/exec/' . date('Y-m');
                    file_put_contents($log_file, $msg, FILE_APPEND);
                    sleep(2);
                    $beanService->delete($job);
                }
                $msg = null;
            }
        }
    }
}
// [
//     [
//         'store_id' => 57,
//         'products_id' => '',
//         'quantity' => 1,
//         'member_shop_coupon_id' => '',
//         'message' => '留言1',
//         'distribution_type' => 3,
//         'take_id' => null,
//         'goods_attr'=>'请选择商品属性',
//         'pay_type'=>1,
//         'is_invoice'=>1
//     ],
// ]