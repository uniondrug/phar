<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents;

use Uniondrug\Console\Console;
use Uniondrug\Framework\Container;

/**
 * 控制台
 * @package Uniondrug\Phar\Agents
 */
class ConsoleAgent extends Abstracts\Agent
{
    protected static $title = '控制终端';
    protected static $description = '';

    public function run()
    {
        array_shift($_SERVER['argv']);
        $container = new Container($this->getRunner()->getConfig()->getArgs()->basePath());
        $console = new Console($container);
        $console->run();
    }

    public function runHelp()
    {
        $this->run();
    }
}
