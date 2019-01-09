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
     * 描述
     * @var string
     */
    protected static $description = '退出Worker/Tasker进程, 并重启';
    /**
     * 名称
     * @var string
     */
    protected static $title = '服务重载';

    public function run() : void
    {
        $this->callAgent("PUT", "/reload");
    }
}
