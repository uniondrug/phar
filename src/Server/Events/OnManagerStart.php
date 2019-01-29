<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * Manager进程启动时触发
 * @package Uniondrug\Phar\Server\Events
 */
trait OnManagerStart
{
    /**
     * @link https://wiki.swoole.com/wiki/page/190.html
     * @param XHttp $server
     */
    final public function onManagerStart($server)
    {
        $server->getConfig()->save();
        $name = $server->setProcessName('manager');
        $server->getLogger()->setServer($server)->setPrefix("[%s:%d][%s][x=n:%d]", $server->getConfig()->getDeployIp(), $server->getConfig()->port, $server->getConfig()->name, $server->getMasterPid());
        $server->getLogger()->info("启动{%s}进程", $name);
        $server->doManagerStart($server);
    }
}
