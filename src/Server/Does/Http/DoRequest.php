<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does\Http;

use Uniondrug\Phar\Server\Handlers\HttpHandler;

/**
 * 处理HTTP请求
 * @package Uniondrug\Phar\Server\Does
 */
trait DoRequest
{
    /**
     * 处理HTTP请求
     * @param HttpHandler $handler
     * @throws \Exception
     */
    public function doRequest(HttpHandler $handler)
    {
        // 1. 静态资源
        if ($handler->isAssetsRequest()) {
            throw new \Exception("忽略静态{".$handler->getUri()."}资源", 304);
        }
        // 2. 运行容器
        $this->runContainer($handler);
    }
}
