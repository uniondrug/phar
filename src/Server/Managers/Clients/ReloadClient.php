<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * 重新加载
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class ReloadClient extends Abstracts\Client
{
    /**
     * 名称
     * @var string
     */
    protected static $title = '重载服务';

    public function run() : void
    {
        $this->callAgent("PUT", "/reloadxd");
    }
}
