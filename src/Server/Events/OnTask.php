<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\Tasks\ITask;
use Uniondrug\Phar\Server\XHttp;

/**
 * 任务开始时触发
 * @package Uniondrug\Phar\Server\Events
 */
trait OnTask
{
    /**
     * @link https://wiki.swoole.com/wiki/page/54.html
     * @param XHttp  $server
     * @param int    $taskId
     * @param int    $srcWorkerId
     * @param string $message
     * @return mixed
     */
    final public function onTask($server, int $taskId, int $srcWorkerId, $message)
    {
        $begin = microtime(true);
        $memory = memory_get_usage(true) / 1024 / 1024;
        $logger = $server->getLogger();
        $logPrefix = sprintf("[t=%d][%s]", $taskId, uniqid('task'));
        try {
            // 1. 任务解码
            //    固定的JSON数据
            $data = json_decode($message, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("解码JSON数据失败");
            }
            // 2. 任务类名
            if (!is_a($data['class'], ITask::class, true)) {
                throw new \Exception("{$data['class']}未实现".ITask::class."接口");
            }
            // 3. 执行任务
            $logger->debug("%sTask{%s}开始,申请内存{%.01f}MB", $logPrefix, $data['class'], $memory);
            $result = $this->doTask($server, $taskId, $logPrefix, $data['class'], $data['params']);
            $logger->debug("%sTask完成,用时{%.06f}秒", $logPrefix, microtime(true) - $begin);
            return $result != false;
        } catch(\Throwable $e) {
            $logger->error("%s[duration=%.06f]执行Task失败 - %s at %s(%d)", $logPrefix, microtime(true) - $begin, $e->getMessage(), $e->getFile(), $e->getLine());
            return false;
        }
    }
}
