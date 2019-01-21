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
        $logUniqid = uniqid('task');
        $logPrefix = sprintf("[r=%s][z=%d]", $logUniqid, $taskId);
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
            $logPrefix .= "[y=".$data['class']."]";
            $logger->debug("%s任务开始,申请内存{%.01f}M内存", $logPrefix, $memory);
            $result = $this->doTask($server, $taskId, $logUniqid, $logPrefix, $data['class'], $data['params']);
            $logger->debug("%s[d=%.06f]任务完成", $logPrefix, microtime(true) - $begin);
            return $result != false;
        } catch(\Throwable $e) {
            $logger->error("%s[d=%.06f]任务返回{%d}错误 - %s - 位于{%s}第{%d}行", $logPrefix, microtime(true) - $begin, $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            return false;
        }
    }
}
