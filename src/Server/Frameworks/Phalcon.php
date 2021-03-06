<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-01
 */
namespace Uniondrug\Phar\Server\Frameworks;

use App\Errors\Error;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Di;
use Phalcon\Http\Response as PhalconResponse;
use Uniondrug\Framework\Application;
use Uniondrug\Framework\Container;
use Uniondrug\Framework\Request;
use Uniondrug\Phar\Server\Args;
use Uniondrug\Phar\Server\Bootstrap;
use Uniondrug\Phar\Server\Handlers\HttpHandler;
use Uniondrug\Phar\Server\Logger;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Service\Server as ServiceServer;

/**
 * Phalcon应用支持
 * @package Uniondrug\Phar\Server\Frameworks
 */
trait Phalcon
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
    private $containerLoggerPrefix = '';
    /**
     * 连接刷新时间
     * 最近一次MySQL/Redis连接刷新时间
     * @var int
     */
    private $connectionLastActived = 0;
    /**
     * 连接刷新频次
     * 单位: 秒
     * 默认: 5
     * 当值为0时, 表示每次请求前主动检查, 反之由定时器
     * 每隔5秒检查一次连接
     * @var int
     */
    protected $connectionFrequences = 0;
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
    protected $connectionRedises = [
        'redis'
    ];

    /**
     * 注册框架
     * 注册基于Phalcon框架的V2版本应用
     * @return $this
     */
    public function frameworkRegister()
    {
        /**
         * @var XHttp $server
         * @var Args  $args
         */
        $server = $this;
        $this->container = new Container($server->getArgs()->getBasePath());
        $this->container->setShared('server', $server);
        $this->containerLoggerPrefix = $server->getLogger()->getPrefix();
        Di::setDefault($this->container);
        $this->application = new Application($this->container);
        $this->application->boot();
        return $this;
    }

    /**
     * 注册Logger
     * 向Phalcon的Shared中注入Logger对象
     * @param Logger $logger
     * @return $this
     */
    public function frameworkLogger(Logger $logger, $prefix = null)
    {
        $prefix === null || $logger->setPrefix($prefix);
        $this->container->setShared('logger', $logger);
        return $this;
    }

    /**
     * 刷新Connection
     */
    public function frameworkConnection()
    {
        $time = time();
        // 1. 定时器模式
        if ($this->connectionFrequences > 0) {
            if (($this->connectionLastActived + $this->connectionFrequences) >= $time) {
                return;
            }
        }
        // 2. 主动检查
        $this->connectionLastActived = $time;
        $this->phalconLoaderMysql();
        $this->phalconLoaderRedis();
    }

    /**
     * 读取Phalcon应用
     * @return Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * 读取Phalcon容器
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * 运行容器
     * 调用Phalcon容器, 由Application/Container触发Controller
     * 路由, 并将Phalcon容器返回的Response转发给Swoole的Response
     * 实现类似FPM的工作
     * @param HttpHandler $handler
     */
    public function runContainer(HttpHandler $handler)
    {
        /**
         * 1. 准备调用
         * @var Bootstrap       $b
         * @var ServiceServer   $service
         * @var Request         $request
         * @var PhalconResponse $response
         */
        $b = $this->boot;
        $service = $this->container->getShared('serviceServer');
        $request = $this->container->getShared('request');
        $logger = $this->container->getShared('logger');
        $logger->setPrefix($b->getLogger()->getPrefix());
        try {
            // 2. 检查连接
            //    MySQL/Redis
            $this->frameworkConnection();
            /**
             * 3. 执行容器
             * @var mixed $result
             */
            $handler->assignPhalcon($request);
            $result = $this->application->handle($handler->getUri());
            if ($result instanceof PhalconResponse) {
                $response = $result;
            } else {
                $handler->setContentType('text/plain');
                $response = new PhalconResponse();
                if (is_string($result)) {
                    $response->setContent($result);
                } else {
                    $response->setContent(gettype($result));
                }
            }
            // 4. 转换Cookie
            //     todo: cookies读不到对象
            $cookies = $response->getCookies();
            if ($cookies instanceof \Phalcon\Http\Response\CookiesInterface) {
            }
            // 5. 转换Header
            $headers = $response->getHeaders();
            if ($headers instanceof \Phalcon\Http\Response\Headers) {
                foreach ($headers->toArray() as $key => $value) {
                    $handler->addResponseHeader($key, $value);
                }
            }
        } catch(\Throwable $e) {
            // 6. 返回错误
            $response = $service->withError($e->getMessage(), $e->getCode());
            if ($e instanceof Error) {
                // 7. 过滤业务错误
                $logger->enableDebug() && $logger->debug("Phalcon业务条件错误 - %s", $e->getMessage());
            } else {
                // 8. 加入报警
                $logger->error("Phalcon{%s}异常 - %s - 位于{%s}第{%d}行", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
            }
        }
        // 9. 转给Handler
        $handler->setStatusCode((int) $response->getStatusCode());
        $handler->setContent((string) $response->getContent());
    }

    /**
     * 刷新MySQL连接
     */
    private function phalconLoaderMysql()
    {
        foreach ($this->connectionMysqls as $name) {
            // 1. not shared
            if (!$this->container->hasSharedInstance($name)) {
                continue;
            }
            /**
             * 2. shared
             * @var Mysql $db
             */
            try {
                $db = $this->container->getShared($name);
                $db->query("SELECT 1");
            } catch(\Throwable $e) {
                // 3. 执行失败
                if (preg_match("/gone\s+away/i", $e->getMessage()) > 0) {
                    $this->container->removeSharedInstance($name);
                    $this->getLogger()->warning("移除断开的{%s}连接 - %s", $name, $e->getMessage());
                }
            }
        }
    }

    /**
     * 刷新Redis连接
     */
    private function phalconLoaderRedis()
    {
        foreach ($this->connectionRedises as $name) {
            // 1. not shared
            if (!$this->container->hasSharedInstance($name)) {
                continue;
            }
            /**
             * 2. shared
             * @var \Redis $db
             */
            try {
                $db = $this->container->getShared($name);
                $db->exists("test");
            } catch(\Throwable $e) {
                // 3. 执行失败
                if (preg_match("/went\s+away/i", $e->getMessage()) > 0) {
                    $this->container->removeSharedInstance($name);
                    $this->getLogger()->warning("移除断开的{%s}连接 - %s", $name, $e->getMessage());
                }
            }
        }
    }
}
