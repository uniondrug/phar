<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * Manager进程退出时触发
 * @package Uniondrug\Phar\Server\Events
 */
trait OnManagerStop
{
    /**
     * @link https://wiki.swoole.com/wiki/page/191.html
     * @param XHttp $server
     */
    final public function onManagerStop($server)
    {
        $this->doManagerStop($server);
    }
}
