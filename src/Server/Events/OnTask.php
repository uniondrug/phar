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
        // 1. 执行起点
        $usage = memory_get_usage(true);
        $begin = microtime(true);
        $memory = ($usage / 1024) / 1024;
        $uniqid = 't'.date('ymdHis').uniqid().mt_rand(100000, 999999);
        // 2. 记录执行前的Log前缀
        //    业务执行完成后重置
        $logger = $server->getLogger();
        $prefix = $server->getLogger()->getPrefix();
        $logger->setPrefix("%s[r=%s][z=%d]", $prefix, $uniqid, $taskId);
        // 3. 内存表记数
        $table = $server->getStatsTable();
        $table->incrTaskOn();
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
            $logger->enableDebug() && $logger->debug("任务开始,申请内存{%.01f}M内存", $memory);
            $result = $this->doTask($server, $taskId, $uniqid, $data['class'], $data['params']);
            $logger->debug("[d=%.06f]任务完成", microtime(true) - $begin);
            $this->stopTaskerAfterOnTask($server, $usage);
            return $result != false;
        } catch(\Throwable $e) {
            $table->incrTaskOnFail();
            if ($e instanceof \App\Errors\Error) {
                $logger->enableDebug() && $logger->debug("[d=%.06f][exception=%s]任务出错 - %s - 位于{%s}第{%d}行", microtime(true) - $begin, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
            } else {
                $logger->error("[d=%.06f][exception=%s]任务出错 - %s - 位于{%s}第{%d}行", microtime(true) - $begin, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
            }
            return false;
        } finally {
            $logger->setPrefix($prefix);
            $this->stopTaskerAfterOnTask($server, $usage);
        }
    }

    /**
     * @param XHttp $server
     * @param int   $usage
     */
    private function stopTaskerAfterOnTask($server, int $usage)
    {
        if ($usage > $server->getConfig()->getAllowMemory()) {
            $server->stop($server->getWorkerId(), true);
        }
    }
}
