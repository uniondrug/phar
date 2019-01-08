<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-05
 */
namespace Uniondrug\Phar\Server\Managers\Agents;

/**
 * IAgent/Manager接口
 * @package Uniondrug\Phar\Server\Managers\Agents
 */
interface IAgent
{
    /**
     * Agent执行过程
     * @return void
     */
    public function run();
}
