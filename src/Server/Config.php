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
 * @property string $name                 项目名
 * @property string $version              项目版本
 * @property string $host                 IP地址
 * @property int    $port                 端口号
 * @property int    $serverMode           Server模式
 * @property int    $serverSockType       ServerSock类型
 * @property array  $settings             Swoole参数
 * @property array  $events               事件列表
 * @property array  $crons                定时任务列表
 * @property bool   $enableCrons          启用定时任务
 * @property array  $tables               内存表列表
 * @property bool   $enableTables         启用内存表
 * @property array  $processes            Process进程列表
 * @property bool   $enableProcesses      启用Process进程
 * @property bool   $processesStdRedirect redirect stdin/out
 * @property bool   $processesCreatePipe  create pipe
 * @property string $deployIp             项目所在机器的IP地址
 * @property string $logTask              异步任务处理
 * @package Uniondrug\Phar\Bootstrap
 */
class Config
{
    const DEFAULT_CRONS_ENABLE = false;
    const DEFAULT_PROCESSES_ENABLE = true;
    const DEFAULT_PROCESSES_PIPE = true;
    const DEFAULT_PROCESSES_STD_REDIRECT = false;
    const DEFAULT_TABLES_ENABLE = true;
    const DEFAULT_HOST = "0.0.0.0";
    const DEFAULT_PORT = 8080;
    /**
     * @var Args
     */
    private $args;
    private $_deployIp;
    private $_class = XHttp::class;
    private $_name = 'sketch';
    private $_version = '1.2.3';
    private $_host = self::DEFAULT_HOST;
    private $_port = self::DEFAULT_PORT;
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
        'shutdown',
        'managerStart',
        'managerStop',
        'workerStart',
        'workerStop',
        'pipeMessage',
        'finish',
        'task',
        // 2. http
        'request',
    ];
    /**
     * @var bool
     */
    private $_enableCrons = self::DEFAULT_CRONS_ENABLE;
    private $_crons = [];
    /**
     * @var bool
     */
    private $_enableTables = self::DEFAULT_TABLES_ENABLE;
    private $_tables = [];
    /**
     * @var bool
     */
    private $_enableProcesses = self::DEFAULT_PROCESSES_ENABLE;
    private $_processes = [];
    private $_processesStdRedirect = self::DEFAULT_PROCESSES_STD_REDIRECT;
    private $_processesCreatePipe = self::DEFAULT_PROCESSES_PIPE;
    private $_logTask = LogTask::class;

    public function __construct(Args $args)
    {
        $this->args = $args;
        $this->_deployIp = $this->ipFromAddr("eth0");
        $this->_deployIp || $this->_deployIp = $this->ipFromConfig("eth0");
        $this->_deployIp || $this->_deployIp = $this->ipFromAddr("en0");
        $this->_deployIp || $this->_deployIp = $this->ipFromConfig("en0");
        $this->_deployIp || $this->_deployIp = "";
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
        throw new ConfigExeption("can not change '{$name}' value of configuration.");
    }

    /**
     * 从文件导入
     * @return $this
     */
    public function fromFiles()
    {
        $conf = [];
        $file = $this->args->getBasePath().'/tmp/config.php';
        if (file_exists($file)) {
            $conf = include($file);
        } else {
            // 1. 扫描配置文件目录
            $env = $this->args->getEnvironment();
            $path = __DIR__.'/../../../../../config';
            if (is_dir($path)) {
                $scan = dir($path);
                while (false !== ($name = $scan->read())) {
                    // 2. not php file
                    if (preg_match("/^(\S+)\.php$/i", $name, $m) === 0) {
                        continue;
                    }
                    // 3. include temp
                    $data = include($path.'/'.$name);
                    if (!is_array($data)) {
                        continue;
                    }
                    // 4. merge
                    $tpl = isset($data['default']) && is_array($data['default']) ? $data['default'] : [];
                    $buf = isset($data[$env]) && is_array($data[$env]) ? $data[$env] : [];
                    $conf[$m[1]] = array_replace_recursive($tpl, $buf);
                }
                $scan->close();
            }
        }
        // 5. 应用配置段
        //    config.app
        $app = isset($conf['app']) && is_array($conf['app']) ? $conf['app'] : [];
        // 5.1 应用名称
        if (isset($app['appName']) && is_string($app['appName']) && $app['appName'] !== '') {
            $this->_name = $app['appName'];
        }
        // 5.2 应用版本
        if (isset($app['appVersion']) && is_string($app['appVersion']) && $app['appVersion'] !== '') {
            $this->_version = $app['appVersion'];
        }
        // 6. 服务配置段
        //    config.server
        $srv = isset($conf['server']) && is_array($conf['server']) ? $conf['server'] : [];
        if (isset($srv['serverMode']) && is_numeric($srv['serverMode'])) {
            $this->_serverMode = $srv['serverMode'];
        }
        if (isset($srv['serverSockType']) && is_numeric($srv['serverSockType'])) {
            $this->_serverSockType = $srv['serverSockType'];
        }
        // 6.1 类名: XHttp|XWebSocket
        if (isset($srv['class']) && is_string($srv['class']) && $srv['class'] !== '') {
            $this->_class = $srv['class'];
        }
        // 6.2 设置: https://wiki.swoole.com/wiki/page/274.html
        if (isset($srv['options']) && is_array($srv['options'])) {
            $this->_settings = $srv['options'];
        }
        $this->_settings['pid_file'] = $this->args->getBasePath().'/tmp/server.pid';
        $this->_settings['log_file'] = $this->args->getBasePath().'/log/server.pid';
        // 6.3 Tables
        if (isset($srv['enableTables']) && is_bool($srv['enableTables'])) {
            $this->_enableTables = $srv['enableTables'];
        }
        if ($this->_enableTables && isset($srv['tables']) && is_array($srv['tables'])) {
            $this->_tables = $srv['tables'];
        }
        // 6.4 Process支持
        if (isset($srv['enableProcesses']) && is_bool($srv['enableProcesses'])) {
            $this->_enableProcesses = $srv['enableProcesses'];
        }
        if ($this->_enableProcesses) {
            if (isset($srv['processes']) && is_array($srv['processes'])) {
                $this->_processes = $srv['processes'];
            }
            if (isset($srv['processesCreatePipe']) && is_bool($srv['processesCreatePipe'])) {
                $this->_processesCreatePipe = self::DEFAULT_PROCESSES_PIPE;
            }
            if (isset($srv['processesStdRedirect']) && is_bool($srv['processesStdRedirect'])) {
                $this->_processesStdRedirect = self::DEFAULT_PROCESSES_STD_REDIRECT;
            }
        }
        // 6.5 定时任务
        //     类似Crontab
        if (isset($srv['enableCrons']) && is_bool($srv['enableCrons'])) {
            $this->_enableCrons = $srv['enableCrons'];
        }
        if ($this->_enableCrons && isset($srv['crons']) && is_array($srv['crons'])) {
            $this->_crons = $srv['crons'];
        }
        // 6.6 覆盖默认事件
        if (isset($srv['events']) && is_array($srv['events']) && count($srv['events']) > 0) {
            $this->_events = $srv['events'];
        }
        // 6.7 IP/端口
        $host = isset($srv['host']) && is_string($srv['host']) ? $srv['host'] : "";
        if (preg_match("/([a-zA-Z0-9\.]+):(\d+)/", $host, $m)) {
            $this->setHost($m[1])->setPort($m[2]);
        }
        // 7. 完成
        return $this;
    }

    /**
     * 加载历史/上次启参数
     * @return $this
     */
    public function fromHistory()
    {
        $file = $this->generateFile();
        if (file_exists($file)) {
            $text = file_get_contents($file);
            $data = unserialize($text);
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $name = "_{$key}";
                    $this->{$name} = $value;
                }
            }
        }
        return $this;
    }

    /**
     * 生成配置字典
     */
    public function generate()
    {
        $cls = get_class($this);
        $ref = new \ReflectionClass($this);
        $buf = [];
        foreach ($ref->getProperties(\ReflectionProperty::IS_PRIVATE) as $prop) {
            if ($prop->class === $cls && preg_match("/^_(\S+)$/", $prop->name, $m)) {
                $buf[$m[1]] = $this->{$prop->name};
            }
        }
        $str = serialize($buf);
        return $str;
    }

    public function generateFile()
    {
        return $this->args->getBasePath().'/tmp/server.cfg';
    }

    public function getServerSoft()
    {
        return $this->name.'/'.$this->version;
    }

    /**
     * 将命令行参数合并入配置
     * @return $this
     */
    public function mergeArgs()
    {
        // 1. host
        if ($this->args->hasOption('host')) {
            $host = $this->args->getOption('host');
            $host && $this->setHost($host);
        }
        // 2. port
        if ($this->args->hasOption('port')) {
            $port = $this->args->getOption('port');
            $port && $this->setPort($port);
        }
        // n. end
        return $this;
    }

    /**
     * 将配置写入文件
     */
    public function save()
    {
        $data = $this->generate();
        $file = $this->generateFile();
        file_put_contents($file, $data);
        return $this;
    }

    /**
     * 设置是否守护启动
     * @param bool $daemon
     * @return $this
     */
    public function setDaemon(bool $daemon = true)
    {
        $this->_settings['daemonize'] = $daemon ? 1 : 0;
        return $this;
    }

    /**
     * 设置IP地址
     * @param string $name
     * @return $this
     */
    public function setHost(string $name)
    {
        $host = preg_match("/^\d+\.\d+\.\d+\.\d+$/", $name) > 0 ? $name : false;
        $host || $host = $this->ipFromAddr($name);
        $host || $host = $this->ipFromConfig($name);
        $host || $host = $name;
        $this->_host = $host;
        return $this;
    }

    /**
     * 设置端口号
     * @param int $port
     * @return $this
     */
    public function setPort(int $port)
    {
        $this->_port = (int) $port;
        return $this;
    }

    /**
     * @param string $name
     * @return  false|string
     */
    private function ipFromAddr(string $name)
    {
        $cmd = "ip -o -4 addr list '{$name}' | head -n1 | awk '{print \$4}' | cut -d/ -f1";
        $addr = shell_exec($cmd);
        $addr = trim($addr);
        if ($addr !== "" && preg_match("/^\d+\.\d+\.\d+\.\d+$/", $addr) > 0) {
            return $addr;
        }
        return false;
    }

    /**
     * @param string $name
     * @return false|string
     */
    private function ipFromConfig(string $name)
    {
        // 1. read all
        $cmd = 'ifconfig';
        $str = shell_exec($cmd);
        $str = preg_replace("/\n\s+/", " ", $str);
        // 2. filter host
        if (preg_match("/({$name}[^\n]+)/", $str, $m) === 0) {
            return false;
        }
        // 3. filter ip addr
        //    inet addr:10.168.74.190
        //    inet 192.168.10.116
        if (preg_match("/inet\s+[a-z:]*(\d+\.\d+\.\d+\.\d+)/", $m[1], $z) > 0) {
            return $z[1];
        }
        return false;
    }
}
