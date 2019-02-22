<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events\Http;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Uniondrug\Phar\Server\Handlers\HttpHandler;
use Uniondrug\Phar\Server\XHttp;

/**
 * 响应HTTP请求
 * @package Uniondrug\Phar\Server\Events
 */
trait OnRequest
{
    /**
     * 响应HTTP请求
     * @param SwooleRequest  $request
     * @param SwooleResponse $response
     */
    public function onRequest($request, $response)
    {
        /**
         * 1. 请求准备
         * @var XHttp $server
         */
        $server = $this;
        $handler = new HttpHandler($server, $request, $response);
        try {
            // 2. 请求过程
            //    当请求过程返回FALSE时, 以无效请求
            //    处理
            if ($handler->isHealthRequest()) {
                // 2.1 检查检查
                $server->doHealthRequest($server, $handler);
            } else if ($handler->isManagerRequest()) {
                // 2.2 以127.0.0.1访问管理
                $server->doManagerRequest($server, $handler);
            } else {
                // 2.3 普通请求
                $server->doRequest($server, $handler);
            }
        } catch(\Throwable $e) {
            // 3. uncatch/运行异常
            //    请求执行过程中, 出现uncatch异常时
            //    以无效请求处理
            $handler->setContent('{"errno":400,"error":"Bad Request","data":{},"dateType":"OBJECT"}');
            $server->getLogger()->error("请求HTTP出错 - %s - 位于{%s}的第{%d}行", $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
        // 4. 释放资源
        $stopWorker = $handler->end();
        unset($handler);
        // 5. 退出进程
        //    内存使用量过大时, 退出Worker进程, Manager
        //    进程将重新启动
        if ($stopWorker) {
            $server->stop($this->getWorkerId());
        }
    }
}
