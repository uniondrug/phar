<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server;

use Swoole\Lock;
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
use Uniondrug\Phar\Server\Processes\CronProcess;
use Uniondrug\Phar\Server\Processes\IProcess;
use Uniondrug\Phar\Server\Processes\LogProcess;
use Uniondrug\Phar\Server\Tables\ITable;
use Uniondrug\Phar\Server\Tables\LogTable;
use Uniondrug\Phar\Server\Tables\StatsTable;

/**
 * HttpServer
 * @package Uniondrug\Phar\Server
 */
abstract class Http extends swoole_http_server
{
    /**
     * Server入口
     * @var Bootstrap
     */
    public $boot;
    private $_mutex;
    private $_tableLoads = [];
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
     * XHttp constructor.
     * @param Bootstrap $boot
     */
    public function __construct(Bootstrap $boot)
    {
        $cfg = $boot->getConfig();
        $log = $boot->getLogger();
        $log->setLogLevel($cfg->logLevel);
        $this->boot = $boot;
        // 1. construct
        $log->setPrefix("[%s:%d][%s]", $cfg->getDeployIp(), $cfg->port, $cfg->name);
        $log->info("创建{%s}实例, 以{%s}Mode和{%s}Sock", get_class($this), $cfg->serverMode, $cfg->serverSockType);
        parent::__construct($cfg->host, $cfg->port, $cfg->serverMode, $cfg->serverSockType);
        $this->_mutex = new Mutex();
        // 2. settings
        $settings = $cfg->settings;
        $log->info("配置{%d}项参数", count($settings));
        $this->set($settings);
        if ($log->enableDebug()) {
            foreach ($settings as $key => $value) {
                $log->debug("参数{%s}赋值为{%s}值", $key, $value);
            }
        }
        // 3. events
        $events = $cfg->events;
        $log->info("绑定{%d}个事件", count($events));
        foreach ($events as $event) {
            $call = 'on'.ucfirst($event);
            if (method_exists($this, $call)) {
                $this->on($event, [
                    $this,
                    'on'.ucfirst($event)
                ]);
                $log->enableDebug() && $log->debug("方法{%s}绑定到{%s}事件回调", $call, $event);
            } else {
                $log->warning("方法{%s}未定, {%s}事件被忽略", $call, $event);
            }
        }
        // 4. tables
        $tables = $cfg->tables;
        // 4.1 tables.stats
        if (!isset($tables[StatsTable::class])) {
            $tables[StatsTable::class] = 2048;
        }
        // 4.2 tables.log
        if (!$boot->getArgs()->hasOption('log-stdout') && !isset($tables[LogTable::class])) {
            $tables[LogTable::class] = LogTable::MESSAGE_SIZE;
        }
        $log->info("注册{%d}个内存表", count($tables));
        // 4.3 tables.*
        foreach ($tables as $table => $size) {
            // 4.3.1 无效表
            if (!is_a($table, ITable::class, true)) {
                $log->warning("Table{%s}未实现{%s}接口", $table, ITable::class);
                continue;
            }
            /**
             * 4.3.2 创建表
             * @var ITable $tbl
             */
            $tbl = new $table($this, $size);
            if ($tbl instanceof LogTable) {
                $tbl->setLimit($boot->getConfig()->logBatchLimit);
            }
            $name = $tbl->getName();
            $this->_tableLoads[$name] = $tbl;
            $log->enableDebug() && $log->debug("内存表{%s}注册到{%s}并初始化{%d}条记录", $name, $table, $size);
        }
        // 5. processes
        $processes = $cfg->processes;
        // 5.1 log process
        if (!$boot->getArgs()->hasOption('log-stdout') && !in_array(LogProcess::class, $processes)) {
            $processes[] = LogProcess::class;
        }
        // 5.2 cron(crontab) process
        if (!in_array(CronProcess::class, $processes)) {
            $processes[] = CronProcess::class;
        }
        // 5.3: 加入启动
        $log->info("加入{%d}个自启动进程", count($processes));
        foreach ($processes as $process) {
            // 5.3.1 invalid
            if (!is_a($process, IProcess::class, true)) {
                $log->warning("Process{%s}未实现{%s}接口", $process, IProcess::class);
                continue;
            }
            // 5.3.2 join
            $proc = new $process($this);
            $this->addProcess($proc);
            $log->enableDebug() && $log->debug("Process{%s}加入启动", $process);
        }
        // 6. manager
        //       swManager_check_status_exit
        //$managerHost = $cfg->getManagerHost();
        //if ($managerHost !== null) {
        //    $this->addListener($managerHost, $cfg->port, $cfg->serverSockType);
        //    $log->info("Agent绑定{%s:%d}Manager代理", $managerHost, $cfg->port);
        //}
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
     * @param string $name
     * @return ITable|false
     */
    public function getTable(string $name)
    {
        if (isset($this->_tableLoads[$name])) {
            return $this->_tableLoads[$name];
        }
        return false;
    }

    /**
     * 读取内存表列表
     * @return array
     */
    public function getTables()
    {
        return $this->_tableLoads;
    }

    /**
     * 读取Log表
     * @return false|LogTable
     */
    public function getLogTable()
    {
        return $this->getTable(LogTable::NAME);
    }

    /**
     * 读取统计表
     * @return false|StatsTable
     */
    public function getStatsTable()
    {
        return $this->getTable(StatsTable::TABLE_NAME);
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
     * 读取全局锁
     * @return Lock
     */
    public function getMutex()
    {
        return $this->_mutex;
    }

    /**
     * 是否为Tasker进程
     * @return bool
     */
    public function isTasker()
    {
        if ($this->isWorker()) {
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
