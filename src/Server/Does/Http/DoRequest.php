<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does\Http;

use Uniondrug\Phar\Server\Handlers\HttpHandler;
use Uniondrug\Phar\Server\Managers\Agents\IAgent;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;

/**
 * 处理HTTP请求
 * @package Uniondrug\Phar\Server\Does
 */
trait DoRequest
{
    /**
     * 处理HTTP请求
     * @param XHttp|XOld  $server
     * @param HttpHandler $handler
     * @throws \Exception
     */
    public function doRequest($server, HttpHandler $handler)
    {
        // 1. 静态资源
        if ($handler->isAssetsRequest()) {
            $server->getLogger()->enableDebug() && $server->getLogger()->debug("%s忽略静态资源", $handler->getRequestHash());
            return;
        }
        // 2. 运行容器
        $this->runContainer($handler);
    }

    /**
     * 健康检查请求
     * @param XHttp|XOld  $server
     * @param HttpHandler $handler
     */
    public function doHealthRequest($server, HttpHandler $handler)
    {
        // /sidecar.health
        $handler->addResponseHeader('content-type', 'application/json');
        $handler->setStatusCode(200);
        if ($handler->getUri() === '/consul.health') {
            $table = $server->getStatsTable();
            $stats = $server->stats();
            $stats['statistic'] = [];
            foreach ($table as $key => $data){
                $stats['statistic'][$key] = $table->getCount($key);
            }
            $handler->setContent(json_encode($stats));
        } else {
            $handler->setContent('{"status":"UP"}');
        }
    }

    /**
     * 管理端请求
     * @param XHttp|XOld  $server
     * @param HttpHandler $handler
     */
    public function doManagerRequest($server, HttpHandler $handler)
    {
        $info = $handler->getClientInfo();
        // 1. info error
        if ($info === false) {
            $handler->setStatusCode(403);
            $handler->setContent("HTTP 403 FORBIDDEN");
            $server->getLogger()->warning("%s读取ClientInfo返回false", $handler->getRequestHash());
            return;
        }
        // 2. not allowed
        if ($info['host'] !== "127.0.0.1:{$server->getConfig()->port}") {
            $handler->setStatusCode(403);
            $handler->setContent("HTTP 403 FORBIDDEN");
            $server->getLogger()->warning("%s限{127.0.0.1:%d}访问Manager", $handler->getRequestHash(), $server->getConfig()->port);
            return;
        }
        // 3. manager check
        if (preg_match("/^([\/]+\S+)\.agent/", $handler->getUri(), $m)) {
            // 3.1 generate
            $class = "\\Uniondrug\\Phar\\Server\\Managers\\Agents\\".preg_replace_callback("/\/+(\S)/", function($a){
                    return strtoupper($a[1]);
                }, $m[1])."Agent";
            // 3.2 not implement
            if (!is_a($class, IAgent::class, true)) {
                $handler->setStatusCode(400);
                $handler->setContent("HTTP 400 Bad Request");
                $server->getLogger()->warning("%s无效的Manager - %s", $handler->getRequestHash(), $class);
                return;
            }
            /**
             * 3.3 runner
             * @var IAgent $agent
             */
            $agent = new $class($server, $handler);
            $agent->run();
            return;
        }
        $handler->setStatusCode(404);
        $handler->setContent("HTTP 404 Not Found");
        $server->getLogger()->warning("%s未定义的Manager", $handler->getRequestHash());
    }
}
