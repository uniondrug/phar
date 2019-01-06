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
        $result = ['stats' => []];
        // 1. stats
        $stats = $this->server->stats();
        foreach ($stats as $key => $value){
            $name = preg_replace_callback("/_(\S)/", function($a){
                print_r($a);
                return strtoupper($a[1]);
            }, $key);
            $result['stats'][$name] = $value;
        }

        print_r ($result);

//        $this->handler->setContent();

        $this->server->getLogger()->debug("收到{%s}指令", "STOP");
        $this->server->shutdown();
    }
}
