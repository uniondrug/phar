<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server;

use Uniondrug\Phar\Server\Tasks\LogTask;

/**
 * Server日志
 * @package Uniondrug\Phar\Bootstrap
 */
class Logger
{
    const LEVEL_DEBUG = 5;
    const LEVEL_INFO = 4;
    const LEVEL_WARNING = 3;
    const LEVEL_ERROR = 2;
    const LEVEL_FATAL = 1;
    const LEVEL_OFF = 0;
    /**
     * 当前级别
     * @var int
     */
    private $level = self::LEVEL_INFO;
    /**
     * @var Args
     */
    private $args;
    private $_debugEnabled = null;
    /**
     * @var XHttp
     */
    private $server;
    /**
     * 日志前缀
     * @var null
     */
    private $logPrefix = '';
    private $logStdout = false;
    /**
     * 日志级别与
     * @var array
     */
    private static $levels = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_FATAL => 'FATAL'
    ];

    /**
     * @param Args $args
     */
    public function __construct(Args $args)
    {
        $this->args = $args;
        $this->logStdout = $args->hasOption('log-stdout');
    }

    /**
     * 是否启用了Debug
     * @return bool|null
     */
    public function enableDebug()
    {
        if ($this->_debugEnabled === null) {
            $this->_debugEnabled = $this->level >= self::LEVEL_DEBUG;
        }
        return $this->_debugEnabled;
    }

    /**
     * 读取Log前缀
     * @return null|string
     */
    public function getPrefix()
    {
        return $this->logPrefix;
    }

    /**
     * 设置Log前缀
     * @param string $message
     * @param array  ...$args
     * @return $this
     */
    public function setPrefix(string $message, ... $args)
    {
        // 1. 入参
        $args = is_array($args) ? $args : [];
        array_unshift($args, $message);
        // 2. 格式化文本
        $this->logPrefix = @call_user_func_array('sprintf', $args);
        if ($this->logPrefix === false) {
            error_clear_last();
            $this->logPrefix = implode("|", $args);
        }
        // 3. next
        return $this;
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
     * 设置日导级别
     * @param int $level
     * @return $this
     */
    public function setLogLevel(int $level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * 设置日志格式
     * 兼容Phalcon/未来移除
     * @param object $formatter
     * @return $this
     * @deprecated 4.0
     */
    public function setFormatter($formatter)
    {
        return $this;
    }

    /**
     * 设置Server
     * 当前Server启时, 一经设置将使用异步模式提交Log
     * @param $server
     * @return $this
     */
    public function setServer($server)
    {
        $this->server = $server;
        return $this;
    }

    /**
     * 开始事务日志
     * 兼容Phalcon/未来移除
     * @deprecated 4.0
     */
    public function begin()
    {
        return $this;
    }

    /**
     * 提交事务日志
     * 兼容Phalcon/未来移除
     * @deprecated 4.0
     */
    public function commit()
    {
        return $this;
    }

    /**
     * 回滚日志
     * 兼容Phalcon/未来移除
     * @deprecated 4.0
     */
    public function rollback()
    {
        return $this;
    }

    /**
     * 兼容Phalcon/未来移除
     * @return bool
     * @deprecated 4.0
     */
    public function isTransaction()
    {
        return false;
    }

    /**
     * 调试日志
     * 本类似日志, 一般便于开发调试, 生产环境不记录，通常
     * 使用本项记录脚本执行到了哪个位置，有时也用于记录关键
     * 性的出入参
     * @param string $message
     * @param array  ...$args
     * @return bool
     */
    public function debug(string $message, ... $args)
    {
        if ($this->enableDebug()) {
            $this->log(self::LEVEL_DEBUG, $message, ... $args);
            return true;
        }
        return false;
    }

    /**
     * 业务日志
     * @param string $message
     * @param array  ...$args
     * @return bool
     * @example ->info("[mobile=%s]绑定手机号成功", "13966013721")
     */
    public function info(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_INFO) {
            $this->log(self::LEVEL_INFO, $message, ... $args);
            return true;
        }
        return false;
    }

    /**
     * 警告消息
     * 执行周期内有警告消息, 不影响本项执行, 但在未知的
     * 时间有出现Error/Fatal等潜在的错误的可能
     * @param string $message
     * @param array  ...$args
     * @return bool
     */
    public function warning(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_WARNING) {
            $this->log(self::LEVEL_WARNING, $message, ... $args);
            return true;
        }
        return false;
    }

    /**
     * @param string $message
     * @param array  ...$args
     * @return bool
     */
    public function notice(string $message, ... $args)
    {
        return $this->warning($message, ... $args);
    }

    /**
     * 运行错误
     * 运行周期内生产错误(或业务错误), 不影响正常运行
     * @param string $message
     * @param array  ...$args
     * @return bool
     */
    public function error(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_ERROR) {
            $this->log(self::LEVEL_ERROR, $message, ... $args);
            return true;
        }
        return false;
    }

    /**
     * 致命错误
     * 导致服务/请求中止, 例如在运行周期内产生了未捕获的异常
     * 并抛给了最上层的业务, 一般不在业务代码中调用
     * @param string $message
     * @param array  ...$args
     * @return bool
     */
    public function fatal(string $message, ... $args)
    {
        if ($this->level >= self::LEVEL_FATAL) {
            $this->log(self::LEVEL_FATAL, $message, ... $args);
            return true;
        }
        return false;
    }

    /**
     * @param string $message
     * @param array  ...$args
     * @return bool
     * @deprecated 4.0
     */
    public function alert(string $message, ... $args)
    {
        return $this->fatal($message, ... $args);
    }

    /**
     * @param string $message
     * @param array  ...$args
     * @return bool
     * @deprecated 4.0
     */
    public function critical(string $message, ... $args)
    {
        return $this->fatal($message, ... $args);
    }

    /**
     * @param string $message
     * @param array  ...$args
     * @return bool
     * @deprecated 4.0
     */
    public function emergency(string $message, ... $args)
    {
        return $this->fatal($message, ... $args);
    }

    /**
     * 本地存储
     * 将日志内容写入到本地存储(磁盘上)
     * @param string $label
     * @param string $message
     * @return bool
     */
    public function localStorage(string $label, string $message)
    {
        try {
            // 1. 计算算路
            $path = $this->args->getLogDir().'/'.date('Y-m');
            $file = $path.'/'.date('Y-m-d').'.log';
            $mode = file_exists($file) ? 'a+' : 'wb+';
            $text = "[".(new \DateTime())->format('Y-m-d H:i:s.u')."][{$label}]{$message}\n";
            // 2. 写入Log
            if (false !== ($fp = @fopen($file, $mode))) {
                @fwrite($fp, $text);
                @fclose($fp);
                return true;
            }
        } catch(\Throwable $e) {
        }
        return false;
    }

    /**
     * 写入Logger
     * 一、 Log格式定义
     *      col.1 - 第1组|时间 - 如[2019-01-04 09:10:12]
     *      col.2 - 第2组|状态 - 即log级别支持[INFO|ERROR|WARNING|FATAL|DEBUG]
     *                          DEBUG: 用于Debug调试的信息, 如: 入参、入参等, 生产环境不记录此类信息。
     *                          INFO: 简略的业务INFO信息, 如: 绑定微信。 详细信息请配合DEBUG段
     *                          WARNING: 简略的节点WARNING信息, 如: 精度丢失败。可配合Debug段
     *                          ERROR: 简略的节点ERROR信息, 如: 请求SDK超时。{发weixin/dingding提醒}, 可配合Debug段
     *                          FATAL: 简略的节点FATAL信息, 如: Worker进程异常退出。{发weixin/dingding提醒}, 可配合Debug段
     *      col.3 - 第3组|机器 - 机器IP与端口, 如[192.168.10.110:8080]
     *      col.4 - 第4组|模块 - 模块名, 如 [user.module]
     *      col.x - 第x组|键值 - 第5-n组为业务键值对/关键元素/字段, 如下
     *                          a): 预定义/Key为单字符
     *                              [a=C|R|U|D] 动作/增、删、改、查   (*)
     *                              [d=0.001358]                    总计用时/duration(秒)
     *                              [m=GET|POST|DELETE...]          请求方式/RESTFUL
     *                              [r=requestid]                   请求ID/request-id
     *                              [u=/index]                      请求地址/URL
     *                              [x=2710]                        进程ID信息
     *                              [y=ExampleTask]                 任务名
     *                              [z=2710]                        任务ID
     *                          b): 自定义/Key为数据表的字段名,长度大于1个字符
     *                              [memberId=1001]                 (*)会员ID为1001
     *                              [mobile=13912345678]            (*)手机号为13912345678
     *      ended - 文本描述, 在文本中可通过'{}'方式标记关键词, 如: 发起{HTTP}请求,申请了{2.2}M内存
     * 二、 Log转发
     *      Log最终转发给Kafka, PHP将以异步方式解析成JSON格式, 发送到Kafka日志中心, 由日志中心存储
     *      到RDB/MySQL
     * 三、 示例结构
     *      [2019-01-04 11:17:31][INFO][192.168.10.122:8080][user.module][r=req5c2ed04b657b9][u=/exit][d=0.001358][a=INSERT][mobile=13912345678] 添加账号
     * @param int    $level
     * @param string $message
     * @param array  ...$args
     * @return bool
     */
    private function log(int $level, string $message, ... $args)
    {
        /**
         * 1. 日志准备
         *    a) 入参转成数组
         *    b) 通过sprintf格式化内容
         *    c) 前置添加日志前缀
         */
        $args = is_array($args) ? $args : [];
        array_unshift($args, $message);
        $label = isset(self::$levels[$level]) ? self::$levels[$level] : 'CUSTOM';
        $message = @call_user_func_array('sprintf', $args);
        if ($message === false) {
            error_clear_last();
            $message = implode("|", $args);
        }
        $message = $this->logPrefix.$message;
        /**
         * 2. 日志处理
         *    a) 在Console打印
         *    b) 加入内存表异步提交
         *    c) 本地落盘
         */
        if ($this->logStdout) {
            // a) 在stdout/标准输出上打印
            //    在启动时指定--log-stdout时生效
            $this->logOutput($label, $message);
            return true;
        }
        if ($this->server !== null) {
            try {
                // b) 加入内存表
                //    当内存表数量满时
                $tbl = $this->server->getLogTable();
                $full = $tbl->add($label, $message);
                if ($full) {
                    $data = $tbl->flush();
                    $this->server->runTask(LogTask::class, $data);
                }
                return true;
            } catch(\Throwable $e) {
            }
        }
        // c) 本地落盘
        return $this->localStorage($label, $message);
    }

    /**
     * 日志输出
     * @param string $level
     * @param string $message
     */
    private function logOutput($level, & $message)
    {
        $text = "[".(new \DateTime())->format('Y-m-d H:i:s.u')."][{$level}]{$message}";
        switch ($level) {
            case self::$levels[self::LEVEL_FATAL] :
                $text = sprintf("\033[%d;%dm%s\033[0m", 33, 41, $text);
                break;
            case self::$levels[self::LEVEL_ERROR] :
                $text = sprintf("\033[%d;%dm%s\033[0m", 31, 49, $text);
                break;
            case self::$levels[self::LEVEL_WARNING] :
                $text = sprintf("\033[%d;%dm%s\033[0m", 33, 49, $text);
                break;
            case self::$levels[self::LEVEL_INFO] :
                $text = sprintf("\033[%d;%dm%s\033[0m", 34, 49, $text);
                break;
            case self::$levels[self::LEVEL_DEBUG] :
                $text = sprintf("\033[%d;%dm%s\033[0m", 37, 49, $text);
                break;
        }
        file_put_contents('php://stdout', "{$text}\n");
    }
}
