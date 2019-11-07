<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Logs;

use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

/**
 * 日志管理器
 * @package Uniondrug\Phar\Server\Logs
 */
class Logger extends Abstracts\Base
{
    /**
     * 日志前缀
     * @var string
     */
    private $_prefix = '';
    /**
     * 缓存区列表
     * @var array
     */
    private $_profileLists = [];
    /**
     * 缓存区状态
     * 当startProfile()被调用时设为true, endProfile()被
     * 调用时重置为false
     * @var bool
     */
    private $_profileStatus = false;
    /**
     * 忽略profile日志集
     * @var bool
     */
    private $_profileIgnored = false;
    /**
     * Server对象
     * @var XHttp|XSocket|XOld
     */
    private $_server;

    /**
     * 结束日志打包
     * @return $this
     */
    public function endProfile()
    {
        // 1. print in stdout
        if ($this->isStdout()) {
            return $this;
        }
        // 2. execution
        if ($this->_profileIgnored) {
            $this->_profileIgnored = false;
        } else {
            if ($this->_server !== null) {
                $this->senderServer($this->_server, $this->_profileLists);
            } else {
                $this->senderStdout($this->_profileLists);
            }
        }
        // 3. initialize
        $this->_profileStatus = false;
        $this->_profileLists = [];
        return $this;
    }

    /**
     * 忽略日志打包
     * 当指定`--log-stdout`选项时, 本项设置无效
     * @param bool $ignored
     * @return $this
     */
    public function ignoreProfile(bool $ignored = true)
    {
        if (!$this->isStdout()) {
            $this->_profileIgnored = $ignored;
        }
        return $this;
    }

    /**
     * 开始日志打包
     * @return $this
     */
    public function startProfile()
    {
        if (!$this->isStdout()) {
            $this->_profileStatus = true;
        }
        return $this;
    }

    /**
     * 读取日志前缀
     * @param bool $pid
     * @return string
     */
    public function getPrefix(bool $pid = false)
    {
        if ($this->_server !== null && $pid) {
            return "[x={$this->_server->getPid()}]{$this->_prefix}";
        }
        return $this->_prefix;
    }

    /**
     * 设置日志前缀
     * @param string $format
     * @param array  ...$args
     * @return $this
     */
    public function setPrefix(string $format = null, ... $args)
    {
        $this->_prefix = $format === null ? "" : sprintf($format, ... $args);
        return $this;
    }

    /**
     * 绑定Server对象
     * @param XHttp|XOld|XSocket $server
     * @return $this
     */
    public function setServer($server)
    {
        $this->_server = $server;
        return $this;
    }

    public function getServer()
    {
        return $this->_server;
    }

    /**
     * 加入DebugLog
     * @param string $format
     * @param array  ...$args
     */
    public function debug(string $format, ... $args)
    {
        $this->debugOn() && $this->log(parent::LEVEL_DEBUG, $format, ... $args);
    }

    /**
     * 加入ErrorLog
     * @param string $format
     * @param array  ...$args
     */
    public function error(string $format, ... $args)
    {
        $this->errorOn() && $this->log(parent::LEVEL_ERROR, $format, ... $args);
    }

    /**
     * 加入FatalLog
     * @param string $format
     * @param array  ...$args
     */
    public function fatal(string $format, ... $args)
    {
        $this->errorOn() && $this->log(parent::LEVEL_FATAL, $format, ... $args);
    }

    /**
     * 加入InfoLog
     * @param string $format
     * @param array  ...$args
     */
    public function info(string $format, ... $args)
    {
        $this->infoOn() && $this->log(parent::LEVEL_INFO, $format, ... $args);
    }

    /**
     * 加入WarningLog
     * @param string $format
     * @param array  ...$args
     */
    public function warning(string $format, ... $args)
    {
        $this->warningOn() && $this->log(parent::LEVEL_WARNING, $format, ... $args);
    }

    /**
     * Log内容解析
     * @param int    $level
     * @param string $format
     * @param array  ...$args
     * @return bool
     */
    public function log(int $level, string $format, ... $args)
    {
        // 1. logger message
        $args = is_array($args) ? $args : [];
        if (count($args) > 0) {
            $message = @sprintf($format, ... $args);
            if ($message === false || $message === null) {
                error_clear_last();
                $message = $format.":".implode('|', $args);
            }
        } else {
            $message = $format;
        }
        // 2. logger payload
        if ($this->_server !== null) {
            $data = $this->formatData($level, $this->getPrefix(true), "[v={$this->getServer()->getTrace()->getLoggerVersion()}]".$message);
            $this->getServer()->getTrace()->plusPoint();
        } else {
            $data = $this->formatData($level, $this->getPrefix(true), $message);
        }
        // 3. logger with stdout
        if ($this->isStdout()) {
            $this->senderStdout([$data]);
            return true;
        }
        // 4. logger to profile
        if ($this->_profileStatus) {
            $this->_profileLists[] = $data;
            return true;
        }
        // 5. sender with sync
        if ($this->_server !== null) {
            $this->senderServer($this->_server, [$data]);
            return true;
        }
        // 6. stdout
        $this->senderStdout([$data]);
        return true;
    }
}
