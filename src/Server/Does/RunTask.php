<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\Tasks\ITask;
use Uniondrug\Phar\Server\XHttp;

/**
 * 发起异步任务
 * @package Uniondrug\Phar\Server\Does
 */
trait RunTask
{
    /**
     * 发起异步任务
     * 1. Worker
     * 2. Tasker
     * 3. Process
     * @param string $class
     * @param array  $data
     * @return bool
     */
    public function runTask(string $class, array $data = [])
    {
        try {
            /**
             * 1. 入参检查
             * @var XHttp $server
             */
            $server = $this;
            if (!is_a($class, ITask::class, true)) {
                $server->boot->getLogger()->error("class {%s} not implements {%s}", $class, ITask::class);
                return false;
            }
            // 2. 任务内容转JSON
            $message = json_encode([
                'class' => $class,
                'params' => $data
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // 3. Task in Worker
            if ($server->worker_pid > 0) {
                if (!$server->taskworker) {
                    $server->boot->getLogger()->debug("call task() method by runTask() method");
                    return $this->task($message, -1) !== false;
                }
            }
            // 4. Task not Worker
            $server->boot->getLogger()->debug("call sendMessage() method by runTask() method");
            return $this->sendMessage($message, 0);
        } catch(\Throwable $e) {
            $server->boot->getLogger()->error("run task failure - %s", $e->getMessage());
            return false;
        }
    }
}
