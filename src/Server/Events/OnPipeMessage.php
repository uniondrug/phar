<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * 响应PIPE消息
 * @package Uniondrug\Phar\Server\Events
 */
trait OnPipeMessage
{
    /**
     * 响应PIPE消息
     * 收到管道消息转发异步任务
     * @param XHttp  $server
     * @param int    $srcWorkerId
     * @param string $message
     */
    final public function onPipeMessage($server, int $srcWorkerId, $message)
    {
        try {
            $taskId = $server->task($message, -1);
            if ($taskId !== false) {
                $server->boot->getLogger()->debug("[task=%d]PIPE转发TASK", $taskId);
                return;
            }
            throw new \Exception("return false from task() method");
        } catch(\Throwable $e) {
            $server->boot->getLogger()->error("PIPE转发TASK失败 - %s", $e->getMessage());
        }
    }
}
