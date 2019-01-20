<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * 控制台重定义
 * 本Client仅为Help列表模式, 无须业务逻辑开发, 当执行command
 * 命令时, 系统自动转发到入口控制层.
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class ConsoleClient extends Abstracts\Client
{
    /**
     * 描述
     * @var string
     */
    protected static $description = '兼容原console模式';
    /**
     * 名称
     * @var string
     */
    protected static $title = '命令脚本';

    public function run() : void
    {
    }
}
