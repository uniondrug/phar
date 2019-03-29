<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Processes;

use Swoole\Process as SwooleProcess;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

abstract class XProcess extends SwooleProcess implements IProcess
{
    /**
     * @var XHttp|XSocket|XOld
     */
    private $_server;
    /**
     * 进程入参
     * @var array
     */
    protected $data;
    /**
     * 进程名称
     * @var string
     */
    protected $processName;

    /**
     * XProcess constructor.
     * @param XHttp|XSocket|XOld $server
     */
    final public function __construct($server, array $data = [])
    {
        parent::__construct([
            $this,
            'runProcess'
        ], $server->getConfig()->processStdInOut, $server->getConfig()->processCreatePipe);
        $this->_server = $server;
        $this->data = $data;
    }

    /**
     * 读取容器实例
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->_server->getContainer()->getShared($name);
    }

    /**
     * 前置操作
     * 是否继续执行run()方法
     * @return bool
     */
    public function beforeRun()
    {
        return true;
    }

    /**
     * 健康检查
     */
    public function checkHealth()
    {
    }

    /**
     * @return XHttp|XOld|XSocket
     */
    public function getServer()
    {
        return $this->_server;
    }

    /**
     * 启动Process
     * 在start()后触发
     */
    final public function runProcess()
    {
        $this->_server->getConfig()->reload();
        $this->_server->setPid($this->pid, 'process', get_class($this), $this->processName);
        $this->_server->getPidTable()->addProcess($this->_server->getPid(), $this->_server->getPidName());
        $this->_server->getLogger()->info("进程号{%d}启动为{%s}.", $this->_server->getPid(), $this->_server->getPidName());
        $this->_server->getLogger()->setServer($this->_server);
        $this->_server->frameworkInitialize($this->_server);
        $this->_server->tick(1000, [
            $this,
            'checkHealth'
        ]);
        $this->beforeRun() === true && $this->run();
    }
}
