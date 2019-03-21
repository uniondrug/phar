<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Services\Traits;

use Uniondrug\Phar\Server\Services\HttpDispatcher;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

trait FrameworkTrait
{
    /**
     * 初始化Phalcon框架
     * @param XHttp|XSocket|XOld $server
     */
    abstract function frameworkInitialize($server);

    /**
     * 初始化Phalcon框架
     * @param XHttp|XSocket|XOld $server
     */
    abstract function frameworkReConnect($server);

    /**
     * 转发请求给Phalcon框架
     * @param XHttp|XSocket|XOld $server
     * @param HttpDispatcher     $dispatcher
     */
    abstract function frameworkRequest($server, HttpDispatcher $dispatcher);
}
