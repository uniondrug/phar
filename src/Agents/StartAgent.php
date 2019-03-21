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
            'name' => 'error',
            'value' => 'ALL',
            'desc' => 'PHP错误级别是, 默认: {yellow=ALL}, 可选: {yellow=ALL}、{yellow=ERROR}、{yellow=PARSE}、{yellow=WARNING}、{yellow=OFF}'
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
            'name' => 'log-level',
            'value' => 'str',
            'desc' => '日志级别, 可选: {yellow=DEBUG}、{yellow=INFO}、{yellow=WARNING}、{yellow=ERROR}, 默认: {yellow=DEBUG}'
        ],
        [
            'name' => 'log-stdout',
            'desc' => '在标准输出打印日志内容, 禁用文件/Kafka日志'
        ],
        [
            'name' => 'consul-register',
            'value' => 'URL',
            'desc' => '服务注册, 项目启动时请求Consul注册服务'
        ],
        [
            'name' => 'consul-name',
            'value' => 'str',
            'desc' => '服务名称, 默认: 域名前缀'
        ],
        [
            'name' => 'consul-address',
            'value' => 'str',
            'desc' => '服务地址, 默认: 项目域名加80端口'
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
        $script = $this->getRunner()->getConfig()->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("启动脚本: %s %s [{yellow=选项}]", $script, $this->getRunner()->getConfig()->getArgs()->getCommand());
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
