<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-04
 */
namespace Uniondrug\Phar\Server\Managers\Agents\Abstracts;

use Uniondrug\Phar\Server\Handlers\HttpHandler;
use Uniondrug\Phar\Server\Managers\Agents\IAgent;
use Uniondrug\Phar\Server\XHttp;

/**
 * Agent/Manger基类
 * @package Uniondrug\Phar\Server\Managers\Agents\Abstracts
 */
abstract class Agent implements IAgent
{
    /**
     * @var HttpHandler
     */
    protected $handler;
    /**
     * @var XHttp
     */
    protected $server;

    /**
     * @param XHttp       $server
     * @param HttpHandler $handler
     */
    public function __construct(XHttp $server, HttpHandler $handler)
    {
        $this->server = $server;
        $this->handler = $handler;
    }
}
