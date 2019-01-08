<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-04
 */
namespace Uniondrug\Phar\Server\Managers\Agents;

/**
 * StopAgent
 * @package Uniondrug\Phar\Server\Managers\Agents
 */
class StopAgent extends Abstracts\Agent
{
    /**
     * 停止Server
     */
    public function run()
    {
        $this->server->getLogger()->debug("收到{%s}指令", "STOP");
        $this->server->shutdown();
    }
}
