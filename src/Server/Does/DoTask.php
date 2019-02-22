<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\Tasks\ITask;
use Uniondrug\Phar\Server\XHttp;

/**
 * @package Uniondrug\Phar\Server\Does
 */
trait DoTask
{
    /**
     * 执行Task任务
     * @param XHttp  $server
     * @param int    $taskId
     * @param string $logUniqid
     * @param string $logPrefix
     * @param string $class
     * @param array  $data
     * @return mixed
     * @throws \Throwable
     */
    public function doTask($server, $taskId, string $logUniqid, string $logPrefix, string $class, array $data)
    {
        /**
         * @var ITask $task
         */
        $task = new $class($server, $data, $taskId, $logUniqid, $logPrefix);
        try {
            if ($task->beforeRun() !== true) {
                return false;
            }
            $result = $task->run();
            $task->afterRun($result);
            return $result;
        } catch(\Throwable $e) {
            throw $e;
        } finally {
            unset($task);
        }
    }
}
