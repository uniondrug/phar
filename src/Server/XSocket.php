<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server;

use Uniondrug\Phar\Server\Services\HttpDispatcher;

/**
 * WebSocket服务
 * 基于uniondrug/framework的WebSocket服务
 * @package Uniondrug\Phar\Server
 */
class XSocket extends Services\Socket
{
    /**
     * 初始化Phalcon框架
     * @param XHttp|XSocket|XOld $server
     */
    function frameworkInitialize($server)
    {
    }

    /**
     * 初始化Phalcon框架
     * @param XHttp|XSocket|XOld $server
     */
    function frameworkReConnect($server)
    {
    }

    /**
     * 转发请求给Phalcon框架
     * @param XHttp|XSocket|XOld $server
     * @param HttpDispatcher     $dispatcher
     */
    function frameworkRequest($server, HttpDispatcher $dispatcher)
    {
    }
}
