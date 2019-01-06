<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server;

use swoole_http_server;
use Uniondrug\Phar\Server\Does\BeforeStart;
use Uniondrug\Phar\Server\Does\DoFinish;
use Uniondrug\Phar\Server\Does\DoManagerStart;
use Uniondrug\Phar\Server\Does\DoManagerStop;
use Uniondrug\Phar\Server\Does\DoShutdown;
use Uniondrug\Phar\Server\Does\DoStart;
use Uniondrug\Phar\Server\Does\DoTask;
use Uniondrug\Phar\Server\Does\DoWorkerError;
use Uniondrug\Phar\Server\Does\DoWorkerStart;
use Uniondrug\Phar\Server\Does\DoWorkerStop;
use Uniondrug\Phar\Server\Does\Http\DoRequest;
use Uniondrug\Phar\Server\Does\RunTask;
use Uniondrug\Phar\Server\Events\Http\OnRequest;
use Uniondrug\Phar\Server\Events\OnFinish;
use Uniondrug\Phar\Server\Events\OnManagerStart;
use Uniondrug\Phar\Server\Events\OnManagerStop;
use Uniondrug\Phar\Server\Events\OnPipeMessage;
use Uniondrug\Phar\Server\Events\OnShutdown;
use Uniondrug\Phar\Server\Events\OnStart;
use Uniondrug\Phar\Server\Events\OnTask;
use Uniondrug\Phar\Server\Events\OnWorkerError;
use Uniondrug\Phar\Server\Events\OnWorkerStart;
use Uniondrug\Phar\Server\Events\OnWorkerStop;
use Uniondrug\Phar\Server\Frameworks\Phalcon;

/**
 * HttpServer
 * @package Uniondrug\Phar\Server
 */
class XHttp extends swoole_http_server
{
    /**
     * Server入口
     * @var Bootstrap
     */
    public $boot;
    /**
     * callbacks
     * 1. before
     * 2. server
     * 3. task
     * 3. http
     */
    use BeforeStart;
    use DoStart, DoManagerStart, DoManagerStop, DoWorkerError, DoWorkerStart, DoWorkerStop, DoShutdown;
    use DoTask, DoFinish;
    use DoRequest;
    /**
     * events:
     * 1. server
     * 2. task
     * 3. http
     */
    use OnStart, OnManagerStart, OnManagerStop, OnWorkerError, OnWorkerStart, OnWorkerStop, OnShutdown;
    use OnTask, OnFinish, OnPipeMessage, RunTask;
    use OnRequest;
    /**
     * frameworks: Phalcon
     */
    use Phalcon;

    /**
     * XHttp constructor.
     * @param Bootstrap $boot
     */
    public function __construct(Bootstrap $boot)
    {
        $cfg = $boot->getConfig();
        $log = $boot->getLogger();
        $log->setLogLevel($cfg->getLogLevel());
        $this->boot = $boot;
        // 1. construct
        $log->info("创建{%s}服务监听{%s:%d}/Server", $cfg->name, $cfg->host, $cfg->port);
        parent::__construct($cfg->host, $cfg->port, $cfg->serverMode, $cfg->serverSockType);
        // 2. settings
        $this->set($cfg->settings);
        // 3. events
        $events = $cfg->events;
        foreach ($events as $event) {
            $call = 'on'.ucfirst($event);
            if (method_exists($this, $call)) {
                $this->on($event, [
                    $this,
                    'on'.ucfirst($event)
                ]);
            } else {
                $log->warning("方法{%s}未定, {%s}事件被忽略", $call, $event);
            }
        }
        // 4. tables
        if ($cfg->enableTables) {
            $tables = $cfg->tables;
            foreach ($tables as $table => $size) {
            }
        }
        // 5. processes
        if ($cfg->enableProcesses) {
            $processes = $cfg->processes;
            foreach ($processes as $process) {
            }
        }
        // 6. manager
        $managerHost = $cfg->getManagerHost();
        if ($managerHost !== null) {
            $this->addListener($managerHost, $cfg->port, $cfg->serverSockType);
        }
    }

    /**
     * 读取Args实例
     * @return Args
     */
    public function getArgs()
    {
        return $this->boot->getArgs();
    }

    /**
     * 读取Config实例
     * @return Config
     */
    public function getConfig()
    {
        return $this->boot->getConfig();
    }

    /**
     * 读取Logger实例
     * @return Logger
     */
    public function getLogger()
    {
        return $this->boot->getLogger();
    }

    /**
     * Master进程ID
     * @return int
     */
    public function getMasterPid()
    {
        return $this->master_pid;
    }

    /**
     * Manager进程ID
     * @return int
     */
    public function getManagerPid()
    {
        return $this->manager_pid;
    }

    /**
     * Worker进程ID
     * @return int
     */
    public function getWorkerPid()
    {
        return $this->worker_pid;
    }

    /**
     * Worker编号
     * @return int
     */
    public function getWorkerId()
    {
        return $this->worker_id;
    }

    /**
     * 是否为Tasker进程
     * @return bool
     */
    public function isTasker()
    {
        if ($this->worker_pid > 0) {
            return $this->taskworker;
        }
        return false;
    }

    /**
     * 是否为Worker进程
     * 1. Worker
     * 2. Tasker
     * @return bool
     */
    public function isWorker()
    {
        return $this->worker_pid > 0;
    }

    /**
     * 启动Server
     */
    final public function start()
    {
        if ($this->beforeStart($this) === true) {
            return parent::start();
        }
        return false;
    }

    /**
     * 设置进程名称
     * @param string $name
     * @param array  ...$args
     * @return string
     */
    final public function setProcessName(string $name, ... $args)
    {
        try {
            $name = substr($this->boot->getConfig()->environment, 0, 1).'.'.$this->boot->getConfig()->name.' '.$name;
            foreach ($args as $arg) {
                $arg = trim($arg);
                if ($arg !== '') {
                    $name .= ' '.$arg;
                }
            }
            if (PHP_OS !== "Darwin") {
                swoole_set_process_name($name);
            }
        } catch(\Throwable $e) {
        }
        return $name;
    }
}
