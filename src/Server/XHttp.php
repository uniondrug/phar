<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server;

use swoole_http_server;
use Uniondrug\Phar\Server\Does\BeforeStart;
use Uniondrug\Phar\Server\Does\DoStart;
use Uniondrug\Phar\Server\Does\DoTask;
use Uniondrug\Phar\Server\Does\Http\DoRequest;
use Uniondrug\Phar\Server\Does\RunTask;
use Uniondrug\Phar\Server\Events\Http\OnRequest;
use Uniondrug\Phar\Server\Events\OnFinish;
use Uniondrug\Phar\Server\Events\OnPipeMessage;
use Uniondrug\Phar\Server\Events\OnStart;
use Uniondrug\Phar\Server\Events\OnTask;
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
     * does: callbacks
     */
    use BeforeStart, DoStart, DoTask;
    use DoRequest;
    /**
     * events: server
     */
    use RunTask, OnFinish, OnTask, OnPipeMessage, OnStart;
    /**
     * events: http
     */
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
        $this->boot = $boot;
        // 1. construct
        $log->setPrefix("[%s:%d]", $cfg->host, $cfg->port);
        $log->info("创建{%s}服务/Server", $cfg->name);
        parent::__construct($cfg->host, $cfg->port, $cfg->serverMode, $cfg->serverSockType);
        // 2. settings
        $this->set($cfg->settings);
        // 3. events
        $log->info("绑定事件监听");
        $events = $cfg->events;
        foreach ($events as $event) {
            $call = 'on'.ucfirst($event);
            if (method_exists($this, $call)) {
                $log->debug("绑定{%s}事件到{%s}回调方法", $event, $call);
                $this->on($event, [
                    $this,
                    'on'.ucfirst($event)
                ]);
            } else {
                $log->warning("方法{%s}未定, {$event}事件被忽略", $call, $event);
            }
        }
        // 4. tables
        if ($cfg->enableTables) {
            $tables = $cfg->tables;
            $log->info("设置内存表{%d}个", count($tables));
            foreach ($tables as $table => $size) {
            }
        }
        // 5. processes
        if ($cfg->enableProcesses) {
            $processes = $cfg->processes;
            $log->info("加入Process进程{%d}个", count($processes));
        }
    }

    /**
     * @return Args
     */
    public function getArgs()
    {
        return $this->boot->getArgs();
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->boot->getConfig();
    }

    /**
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
     * 是否为Worker进程
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
}
