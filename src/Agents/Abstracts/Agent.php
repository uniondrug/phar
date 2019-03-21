<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents\Abstracts;

use Uniondrug\Phar\Server\Bases\Runner;
use Uniondrug\Phar\Server\XVersion;

/**
 * Agent基类
 * @package Uniondrug\Phar\Agents\Abstracts
 */
abstract class Agent implements IAgent
{
    /**
     * Agent名称
     * @var string
     */
    protected static $title = '';
    /**
     * Agent描述
     * @var string
     */
    protected static $description = '';
    private $_runner;

    /**
     * Agent constructor.
     * @param Runner $runner
     */
    public function __construct(Runner $runner)
    {
        $this->_runner = $runner;
        /**
         * 版本信息
         */
        $phpver = PHP_VERSION;
        $swover = SWOOLE_VERSION;
        $phaver = \Phalcon\Version::get();
        $fraver = \Uniondrug\Framework\Container::VERSION;
        $this->printLine("当前项目: {green=%s/%s} in {green=%s}", $this->_runner->getConfig()->appName, $this->_runner->getConfig()->appVersion, $this->_runner->getConfig()->getArgs()->getEnvironment());
        $this->printLine("项目目录: {green=%s}", $this->_runner->getConfig()->getArgs()->basePath());
        $this->printLine("运行环境: {green=xphar/%s}, {green=php/%s}, {green=swoole/%s}, {green=phalcon/%s}, {green=framework/%s}", XVersion::get(), $phpver, $swover, $phaver, $fraver);
        $this->printLine("服务地址: {green=%s}:{green=%d} 部署于 {green=%s}:{green=%d}", $this->_runner->getConfig()->host, $this->_runner->getConfig()->port, $this->_runner->getConfig()->deployIp, $this->_runner->getConfig()->port);
    }

    public static function getDescription()
    {
        return static::$description;
    }

    /**
     * @return Runner
     */
    public function getRunner()
    {
        return $this->_runner;
    }

    public static function getTitle()
    {
        return static::$title;
    }

    /**
     * 运行帮助
     */
    public function runHelp()
    {
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
        file_put_contents('php://stdout', "{$message}\n");
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
                file_put_contents('php://stdout', $str);
            }
            // 2. body
            $str = "";
            $comma = $beginComma;
            foreach ($item as $key => $value) {
                $str .= $comma.sprintf("%-".$size[$key]."s", $value);
                $comma = $middleComma;
            }
            $str .= $endComma;
            file_put_contents('php://stdout', $str);
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
