<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server;

use Uniondrug\Phar\Server\Exceptions\ConfigExeption;
use Uniondrug\Phar\Server\Tasks\LogTask;

/**
 * Server服务配置
 * @property string $name            项目名
 * @property string $version         项目版本
 * @property string $host            IP地址
 * @property int    $port            端口号
 * @property int    $serverMode      Server模式
 * @property int    $serverSockType  ServerSock类型
 * @property array  $settings        Swoole参数
 * @property array  $events          事件列表
 * @property array  $crons           定时任务列表
 * @property bool   $enableCrons     启用定时任务
 * @property array  $tables          内存表列表
 * @property bool   $enableTables    启用内存表
 * @property array  $processes       Process进程列表
 * @property bool   $enableProcesses 启用Process进程
 * @property string $logTask         异步任务处理
 * @package Uniondrug\Phar\Bootstrap
 */
class Config
{
    private $_class = XHttp::class;
    private $_name = 'sketch';
    private $_version = '1.2.3';
    private $_host = '0.0.0.0';
    private $_port = 18080;
    private $_serverMode = \SWOOLE_PROCESS;
    private $_serverSockType = \SWOOLE_SOCK_TCP;
    private $_settings = [
        'log_level' => 0,
        'worker_num' => 2,
        'task_worker_num' => 8,
        'max_request' => 5000,
        'task_max_request' => 5000
    ];
    private $_events = [
        // 1. server
        'start',
        'finish',
        'task',
        // 2. http
        'request',
    ];
    /**
     * @var bool
     */
    private $_enableCrons = true;
    private $_crons = [];
    /**
     * @var bool
     */
    private $_enableTables = true;
    private $_tables = [];
    /**
     * @var bool
     */
    private $_enableProcesses = true;
    private $_processes = [];
    private $_logTask = LogTask::class;

    public function __construct(Args $args)
    {
    }

    final public function __get($name)
    {
        $prop = "_{$name}";
        if (isset($this->{$prop})) {
            return $this->{$prop};
        }
        throw new ConfigExeption("call undefined '{$name}' configuration.");
    }

    final public function __set($name, $value)
    {
    }

    /**
     * 加载历史/上次启参数
     */
    public function fromHistory()
    {
    }

    public function getServerSoft()
    {
        return $this->name.'/'.$this->version;
    }
}
