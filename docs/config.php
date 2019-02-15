<?php
/**
 * Phar模式下, 默认配置信息, 各定义项非必须
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-02-13
 */
return [
    'default' => [
        'class' => \Uniondrug\Phar\Server\Http::class,
        'logBatchLimit' => 2048,
        'logBatchSeconds' => 60,
        /**
         * Process进程
         * <code>
         * [
         *     \App\Servers\Processes\NameProcess::class
         * ]
         * </code>
         */
        'processes' => [],
        /**
         * 内存表
         * <code>
         * [
         *     \App\Servers\Tables\ExampleTable::class => 128
         * ]
         * </code>
         */
        'tables' => [],
        /**
         * Swoole启动参数
         * @link https://wiki.swoole.com/wiki/page/274.html
         */
        'settings' => [
            'reactor_num' => 8,
            'worker_num' => 8,
            'max_request' => 20000,
            'task_worker_num' => 2,
            'task_max_request' => 20000,
            'log_level' => 0,
            'request_slowlog_file' => ''
        ]
    ],
    'development' => [
        'host' => '0.0.0.0:18000',
        'logKafkaOn' => false,
        'logKafkaUrl' => 'http://log.dev.dovecot.cn/log/saveall'
    ],
    'testing' => [
        'host' => '0.0.0.0:8101',
        'logKafkaOn' => false,
        'logKafkaUrl' => 'http://log.test.dovecot.cn/log/saveall'
    ],
    'release' => [
        'host' => 'eth0:8101',
        'logKafkaOn' => false,
        'logKafkaUrl' => 'http://log.uniondrug.net/log/saveall'
    ],
    'production' => [
        'host' => 'eth0:8101',
        'logKafkaOn' => false,
        'logKafkaUrl' => 'http://log.uniondrug.cn/log/saveall'
    ]
];
