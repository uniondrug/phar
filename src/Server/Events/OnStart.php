<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * Master进程启动时触发
 * @package Uniondrug\Phar\Server\Events
 */
trait OnStart
{
    /**
     * Server启动在主进程的主线程回调此函数
     * @link https://wiki.swoole.com/wiki/page/p-event/onStart.html
     * @param XHttp $server
     */
    final public function onStart($server)
    {
        $server->setMutex();
        $name = $server->setProcessName('master');
        $server->getLogger()->setServer($server)->setPrefix("[%s:%d][%s][x=m:%d]", $server->getConfig()->getDeployIp(), $server->getConfig()->port, $server->getConfig()->name, $server->getMasterPid());
        $server->getLogger()->info("启动{%s}进程", $name);
        $server->doStart($server);
    }
}
