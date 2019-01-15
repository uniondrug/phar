<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * 查看配置
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class ConfigClient extends Abstracts\Client
{
    protected static $title = '查看配置';
    protected static $description = '查看系统运行时的配置信息';
    protected static $options = [];

    public function run() : void
    {
    }
}
