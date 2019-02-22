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
    protected $connectionRedises = [
        'redis'
    ];

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
        $this->phalconLoader($handler->getRequestHash());
        $service = $this->container->getShared('serviceServer');
        $request = $this->container->getShared('request');
        $logger = $this->container->getShared('logger');
        $logger->setPrefix($b->getLogger()->getPrefix().$handler->getRequestHash());
        try {
            /**
             * 2. 执行容器
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
            // 3. 转换Cookie
            //     todo: cookies读不到对象
            $cookies = $response->getCookies();
            if ($cookies instanceof \Phalcon\Http\Response\CookiesInterface) {
            }
            // 4. 转换Header
            $headers = $response->getHeaders();
            if ($headers instanceof \Phalcon\Http\Response\Headers) {
                foreach ($headers->toArray() as $key => $value) {
                    $handler->addResponseHeader($key, $value);
                }
            }
        } catch(\Throwable $e) {
            // 5. 返回错误
            $response = $service->withError($e->getMessage(), $e->getCode());
            if ($e instanceof Error) {
                // 6. 过滤业务错误
                $logger->enableDebug() && $logger->debug("Phalcon业务条件错误 - %s", $e->getMessage());
            } else {
                // 7. 加入报警
                $logger->error("Phalcon{%s}异常 - %s - 位于{%s}第{%d}行", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
            }
        }
        // 8. 转给Handler
        $handler->setStatusCode((int) $response->getStatusCode());
        $handler->setContent((string) $response->getContent());
    }

    /**
     * 加载Phalcon框架
     * @return $this
     */
    private function phalconLoader($hash = '')
    {
        /**
         * 1. 创建实例
         * @var XHttp $server
         * @var Args  $args
         */
        $server = $this;
        if ($this->application === null || $this->container === null) {
            $cfg = $server->getConfig();
            $args = $server->getArgs();
            $server->getLogger()->enableDebug() && $server->getLogger()->debug("{$hash}初始化Phalcon容器");
            // 1.1 create object
            $this->container = new Container($args->getBasePath());
            // 1.2 set shared server
            $this->container->setShared('server', $server);
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
