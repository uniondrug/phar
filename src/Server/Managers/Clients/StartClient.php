<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

use Uniondrug\Phar\Server\XHttp;

/**
 * 启动Server
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class StartClient extends Abstracts\Client
{
    /**
     * 描述
     * @var string
     */
    protected static $description = 'start http server';
    /**
     * 名称
     * @var string
     */
    protected static $title = '启动服务';
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
        //[
        //    'name' => 'enable-crontab',
        //    'desc' => '启用定时任务, 类似于crontab'
        //],
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
     * 载入配置
     */
    public function loadConfig()
    {
        $this->boot->getConfig()->fromFiles()->mergeArgs();
    }

    /**
     * 启动HTTP服务
     */
    public function run() : void
    {
        /**
         * @var XHttp $server
         */
        $this->boot->getArgs()->makeTmpDir();
        $this->boot->getArgs()->makeLogDir();
        $daemon = $this->boot->getArgs()->hasOption('d');
        $daemon || $daemon = $this->boot->getArgs()->hasOption('daemon');
        $this->boot->getConfig()->setDaemon($daemon);
        $class = $this->boot->getConfig()->class;
        $server = new $class($this->boot);
        $server->start();
    }

    /**
     * 打印帮助
     */
    public function runHelp() : void
    {
        $script = $this->boot->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("启动脚本: %s %s [{yellow=选项}]", $script, $this->boot->getArgs()->getCommand());
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
