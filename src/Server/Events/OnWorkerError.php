<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * Worker进程出错致意外退出时触发
 * 1. exit/die
 * 2. uncatch exception
 * @package Uniondrug\Phar\Server\Events
 */
trait OnWorkerError
{
    /**
     * @link https://wiki.swoole.com/wiki/page/166.html
     * @param XHttp $server
     * @param int   $workerId
     * @param int   $workerPid
     * @param int   $errno
     * @param int   $signal
     */
    final public function onWorkerError($server, int $workerId, int $workerPid, int $errno, int $signal)
    {
        $server->getLogger()->fatal("Worker异常退出");
        $this->doWorkerError($server, $workerId, $workerPid, $errno, $signal);
    }
}
