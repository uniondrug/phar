<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */

namespace Uniondrug\Phar\Server\Bases;

use Uniondrug\Phar\Exceptions\ConfigException;
use Uniondrug\Phar\Server\Logs\Logger;
use Uniondrug\Phar\Server\XHttp;

/**
 * 配置管理
 * 1. Phar
 * 2. Swoole
 * 3. Application
 * @property string $appName             应用(模块)名称
 * @property string $appVersion          应用版本号
 * @property string $charset             默认编码
 * @property string $contentType         默认文档类型
 * @property int $statusCode          默认HTTP状态码
 * @property double $slowRequestDuration 请求开始到结束用时, 超过此值加入慢日志
 * @property int $memoryLimit         当Worker进程使用量达此临界值时, 退出重启
 * @property string $deployIp            部署机器的IP地址
 * @property string $class               服务类名
 * @property string $host                鉴听IP
 * @property int $port                鉴听Port
 * @property int $serverMode          Server模式
 * @property int $serverSockType      Sock类型
 * @property int $processStdInOut
 * @property int $processCreatePipe
 * @package Uniondrug\Phar\Server\Bases
 */
class Config
{
    /**
     * @var Args
     */
    private $_args;
    /**
     * 扫描config目录结果
     * @var array
     */
    private $_configurations = [];
    /**
     * 全局Phar配置
     * @var array
     */
    private $_configPhpArchive = [
        'appName' => 'sketch',
        'appVersion' => '0.0.0',
        'charset' => 'utf-8',
        'contentType' => 'application/json;charset=utf-8',
        'statusCode' => 200,
        'slowRequestDuration' => 1.0,
        'memoryLimit' => 0,
        'deployIp' => '',
        'class' => '',
        'host' => '0.0.0.0',
        'port' => 8080,
        'serverMode' => SWOOLE_PROCESS,
        'serverSockType' => SWOOLE_SOCK_TCP,
        'processStdInOut' => false,
        'processCreatePipe' => true
    ];
    private $_logLevel = 0;
    private $_logRedis = false;
    private $_logRedisCfg = [];
    private $_logRedisKey = 'logger';
    private $_logRedisDeadline = 86400;
    private $_logKafka = false;
    private $_logFile = false;
    private $_logKafkaUrl = '';
    private $_logKafkaTimeout = 30;
    /**
     * 定时器目录
     * <code>
     * $_swooleCrontabs = [
     *     'app/Servers/Crons' => "\\App\\Servers\\Crons"
     * ]
     * </code>
     * @var array
     */
    private $_swooleCrontabs = [
        'app/Servers/Crons' => "\\App\\Servers\\Crons\\"
    ];
    /**
     * 事件定义
     * 来在配置文件`config/server.php`的定义
     * @var array
     */
    private $_swooleEvents = [
        'finish',
        'managerStart',
        'managerStop',
        'pipeMessage',
        'request',
        'shutdown',
        'start',
        'task',
        'workerStart',
        'workerStop',
    ];
    /**
     * Process进程
     * @var array
     */
    private $_swooleProcesses = [];
    /**
     * Swoole参数
     * @var array
     */
    private $_swooleSettings = [
        'reactor_num' => 8,
        'worker_num' => 8,
        'max_request' => 5000,
        'task_worker_num' => 4,
        'task_max_request' => 5000,
        'dispatch_mode' => 1,
        'open_tcp_nodelay' => 1,
        'buffer_output_size' => 16777216,
        'enable_static_handler' => false,
        'log_level' => 4,
        'request_slowlog_file' => '',
        'request_slowlog_timeout' => 5,
        'daemonize' => 0,
        'reload_async' => true
    ];
    /**
     * 内存表
     * @var array
     */
    private $_swooleTables = [];

    /**
     * Config constructor.
     * @param Args $args
     */
    public function __construct(Args $args)
    {
        $this->_args = $args;
        $this->_scanner();
        $this->_merger();
        $this->_mergerArgs();
    }

    /**
     * @param string $name
     * @return mixed
     * @throws ConfigException
     */
    public function __get(string $name)
    {
        if (isset($this->_configPhpArchive[$name])) {
            return $this->_configPhpArchive[$name];
        }
        throw new ConfigException("unknown {$name} config");
    }

    /**
     * @return Args
     */
    public function getArgs()
    {
        return $this->_args;
    }

    /**
     * Logger/Kafka超时
     * @return int
     */
    public function getKafkaTimeout()
    {
        return $this->_logKafkaTimeout;
    }

    /**
     * Logger/Kafka地址
     * @return string
     */
    public function getKafkaUrl()
    {
        return $this->_logKafkaUrl;
    }

    /**
     * Logger/Redis配置
     * @return array
     */
    public function getRedisCfg()
    {
        return $this->_logRedisCfg;
    }

    /**
     * Logger/Redis键前缀
     * @return string
     */
    public function getRedisKey()
    {
        return $this->_logRedisKey;
    }

    /**
     * Logger/Redis键前缀
     * @return string
     */
    public function getRedisDeadline()
    {
        return $this->_logRedisDeadline;
    }

    /**
     * 日志级别
     * @return int
     */
    public function getLogLevel()
    {
        return $this->_logLevel;
    }

    /**
     * 扫描结果
     * 扫描配置文件合并配置结果
     * @return array
     * @throws ConfigException
     */
    public function getScanned()
    {
        $data = [];
        $rexp = "/^([a-z][\w]*)\.php$/";
        $scan = dir($this->_args->configPath());
        $type = $this->_args->getEnvironment();
        while (false !== ($entry = $scan->read())) {
            // 3. 文件名不符合规则
            if (preg_match($rexp, $entry, $m) === 0) {
                continue;
            }
            // 4. 文件内容不是合法数组
            $cfgData = include($this->_args->configPath() . "/" . $entry);
            if (!is_array($cfgData)) {
                throw new ConfigException("配置文件{$entry}不是有效的数组");
            }
            // 5. 解析数据
            if (isset($cfgData['default']) || isset($cfgData['development']) || $cfgData['testing'] || $cfgData['release'] || $cfgData['production']) {
                $tmpData = isset($cfgData['default']) && is_array($cfgData['default']) ? $cfgData['default'] : [];
                $envData = isset($cfgData[$type]) && is_array($cfgData[$type]) ? $cfgData[$type] : [];
                $data[$m[1]] = [
                    'key' => $type,
                    'value' => array_replace_recursive($tmpData, $envData)
                ];
            } else {
                $data[$m[1]] = [
                    'key' => $type,
                    'value' => $cfgData
                ];
            }
        }
        $scan->close();
        return $data;
    }

    /**
     * 读取配置项
     * @param string $section
     * @param bool $throws
     * @return array|false
     * @throws ConfigException
     */
    public function getSection(string $section, bool $throws = true)
    {
        $value = 'value';
        if (isset($this->_configurations[$section]) && isset($this->_configurations[$section][$value])) {
            return $this->_configurations[$section][$value];
        }
        if ($throws) {
            throw new ConfigException("unknown {$section} section of config.");
        }
        return false;
    }

    /**
     * 定时器目录名
     * @return array
     */
    public function getSwooleCrontabs()
    {
        return $this->_swooleCrontabs;
    }

    /**
     * 读取事件列表
     * @return array
     */
    public function getSwooleEvents()
    {
        return $this->_swooleEvents;
    }

    /**
     * Swoole进程列表
     * @return array
     */
    public function getSwooleProcesses()
    {
        return $this->_swooleProcesses;
    }

    /**
     * Swoole启动参数
     * @return array
     */
    public function getSwooleSettings()
    {
        return $this->_swooleSettings;
    }

    /**
     * 内存表定义
     * @return array
     */
    public function getSwooleTables()
    {
        return $this->_swooleTables;
    }

    /**
     * KafkaLooger状态
     * @return bool
     */
    public function isFileLogger()
    {
        return $this->_logFile;
    }

    /**
     * KafkaLooger状态
     * @return bool
     */
    public function isKafkaLogger()
    {
        return $this->_logKafka;
    }

    /**
     * RedisLooger状态
     * @return bool
     */
    public function isRedisLogger()
    {
        return $this->_logRedis;
    }

    /**
     * 加载文件
     * 当配置变量时, 通过向Master进程发送SIGUSR1信号, Master
     * 进程将通知Manager进程重载加载配置信息, 并重启子进程
     * 1. Process
     * 2. Worker/Tasker
     */
    public function reload()
    {
        $this->_scanner();
        $this->_merger();
        $this->_mergerArgs();
    }

    /**
     * 扫描配置信息
     */
    private function _scanner()
    {
        // 1. 从TMP文件中读取
        //    TMP文件由config目录下的文件与Consul-KV合并后
        //    的结果
        $tmpFile = $this->_args->tmpPath() . '/config.php';
        if (file_exists($tmpFile)) {
            $tmpData = include($tmpFile);
            if (is_array($tmpData)) {
                $this->_configurations = $tmpData;
                return;
            }
            throw new ConfigException("临时文件{$tmpFile}不是有效的数组");
        }
        // 2. 扫描配置文件目录
        $this->_configurations = $this->getScanned();
    }

    /**
     * 合并配置信息到
     */
    private function _merger()
    {
        // 1. APP选项
        $app = $this->getSection('app');
        isset($app['appName']) && $this->_configPhpArchive['appName'] = $app['appName'];
        isset($app['appVersion']) && $this->_configPhpArchive['appVersion'] = $app['appVersion'];
        // 2. SERV基础项
        $serv = $this->getSection('server');
        isset($serv['charset']) && $this->_configPhpArchive['charset'] = $serv['charset'];
        isset($serv['contentType']) && $this->_configPhpArchive['contentType'] = $serv['contentType'];
        isset($serv['statusCode']) && is_numeric($serv['statusCode']) && $serv['statusCode'] > 0 && $this->_configPhpArchive['statusCode'] = (int)$serv['statusCode'];
        isset($serv['slowRequestDuration']) && is_numeric($serv['slowRequestDuration']) && $serv['slowRequestDuration'] > 0 && $this->_configPhpArchive['slowRequestDuration'] = (double)$serv['slowRequestDuration'];
        isset($serv['memoryLimit']) && is_numeric($serv['memoryLimit']) && $serv['memoryLimit'] > 0 && $this->_configPhpArchive['memoryLimit'] = (int)$serv['memoryLimit'];
        isset($serv['serverMode']) && is_numeric($serv['serverMode']) && $this->_configPhpArchive['serverMode'] = (int)$serv['serverMode'];
        isset($serv['serverSockType']) && is_numeric($serv['serverSockType']) && $this->_configPhpArchive['serverSockType'] = (int)$serv['serverSockType'];
        isset($serv['processStdInOut']) && is_bool($serv['processStdInOut']) && $this->_configPhpArchive['processStdInOut'] = $serv['processStdInOut'];
        isset($serv['processCreatePipe']) && is_bool($serv['processCreatePipe']) && $this->_configPhpArchive['processCreatePipe'] = $serv['processCreatePipe'];
        // 3. SERV启动点
        $this->_configPhpArchive['deployIp'] = $this->_args->getDeployIp();
        $this->_configPhpArchive['class'] = isset($serv['class']) && is_string($serv['class']) && $serv['class'] !== '' ? $serv['class'] : XHttp::class;
        $host = isset($serv['host']) && is_string($serv['host']) && $serv['host'] !== '' ? $serv['host'] : false;
        if ($host) {
            if (preg_match("/(\S+):(\d+)/", $host, $m) > 0) {
                $this->_configPhpArchive['port'] = (int)$m[2];
                if (preg_match("/^\d+\.\d+\.\d+\.\d+$/", $m[1]) > 0) {
                    $this->_configPhpArchive['host'] = $m[1];
                } else {
                    $addr = $this->_args->getIpAddr($m[1]);
                    if ($addr !== false) {
                        $this->_configPhpArchive['host'] = $addr;
                    }
                }
            }
        }
        // 4. 高级应用
        //    a). 共享内存表
        //    b). Process进程
        //    c). Swoole服务配置
        //    d). Swoole事件列表
        isset($serv['tables']) && is_array($serv['tables']) && $this->_swooleTables = array_replace_recursive($this->_swooleTables, $serv['tables']);
        isset($serv['processes']) && is_array($serv['processes']) && $this->_swooleProcesses = array_replace_recursive($this->_swooleProcesses, $serv['processes']);
        isset($serv['events']) && is_array($serv['events']) && $this->_swooleEvents = array_replace_recursive($this->_swooleEvents, $serv['events']);
        isset($serv['settings']) && is_array($serv['settings']) && $this->_swooleSettings = array_replace_recursive($this->_swooleSettings, $serv['settings']);
        isset($serv['crontabs']) && is_array($serv['crontabs']) && $this->_swooleCrontabs = $serv['crontabs'];
        // 5. Swoole参数
        $this->_swooleSettings['log_file'] = $this->_args->logPath() . '/server.log';
        $this->_swooleSettings['pid_file'] = $this->_args->logPath() . '/server.pid';
        $this->_swooleSettings['request_slowlog_file'] = $this->_args->logPath() . '/slow.log';
        $this->_swooleSettings['task_tmpdir'] = $this->_args->tmpPath() . '/tasks';
        $this->_swooleSettings['upload_tmp_dir'] = $this->_args->tmpPath() . '/uploads';
        // 6. Logger
        //    a): Redis
        //    b): Kafka
        //    c): File/Local
        $servLogger = isset($serv['logger']) && is_array($serv['logger']) ? $serv['logger'] : [];
        // 6.1 Kafka: 发送到Kafka
        if (isset($servLogger['kafkaOn'], $servLogger['kafkaUrl']) && $servLogger['kafkaUrl'] !== '') {
            if (is_bool($servLogger['kafkaOn'])) {
                $this->_logKafka = $servLogger['kafkaOn'];
            } else if (is_string($servLogger['kafkaOn'])) {
                $this->_logKafka = strtolower($servLogger['kafkaOn']) === 'true';
            }
            if ($this->_logKafka) {
                $this->_logKafkaUrl = $servLogger['kafkaUrl'];
                if (isset($servLogger['kafkaTimeout']) && is_numeric($servLogger['kafkaTimeout']) && $servLogger['kafkaTimeout'] > 0) {
                    $this->_logKafkaTimeout = (int)$servLogger['kafkaTimeout'];
                }
            }
        }
        // 6.2 Redis: 发送到Redis
        if (isset($servLogger['redisOn'], $servLogger['redisCfg']) && is_array($servLogger['redisCfg'])) {
            if (is_bool($servLogger['redisOn'])) {
                $this->_logRedis = $servLogger['redisOn'];
            } else if (is_string($servLogger['redisOn'])) {
                $this->_logRedis = strtolower($servLogger['redisOn']) === 'true';
            }
            if ($this->_logRedis) {
                $this->_logRedisCfg = $servLogger['redisCfg'];
                if (isset($servLogger['redisKey']) && is_string($servLogger['redisKey']) && $servLogger['redisKey'] !== '') {
                    $this->_logRedisKey = $servLogger['redisKey'];
                }
                if (isset($servLogger['redisDeadline']) && is_numeric($servLogger['redisDeadline']) && $servLogger['redisDeadline'] > 0) {
                    $this->_logRedisDeadline = (int)$servLogger['redisDeadline'];
                }
            }
        }
        // 6.3 File: 日志落盘
        if (isset($servLogger['fileOn'])) {
            if (is_bool($servLogger['fileOn'])) {
                $this->_logFile = $servLogger['fileOn'];
            } else if (is_string($servLogger['fileOn'])) {
                $this->_logFile = strtolower($servLogger['fileOn']) === 'true';
            }
        }
        if ($this->_logFile === false) {
            if (!$this->_logKafka && !$this->_logRedis) {
                $this->_logFile = true;
            }
        }
        // 7. Listener
        // 8. static support
        if (isset($serv['enable_static_handler'])) {
            if (is_bool($serv['enable_static_handler'])) {
                $this->_swooleSettings['enable_static_handler'] = $serv['enable_static_handler'];
            } else if (is_string($serv['enable_static_handler'])) {
                $this->_swooleSettings['enable_static_handler'] = 'true' === strtolower($serv['enable_static_handler']);
            }
            if ($this->_swooleSettings['enable_static_handler']) {
                if (isset($serv['document_root']) && is_string($serv['document_root']) && $serv['document_root'] !== '') {
                    $this->_swooleSettings['document_root'] = $this->_args->workingPath() . '/' . $serv['document_root'];
                } else {
                    $this->_swooleSettings['document_root'] = $this->_args->assetsPath();
                }
            }
        }
        // n. 内存极限
        if ($this->_configPhpArchive['memoryLimit'] === 0) {
            if (preg_match("/(\d+)(\S*)/", ini_get('memory_limit'), $m) > 0) {
                $this->_configPhpArchive['memoryLimit'] = (int)$m[1];
                switch (strtoupper($m[2][0])) {
                    case 'K' :
                        $this->_configPhpArchive['memoryLimit'] *= 1024;
                        break;
                    case 'M' :
                        $this->_configPhpArchive['memoryLimit'] *= 1024 * 1024;
                        break;
                    case 'G' :
                        $this->_configPhpArchive['memoryLimit'] *= 1024 * 1024 * 1024;
                        break;
                }
                $this->_configPhpArchive['memoryLimit'] -= 8 * 1024 * 1024;
            } else {
                // 120M
                $this->_configPhpArchive['memoryLimit'] = 125829120;
            }
        }
    }

    /**
     * 从Args合并配置
     */
    private function _mergerArgs()
    {
        // 1. 守护进程
        $this->_args->hasOption('daemon') && $this->_swooleSettings['daemonize'] = 1;
        // 2. host
        $host = $this->_args->getOption('host');
        $host && $this->_configPhpArchive['host'] = $host;
        // 3. port
        $port = $this->_args->getOption('port');
        $port && $this->_configPhpArchive['port'] = $port;
        // 4. reactor-num
        $reactorNum = $this->_args->getOption('reactor-num');
        if ($reactorNum !== false && $reactorNum > 0) {
            $this->_swooleSettings['reactor_num'] = (int)$reactorNum;
        }
        // 5. worker-num
        $workerNum = $this->_args->getOption('worker-num');
        if ($workerNum !== false && $workerNum > 0) {
            $this->_swooleSettings['worker_num'] = (int)$workerNum;
        }
        // 6. tasker-num
        $taskerNum = $this->_args->getOption('tasker-num');
        if ($taskerNum !== false && $taskerNum > 0) {
            $this->_swooleSettings['task_worker_num'] = (int)$taskerNum;
        }
        // 7. logger level
        $level = Logger::LEVEL_DEBUG;
        $swooleLevel = 5;
        // 7.1 优先使用命令行启动选项
        $logLevel = $this->_args->getOption('log-level');
        // 7.2 次选配置文件选项
        if ($logLevel === false) {
            $serv = $this->getSection('server');
            if (isset($serv['logger'], $serv['logger']['level']) && is_string($serv['logger']['level'])) {
                $logLevel = $serv['logger']['level'];
            }
        }
        // 7.3 选项设别转换
        if ($logLevel !== false) {
            switch (strtoupper($logLevel)) {
                case "DEBUG" :
                    $level = Logger::LEVEL_DEBUG;
                    $swooleLevel = 0;
                    break;
                case "INFO" :
                    $level = Logger::LEVEL_INFO;
                    $swooleLevel = 2;
                    break;
                case "WARN" :
                case "WARNING" :
                case "WARNNING" :
                    $level = Logger::LEVEL_WARNING;
                    $swooleLevel = 4;
                    break;
                case "ERROR" :
                    $level = Logger::LEVEL_ERROR;
                    $swooleLevel = 5;
                    break;
                case "FATAL" :
                    $level = Logger::LEVEL_FATAL;
                    $swooleLevel = 5;
                    break;
            }
        }
        $this->_logLevel = $level;
        $this->_swooleSettings['log_level'] = $swooleLevel;
    }
}
