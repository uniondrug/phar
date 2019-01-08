<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-04
 */
namespace Uniondrug\Phar\Server\Managers\Agents;

/**
 * Reload
 * @package Uniondrug\Phar\Server\Managers\Agents
 */
class ReloadAgent extends Abstracts\Agent
{
    public function run()
    {
        $this->server->getLogger()->debug("收到{%s}指令", "RELOAD");
        $this->server->reload();
    }
}
