<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients\Abstracts;

use Uniondrug\Phar\Server\Managers\Clients\IClient;
use Uniondrug\Phar\Server\Bootstrap;

/**
 * Client基础类
 * @package Uniondrug\Phar\Server\Managers\Clients\Abstracts
 */
abstract class Client implements IClient
{
    /**
     * @var Bootstrap
     */
    public $boot;

    public function __construct(Bootstrap $boot)
    {
        $this->boot = $boot;
        $this->loadConfig();
    }

    /**
     * 从历史记录读取配置
     */
    public function loadConfig()
    {
        $this->boot->getConfig()->fromHistory()->mergeArgs();
    }
}
