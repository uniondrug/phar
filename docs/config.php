<?php
/**
 * 完整的Server配置片段
 */
return [
    'default' => [
        'charset' => 'utf-8',
        'contentType' => 'application/json;charset=utf-8',
        'statusCode' => 200,
        'slowRequestDuration' => 1.0,
        'memoryLimit' => 0,
        'serverMode' => SWOOLE_PROCESS,
        'serverSockType' => SWOOLE_SOCK_TCP,
        'processStdInOut' => false,
        'processCreatePipe' => true,
        // Server实例
        'class' => \Uniondrug\Phar\Server\XHttp::class,
        // 事件回调
        'events' => [],
        // 内存表
        'tables' => [],
        // Process进程
        'processes' => [],
        // Swoole启动选项
        // @link https://wiki.swoole.com/wiki/page/274.html
        'settings' => [],
        'logger' => [
            // RedisLog
            // 启用此模式时, 系统默认以异步模式将日志写入Redis
            // 的logger:list队列(rpush)中, Kafka客户端主动从
            // 队列logger:list中(lpop)中拉取数据存入Kafka/ES.
            // 最终通过查询ES获取Logger信息
            'logRedisOn' => false,
            'logRedisCfg' => [
                'host' => 'redis.dovecot.cn',
                'port' => 63791,
                'auth' => 'juyin@2018'
            ],
            'logRedisKey' => 'logger',
            'logRedisDeadline' => 2592000,
            // KafkaLogger
            // 当Redis未启用或Redis写入失败时, 以Rest方式调用
            // JavaLogger端提供的接口, 发送给Logger; 最终的查询
            // 方式与Redis相同。
            // 若Redis/Kafka都未开启(或失败), 系统将自动落盘, 即
            // 写入项目所在的log目录下
            'logKafkaOn' => false,
            'logKafkaUrl' => 'http://java.logger.uniondrug.cn/log/saveall',
            'logKafkaTimeout' => 30,
        ]
    ],
    'development' => [
        'host' => '0.0.0.0:8080'
    ],
    'testing' => [
        'host' => '0.0.0.0:8080'
    ],
    'release' => [
        'host' => 'eth1:8080'
    ],
    'production' => [
        'host' => 'eth0:8080'
    ]
];
