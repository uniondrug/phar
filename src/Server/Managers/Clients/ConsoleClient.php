<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * 控制台操作
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class ConsoleClient extends Abstracts\Client
{
    protected static $title = '执行脚本';
    protected static $description = '兼容Command脚本运行方式';
    protected static $options = [];

    public function run() : void
    {
    }
}
