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
    /**
     * 启动HTTP服务
     */
    public function run()
    {
        /**
         * @var XHttp $server
         */
        $class = $this->boot->getConfig()->class;
        $server = new $class($this->boot);
        $server->start();
    }
}
