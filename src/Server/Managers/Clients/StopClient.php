<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * 关闭服务
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class StopClient extends Abstracts\Client
{
    /**
     * 描述
     * @var string
     */
    protected static $description = 'stop http server';
    /**
     * 名称
     * @var string
     */
    protected static $title = '退出服务';

    /**
     * 启动HTTP服务
     */
    public function run() : void
    {
        $this->callAgent("PUT", "/stop");
    }
}
