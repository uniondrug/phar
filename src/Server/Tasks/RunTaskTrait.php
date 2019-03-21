<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tasks;

use Uniondrug\Phar\Server\Services\Http;
use Uniondrug\Phar\Server\Services\Socket;

/**
 * 异步任务
 * @package Uniondrug\Phar\Server\Services\Traits
 */
trait RunTaskTrait
{
    public function doTask()
    {
    }

    /**
     * 投递异步Task
     * @param string     $class
     * @param array|null $data
     * @return bool
     */
    public function runTask(string $class, array $data = null)
    {
        /**
         * 1. 消息内容
         * @var Http|Socket $server
         */
        $server = $this;
        $server->getStatsTable()->incrTaskRun();
        if ($server->getLogger()->isStdout()) {
            $server->getLogger()->debug("投递{%s}任务", $class);
        }
        $message = json_encode([
            'class' => $class,
            'params' => is_array($data) ? $data : []
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($server->isWorker() && !$server->isTasker()) {
            return $server->task($message, -1) !== false;
        }
        return $this->sendMessage($message, 0);
    }
}
