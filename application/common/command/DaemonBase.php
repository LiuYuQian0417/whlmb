<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-03-25
 * Time: 08:51
 */

namespace app\common\command;

use Swoole\Process;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class DaemonBase extends Command
{
    protected $config = [];

    public function configure()
    {
        $this->setName('daemonService');
    }

    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        $this->init();

        if (in_array($action, ['start', 'stop', 'reload', 'restart','status'])) {
            $this->$action();
        } else {
            $output->writeln("<error>无效的操作:{$action}, 支持 start|stop|restart|reload|status .</error>");
        }
    }

    protected function init()
    {
    }

    protected function getHost()
    {
        if ($this->input->hasOption('host')) {
            $host = $this->input->getOption('host');
        } else {
            $host = !empty($this->config['host']) ? $this->config['host'] : '0.0.0.0';
        }

        return $host;
    }

    protected function getPort()
    {
        if ($this->input->hasOption('port')) {
            $port = $this->input->getOption('port');
        } else {
            $port = !empty($this->config['port']) ? $this->config['port'] : 9501;
        }

        return $port;
    }

    /**
     * 启动server
     * @access protected
     * @return void
     */
    protected function start()
    {}

    /**
     * 柔性重启server
     * @access protected
     * @return void
     */
    protected function reload()
    {}

    /**
     * 停止server
     * @access protected
     * @return void
     */
    protected function stop()
    {}

    /**
     * 重启server
     * @access protected
     * @return void
     */
    protected function restart()
    {}

    /**
     * 获取主进程PID
     * @access protected
     * @return int
     */
    protected function getMasterPid()
    {
        $pidFile = $this->config['pid_file'];

        if (is_file($pidFile)) {
            $masterPid = (int) file_get_contents($pidFile);
        } else {
            $masterPid = 0;
        }

        return $masterPid;
    }

    /**
     * 删除PID文件
     * @access protected
     * @return void
     */
    protected function removePid()
    {
        $masterPid = $this->config['pid_file'];

        if (is_file($masterPid)) {
            unlink($masterPid);
        }
    }

    /**
     * 判断PID是否在运行
     * @access protected
     * @param  int $pid
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (empty($pid)) {
            return false;
        }

        return Process::kill($pid, 0);
    }
}