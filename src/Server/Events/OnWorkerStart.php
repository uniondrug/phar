<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * Worker进程启动时触发
 * @package Uniondrug\Phar\Server\Events
 */
trait OnWorkerStart
{
    /**
     * @link https://wiki.swoole.com/wiki/page/p-event/onWorkerStart.html
     * @param XHttp $server
     * @param int   $workerId
     */
    final public function onWorkerStart($server, $workerId)
    {
        $proc = $server->isTasker() ? 'tasker' : 'worker';
        $name = $server->setProcessName($proc, $workerId);
        $server->getLogger()->setServer($server)->setPrefix("[%s:%d][%s][x=%s:%d:%d]", $server->getConfig()->host, $server->getConfig()->port, $server->getConfig()->name, ($server->isTasker() ? 't' : 'w'), $server->getWorkerPid(), $workerId);
        $server->getLogger()->info("启动{%s}进程", $name);
        $server->doWorkerStart($server, $workerId);
    }
}
