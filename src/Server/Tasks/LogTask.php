<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tasks;

use Uniondrug\Phar\Server\Logs\Adapters\FileAdapter;
use Uniondrug\Phar\Server\Logs\Adapters\KafkaAdapter;
use Uniondrug\Phar\Server\Logs\Adapters\RedisAdapter;
use Uniondrug\Phar\Server\Logs\Adapters\StdoutAdapter;
use Uniondrug\Phar\Server\Logs\Logger;

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
     * @return string
     */
    public function run()
    {
        // 1. 写入Redis
        //    将日志写入到Redis, Java端从Redis中提取发布到Kafka
        //    然后通过eshead查询
        if ($this->getServer()->getConfig()->isRedisLogger()) {
            try {
                $this->getServer()->getLogger()->senderAdapter(RedisAdapter::class, $this->data);
                return "redis";
            } catch(\Throwable $e) {
                $this->data[] = $this->getServer()->getLogger()->formatData(Logger::LEVEL_ERROR, $this->getServer()->getLogger()->getPrefix(true), "向Redis发送Logger出错 - ".$e->getMessage());
            }
        }
        // 2. 直对LoggerAPI
        //    异步调用LoggerAPI, 由API处理Logger数据
        if ($this->getServer()->getConfig()->isKafkaLogger()) {
            try {
                $this->getServer()->getLogger()->senderAdapter(KafkaAdapter::class, $this->data);
                return "kafka";
            } catch(\Throwable $e) {
                $this->data[] = $this->getServer()->getLogger()->formatData(Logger::LEVEL_ERROR, $this->getServer()->getLogger()->getPrefix(true), "向Kafka发送Logger失败 - ".$e->getMessage());
            }
        }
        // 3. 本地落盘
        try {
            $this->getServer()->getLogger()->senderAdapter(FileAdapter::class, $this->data);
            return "local";
        } catch(\Throwable $e) {
            $this->data[] = $this->getServer()->getLogger()->formatData(Logger::LEVEL_ERROR, $this->getServer()->getLogger()->getPrefix(true), "Logger写入文件失败 - ".$e->getMessage());
        }
        // 4. 写入字符流
        //    log/server.log
        $this->getServer()->getLogger()->senderAdapter(StdoutAdapter::class, $this->data);
        return "failure";
    }
}
