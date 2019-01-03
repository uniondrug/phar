<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * 响应PIPE消息
 * 在调用方法, 务必判断所在进程
 * @package Uniondrug\Phar\Server\Events
 */
trait OnPipeMessage
{
    /**
     * @link https://wiki.swoole.com/wiki/page/366.html
     * @param XHttp  $server
     * @param int    $srcWorkerId
     * @param string $message
     */
    final public function onPipeMessage($server, int $srcWorkerId, $message)
    {
        try {
            $taskId = $server->task($message, -1);
            if ($taskId !== false) {
                return;
            }
            throw new \Exception("调用task()时返回了false");
        } catch(\Throwable $e) {
            $server->boot->getLogger()->error("PIPE转发TASK失败 - %s - %s", $e->getMessage(), $message);
        }
    }
}
