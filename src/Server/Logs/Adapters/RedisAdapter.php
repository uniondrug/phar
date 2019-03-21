<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Logs\Adapters;

use Uniondrug\Phar\Server\Logs\Abstracts\Adapter;

/**
 * Logger写入Redis
 * @package Uniondrug\Phar\Server\Logs\Adapters
 */
class RedisAdapter extends Adapter
{
    /**
     * @var \Redis
     */
    private static $redis;
    private static $keyPrefix;
    private static $keyRandom;

    /**
     * 发送到Redis
     * @inheritdoc
     */
    public function run(array $datas)
    {
        // 1. prepare key
        self::$keyPrefix === null && self::$keyPrefix = $this->logger->getConfig()->getRedisKey();
        self::$keyRandom = 't'.date('dHis').mt_rand(1001, 9999).mt_rand(1001, 9999);
        // 2. parser logger
        $list = [];
        foreach ($datas as $i => $data) {
            $list[self::$keyRandom.sprintf("%04d", $i)] = $this->parserLogger($data);
        }
        // 3. check status
        $this->createRedis();
        $this->checkHealth();
        // 4. append redis
        $keys = [];
        $deadline = $this->logger->getConfig()->getRedisDeadline();
        foreach ($list as $name => $value) {
            $key = self::$keyPrefix.':'.$name;
            self::$redis->set($key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $deadline);
            $keys[] = $key;
        }
        count($keys) && self::$redis->rPush(self::$keyPrefix.':list', ... $keys);
    }

    /**
     * 创建Redis实例
     */
    private function connect()
    {
        // 1. 配置参数
        $conf = $this->logger->getConfig()->getRedisCfg();
        $conf['host'] = isset($conf['host']) ? $conf['host'] : '127.0.0.1';
        $conf['port'] = isset($conf['port']) ? $conf['port'] : 6379;
        $conf['timeout'] = isset($conf['timeout']) ? $conf['timeout'] : 10;
        $conf['auth'] = isset($conf['auth']) ? (string) $conf['auth'] : '';
        $conf['index'] = isset($conf['index']) && is_numeric($conf['index']) ? (int) $conf['index'] : 0;
        // 2. 重新连接
        self::$redis->connect($conf['host'], $conf['port'], $conf['timeout']);
        $conf['auth'] === '' || self::$redis->auth($conf['auth']);
        $conf['index'] > 0 && self::$redis->select($conf['index']);
    }

    /**
     * 健康检查
     * @param bool $retry
     * @throws \Throwable
     */
    private function checkHealth(bool $retry = false)
    {
        try {
            self::$redis->ping();
        } catch(\Throwable $e) {
            if ($retry) {
                throw $e;
            }
            $this->connect();
            $this->checkHealth(true);
        }
    }

    /**
     * 创建Redis实例
     */
    private function createRedis()
    {
        if (self::$redis === null) {
            self::$redis = new \Redis();
            $this->connect();
        }
    }
}
