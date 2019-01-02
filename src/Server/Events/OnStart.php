<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * 响应HTTP请求
 * @package Uniondrug\Phar\Server\Events
 */
trait OnStart
{
    /**
     * Server启动在主进程的主线程回调此函数
     * @link https://wiki.swoole.com/wiki/page/p-event/onStart.html
     * @param XHttp $server
     * @return void
     */
    final public function onStart($server)
    {
        $server->doStart($server);
    }
}
