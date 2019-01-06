<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * 列出Server配置
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class ConfigClient extends Abstracts\Client
{
    /**
     * 运行Server状态
     */
    public function run()
    {
        $config = unserialize($this->boot->getConfig()->generate());
        echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT |JSON_UNESCAPED_SLASHES)."\n";
    }
}
