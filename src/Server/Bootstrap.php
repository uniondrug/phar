<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server;

use Uniondrug\Phar\Server\Exceptions\ClientExeption;
use Uniondrug\Phar\Server\Managers\Clients\HelpClient;
use Uniondrug\Phar\Server\Managers\Clients\IClient;

/**
 * Boot管理器
 * @package Uniondrug\Phar\Bootstrap
 */
class Bootstrap
{
    /**
     * 命令行
     * @var Args
     */
    protected $args;
    /**
     * Server配置
     * @var Config
     */
    protected $config;
    /**
     * Logger实例
     * @var Logger
     */
    protected $logger;

    /**
     * @param Args   $args
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(Args $args, Config $config, Logger $logger)
    {
        $this->args = $args;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return Args
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * 按Command执行
     */
    public function run()
    {
        // 1. chose command
        $command = $this->args->getCommand();
        if ($command === null) {
            $class = HelpClient::class;
        } else {
            $class = "\\Uniondrug\\Phar\\Server\\Managers\\Clients\\".ucfirst(preg_replace_callback("/[\-]([\S])/", function($a){
                    return strtoupper($a[1]);
                }, $command))."Client";
        }
        // 2. implements validator
        if (!is_a($class, IClient::class, true)) {
            throw new ClientExeption("unknown {$command} command");
        }
        /**
         * 3. runner
         * @var IClient $client
         */
        $client = new $class($this);
        $this->args->hasOption('help') ? $client->runHelp() : $client->run();
    }
}
