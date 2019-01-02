<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does\Http;

use Uniondrug\Phar\Server\Handlers\HttpHandler;
use Uniondrug\Phar\Server\Logger;

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
            throw new \Exception("ignored by assets request", 400);
        }
        // 2. 运行容器
        $logger = $this->runContainer($handler);
        if ($logger instanceof Logger) {
            $this->runTask($this->getConfig()->logTask, $logger->endLogData());
        }
    }
}
