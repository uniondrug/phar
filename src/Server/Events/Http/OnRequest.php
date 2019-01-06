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
        $handler->addResponseContentType();
        $handler->addResponseHeader(HttpHandler::REQID_KEY, $handler->getRequestId());
        $handler->addResponseHeader('Server', $server->getConfig()->getServerSoft());
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
            $server->getLogger()->error("请求获得{%d}失败 - %s - 位于{%s}的第{%d}行", $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
        // 4. 返回结果
        $response->status($handler->getStatusCode());
        // 4.1 Header
        foreach ($handler->getResponseHeader() as $key => $value) {
            $response->header($key, $value);
        }
        // 4.2 Cookie
        foreach ($handler->getResponseCookie() as $cookie) {
            $response->cookie($cookie[0], $cookie[1], $cookie[2], $cookie[3], $cookie[4], $cookie[5], $cookie[6]);
        }
        // 5. 打印内容
        $response->end($handler->getContent());
        // 6. 释放资源
//        unset($handler);
    }
}
