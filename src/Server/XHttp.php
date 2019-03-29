<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server;

use App\Errors\Error;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Di;
use Phalcon\Events\Manager;
use Phalcon\Http\Response;
use Uniondrug\Framework\Application;
use Uniondrug\Framework\Container;
use Uniondrug\Framework\Request;
use Uniondrug\Phar\Server\Listeners\MysqlListener;
use Uniondrug\Phar\Server\Listeners\RedisListener;
use Uniondrug\Phar\Server\Logs\Logger;
use Uniondrug\Phar\Server\Services\HttpDispatcher;
use Uniondrug\Service\Server as ServiceServer;
use Uniondrug\Structs\Exception;

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
    private $listenerMysql;
    private $listenerRedis;
    private $listenerHistories = [];
    /**
     * @var Manager
     */
    private $listenerManager;

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
        // 5. listener
        $this->listenerManager = $container->getEventsManager();
        if ($server->getConfig()->mysqlListenerOn()) {
            // 5.1
            $listener = $server->getConfig()->mysqlListenerClass();
            $this->listenerMysql = new $listener($server);
        }
        if ($server->getConfig()->redisListenerOn()) {
            // 5.2
            $listener = $server->getConfig()->redisListenerClass();
            $this->listenerRedis = new $listener($server);
        }
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
            if (($e instanceof Error) || ($e instanceof Exception)) {
                $server->getLogger()->warning($e->getMessage());
            } else {
                $server->getLogger()->error($e->getMessage());
            }
            $server->getLogger()->log(Logger::LEVEL_DEBUG, "%s at %s(%d)", get_class($e), $e->getFile(), $e->getLine());
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
        $content = $response->getContent();
        if (preg_match("/^\s*\<[a-z]+ml/i", $content) > 0) {
            $dispatcher->setContentType('text/html');
        } else if (preg_match("/^\s*\{/", $content) === 0) {
            $dispatcher->setContentType('text/plain');
        }
        $dispatcher->setStatus($statusCode);
        $dispatcher->setContent($content);
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
     * 绑定Listener
     * @param string      $type
     * @param string      $name
     * @param object|null $listener
     */
    public function attachListener($type, $name, $listener)
    {
        // 1. not object or disabled
        if ($listener === null) {
            return;
        }
        // 2. category
        if (!isset($this->listenerHistories[$type]) || !is_array($this->listenerHistories[$type])) {
            $this->listenerHistories[$type] = [];
        }
        // 3. append
        if (!isset($this->listenerHistories[$type][$name]) || $this->listenerHistories[$type][$name] !== true) {
            $this->listenerManager->attach($name, $listener);
            $this->listenerHistories[$type][$name] = true;
        }
    }

    /**
     * 取消Listener
     * @param string $type
     * @param string $name
     */
    public function detachListener($type, $name)
    {
        if (isset($this->listenerHistories[$type], $this->listenerHistories[$type][$name]) && $this->listenerHistories[$type][$name] === true) {
            $this->listenerHistories[$type][$name] = false;
        }
    }

    /**
     * 检查MySQL连接
     * @param XHttp|XSocket|XOld $server
     */
    private function _connectionCheckMysql($server)
    {
        foreach ($this->connectionMysqls as $name) {
            // 1. not shared
            if (!$this->_container->hasSharedInstance($name)) {
                continue;
            }
            /**
             * 2. check
             * @var Mysql $mysql
             */
            $mysql = $this->_container->getShared($name);
            try {
                $server->attachListener('mysql', $name, $this->listenerMysql);
                $mysql->query("SELECT 1");
            } catch(\Throwable $e) {
                $server->detachListener('mysql', $name);
                $this->_container->removeSharedInstance($name);
                $server->getLogger()->log(Logger::LEVEL_WARNING, "移除共享的MySQL-{%s}实例 - %s", $name, $e->getMessage());
            }
        }
    }

    /**
     * 检查Redis连接
     * @param XHttp|XSocket|XOld $server
     */
    private function _connectionCheckRedis($server)
    {
        foreach ($this->connectionRedises as $name) {
            // 1. not shared
            if (!$this->_container->hasSharedInstance($name)) {
                continue;
            }
            /**
             * 2. check
             * @var \Redis $redis
             */
            $redis = $this->_container->getShared($name);
            try {
                //$server->attachListener('redis', $name, $this->listenerRedis);
                $redis->ping();
            } catch(\Throwable $e) {
                //$server->detachListener('redis', $name);
                $this->_container->removeSharedInstance($name);
                $server->getLogger()->log(Logger::LEVEL_WARNING, "移除共享的Redis-{%s}实例 - %s", $name, $e->getMessage());
            }
        }
    }
}
