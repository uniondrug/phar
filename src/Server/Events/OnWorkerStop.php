<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * Worker进程退出时触发
 * 若是异常退出, 本方法不会触发
 * @package Uniondrug\Phar\Server\Events
 */
trait OnWorkerStop
{
    /**
     * @link https://wiki.swoole.com/wiki/page/p-event/onWorkerStop.html
     * @param XHttp $server
     * @param int   $workerId
     */
    final public function onWorkerStop($server, int $workerId)
    {
        $this->doWorkerStop($server, $workerId);
        $server->getLogger()->enableDebug() && $server->getLogger()->debug("%s进程退出", $server->isTasker() ? 'tasker' : 'worker');
    }
}
