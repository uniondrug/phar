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
    public function beforeRun()
    {
        parent::beforeRun();
        $this->printLine("操作: 发送停止指令");
    }

    /**
     * 启动HTTP服务
     */
    public function run()
    {
        $this->callAgent("PUT", "/stop");
    }
}
