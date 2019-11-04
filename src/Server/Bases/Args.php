<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Bases;

use Uniondrug\Phar\Exceptions\ArgsException;

/**
 * 命令行管理
 * @package Uniondrug\Phar\Server\Bases
 */
class Args
{
    /**
     * 环境定义
     */
    const ENV_DEVELOPMENT = 'development';
    const ENV_TESTING = 'testing';
    const ENV_RELEASE = 'release';
    const ENV_PRODUCTION = 'production';
    /**
     * 命令名
     * @var string
     */
    private $_command = '';
    /**
     * 预定义域名
     * @var array
     */
    private $_domains = [
        'development' => 'dev.uniondrug.info',
        'testing' => 'turboradio.cn',
        'release' => 'uniondrug.net',
        'production' => 'uniondrug.cn'
    ];
    private $_domainSuffix;
    /**
     * 环境名
     * @var string
     */
    private $_environment;
    private $_ipAddresses = [];
    private $_workingPath;
    /**
     * 是否为DEV环境
     * @var bool
     */
    private $_isDevelopment;
    /**
     * 命令行选项
     * @var array
     */
    private $_options = [];
    /**
     * 脚本路径
     * @var string
     */
    private $_script = '';

    /**
     * Args constructor.
     */
    public function __construct()
    {
        $this->_parserIfconfig();
        $this->_parseArguments();
    }

    /**
     * 应用主目录
     * @return string
     * @return "/data/apps/sketch/app"
     * @return "/data/apps/sketch/name.phar/app"
     */
    public function appPath()
    {
        return $this->basePath()."/config";
    }

    /**
     * 资源目录
     * @return string
     * @example return "/data/apps/sketch/assets"
     */
    public function assetsPath()
    {
        return $this->workingPath()."/assets";
    }

    /**
     * 项目路径
     * @return string
     * @example return "/data/apps/sketch"
     * @example return "/data/apps/sketch/name.phar"
     */
    public function basePath()
    {
        return PHAR_ROOT;
    }

    /**
     * 应用主目录
     * @return string
     * @return "/data/apps/sketch/config"
     * @return "/data/apps/sketch/name.phar/config"
     */
    public function configPath()
    {
        return $this->basePath()."/config";
    }

    /**
     * 日志路径
     * @return string
     * @example return "/data/apps/sketch/log"
     */
    public function logPath()
    {
        return $this->workingPath()."/log";
    }

    /**
     * 临时路径
     * @return string
     * @example return "/data/apps/sketch/tmp"
     */
    public function tmpPath()
    {
        return $this->workingPath()."/tmp";
    }

    /**
     * 创建目录
     */
    public function buildPath()
    {
        is_dir($this->logPath()) || mkdir($this->logPath(), 0777, true);
        is_dir($this->tmpPath().'/tasks') || mkdir($this->tmpPath()."/tasks", 0777, true);
        is_dir($this->tmpPath().'/uploads') || mkdir($this->tmpPath()."/uploads", 0777, true);
    }

    /**
     * 工作路径
     * @return string
     * @example return "/data/apps/sketch"
     */
    public function workingPath()
    {
        if (defined("PHAR_WORKING_DIR")) {
            return PHAR_WORKING_DIR;
        }
        if ($this->_workingPath === null) {
            $this->_workingPath = getcwd();
        }
        return $this->_workingPath;
    }

    /**
     * 按网卡名读IP
     * @param string $name
     * @return string|false
     */
    public function getIpAddr(string $name)
    {
        if ($name === 'eth0') {
            $eth0 = $this->getOption('eth0');
            if (is_string($eth0) && $eth0 !== '') {
                $name = $eth0;
            }
        }
        if (isset($this->_ipAddresses[$name])) {
            return $this->_ipAddresses[$name];
        }
        return false;
    }

    /**
     * 读取部署机器IP地址
     * @return string
     */
    public function getDeployIp()
    {
        $ip = 'unknown';
        foreach ([
            'en0',
            'eth0',
            'lo',
            'lo0'
        ] as $name) {
            $addr = $this->getIpAddr($name);
            if ($addr !== false) {
                $ip = $addr;
                break;
            }
        }
        return $ip;
    }

    /**
     * 读取命令名
     * @return string
     */
    public function getCommand()
    {
        return $this->_command;
    }

    /**
     * 读取命令对应的类名
     * @return string|null
     */
    public function getCommandClass()
    {
        if ($this->_command === '') {
            return null;
        }
        $command = preg_replace_callback("/[\-]+(\S)/", function($a){
            return strtoupper($a[1]);
        }, $this->_command);
        return ucfirst($command);
    }

    /**
     * 读当前环境域名后缀
     * @return string
     */
    public function getDomainSuffix()
    {
        if ($this->_domainSuffix === null) {
            $env = $this->getEnvironment();
            if (isset($this->_domains[$env])) {
                $this->_domainSuffix = $this->_domains[$env];
            } else {
                $this->_domainSuffix = $this->_domains['development'];
            }
        }
        return $this->_domainSuffix;
    }

    /**
     * 读取全部环境域名后缀
     * @return array
     */
    public function getDomainSuffixes()
    {
        return $this->_domains;
    }

    /**
     * 读取环境名称
     * @return string
     */
    public function getEnvironment()
    {
        if ($this->_environment === null) {
            $env = $this->getOption('env');
            $env === false && $env = self::ENV_DEVELOPMENT;
            $this->_environment = $env;
        }
        return $this->_environment;
    }

    /**
     * 读取环境类型/短名
     * @return string
     */
    public function getEnvironmentType()
    {
        return strtolower(substr($this->getEnvironment(), 0, 1));
    }

    /**
     * 读取选项值
     * @param string $name
     * @return false|string
     */
    public function getOption(string $name)
    {
        if ($this->hasOption($name)) {
            return $this->_options[$name];
        }
        return false;
    }

    /**
     * 读全部选项
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * 读取脚本名称
     * @return string
     */
    public function getScript()
    {
        return $this->_script;
    }

    /**
     * 选项是否定义
     * @param string $name
     * @return bool
     */
    public function hasOption(string $name)
    {
        return isset($this->_options[$name]) && $this->_options[$name] !== null;
    }

    /**
     * 是否为DEV环境
     * @return bool
     */
    public function isDevelopment()
    {
        if ($this->_isDevelopment === null) {
            $this->_isDevelopment = strtolower($this->getEnvironment()) === self::ENV_DEVELOPMENT;
        }
        return $this->_isDevelopment;
    }

    /**
     * 解析参数选项
     * @param string $key
     * @param string $value
     */
    private function _parseArgument(string $key, string $value = '')
    {
        $this->_options[$key] = $value;
    }

    /**
     * 解析命令行入参
     */
    private function _parseArguments()
    {
        // 1. arguments count
        $argv = isset($_SERVER, $_SERVER['argv']) && is_array($_SERVER['argv']) ? $_SERVER['argv'] : [];
        if (count($argv) < 1) {
            throw new ArgsException("unknown command line arguments");
        }
        // 2. regular expressions
        $rexpIsOption = "/^\-/";
        $rexpIsValue = "/^\-\-([_a-zA-Z0-9\-]{2,})=(.*)/";
        $rexpIsDouble = "/^\-\-([_a-z0-9A-Z\-]{2,})$/";
        $rexpIsSingal = "/^\-([a-z0-9A-Z]+)$/";
        // 3. script
        $this->_script = $argv[0];
        array_shift($argv);
        // 4. command
        if (isset($argv[0]) && !preg_match($rexpIsOption, $argv[0])) {
            $this->_command = $argv[0];
            array_shift($argv);
        }
        // 5. options & values
        $key = null;
        foreach ($argv as $arg) {
            // 5.1 选项赋值
            //     eg. --env=production
            if (preg_match($rexpIsValue, $arg, $m) > 0) {
                $this->_parseArgument($m[1], $m[2]);
                continue;
            }
            // 5.2 选项命名
            //     eg. --log-stdout
            if (preg_match($rexpIsDouble, $arg, $m) > 0) {
                $key = $m[1];
                $this->_parseArgument($key);
                continue;
            }
            // 5.3 单一选项
            //     eg. -d
            if (preg_match($rexpIsSingal, $arg, $m)) {
                for ($i = 0; $i < strlen($m[1]); $i++) {
                    $k = $this->_parseArgumentKey($m[1][$i]);
                    if ($k !== null) {
                        $key = $k;
                        $this->_parseArgument($k);
                    }
                }
                continue;
            }
            // 5.4 无效参数
            if (preg_match($rexpIsOption, $arg) > 0) {
                throw new ArgsException("unknown '{$arg}' option");
            }
            // 5.5 赋值
            if ($key !== null) {
                $this->_parseArgument($key, $arg);
                $key = null;
            }
        }
        if (isset($this->_options['consul-domain']) && is_string($this->_options['consul-domain']) && $this->_options['consul-domain'] !== '') {
            $this->_domainSuffix = $this->_options['consul-domain'];
        }
    }

    /**
     * 单选项转名称
     * @param string $key
     * @return string|null
     */
    private function _parseArgumentKey(string $key)
    {
        switch ($key) {
            case 'd' :
                $name = 'daemon';
                break;
            case 'e' :
                $name = 'env';
                break;
            case 'h' :
                $name = 'help';
                break;
            default :
                $name = $key;
                break;
        }
        return $name;
    }

    /**
     * 解析IP地址
     * 通过执行ifconfig命令, 获取全部网卡信息, 并
     * 从中提取IPv4片段, 并存入$_ipAddresses私有
     * 属性中
     */
    private function _parserIfconfig()
    {
        // 1. 读取网卡信息
        //    ifconfig
        $lines = $comma = "";
        $buffer = shell_exec('ifconfig');
        foreach (explode("\n", $buffer) as $line) {
            $name = preg_match("/^\S/", $line) > 0;
            if ($name) {
                $lines .= "\r\n";
            }
            $line = trim($line);
            if ($line !== '') {
                $lines .= $line." ";
            }
        }
        // 2. 解析IP
        foreach (explode("\n", $lines) as $line) {
            if (preg_match("/^([_a-zA-Z0-9\-]+)/", $line, $m) === 0) {
                continue;
            }
            if (preg_match("/inet\s+(\d+\.\d+\.\d+\.\d+)/", $line, $x) > 0) {
                $this->_ipAddresses[$m[1]] = $x[1];
            } else if (preg_match("/inet\s+addr\s*:\s*(\d+\.\d+\.\d+\.\d+)/", $line, $x) > 0) {
                $this->_ipAddresses[$m[1]] = $x[1];
            }
        }
    }
}
