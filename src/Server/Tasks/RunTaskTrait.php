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
     * 消息投递内容已经过压缩处理
     * @param string     $class
     * @param array|null $data
     * @return bool
     */
    public function runTask(string $class, array $data = null)
    {
        /**
         * 1. 数量统计
         * @var Http|Socket $server
         */
        $server = $this;
        $server->getStatsTable()->incrTaskRun();
        // 2. 消息内容
        $json = json_encode([
            'class' => $class,
            'params' => is_array($data) ? $data : [],
            'headers' => $server->getTrace()->getAppendTrace(true)
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // 3. 内容压缩
        if ($server->isWorker() && !$server->isTasker()) {
            return $server->task($json, -1) !== false;
        }
        // 4. 管道消息
        return $this->sendMessage($json, 0);
    }
}
