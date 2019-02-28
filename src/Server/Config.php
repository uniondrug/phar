<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server;

use Uniondrug\Phar\Server\Exceptions\ConfigExeption;

/**
 * Server/服务配置
 * @property string $environment          环境名
 * @property int    $charset              编码
 * @property int    $contentType          输出格式
 * @property int    $logLevel             Log级别
 * @property bool   $logKafkaOn           Kafka/是否启用Kafak日志
 * @property string $logKafkaUrl          Kafka/Restful地址
 * @property int    $logKafkaTimeout      提交Kafka超时时长
 * @property int    $logBatchLimit        Log保存数量
 * @property int    $logBatchSeconds      Log保存时长
 * @property string $name                 项目名
 * @property string $version              项目版本
 * @property string $host                 IP地址
 * @property int    $port                 端口号
 * @property int    $serverMode           Server模式
 * @property int    $serverSockType       ServerSock类型
 * @property array  $settings             Swoole参数
 * @property array  $events               事件列表
 * @property array  $crons                定时任务列表
 * @property array  $tables               内存表列表
 * @property array  $processes            Process进程列表
 * @property bool   $processesStdRedirect redirect stdin/out
 * @property bool   $processesCreatePipe  create pipe
 * @package Uniondrug\Phar\Bootstrap
 */
class Config
{
    const DEFAULT_PROCESSES_PIPE = true;
    const DEFAULT_PROCESSES_STD_REDIRECT = false;
    const DEFAULT_HOST = "0.0.0.0";
    const DEFAULT_PORT = 8080;
    /**
     * 命令行Args对象
     * @var Args
     */
    private $args;
    private $addr;
    /**
     * PHP默认最大可申请内存
     * @var int
     */
    private $memoryLimit = 134217728;
    private $memoryAllow = 0;
    private $memoryProtected = 8388608;
    private $_charset = 'utf-8';
    private $_contentType = 'application/json';
    /**
     * 日志落盘频率
     * 每100条Log保存一次
     * @var int
     */
    private $_logBatchLimit = 100;
    /**
     * 日志落盘频率
     * 每隔60秒保存一次Log
     * @var int
     */
    private $_logBatchSeconds = 60;
    /**
     * 日志启用Kafka
     * 启用Kafka时, Kafka对应的URL必须指定, 当启用时
     * 日志将批量发到Kafka, 由Kafka进程日志处理
     * @var bool
     */
    private $_logKafkaOn = false;
    /**
     * Kafka接收地址
     * 接收Log的Kafka服务的URL地址
     * @var string
     */
    private $_logKafkaUrl = "";
    /**
     * Kafka超时
     * 将Log提交到Kafka超时时长
     * @var int
     */
    private $_logKafkaTimeout = 25;
    /**
     * 日志级别
     * @var int
     */
    private $_logLevel = Logger::LEVEL_INFO;
    /**
     * HTTP服务对象
     * 本项为默认值, 可通过配置文件的class字段重定义
     * @var string
     */
    private $_class = XHttp::class;
    /**
     * 运行环境名
     * 支持development、testing、release、production中之一
     * @var string
     */
    private $_environment;
    /**
     * 项目名称
     * @var string
     */
    private $_name = 'sketch';
    /**
     * 项目版本
     * @var string
     */
    private $_version = '0.0.0';
    /**
     * 启动主机/IP地址
     * @var string
     */
    private $_host = self::DEFAULT_HOST;
    /**
     * 启动端口
     * @var int
     */
    private $_port = self::DEFAULT_PORT;
    /**
     * 服务模式
     * 支持SWOOLE_PROCESS、SWOOLE_BASE
     * @var int
     */
    private $_serverMode = \SWOOLE_PROCESS;
    /**
     * Sock类型
     * @var int
     */
    private $_serverSockType = \SWOOLE_SOCK_TCP;
    /**
     * SwooleServer配置
     * 可以配置文件的settings字段重定义
     * @var array
     */
    private $_settings = [
        'reactor_num' => 8,
        'worker_num' => 8,
        'max_request' => 5000,
        'task_worker_num' => 4,
        'task_max_request' => 5000,
        'log_level' => 4,
        'request_slowlog_file' => '',
        'request_slowlog_timeout' => 5
    ];
    /**
     * Swoole事件
     * 可以配置文件的events字段重定义
     * @var array
     */
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
     * 定时任务
     * @var array
     */
    private $_crons = [];
    /**
     * 内存表
     * @var array
     */
    private $_tables = [];
    /**
     * Process进程
     * @var array
     */
    private $_processes = [];
    /**
     * Process STD重定向
     * @var bool
     */
    private $_processesStdRedirect = self::DEFAULT_PROCESSES_STD_REDIRECT;
    /**
     * Process Pipe管道
     * @var bool
     */
    private $_processesCreatePipe = self::DEFAULT_PROCESSES_PIPE;

    /**
     * @param Args $args
     */
    public function __construct(Args $args)
    {
        $this->args = $args;
        // 1. PHP最大可申请内存峰值
        $iniMemory = trim(ini_get('memory_limit'));
        if (preg_match("/^(\d+)([^\d]+)$/", $iniMemory, $m) > 0) {
            $m[1] = (int) $m[1];
            $m[2] = strtoupper($m[2]);
            switch ($m[2]) {
                case 'K' :
                case 'KB' :
                    $this->memoryLimit = $m[1] * 1024;
                    break;
                case 'M' :
                case 'MB' :
                    $this->memoryLimit = $m[1] * 1024 * 1024;
                    break;
                case 'G' :
                case "GB" :
                    $this->memoryLimit = $m[1] * 1024 * 1024 * 1024;
            }
        } else if (preg_match("/^\d+$/", $iniMemory)) {
            $this->memoryLimit = (int) $iniMemory;
        }
        // 2. PHAR允许申请内存值
        //    当实际超过此时值, 退出Worker/Tasker进程并重启
        $this->memoryAllow = $this->memoryLimit - $this->memoryProtected;
        $this->memoryAllow < 0 && $this->memoryAllow = 58720256; // 最低56M内存
        // 3. 环境名称定义
        $this->_environment = $this->args->getEnvironment();
    }

    /**
     * 读取配置项
     * @param string $name
     * @return mixed
     */
    final public function __get($name)
    {
        $prop = "_{$name}";
        if (isset($this->{$prop})) {
            return $this->{$prop};
        }
        throw new ConfigExeption("call undefined '{$name}' configuration.");
    }

    /**
     * 配置项只读模式
     * @param string $name
     * @param mixed  $value
     */
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
        $file = $this->args->getTmpDir().'/config.php';
        if (file_exists($file)) {
            $conf = include($file);
        } else {
            // 1. 扫描配置文件目录
            $env = $this->_environment;
            if (defined('PHAR_WORKING')) {
                $path = __DIR__.'/../../../../../config';
            } else {
                $path = $this->args->getBasePath().'/config';
            }
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
        if (isset($app['charset']) && is_string($app['charset']) && $app['charset'] !== '') {
            $this->_charset = $app['charset'];
        }
        if (isset($app['contentType']) && is_string($app['contentType']) && $app['contentType'] !== '') {
            $this->_contentType = $app['contentType'];
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
        $settings = $this->_settings;
        if (isset($srv['settings']) && is_array($srv['settings'])) {
            $this->_settings = array_replace_recursive($settings, $srv['settings']);
        }
        $this->_settings['pid_file'] = $this->args->getTmpDir().'/server.pid';
        $this->_settings['log_file'] = $this->args->getLogDir().'/server.log';
        $this->_settings['request_slowlog_file'] = $this->args->getLogDir().'/slow.log';
        $this->_settings['task_tmpdir'] = $this->args->getTmpDir().'/tasks';
        // 6.3 Tables
        if (isset($srv['tables']) && is_array($srv['tables'])) {
            $this->_tables = $srv['tables'];
        }
        // 6.4 Process支持
        if (isset($srv['processes']) && is_array($srv['processes'])) {
            $this->_processes = $srv['processes'];
        }
        if (isset($srv['processesCreatePipe']) && is_bool($srv['processesCreatePipe'])) {
            $this->_processesCreatePipe = self::DEFAULT_PROCESSES_PIPE;
        }
        if (isset($srv['processesStdRedirect']) && is_bool($srv['processesStdRedirect'])) {
            $this->_processesStdRedirect = self::DEFAULT_PROCESSES_STD_REDIRECT;
        }
        // 6.5 定时任务
        //     类似Crontab
        if (isset($srv['crons']) && is_array($srv['crons'])) {
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
        // 7. logger
        if (isset($srv['logBatchLimit']) && is_numeric($srv['logBatchLimit']) && $srv['logBatchLimit'] >= 32 && $srv['logBatchLimit'] <= 1024) {
            $this->_logBatchLimit = (int) $srv['logBatchLimit'];
        }
        if (isset($srv['logBatchSeconds']) && is_numeric($srv['logBatchSeconds']) && $srv['logBatchSeconds'] >= 3 && $srv['logBatchSeconds'] <= 300) {
            $this->_logBatchSeconds = (int) $srv['logBatchSeconds'];
        }
        if (isset($srv['logKafkaOn'])) {
            if (is_bool($srv['logKafkaOn'])) {
                $this->_logKafkaOn = $srv['logKafkaOn'];
            } else if (is_string($srv['logKafkaOn'])) {
                $this->_logKafkaOn = strtolower($srv['logKafkaOn']) === "true";
            }
        }
        if (isset($srv['logKafkaUrl']) && is_string($srv['logKafkaUrl'])) {
            $this->_logKafkaUrl = $srv['logKafkaUrl'];
        }
        if (isset($srv['logKafkaTimeout']) && is_numeric($srv['logKafkaTimeout']) && $srv['logKafkaTimeout'] > 0) {
            $this->_logKafkaTimeout = (int) $srv['logKafkaTimeout'];
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
                    if (isset($this->{$name})) {
                        $this->{$name} = $value;
                    }
                }
            }
        }
        return $this;
    }

    /**
     * 生成配置字典
     * @return string
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

    /**
     * 生成配置文件名称
     * @return string
     */
    public function generateFile()
    {
        return $this->args->getTmpDir().'/server.cfg';
    }

    /**
     * PHAR允许内存
     * @return int
     */
    public function getAllowMemory()
    {
        return $this->memoryAllow;
    }

    /**
     * PHP最大申请内存
     * @return int
     */
    public function getLimitMemory()
    {
        return $this->memoryLimit;
    }

    /**
     * 读取管理端监听地址
     * @return string|null
     */
    public function getManagerHost()
    {
        if (preg_match("/^0\.0\.0\.0$/", $this->_host) > 0 || preg_match("/^127\.0\.0\.1$/", $this->_host) > 0) {
            return null;
        }
        return "127.0.0.1";
    }

    /**
     * 读取Server名称
     * 用于在Header中输出
     * @return string
     */
    public function getServerSoft()
    {
        return $this->name.'/'.$this->version;
    }

    /**
     * 合并配置
     * 将命令行中的参数合并到配置中
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
        // 3. env
        if ($this->args->hasOption('e')) {
            $e = $this->args->getOption('e');
            $e && $this->setEnvironment($e);
        } else if ($this->args->hasOption('env')) {
            $e = $this->args->getOption('env');
            $e && $this->setEnvironment($e);
        }
        // 4. log level
        $level = $this->args->getOption('log-level');
        if ($level) {
            $level = strtoupper($level);
            switch ($level) {
                case "DEBUG" :
                    $this->_logLevel = Logger::LEVEL_DEBUG;
                    break;
                case "INFO" :
                    $this->_logLevel = Logger::LEVEL_INFO;
                    break;
                case "WARNING" :
                    $this->_logLevel = Logger::LEVEL_WARNING;
                    break;
                case "ERROR" :
                    $this->_logLevel = Logger::LEVEL_ERROR;
                    break;
                case "FATAL" :
                    $this->_logLevel = Logger::LEVEL_FATAL;
                    break;
                case "OFF" :
                    $this->_logLevel = Logger::LEVEL_OFF;
                    break;
            }
        }
        // 5. worker num
        if ($this->args->hasOption('worker-num')) {
            $workerNum = (int) $this->args->getOption('worker-num');
            if ($workerNum > 0) {
                $this->_settings['worker_num'] = $workerNum;
            }
        }
        // 6. tasker num
        if ($this->args->hasOption('tasker-num')) {
            $taskerNum = (int) $this->args->getOption('tasker-num');
            if ($taskerNum > 0) {
                $this->_settings['task_worker_num'] = $taskerNum;
            }
        }
        // 7. reactor num
        if ($this->args->hasOption('reactor-num')) {
            $reactorNum = (int) $this->args->getOption('reactor-num');
            if ($reactorNum > 0) {
                $this->_settings['reactor_num'] = $reactorNum;
            }
        }
        // n. end
        return $this;
    }

    /**
     * 将启动配置落盘保存
     * @return $this
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
     * 设置环境名称
     * @param string $env
     * @return $this
     */
    public function setEnvironment(string $env)
    {
        $this->_environment = $env;
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
     * 读取部署机器的IP
     * @return string
     */
    public function getDeployIp()
    {
        if ($this->addr === null) {
            $names = [
                'eth0',
                'eth1',
                'en0'
            ];
            foreach ($names as $name) {
                $ip = $this->ipFromAddr($name);
                $ip || $ip = $this->ipFromConfig($name);
                if ($ip) {
                    $this->addr = $ip;
                    break;
                }
            }
            if ($this->addr === null) {
                $this->addr = '0.0.0.0';
            }
        }
        return $this->addr;
    }

    /**
     * 用网卡名读取IP地址
     * 使用Shell调用ip add
     * @param string $name
     * @return  false|string
     */
    public function ipFromAddr(string $name)
    {
        // stderr redirect
        $cmd = "ip -o -4 addr list '{$name}' | head -n1 | awk '{print \$4}' | cut -d/ -f1";
        $addr = shell_exec($cmd);
        $addr = trim($addr);
        if ($addr !== "" && preg_match("/^\d+\.\d+\.\d+\.\d+$/", $addr) > 0) {
            return $addr;
        }
        return false;
    }

    /**
     * 用网卡名读取IP地址
     * 使用Shell调用ifconfig
     * @param string $name
     * @return false|string
     */
    public function ipFromConfig(string $name)
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
