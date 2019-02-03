<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Processes;

use Uniondrug\Phar\Server\Tasks\LogTask;

/**
 * 日志管理器
 * @package Uniondrug\Phar\Server\Processes
 */
class LogProcess extends XProcess
{
    /**
     * 注册定时器
     * 每隔N秒, 发送一次日志
     */
    public function run()
    {
        // disable log storage.
        if ($this->getServer()->getArgs()->hasOption('log-stdout')) {
            return;
        }
        // enable log process
        $seconds = (int) $this->getServer()->getConfig()->logBatchSeconds;
        $seconds > 1 || $seconds = 5;
        $this->getServer()->getLogger()->debug("设置每隔{%d}秒后,保存一次日志", $seconds);
        $this->getServer()->tick($seconds * 1000, [
            $this,
            'publishLogs'
        ]);
    }

    /**
     * 保存日志
     */
    public function publishLogs()
    {
        $data = $this->getServer()->getLogTable()->flush();
        if ($data !== null) {
            $this->getServer()->runTask(LogTask::class, $data);
        }
    }
}
