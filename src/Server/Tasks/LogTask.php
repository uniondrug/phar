<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tasks;

use Uniondrug\Phar\Server\Logs\Adapters\FileAdapter;
use Uniondrug\Phar\Server\Logs\Adapters\KafkaAdapter;
use Uniondrug\Phar\Server\Logs\Adapters\RedisAdapter;

/**
 * 异步保存Logger
 * @package Uniondrug\Phar\Server\Tasks
 */
class LogTask extends XTask
{
    /**
     * 前置
     * 在Logger过程中, 不在记录Logger
     * @return bool
     */
    public function beforeRun()
    {
        $this->getServer()->getLogger()->ignoreProfile();
        return parent::beforeRun();
    }

    /**
     * 选择Logger模式
     */
    public function run()
    {
        $this->getServer()->getConfig()->isRedisLogger() && $this->getServer()->getLogger()->senderAdapter(RedisAdapter::class, $this->data);
        $this->getServer()->getConfig()->isKafkaLogger() && $this->getServer()->getLogger()->senderAdapter(KafkaAdapter::class, $this->data);
        $this->getServer()->getConfig()->isFileLogger() && $this->getServer()->getLogger()->senderAdapter(FileAdapter::class, $this->data);
    }
}
