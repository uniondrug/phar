<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server\Services\Traits;

use Uniondrug\Phar\Exceptions\ServiceException;
use Uniondrug\Phar\Server\Bases\Args;
use Uniondrug\Phar\Server\Bases\Config;
use Uniondrug\Phar\Server\Bases\Runner;
use Uniondrug\Phar\Server\Bases\Trace;
use Uniondrug\Phar\Server\Logs\Logger;
use Uniondrug\Phar\Server\Processes\PharProcess;
use Uniondrug\Phar\Server\Processes\ServicePushProcess;
use Uniondrug\Phar\Server\Services\Http;
use Uniondrug\Phar\Server\Services\Socket;
use Uniondrug\Phar\Server\Tables\ITable;
use Uniondrug\Phar\Server\Tables\PidTable;
use Uniondrug\Phar\Server\Tables\StatsTable;

/**
 * @package Uniondrug\Phar\Server\Services\Traits
 */
trait ConstractTrait
{
    /**
     * 当前进程ID
     * @var int
     */
    private $_pid = 0;
    private $_pidName = '';
    /**
     * @var Runner
     */
    private $_runner;
    /**
     * 内存表记录
     * @var array
     */
    private $_tables;
    /**
     * @var Trace
     */
    private $_trace;

    /**
     * 构造Server实例
     * 1. Http
     * 2. WebSocket
     * @param Runner $runner
     */
    public function __construct(Runner $runner)
    {
        $this->_runner = $runner;
        $this->_runner->registerHandler();
        $this->_trace = new Trace();
        $this->getArgs()->buildPath();
        parent::__construct($this->getConfig()->host, $this->getConfig()->port, $this->getConfig()->serverMode, $this->getConfig()->serverSockType);
        $this->getLogger()->info("服务名{%s/%s}准备启动", $this->getConfig()->appName, $this->getConfig()->appVersion);
        $this->getLogger()->info("监听在{%s:%s}端口上.", $this->getConfig()->host, $this->getConfig()->port);
        $this->getLogger()->info("初始化{%d}号模式与{%d}号Sock.", $this->getConfig()->serverMode, $this->getConfig()->serverSockType);
        $this->initSettings();
        $this->initTables();
        $this->initEvents();
        $this->initProcesses();
    }

    /**
     * @return Args
     */
    public function getArgs()
    {
        return $this->_runner->getConfig()->getArgs();
    }

    /**
     * 读取配置
     * @return Config
     */
    public function getConfig()
    {
        return $this->_runner->getConfig();
    }

    /**
     * 读取配置
     * @return Logger
     */
    public function getLogger()
    {
        return $this->_runner->getLogger();
    }

    /**
     * 读取Manager进程ID
     * @return int
     */
    public function getManagerPid()
    {
        return $this->manager_pid;
    }

    /**
     * 读取Master进程ID
     * @return int
     */
    public function getMasterPid()
    {
        return $this->master_pid;
    }

    /**
     * 读取当前进程ID
     * 1. master
     * 2. manager
     * 3. worker
     * 4. process
     * @return int
     */
    public function getPid()
    {
        return $this->_pid;
    }

    /**
     * 读取进程名称
     * @return string
     */
    public function getPidName()
    {
        return $this->_pidName;
    }

    /**
     * 读取Runner对象
     * @return Runner
     */
    public function gerRunner()
    {
        return $this->_runner;
    }

    /**
     * 读取指定表
     * @param string $name
     * @return ITable
     * @throws ServiceException
     */
    public function getTable(string $name)
    {
        if (isset($this->_tables[$name])) {
            return $this->_tables[$name];
        }
        throw new ServiceException("call undefined {$name} table.");
    }

    /**
     * 读取全部表
     * @return array
     */
    public function getTables()
    {
        return $this->_tables;
    }

    /**
     * @return Trace
     */
    public function getTrace()
    {
        return $this->_trace;
    }

    /**
     * 进程表
     * @return PidTable|ITable
     */
    public function getPidTable()
    {
        return $this->getTable(PidTable::$name);
    }

    /**
     * 进程表
     * @return StatsTable|ITable
     */
    public function getStatsTable()
    {
        return $this->getTable(StatsTable::$name);
    }

    /**
     * 读取Worker进程编号
     * @return int
     */
    public function getWorkerId()
    {
        return $this->worker_id;
    }

    /**
     * 读取Worker进程ID
     * @return int
     */
    public function getWorkerPid()
    {
        return $this->worker_pid;
    }

    /**
     * 是否为Tasker进程
     * @return bool
     */
    public function isTasker()
    {
        return $this->taskworker;
    }

    /**
     * 是否为Worker进程
     * @return bool
     */
    public function isWorker()
    {
        return $this->getWorkerPid() > 0;
    }

    /**
     * 记录进程ID
     * 1. base
     *    a) env
     *    b) appname
     * 2. type
     * 3. exts
     *    a) workerId/Process
     *    b) index
     * @param int   $pid
     * @param array $args
     * @return $this
     */
    public function setPid(int $pid, ... $args)
    {
        $args = is_array($args) ? $args : [];
        // 1. 进程名第1段
        $pidName = $this->getArgs()->getEnvironmentType().'.'.$this->getConfig()->appName;
        // 2. 进程名第2-3段
        foreach ($args as $arg) {
            $arg = (string) $arg;
            if ($arg === '') {
                continue;
            }
            $pidName .= ' '.$arg;
        }
        // 3. 进程赋值
        $this->_pid = $pid;
        $this->_pidName = $pidName;
        // 4. 设置进程名/进程参数
        if (PHP_OS !== "Darwin") {
            try {
                swoole_set_process_name($pidName);
            } catch(\Throwable $e) {
            }
        }
        return $this;
    }

    /**
     * 初始化事件绑定
     */
    private function initEvents()
    {
        $count = 0;
        $events = $this->getConfig()->getSwooleEvents();
        foreach ($events as $event) {
            $count++;
            $call = 'on'.ucfirst($event);
            $this->on($event, [
                $this,
                $call
            ]);
        }
        $this->getLogger()->info("初始化{%d}个事件{回调}方法.", $count);
    }

    /**
     * 初始化进程表
     */
    private function initProcesses()
    {
        $processes = $this->getConfig()->getSwooleProcesses();
        $pharProcess = true;
        $countProcess = 0;
        foreach ($processes as $process) {
            if (is_a($process, PharProcess::class, true)) {
                $pharProcess = false;
            }
            $this->addProcess(new $process($this));
            $countProcess++;
        }
        if ($pharProcess) {
            $this->addProcess(new PharProcess($this));
            $countProcess++;
        }
        //判断服务是否可以上报
        if(array_key_exists('servicePush',$this->getConfig()->getScanned())){
            //wss 增加服务上报内容
            $servicePush = $this->getConfig()->getScanned()['servicePush']['value']['servicePush'];

            if($servicePush == 1)
            {
                $this->addProcess(new ServicePushProcess($this));
                $countProcess++;
            }
        }
        $this->getLogger()->info("初始化{%d}个{Process}进程.", $countProcess);
    }

    /**
     * 初始化Swoole启动参数
     * 一经指定(若未重启)不可变量
     */
    private function initSettings()
    {
        $settings = $this->getConfig()->getSwooleSettings();
        $this->set($settings);
        $this->getLogger()->info("初始化{%d}个Swoole{服务参数}.", count($settings));
    }

    /**
     * 初始内存表
     */
    private function initTables()
    {
        /**
         * @var Http|Socket $server
         */
        $server = $this;
        $tables = $this->getConfig()->getSwooleTables();
        $pidTable = true;
        $statsTable = true;
        $countTable = 0;
        // 1. 基础内存表
        foreach ($tables as $table => $size) {
            // 2. pid table set
            if (is_a($table, PidTable::class, true)) {
                $pidTable = false;
            }
            if (is_a($table, StatsTable::class, true)) {
                $statsTable = false;
            }
            // 3. calc
            if (!is_a($table, ITable::class, true)) {
                throw new ServiceException("invalid {$table} table.");
            }
            // 4. size
            $size = is_numeric($size) && $size > 2 ? $size : 64;
            $name = $table::$name;
            $this->_tables[$name] = new $table($this, $size);
            $countTable++;
        }
        // 5. 预置内存表
        if ($pidTable) {
            $pidSize = 128;
            $this->_tables[PidTable::$name] = new PidTable($server, $pidSize);
            $countTable++;
        }
        // 6. 预置内存表
        if ($statsTable) {
            $statsSize = 128;
            $this->_tables[StatsTable::$name] = new StatsTable($server, $statsSize);
            $countTable++;
        }
        $this->getLogger()->info("初始化{%d}个共享{内存表}.", $countTable);
    }
}
