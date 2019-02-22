<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events\Http;

use App\Errors\Error as AppError;
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
        // 2. 计算请求ID
        $reqKey = strtolower(HttpHandler::REQID_KEY);
        $reqName = strtolower(HttpHandler::REQID_HTTP);
        $requestId = isset($request->header[$reqKey]) ? $request->header[$reqKey] : null;
        $requestId || $requestId = isset($request->header[$reqName]) ? $request->header[$reqName] : null;
        $requestId || $requestId = 'r'.date('ymdHis').uniqid().mt_rand(100000, 999999);
        // 3. 请求地址
        $url = isset($request->server['request_uri']) ? $request->server['request_uri'] : '/';
        // 4. 请求方式
        $method = isset($request->server['request_method']) ? strtoupper($request->server['request_method']) : 'NULL';
        // 5. Logger前缀
        $prefix = $server->getLogger()->getPrefix();
        $server->getLogger()->setPrefix("%s[r=%s][m=%s][u=%s]", $prefix, $requestId, $method, $url);
        // 6. 请求Handler
        $handler = new HttpHandler($server, $request, $response);
        $handler->setRequestId($requestId);
        $handler->setUrl($url);
        try {
            if ($handler->isHealthRequest()) {
                // 7. 检查检查
                $server->doHealthRequest($server, $handler);
            } else if ($handler->isManagerRequest()) {
                // 8. 以127.0.0.1访问管理
                $server->doManagerRequest($server, $handler);
            } else {
                // 9. 用户请求
                $server->doRequest($server, $handler);
            }
        } catch(\Throwable $e) {
            // m. 请求有异常
            $handler->setContent($e->getMessage());
            if ($e instanceof AppError) {
                // m1. 业务异常
                $server->getLogger()->enableDebug() && $server->getLogger()->debug("业务异常 - %s", $e->getMessage());
            } else {
                // m2. 发送报警
                $server->getLogger()->error("严重异常 - %s", $e->getMessage());
            }
        } finally {
            // n. 结束请求
            $restart = $handler->end();
            unset($handler);
            // n1. 重设Logger前缀
            // n2. 退出Worker进程/由Manager进程重启
            if ($restart) {
                $server->stop($this->getWorkerId());
            } else {
                $server->getLogger()->setPrefix($prefix);
            }
        }
    }
}
