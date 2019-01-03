<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Processes;

use Swoole\Process;
use Uniondrug\Phar\Server\XHttp;

abstract class XProcess extends Process implements IProcess
{
    /**
     * @var XHttp
     */
    private $server;
    protected $data;

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
        ], $server->getConfig()->processesCreatePipe, $server->getConfig()->processesCreatePipe);
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

    final public function runProcess()
    {
    }
}

