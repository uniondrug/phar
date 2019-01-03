<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

use Uniondrug\Phar\Server\XHttp;

/**
 * 启动Server
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class StartClient extends Abstracts\Client
{
    public function loadConfig()
    {
        $this->boot->getConfig()->fromFiles()->mergeArgs();
    }

    /**
     * 启动HTTP服务
     */
    public function run()
    {
        /**
         * @var XHttp $server
         */
        $daemon = $this->boot->getArgs()->hasOption('d');
        $daemon || $daemon = $this->boot->getArgs()->hasOption('daemon');
        $this->boot->getConfig()->setDaemon($daemon);
        $class = $this->boot->getConfig()->class;
        $server = new $class($this->boot);
        $server->start();
    }
}
