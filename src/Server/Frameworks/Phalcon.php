<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-01
 */
namespace Uniondrug\Phar\Server\Frameworks;

use Phalcon\Db\Adapter\Pdo\Mysql;
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
        $t = microtime(true);
        /**
         * 2. init container
         * @var ServiceServer $service
         */
        $this->phalconLoader();
        // 3. init logger
        $prefix = $b->getLogger()->getPrefix();
        $logger = $this->container->getShared('logger');
        /**
         * 4. assign phalcon request
         * @var Request         $request
         * @var PhalconResponse $result
         */
        $request = $this->container->getShared('request');
        $handler->assignPhalcon($request);
        $logger->setPrefix("%s[r=%s][m=%s][u=%s]", $prefix, $handler->getRequestId(), $request->getMethod(), $request->getURI());
        $logger->debug("开始{HTTP}请求,申请{%.01f}M内存", $handler->getMemoryUsed());
        // 5. run progress
        try {
            $result = $this->application->handle($handler->getUri());
            if (!($result instanceof PhalconResponse)) {
                $result = $service->withSuccess();
            }
        } catch(\Throwable $e) {
            $logger->error("请求获得{%d}号错误 - %s - 位于{%s}第{%d}行", $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            $service = $this->container->getShared('serviceServer');
            $result = $service->withError($e->getMessage(), $e->getCode());
        }
        $handler->setStatusCode($result->getStatusCode());
        $handler->setContent($result->getContent());
        // n. 请求完成
        $logger->debug("[d=%f]完成请求", sprintf("%.06f", microtime(true) - $t));
    }

    /**
     * 加载Phalcon框架
     * @return $this
     */
    private function phalconLoader()
    {
        /**
         * 1. 创建实例
         * @var XHttp $server
         * @var Args  $args
         */
        $server = $this;
        if ($this->application === null || $this->container === null) {
            $args = $server->getArgs();
            // 1.1 create object
            $this->container = new Container($args->getBasePath());
            $this->application = new Application($this->container);
            $this->application->boot();
            // 1.2 set shared server
            $this->container->setShared('server', $server);
            // 1.3 remove/reset shared logger
            $this->container->setShared('logger', function() use ($server, $args){
                $logger = new Logger($args);
                $logger->setServer($server);
                return $logger;
            });
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
