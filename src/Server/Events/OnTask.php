<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\Tasks\ITask;
use Uniondrug\Phar\Server\XHttp;

/**
 * 响应Task开始事件
 * @package Uniondrug\Phar\Server\Events
 */
trait OnTask
{
    /**
     * 响应Task开始事件
     * @link https://wiki.swoole.com/wiki/page/54.html
     * @param XHttp  $server
     * @param int    $taskId
     * @param int    $srcWorkerId
     * @param string $message
     * @return mixed
     */
    final public function onTask($server, int $taskId, int $srcWorkerId, $message)
    {
        // 1. prepare
        $t1 = microtime(true);
        $logger = $server->boot->getLogger();
        $logger->info("[task=%d]任务开始", $taskId);
        try {
            // 2. parse message
            $data = json_decode($message, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(json_last_error_msg());
            }
            $data['class'] = isset($data['class']) ? $data['class'] : null;
            $data['params'] = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
            /**
             * 3. create instance
             * @var ITask $task
             */
            $task = new $data['class']($this, $data['params'], $taskId);
            if ($task->beforeRun() !== true) {
                throw new \Exception("run false from beforeRun()");
            }
            // 4. run progress
            $result = $task->run();
            $task->afterRun($result);
            $logger->debug("[task=%d][duration=%f]任务完成", $taskId, sprintf('%06f', microtime(true) - $t1));
            if ($result === null) {
                $result = true;
            }
            return $result;
        } catch(\Throwable $e) {
            $logger->error("[task=%d]任务失败 - %s", $taskId, $e->getMessage());
            return false;
        }
    }
}
