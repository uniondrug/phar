<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-01
 */
namespace Uniondrug\Phar\Server\Frameworks;

use App\Http\Application;
use Pails\Container;
use Phalcon\Di;
use Phalcon\Http\Response;
use Uniondrug\Framework\Request;
use Uniondrug\Phar\Server\Args;
use Uniondrug\Phar\Server\Bootstrap;
use Uniondrug\Phar\Server\Handlers\HttpHandler;
use Uniondrug\Phar\Server\Logger;
use Uniondrug\Phar\Server\XOld;

/**
 * Phalcon应用支持
 * @package Uniondrug\Phar\Server\Frameworks
 */
trait OldPhalcon
{
    /**
     * Phalcon应用
     * @var Application
     */
    private $application;
    /**
     * Phalcon容器
     * @var Container
     */
    private $container;
    /**
     * 连接刷新时间
     * 最近一次MySQL/Redis连接刷新时间
     * @var int
     */
    protected $connectionActived = 0;
    /**
     * 连接刷新频次
     * 每隔5秒, 检查连接是否已断开
     * @var int
     */
    protected $connectionFrequences = 5;
    /**
     * 刷新MySQL共享名称
     * @var array
     */
    protected $connectionMysqls = [
        'db',
        'dbSlave'
    ];
    /**
     * 刷新Redis共享名称
     * @var array
     */
    protected $connectionRedises = ['redis'];

    /**
     * 读取Phalcon应用
     * @return Application
     */
    public function getApplication()
    {
        return $this->phalconLoader()->application;
    }

    /**
     * 读取Phalcon容器
     * @return Container
     */
    public function getContainer()
    {
        return $this->phalconLoader()->container;
    }

    /**
     * 运行Phalcon容器
     * @param HttpHandler $handler
     */
    public function runContainer(HttpHandler $handler)
    {
        /**
         * 1. begin
         * @var Bootstrap $b
         */
        $b = $this->boot;
        /**
         * 2. init container
         */
        $this->phalconLoader();
        // 3. init logger
        $logger = $this->container->getShared('logger');
        /**
         * 4. assign phalcon request
         * @var Request  $request
         * @var Response $result
         */
        $request = $this->container->getShared('request');
        $handler->assignPhalcon($request);
        $logger->setPrefix($b->getLogger()->getPrefix());
        // 5. run progress
        try {
            $result = $this->application->handle($handler->getUri());
            if (!$result instanceof Response) {
                $result = new Response();
                $result->setStatusCode(200);
                $result->setContent('{}');
                $result->setContentType("application/json;charset=utf-8");
            }
        } catch(\Throwable $e) {
            $logger->error("请求获得{%d}号错误 - %s - 位于{%s}第{%d}行", $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            $result = new Response();
            $result->setStatusCode(500);
            $result->setContent('{"errno":1,"error":"'.addslashes($e->getMessage()).'","data":{},"dataType":"ERROR"}');
            $result->setContentType("application/json;charset=utf-8");
        }
        $code = (int) $result->getStatusCode();
        $handler->setStatusCode($code);
        $handler->setContent((string) $result->getContent());
    }

    /**
     * 加载Phalcon框架
     * @return $this
     */
    private function phalconLoader()
    {
        /**
         * 1. 创建实例
         * @var XOld $server
         * @var Args $args
         */
        $server = $this;
        if ($this->application === null || $this->container === null) {
            $cfg = $server->getConfig();
            $args = $server->getArgs();
            $server->getLogger()->debug("初始化Phalcon容器");
            // 1.0 Constants
            defined("APP_DEBUG") || define("APP_DEBUG", false);
            // 1.1 create object
            $this->container = new Container($args->getBasePath());
            // 1.2 set shared server
            $this->container->setShared('server', $server);
            $this->container->setShared('request', new Request());
            // 1.3 remove/reset shared logger
            $this->container->setShared('logger', function() use ($server, $cfg, $args){
                $logger = new Logger($args);
                $logger->setServer($server);
                $logger->setLogLevel($cfg->logLevel);
                return $logger;
            });
            Di::setDefault($this->container);
            // 1.4 application boot
            $this->application = new Application($this->container);
            $this->application->boot();
        }
        // 2. 刷新连接
        $limitTime = time() - $this->connectionFrequences;
        if ($this->connectionActived < $limitTime) {
            $this->connectionActived = time();
            $this->phalconLoaderMysql();
            $this->phalconLoaderRedis();
        }
        // 3. 返回实例
        return $this;
    }

    /**
     * 刷新MySQL连接
     */
    private function phalconLoaderMysql()
    {
    }

    /**
     * 刷新Redis连接
     */
    private function phalconLoaderRedis()
    {
    }
}
