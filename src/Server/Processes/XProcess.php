<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Processes;

use Swoole\Process;
use Uniondrug\Phar\Server\XHttp;

/**
 * Class XProcess
 * @package Uniondrug\Phar\Server\Processes
 */
abstract class XProcess extends Process implements IProcess
{
    /**
     * @var XHttp
     */
    private $server;
    protected $data;
    protected $processName = null;

    /**
     * @param XHttp $server
     * @param array $data
     */
    public function __construct($server, array $data = [])
    {
        $this->server = $server;
        $this->data = $data;
        parent::__construct([
            $this,
            'runProcess'
        ], $server->getConfig()->processesStdRedirect, $server->getConfig()->processesCreatePipe);
    }

    /**
     * 读取容器实例
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->server->getContainer()->getShared($name);
    }

    /**
     * Server对象
     * @return XHttp
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * 后置操作
     */
    public function afterRun()
    {
    }

    /**
     * 前置操作
     */
    public function beforeRun()
    {
    }

    /**
     * 执行过程
     */
    final public function runProcess()
    {
        // 1. prepare
        $name = $this->getServer()->setProcessName('process', get_class($this), $this->processName);
        $this->getServer()->getLogger()->setServer($this->getServer())->setPrefix("[%s:%d][%s][x=p:%d]", $this->getServer()->getConfig()->getDeployIp(), $this->getServer()->getConfig()->port, $this->getServer()->getConfig()->name, $this->pid);
        $this->getServer()->getLogger()->info("启动{%s}进程", $name);
        if (method_exists($this->getServer(), 'frameworkRegister')) {
            $this->getServer()->frameworkRegister();
            if (method_exists($this->getServer(), 'frameworkLogger')) {
                $this->getServer()->frameworkLogger($this->getServer()->getLogger());
            }
        }
        // 2. 每1秒进程一次健康检查
        swoole_timer_tick(1000, [
            $this,
            'runHealth'
        ]);
        // 3. run progress
        $this->beforeRun();
        $this->run();
    }

    /**
     * Process健康检查
     */
    public function runHealth()
    {
        // todo: Process进程健康检查未实现
    }
}

