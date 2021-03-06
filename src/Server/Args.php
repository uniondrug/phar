<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server;

/**
 * 命令行入参
 * @package Uniondrug\Phar\Bootstrap
 */
class Args
{
    /**
     * 默认环境变量名称
     */
    const ENVIRONMENT_DEFAULT = 'development';
    /**
     * 项目根目录
     * @var string
     */
    private $basePath;
    /**
     * 脚本名称
     * @var string
     */
    private $script = "";
    /**
     * 命令名称
     * @var string
     */
    private $command;
    /**
     * 子命令名
     * 兼容原`php console`用法
     * @var string
     */
    private $subCommand;
    /**
     * 命令选项
     * 从命令行脚本中提取
     * @var array
     */
    private $options = [];
    private $errorReportingCode = E_ALL;

    /**
     * 从命令行解析入参
     */
    public function __construct()
    {
        // 1. arguments init
        $args = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? $_SERVER['argv'] : [];
        $argc = count($args);
        // 2. script
        if ($argc > 0) {
            $this->script = $args[0];
            array_shift($args);
            $argc--;
        }
        // 3. command|options
        if ($argc > 0) {
            $key = null;
            $rexpOpt = "/^[\-]+([_a-zA-Z0-9\-]+)/";
            $rexpFull = "/^[\-]+([_a-zA-Z0-9\-]+)[=|\s]+(.+)/";
            foreach ($args as $i => $arg) {
                // 3.0 空值
                $arg = trim($arg);
                if ($arg === '') {
                    continue;
                }
                // 3.1 完整模式
                //     --key=value
                //     --key value
                if (preg_match($rexpFull, $arg, $m) > 0) {
                    $m[1] = strtolower($m[1]);
                    $this->options[$m[1]] = trim($m[2]);
                    continue;
                }
                // 3.2 单项模式
                //     -k
                //     --key
                if (preg_match($rexpOpt, $arg, $m)) {
                    $m[1] = strtolower($m[1]);
                    $key = $m[1];
                    $this->options[$key] = "";
                    continue;
                }
                // 3.3 命令名
                if ($i === 0) {
                    $this->command = $arg;
                    continue;
                }
                // 3.4 子命令
                //     在php server console name命令时使用
                //     兼容原php console命令
                if ($i === 1) {
                    $this->subCommand = $arg;
                }
                // 3.5 值模式
                if ($key !== null) {
                    $this->options[$key] = $arg;
                    $key = null;
                }
            }
        }
        // 4. base path
        $this->basePath = getcwd();
    }

    /**
     * 读取应用工作目录
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * 读取命令
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * 读取子命令
     * @return string
     */
    public function getSubCommand()
    {
        return $this->subCommand;
    }

    /**
     * 环境名称
     * @return string
     */
    public function getEnvironment()
    {
        $e = $this->getOption('e');
        $e === null && $this->getOption('env');
        $e === null && $e = self::ENVIRONMENT_DEFAULT;
        return $e;
    }

    /**
     * 读取Log目录
     * @return string
     */
    public function getLogDir()
    {
        return $this->basePath.'/log';
    }

    /**
     * 读取指定选项
     * @param string $key
     * @return string|null
     */
    public function getOption(string $key)
    {
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }

    /**
     * 读取全部选项
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * 读取脚本名称
     * @return string
     */
    public function getScript()
    {
        return $this->script;
    }

    /**
     * 读取Tmp目录
     * @return string
     */
    public function getTmpDir()
    {
        return $this->basePath.'/tmp';
    }

    /**
     * 命令行选项是否传递
     * @param string $key
     * @return bool
     */
    public function hasOption(string $key)
    {
        return isset($this->options[$key]);
    }

    /**
     * 创建目录
     * @param $path
     * @return bool
     */
    public function makeDir($path)
    {
        if (@mkdir($path, 0777, true)) {
            return is_dir($path);
        }
        return false;
    }

    /**
     * 创建Log存储目录
     * @return bool
     */
    public function makeLogDir()
    {
        $path = $this->getLogDir();
        return is_dir($path) ? true : $this->makeDir($path);
    }

    /**
     * 创建Tmp存储目录
     * @return bool
     */
    public function makeTmpDir()
    {
        $path = $this->getTmpDir().'/tasks';
        return is_dir($path) ? true : $this->makeDir($path);
    }

    public function getErrorReportingCode()
    {
        return $this->errorReportingCode;
    }

    public function setErrorReportingCode(int $code)
    {
        $this->errorReportingCode = $code;
        return $this;
    }
}
