<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * Master进程退出时触发
 * @package Uniondrug\Phar\Server\Events
 */
trait OnShutdown
{
    /**
     * @link https://wiki.swoole.com/wiki/page/p-event/onShutdown.html
     * @param XHttp $server
     */
    final public function onShutdown($server)
    {
        $this->doShutdown($server);
        $server->getLogger()->fatal("Master进程退出");
    }
}
