<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents;

use Uniondrug\Phar\Exceptions\ServiceException;
use Uniondrug\Phar\Server\Services\Http;
use Uniondrug\Phar\Server\Services\Socket;
use Uniondrug\Phar\Server\XHttp;

/**
 * 启动服务
 * @package Uniondrug\Phar\Agents
 */
class StartAgent extends Abstracts\Agent
{
    protected static $title = '启动服务';
    protected static $description = '启动Http/WebSocket服务';
    /**
     * 启动选项
     * @var array
     */
    protected static $options = [
        [
            'name' => 'daemon',
            'short' => 'd',
            'desc' => '以守护进程启动'
        ],
        [
            'name' => 'env',
            'short' => 'e',
            'value' => 'name',
            'desc' => '指定环境名, 可选: {yellow=development}、{yellow=testing}、{yellow=release}、{yellow=production}, 默认: {yellow=development}'
        ],
        [
            'name' => 'eth0',
            'value' => 'eth0',
            'desc' => '自定义内网网卡名称, 默认: {red=eth0}'
        ],
        [
            'name' => 'host',
            'value' => 'ip|eth',
            'desc' => '指定IP地址, 默认: 从配置文件{yellow=config/server.php}中读取'
        ],
        [
            'name' => 'port',
            'value' => 'int',
            'desc' => '指定端口号, 默认: 从配置文件{yellow=config/server.php}中读取'
        ],
        [
            'name' => 'reactor-num',
            'value' => 'int',
            'desc' => 'Master进程的线程数, 一般为CPU核心数的2倍, 默认: {red=8}个'
        ],
        [
            'name' => 'worker-num',
            'value' => 'int',
            'desc' => 'Worker进程数, 默认: {red=8}个'
        ],
        [
            'name' => 'tasker-num',
            'value' => 'int',
            'desc' => 'Tasker进程数, 默认: {red=8}个'
        ],
        [
            'name' => 'disable-cron',
            'desc' => '禁用Crontab定时器, 当Cron有NotAllowDisable注解定义时不受此约束'
        ],
        [
            'name' => 'error',
            'value' => 'str',
            'desc' => '错误级别, 可选: {yellow=ERROR}、{yellow=WARNING}、{yellow=ALL}, 默认按启动变量设置'
        ],
        [
            'name' => 'log-level',
            'desc' => '日志级别, 可选: {yellow=DEBUG}、{yellow=INFO}、{yellow=WARNING}、{yellow=ERROR}, 默认: {yellow=DEBUG}'
        ],
        [
            'name' => 'log-stdout',
            'desc' => '在控制台打印日志, 当启用时不再上报Log、同时也不能写入文件'
        ],
        [
            'name' => 'consul-register',
            'value' => 'URL',
            'desc' => '服务注册, 例如: 172.16.0.100:8500'
        ],
        [
            'name' => 'consul-name',
            'value' => 'str',
            'desc' => '服务名称, 例如: rule.module'
        ],
        [
            'name' => 'consul-address',
            'value' => 'str',
            'desc' => '服务地址, 例如: rule.module.uniondrug.cn'
        ],
        [
            'name' => 'consul-domain',
            'value' => 'str',
            'desc' => '服务域名后缀, 例如: uniondrug.cn'
        ],
        [
            'name' => 'consul-health',
            'value' => 'str',
            'desc' => '健康检查地址, 例如: 172.16.0.100:8095'
        ],
        [
            'name' => 'consul-heartbeat',
            'value' => 'int',
            'desc' => '健康心跳时长, 例如: 5, 表示5秒检查一次'
        ]
    ];

    /**
     * 运行服务
     */
    public function run()
    {
        /**
         * Http类名
         */
        $class = $this->getRunner()->getConfig()->class;
        $class || $class = XHttp::class;
        if (!is_a($class, Http::class, true) && !is_a($class, Socket::class, true)) {
            throw new ServiceException("unknown '{$class}' server class.");
        }
        /**
         * @var Http|Socket $server
         */
        $server = new $class($this->getRunner());
        $server->start();
    }

    /**
     * 打印帮助
     */
    public function runHelp() : void
    {
        $script = $this->getRunner()->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("启动脚本: %s %s [{yellow=选项}]", $script, $this->getRunner()->getArgs()->getCommand());
        foreach (self::$options as $option) {
            $pre = isset($option['short']) ? "-{$option['short']}," : '   ';
            $opt = "{$pre}--{$option['name']}";
            if (isset($option['value'])) {
                $opt .= '=['.$option['value'].']';
            }
            $txt = isset($option['desc']) ? $option['desc'] : '';
            $this->printLine("          {yellow=%s} %s", sprintf("%-28s", $opt), $txt);
        }
    }
}
