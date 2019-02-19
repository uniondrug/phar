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
        /**
         * @var XHttp $server
         */
        $server = $this;
        $logger = $server->getLogger();
        $table = $server->getStatsTable();
        $table->incrTaskRun();
        try {
            // 1. 入参检查
            if (!is_a($class, ITask::class, true)) {
                $logger->fatal("Task{%s}未实现{%s}接口", $class, ITask::class);
                $table->incrTaskRunFail();
                return false;
            }
            // 2. 内容转换
            $message = json_encode([
                'class' => $class,
                'params' => $data
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // 3. Worker进程
            if ($server->worker_pid > 0) {
                if (!$server->taskworker) {
                    if ($this->task($message, -1) !== false) {
                        return true;
                    }
                    $table->incrTaskRunFail();
                    return false;
                }
            }
            // 4. 非Worker进程
            $send = $this->sendMessage($message, 0);
            if (error_get_last() !== null) {
                error_clear_last();
            }
            $send || $table->incrTaskRunFail();
            return $send;
        } catch(\Throwable $e) {
            // 5. 发送Task错误
            $logger->fatal("调用runTask()失败 - %s", $e->getMessage());
            return false;
        }
    }
}
