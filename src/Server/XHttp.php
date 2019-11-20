<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server;

use App\Errors\Error;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Di;
use Phalcon\Http\Response;
use Uniondrug\Framework\Application;
use Uniondrug\Framework\Container;
use Uniondrug\Framework\Request;
use Uniondrug\Phar\Server\Logs\Logger;
use Uniondrug\Phar\Server\Services\HttpDispatcher;
use Uniondrug\Service\Server as ServiceServer;
use Uniondrug\Structs\Exception;
use Uniondrug\Validation\Exceptions\ParamException;

/**
 * HTTP服务
 * 基于uniondrug/framework的HTTP服务
 * @package Uniondrug\Phar\Server
 */
class XHttp extends Services\Http
{
    /**
     * @var Application
     */
    private $_application;
    /**
     * @var Container
     */
    private $_container;
    private $_connectionActived = 0;
    /**
     * 连接刷新频次
     * 单位: 秒
     * 默认: 5
     * 当值为0时, 表示每次请求前主动检查, 反之由定时器
     * 每隔5秒检查一次连接
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
     * 连接检查
     * @param XHttp|XSocket|XOld $server
     */
    public function frameworkReConnect($server)
    {
        $timestamp = time();
        if ($this->connectionFrequences > 0) {
            if (($timestamp - $this->_connectionActived) <= $this->connectionFrequences) {
                return;
            }
        }
        $this->_connectionActived = $timestamp;
        $this->_connectionCheckMysql($server);
        $this->_connectionCheckRedis($server);
    }

    /**
     * 初始化Phalcon框架
     * @param XHttp|XSocket|XOld $server
     */
    public function frameworkInitialize($server)
    {
        // 1. container
        $container = new Container($server->getArgs()->basePath());
        Di::setDefault($container);
        // 2. override shared instances
        $container->setShared('server', $server);
        $container->setShared('logger', $server->getLogger());
        $container->setShared('request', new Request());
        // 3. application
        $application = new Application($container);
        $application->boot();
        // 4. set globals
        $this->_application = $application;
        $this->_container = $container;
    }

    /**
     * 转发请求给Phalcon框架
     * @param XHttp|XSocket|XOld $server
     * @param HttpDispatcher     $dispatcher
     */
    public function frameworkRequest($server, HttpDispatcher $dispatcher)
    {
        $this->frameworkReConnect($server);
        /**
         * 1. 入参
         * @var Request $request
         */
        $request = $this->_container->getShared('request');
        $request->setRawBody($dispatcher->getRawBody());
        /**
         * 2. 请求过程
         * @var ServiceServer $service
         * @var Response      $response
         */
        try {
            $response = $this->_application->handle($dispatcher->getUrl());
            if (!($response instanceof Response)) {
                throw new Error(0, "unknown framework response");
            }
        } catch(\Throwable $e) {
            if (($e instanceof Error) || ($e instanceof Exception) || ($e instanceof ParamException)) {
                $server->getLogger()->debug($e->getMessage());
            } else {
                $server->getLogger()->error($e->getMessage());
            }
            $server->getLogger()->debugOn() && $server->getLogger()->debug("{%s}: %s(%d)", get_class($e), $e->getFile(), $e->getLine());
            $service = $this->_container->getShared('serviceServer');
            $response = $service->withError($e->getMessage(), $e->getCode());
        }
        // 3. 转换Cookie
        //     todo: cookies读不到对象
        $cookies = $response->getCookies();
        if ($cookies instanceof \Phalcon\Http\Response\CookiesInterface) {
        }
        // 4. 转发Header
        $headers = $response->getHeaders();
        if ($headers instanceof \Phalcon\Http\Response\Headers) {
            foreach ($headers->toArray() as $key => $value) {
                if (preg_match("/access\-control/i", $key)) {
                    continue;
                }
                $dispatcher->setHeader($key, $value);
            }
        }
        // n. 写入返回数据
        //    a). 内容
        //    b). Status
        $statusCode = (int) $response->getStatusCode();
        $dispatcher->setStatus($statusCode);
        $content = $response->getContent();
        $dispatcher->setContent($content);
        // m. unclosed transaction
        $this->_connectionCheckUncommitTransaction($server);
    }

    /**
     * 读取应用
     * @return Application
     */
    public function getApplication()
    {
        return $this->_application;
    }

    /**
     * 读取容器
     * @return Container
     */
    public function getContainer()
    {
        return $this->_container;
    }

    /**
     * 检查事务
     * 在Swoole启动时, 当请求结束检查是否有未提交的事务
     * 若有, 则强制提交
     * @param XHttp|XSocket|XOld $server
     */
    private function _connectionCheckUncommitTransaction($server)
    {
        // 1. read shared db
        $shares = $this->connectionMysqls;
        if (method_exists($this->_container, 'getSharedDatabaseKeys')) {
            $shares = $this->_container->getSharedDatabaseKeys();
        }
        // 2. check shared db
        foreach ($shares as $name) {
            // 1. not shared
            if (!$this->_container->hasSharedInstance($name)) {
                continue;
            }
            /**
             * 2. 请求结束前事务报警
             *    a). 事务未提交, 未执行commit()方法
             *    b). not rollback for exception
             * @var Mysql $mysql
             */
            $mysql = $this->_container->getShared($name);
            if ($mysql->isUnderTransaction()) {
                $server->getLogger()->error("transaction not commit/rollback");
                try {
                    $mysql->rollback();
                    $server->getLogger()->info("transaction rollback with uniondrug/phar");
                } catch(\Throwable $e) {
                    $server->getLogger()->info("transaction rollback failure with uniondrug/phar for - {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * 检查MySQL连接
     * @param XHttp|XSocket|XOld $server
     */
    private function _connectionCheckMysql($server)
    {
        // 1. read shared db
        $shares = $this->connectionMysqls;
        if (method_exists($this->_container, 'getSharedDatabaseKeys')) {
            $shares = $this->_container->getSharedDatabaseKeys();
        }
        // 2. check shared db
        foreach ($shares as $name) {
            // 1. not shared
            if (!$this->_container->hasSharedInstance($name)) {
                $server->getLogger()->debugOn() && $server->getLogger()->debug("MySQL实例{%s}未创建", $name);
                continue;
            }
            /**
             * 2. check
             * @var Mysql $mysql
             */
            $mysql = $this->_container->getShared($name);
            try {
                $server->getLogger()->debugOn() && $server->getLogger()->debug("MySQL实例{%s}状态检查", $name);
                $mysql->query("SELECT 1");
            } catch(\Throwable $e) {
                $server->getLogger()->warning("MySQL实例{%s}断开 - %s", $name, $e->getMessage());
                $mysql->connect();
            }
        }
    }

    /**
     * 检查Redis连接
     * @param XHttp|XSocket|XOld $server
     */
    private function _connectionCheckRedis($server)
    {
        // 1. read shared db
        $shares = $this->connectionRedises;
        if (method_exists($this->_container, 'getSharedRedisKeys')) {
            $shares = $this->_container->getSharedRedisKeys();
        }
        foreach ($shares as $name) {
            // 1. not shared
            if (!$this->_container->hasSharedInstance($name)) {
                $server->getLogger()->debugOn() && $server->getLogger()->debug("Redis实例{%s}未创建", $name);
                continue;
            }
            /**
             * 2. check
             * @var \Redis $redis
             */
            $redis = $this->_container->getShared($name);
            try {
                $redis->ping();
            } catch(\Throwable $e) {
                $this->_container->removeSharedInstance($name);
                $server->getLogger()->warning("Redis实例{%s}断开 - %s", $name, $e->getMessage());
            }
        }
    }
}
