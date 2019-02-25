<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does\Http;

use Uniondrug\Phar\Server\Handlers\HttpHandler;
use Uniondrug\Phar\Server\Managers\Agents\IAgent;
use Uniondrug\Phar\Server\Tables\XTable;
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
            $server->getLogger()->enableDebug() && $server->getLogger()->debug("忽略静态资源");
            return;
        }
        // 2. 运行容器
        $server->runContainer($handler);
    }

    /**
     * 健康检查请求
     * @param XHttp|XOld  $server
     * @param HttpHandler $handler
     */
    public function doHealthRequest($server, HttpHandler $handler)
    {
        // /sidecar.health
        $handler->setContentType('application/json');
        $handler->setStatusCode(200);
        $url = $handler->getUri();
        switch ($url) {
            case '/consul.health' :
                $this->healthForConsul($handler, $server);
                break;
            case '/sidecar.health' :
                $this->healthForSidecar($handler);
                break;
            case '/table.health' :
                $this->healthForTables($handler, $server);
                break;
            default :
                $handler->setContent('{}');
                break;
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
            $server->getLogger()->warning("读取ClientInfo返回false");
            return;
        }
        // 2. not allowed
        if ($info['host'] !== "127.0.0.1:{$server->getConfig()->port}") {
            $handler->setStatusCode(403);
            $handler->setContent("HTTP 403 FORBIDDEN");
            $server->getLogger()->warning("限{127.0.0.1:%d}访问Manager", $server->getConfig()->port);
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
                $server->getLogger()->warning("无效的Manager - %s", $class);
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
        $server->getLogger()->warning("未定义的Manager");
    }

    /**
     * @param HttpHandler $handler
     * @param XHttp|XOld  $server
     */
    private function healthForConsul(HttpHandler $handler, $server)
    {
        $table = $server->getStatsTable();
        $stats = $server->stats();
        $stats['start_time'] = date('Y-m-d H:i:s', $stats['start_time']);
        $stats['statistic'] = [];
        foreach ($table as $key => $data) {
            $stats['statistic'][$key] = $table->getCount($key);
        }
        $handler->setContent(json_encode($stats));
    }

    /**
     * @param HttpHandler $handler
     */
    private function healthForSidecar(HttpHandler $handler)
    {
        $handler->setContent('{"status":"UP"}');
    }

    /**
     * 读取内存表信息
     * 遍历全部内存表, 并最多30条数据
     * @param HttpHandler $handler
     * @param XHttp|XOld  $server
     * @param int         $limit
     */
    private function healthForTables(HttpHandler $handler, $server, $limit = 30)
    {
        /**
         * @var array  $data
         * @var array  $tables
         * @var XTable $table
         */
        $data = [];
        $tables = $server->getTables();
        foreach ($tables as $table) {
            $name = $table->getName();
            $data[$name] = [
                'count' => $table->count(),
                'items' => []
            ];
            if ($data[$name] > 0) {
                $i = 0;
                foreach ($table as $key => $item) {
                    $data[$name]['items'][$key] = $item;
                    $i++;
                    if ($i >= $limit) {
                        break;
                    }
                }
            }
        }
        $handler->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
