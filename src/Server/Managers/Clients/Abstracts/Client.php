<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients\Abstracts;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException as HttpClientException;
use GuzzleHttp\Exception\ConnectException as HttpConnectException;
use Uniondrug\Phar\Server\Managers\Clients\IClient;
use Uniondrug\Phar\Server\Bootstrap;
use Uniondrug\Phar\Server\XVersion;

/**
 * Client基类
 * @package Uniondrug\Phar\Server\Managers\Clients\Abstracts
 */
abstract class Client implements IClient
{
    /**
     * @var Bootstrap
     */
    public $boot;
    protected static $options = [];
    protected static $title = '';
    protected static $description = '';

    /**
     * @param Bootstrap $boot
     */
    public function __construct(Bootstrap $boot)
    {
        $this->boot = $boot;
        $this->loadConfig();
        // 1. 设置ENV参数量
        $environment = $this->boot->getConfig()->environment;
        putenv("APP_ENV={$environment}");
        // 2. 开始RUN
        $this->beforeRun();
    }

    /**
     * 前置执行
     */
    public function beforeRun() : void
    {
        /**
         * 版本信息
         */
        $phpver = PHP_VERSION;
        $swover = SWOOLE_VERSION;
        $phaver = \Phalcon\Version::get();
        $fraver = \Uniondrug\Framework\Container::VERSION;
        $this->printLine("当前项目: {green=%s/%s} in {green=%s}", $this->boot->getConfig()->name, $this->boot->getConfig()->version, $this->boot->getConfig()->environment);
        $this->printLine("项目目录: {green=%s}", $this->boot->getArgs()->getBasePath());
        $this->printLine("运行环境: {green=xphar/%s}, {green=php/%s}, {green=swoole/%s}, {green=phalcon/%s}, {green=framework/%s}", XVersion::get(), $phpver, $swover, $phaver, $fraver);
        $this->printLine("服务地址: {green=%s}:{green=%s}", $this->boot->getConfig()->host, $this->boot->getConfig()->port);
    }

    public static function getDescription() : string
    {
        return (string) static::$description;
    }

    public static function getOptions() : array
    {
        return static::$options;
    }

    public static function getTitle() : string
    {
        return (string) static::$title;
    }

    /**
     * 从历史记录读取配置
     */
    public function loadConfig()
    {
        $this->boot->getConfig()->fromHistory();
    }

    public function runHelp() : void
    {
    }

    /**
     * 向Agent发送HTTP请求
     * @param string $method
     * @param string $uri
     * @param array  $raw
     * @return bool|array
     */
    protected function callAgent(string $method, $uri, array $raw = null)
    {
        // 1. 请求地址
        $uri = preg_replace("/^\/+/", "", $uri);
        $url = sprintf("http://127.0.0.1:%d/%s.agent", $this->boot->getConfig()->port, $uri);
        // 2. 请求选项
        $opt = [
            'headers' => [],
            'timeout' => 2,
            'http_errors' => true
        ];
        // 2.1 raw body
        if (is_array($raw) && count($raw)) {
            $opt['json'] = $raw;
        }
        // 3. 请求过程
        try {
            $http = new HttpClient();
            $result = $http->request($method, $url, $opt);
            $this->printLine("完成指定: {blue=指令已发送完成}");
            $content = $result->getBody()->getContents();
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
            return true;
        } catch(\Throwable $e) {
            if ($e instanceof HttpConnectException) {
                $this->printLine("指令错误: {red=服务未启动或已退出}");
            } else if ($e instanceof HttpClientException) {
                $this->printLine("指令错误: {red=请求获得{%d}返回}", $e->getCode());
            } else {
                $this->printLine("指令错误: {red=%s}", get_class($e), $e->getMessage());
            }
        }
        return false;
    }

    /**
     * 从PID文件读取主进程ID
     * @return bool|int
     */
    protected function callMasterPid()
    {
        $pidFile = $this->boot->getArgs()->getTmpDir().'/server.pid';
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if (false !== $pid) {
                $pid = trim($pid);
                if (preg_match("/^\d+$/", $pid)) {
                    return (int) $pid;
                }
            }
        }
        return false;
    }

    /**
     * @param string $name
     * @return array
     */
    protected function callProcessByName(string $name)
    {
        // 1. prepare args
        $num = 0;
        $cmd = "ps x -o ppid,pid,args";
        foreach (explode(" ", $name) as $arg) {
            $arg = trim($arg);
            if ($arg !== '') {
                $num++;
                $cmd .= " | grep '{$arg}'";
            }
        }
        $cmd .= " | grep -v grep | grep -v '".$this->boot->getArgs()->getCommand()."'";
        $data = [];
        // 2. no args
        if ($num === 0) {
            return $data;
        }
        // 3. run shell
        $str = shell_exec($cmd);
        foreach (explode("\n", $str) as $line) {
            // 4. line
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // 5. column
            $cols = explode(" ", preg_replace("/\s+/", " ", $line));
            $lens = count($cols);
            if ($lens < 3) {
                continue;
            }
            // 6. parser
            $data[] = [
                'ppid' => $cols[0],
                'pid' => $cols[1],
                'args' => implode(" ", array_slice($cols, 2, $lens - 2))
            ];
        }
        return $data;
    }

    /**
     * 打印行
     * @param string $format
     * @param array  ...$args
     */
    protected function printLine(string $format, ... $args)
    {
        // 1. contents
        $args = is_array($args) ? $args : [];
        array_unshift($args, $format);
        $message = call_user_func_array('sprintf', $args);
        // 2. print
        $this->println($message);
    }

    /**
     * 颜色打印
     * @param string $message
     */
    protected function println(string $message)
    {
        $message = preg_replace_callback("/\{([a-zA-Z0-9]+)=([^\}]+)\}/", function($a){
            $a[1] = strtolower($a[1]);
            switch ($a[1]) {
                case 'red' :
                    return sprintf("\033[%d;%dm%s\033[0m", 31, 49, $a[2]);
                case 'gray' :
                    return sprintf("\033[%d;%dm%s\033[0m", 37, 49, $a[2]);
                case 'blue' :
                    return sprintf("\033[%d;%dm%s\033[0m", 34, 49, $a[2]);
                case 'yellow' :
                    return sprintf("\033[%d;%dm%s\033[0m", 33, 49, $a[2]);
                case 'green' :
                    return sprintf("\033[%d;%dm%s\033[0m", 32, 49, $a[2]);
                case 'red2' :
                    return sprintf("\033[%d;%dm %s \033[0m", 33, 41, $a[2]);
                case 'blue2' :
                    return sprintf("\033[%d;%dm %s \033[0m", 37, 44, $a[2]);
                case 'yellow2' :
                    return sprintf("\033[%d;%dm %s \033[0m", 30, 43, $a[2]);
                case 'green2' :
                    return sprintf("\033[%d;%dm %s \033[0m", 30, 42, $a[2]);
            }
            return $a[2];
        }, $message);
        echo "{$message}\n";
    }

    /**
     * 打印表格
     * @param array
     */
    protected function printTable(array $data)
    {
        $size = $this->getTableWidth($data);
        $beginComma = "| ";
        $middleComma = " | ";
        $endComma = " |\n";
        foreach ($data as $i => $item) {
            // 1. head
            if ($i === 0) {
                $str = "";
                $comma = $beginComma;
                foreach (array_keys($item) as $key) {
                    $str .= $comma.sprintf("%-".$size[$key]."s", $key);
                    $comma = $middleComma;
                }
                $str .= $endComma;
                echo $str;
            }
            // 2. body
            $str = "";
            $comma = $beginComma;
            foreach ($item as $key => $value) {
                $str .= $comma.sprintf("%-".$size[$key]."s", $value);
                $comma = $middleComma;
            }
            $str .= $endComma;
            echo $str;
        }
    }

    /**
     * 读取表格宽度
     * @param array
     * @return array
     */
    protected function getTableWidth(array $data)
    {
        $size = [];
        foreach ($data as $i => $item) {
            foreach ($item as $key => $value) {
                // 1. head
                if ($i === 0) {
                    $size[$key] = strlen($key);
                }
                // 2. body
                $size[$key] = max($size[$key], strlen($value));
            }
        }
        return $size;
    }
}
