<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server;

/**
 * Server日志
 * @package Uniondrug\Phar\Bootstrap
 */
class Logger
{
    const LEVEL_SPECIAL = 9;
    const LEVEL_CUSTOM = 8;
    const LEVEL_DEBUG = 7;
    const LEVEL_INFO = 6;
    const LEVEL_NOTICE = 5;
    const LEVEL_WARNING = 4;
    const LEVEL_ERROR = 3;
    const LEVEL_ALERT = 2;
    const LEVEL_CRITICAL = 1;
    const LEVEL_EMERGENCY = 0;
    /**
     * 当前级别
     * @var int
     */
    private $level = self::LEVEL_SPECIAL;
    /**
     * 日志数据
     * 从请求开始前清空
     * @var array
     */
    private $logData = [];
    /**
     * 日志前缀
     * @var null
     */
    private $logPrefix = null;
    private $requestId = null;
    /**
     * 日志级别与
     * @var array
     */
    private static $levels = [
        self::LEVEL_SPECIAL => 'SPECIAL',
        self::LEVEL_CUSTOM => 'CUSTOM',
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_NOTICE => 'NOTICE',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_ALERT => 'ALERT',
        self::LEVEL_CRITICAL => 'CRITICAL',
        self::LEVEL_EMERGENCY => 'EMERGENCY',
    ];

    /**
     * 追加Log前缀
     * @param string $message
     * @param array  ...$args
     * @return $this
     */
    public function appendPrefix(string $message, ... $args)
    {
        $args = is_array($args) ? $args : [];
        array_unshift($args, $message);
        $prefix = call_user_func_array('sprintf', $args);
        if ($this->logPrefix === null) {
            $this->logPrefix = $prefix;
        } else {
            $this->logPrefix = $prefix;
        }
        return $this;
    }

    /**
     * 设置Log前缀
     * @param string $message
     * @param array  ...$args
     * @return $this
     */
    public function setPrefix(string $message, ... $args)
    {
        $args = is_array($args) ? $args : [];
        array_unshift($args, $message);
        $this->logPrefix = call_user_func_array('sprintf', $args);
        return $this;
    }

    /**
     * 设置请求ID
     * @param string $requestId
     * @return $this
     */
    public function setRequestId(string $requestId)
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * 设置日导级别
     * @return $this
     */
    public function setLogLevel(int $level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * 开始日志收集
     */
    public function beginLogData()
    {
        $this->logData = [];
    }

    /**
     * 日志数据
     * @return array|null
     */
    public function endLogData()
    {
        return $this->logData;
    }

    /**
     * 获取日志级别
     * @return int
     */
    public function getLogLevel()
    {
        return $this->level;
    }

    /**
     * 设置日志格式
     * @param object $formatter
     * @return $this
     */
    public function setFormatter($formatter)
    {
        return $this;
    }

    /**
     * 开始事务日志
     */
    public function begin()
    {
        return $this;
    }

    /**
     * 提交事务日志
     */
    public function commit()
    {
        return $this;
    }

    /**
     * 回滚日志
     */
    public function rollback()
    {
        return $this;
    }

    public function isTransaction()
    {
        return false;
    }

    /**
     * 发送关键日志
     * @param string $message
     * @param array  ...$args
     * @return Logger
     */
    public function critical(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_CRITICAL) {
            $this->log(self::LEVEL_CRITICAL, $message, ... $args);
        }
        return $this;
    }

    /**
     * 发送警急日志
     * @param string $message
     * @param array  ...$args
     * @return Logger
     */
    public function emergency(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_EMERGENCY) {
            $this->log(self::LEVEL_EMERGENCY, $message, ... $args);
        }
        return $this;
    }

    /**
     * 发送调试日志
     * @param string $message
     * @param array  ...$args
     * @return Logger
     */
    public function debug(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_DEBUG) {
            $this->log(self::LEVEL_DEBUG, $message, ... $args);
        }
        return $this;
    }

    /**
     * 发送错误日志
     * @param string $message
     * @param array  ...$args
     * @return Logger
     */
    public function error(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_ERROR) {
            $this->log(self::LEVEL_ERROR, $message, ... $args);
        }
        return $this;
    }

    /**
     * 发送业务日志
     * @param string $message
     * @param array  ...$args
     * @return Logger
     */
    public function info(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_INFO) {
            $this->log(self::LEVEL_INFO, $message, ... $args);
        }
        return $this;
    }

    /**
     * 发送通知日志
     * @param string $message
     * @param array  ...$args
     * @return Logger
     */
    public function notice(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_NOTICE) {
            $this->log(self::LEVEL_NOTICE, $message, ... $args);
        }
        return $this;
    }

    /**
     * 发送警告日志
     * @param string $message
     * @param array  ...$args
     * @return Logger
     */
    public function warning(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_WARNING) {
            $this->log(self::LEVEL_WARNING, $message, ... $args);
        }
        return $this;
    }

    /**
     * 发送警报日志
     * @param string $message
     * @param array  ...$args
     * @return Logger
     */
    public function alert(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_ALERT) {
            $this->log(self::LEVEL_ALERT, $message, ... $args);
        }
        return $this;
    }

    /**
     * 写入Logger
     * @param int    $level
     * @param string $message
     * @param array  ...$args
     * @return $this
     */
    public function log(int $level, string $message, ... $args)
    {
        // 1. 日志基础内容
        $args = is_array($args) ? $args : [];
        array_unshift($args, $message);
        $message = call_user_func_array('sprintf', $args);
        // 2. 日志前缀
        $this->logPrefix === null || $message = $this->logPrefix.$message;
        // 3. 请求ID
        $this->requestId === null || $message = "[reqid={$this->requestId}]".$message;
        // 3. 日志Level
        if (isset(self::$levels[$level])) {
            $message = "[".self::$levels[$level]."] ".$message;
        }
        // 4. 时间
        $message = "[".(new \DateTime())->format('d/M/y/H:i:s.u')."]".$message;
        // 5. 加入内存
        $this->logData[] = $message;
        // 6. 向stdout写入内容
        // todo: 在swoole启下, 如下代码需注释
        //echo "logs: ".count($this->logData)."\n";
        // file_put_contents('php://stdout', "{$message}\n");
        return $this;
    }
}
