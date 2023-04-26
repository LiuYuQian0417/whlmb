<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-03-25
 * Time: 09:06
 */

namespace app\common\command;


use Swoole\Process;
use think\facade\Config;
use think\console\input\Argument;
use think\facade\Env;
use think\swoole\Server as ThinkServer;

class DaemonNotify extends DaemonBase
{
    public function configure()
    {
        $this->setName('daemonService:notify')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload|status", 'start')
            ->setDescription('推送模块');
    }

    protected function init()
    {
        $this->config = Config::pull('daemon')['notify'];

        if (empty($this->config))
        {
            $this->output->writeln('<error>配置文件无效</error>');
            exit;
        }

        if (empty($this->config['pid_file']))
        {
            $this->config['pid_file'] = Env::get('runtime_path') . 'customer.pid';
        }

        $_lastDotPosition = strripos($this->config['pid_file'], '.');

        // 避免pid混乱
        if ($_lastDotPosition)
        {
            $this->config['pid_file'] = substr_replace(
                $this->config['pid_file'],
                '_' . $this->getPort(),
                $_lastDotPosition,
                0
            );
        } else
        {
            $this->config['pid_file'] .= '_' . $this->getPort();
        }
    }

    /**
     * 启动server
     *
     * @access protected
     * @return void
     */
    protected function start()
    {
        $pid = $this->getMasterPid();

        if ($this->isRunning($pid))
        {
            $this->output->writeln('<error>推送模块运行中! 无需再次运行.</error>');
            return;
        }

        $this->output->writeln('推送模块启动中...');

        $class = $this->config['class'];

        if (class_exists($class))
        {
            $swoole = new $class;
            if (!$swoole instanceof ThinkServer)
            {
                $this->output->writeln("<error>模块继承出错 \\think\\swoole\\Server</error>");
                return;
            }
        } else
        {
            $this->output->writeln("<error>定义的模块执行类不存在 : {$class}</error>");
            return;
        }
    }

    /**
     * 柔性重启server
     *
     * @access protected
     * @return void
     */
    protected function reload()
    {
        // 柔性重启使用管理PID
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid))
        {
            $this->output->writeln('<error>推送模块已停止! 请直接启动</error>');
            return;
        }

        $this->output->writeln('推送模块重新启动中...');
        Process::kill($pid, SIGUSR1);
        $this->output->writeln('> 已重启');
    }

    /**
     * 停止server
     *
     * @access protected
     * @return void
     */
    protected function stop()
    {
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid))
        {
            $this->output->writeln('<error>推送模块已停止! 无需再次停止.</error>');
            return;
        }

        $this->output->writeln('推送模块停止中...');

        Process::kill($pid, SIGTERM);
        $this->removePid();

        $this->output->writeln('> 已停止');
    }

    /**
     * server状态
     *
     * @access protected
     * @return void
     */
    protected function status()
    {
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid))
        {
            $this->output->writeln('推送模块已停止');
            return;
        }

        $this->output->writeln('推送模块运行中');
    }
}