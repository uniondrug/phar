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
         * @var XHttp $server
         */
        $server = $this;
        try {
            // 1. 请求准备
            $handler = new HttpHandler($request, $response);
            $handler->addResponseHeader('Server', $server->getConfig()->getServerSoft());
            $handler->addResponseHeader(HttpHandler::REQID_KEY, $handler->getRequestId());
            // 2. 请求过程
            //    当请求过程返回FALSE时, 以无效请求
            //    处理
            $server->doRequest($handler);
            // 3. 请求完成
            //    a) 状态码
            //    b) Headers
            //    c) Cookies
            $response->status($handler->getStatusCode());
            foreach ($handler->getResponseHeader() as $key => $value) {
                $response->header($key, $value);
            }
            foreach ($handler->getResponseCookie() as $cookie) {
                $response->cookie($cookie[0], $cookie[1], $cookie[2], $cookie[3], $cookie[4], $cookie[5], $cookie[6]);
            }
            // 4. 打印内容
            $response->end($handler->getContent());
        } catch(\Throwable $e) {
            // 5. uncatch/运行异常
            //    请求执行过程中, 出现uncatch异常时
            //    以无效请求处理
            $response->status(200);
            $response->end('{"errno":400,"error":"Bad Request","data":{},"dateType":"OBJECT"}');
            $server->getLogger()->error("请求失败 - (%d) %s - %s(%d)", $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }
}
