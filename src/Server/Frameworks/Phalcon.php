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
     * @return Logger 返回业务Logger数据
     */
    public function runContainer(HttpHandler $handler)
    {
        /**
         * @var Bootstrap $boot
         */
        $t1 = microtime(true);
        $boot = $this->boot;
        /**
         * 1. init container
         * @var ServiceServer $service
         */
        $this->phalconLoader();
        // 2. remove shared instance
        if ($this->container->hasSharedInstance('logger')) {
            $this->container->removeSharedInstance('logger');
        }
        // 3. register new logger handler
        $logger = new Logger($boot->getArgs());
        $logger->setPrefix("[%s:%d][ram=%sM][req=%s]", $boot->getConfig()->host, $boot->getConfig()->port, $handler->getMemoryUsed(), $handler->getRequestId());
        $this->container->setShared('logger', $logger);
        /**
         * 4. assign phalcon request
         * @var Request         $request
         * @var PhalconResponse $result
         */
        $request = $this->container->getShared('request');
        $handler->assignPhalcon($request);
        $logger->debug("开始请求 - HTTP %s %s", $request->getMethod(), $request->getURI());
        // 5. run progress
        try {
            $result = $this->application->handle($handler->getUri());
            if (!($result instanceof PhalconResponse)) {
                $result = $service->withSuccess();
            }
        } catch(\Throwable $e) {
            $logger->error("请求出错 - (%d) %s at line %d of %s", $e->getCode(), $e->getMessage(), $e->getLine(), $e->getFile());
            $service = $this->container->getShared('serviceServer');
            $result = $service->withError($e->getMessage(), $e->getCode());
        }
        $handler->setStatusCode($result->getStatusCode());
        $handler->setContent($result->getContent());
        // n. 返回业务日志
        //    上层DoRequest将返回的数据以异步方式
        //    提交给异步Logger引擎
        $logger->debug("[duration=%f]请求完成", sprintf("%.06f", microtime(true) - $t1));
        return $logger;
    }

    /**
     * 加载Phalcon框架
     * @return $this
     */
    private function phalconLoader()
    {
        /**
         * 1. 创建实例
         * @var Bootstrap $boot
         * @var Args      $args
         */
        if ($this->application === null || $this->container === null) {
            $boot = $this->boot;
            $args = $boot->getArgs();
            $this->container = new Container($args->getBasePath());
            $this->container->setShared('server', $this);
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
                    $this->getLogger()->error("remove {%s} connction - %s", $name, $e->getMessage());
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
                    $this->getLogger()->error("remove {%s} connction - %s", $name, $e->getMessage());
                }
            }
        }
    }
}
