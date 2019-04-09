<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Logs\Abstracts;

use Uniondrug\Phar\Server\Bases\Config;
use Uniondrug\Phar\Server\Logs\Adapters\StdoutAdapter;
use Uniondrug\Phar\Server\Tasks\LogTask;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

abstract class Base
{
    const LEVEL_DEBUG = 5;
    const LEVEL_INFO = 4;
    const LEVEL_WARNING = 3;
    const LEVEL_ERROR = 2;
    const LEVEL_FATAL = 1;
    const LEVEL_OFF = 0;
    private static $adapters = [];
    /**
     * @var Config
     */
    private $_config;
    private $_level = 5;
    private $_levelDebugOn;
    private $_levelInfoOn;
    private $_levelWarningOn;
    private $_levelErrorOn;
    private $_isStdout;

    public function __construct(Config $config)
    {
        $this->_config = $config;
        $this->_level = $config->getLogLevel();
    }

    public function getArgs()
    {
        return $this->getConfig()->getArgs();
    }

    public function getConfig()
    {
        return $this->_config;
    }

    public function isStdout()
    {
        if ($this->_isStdout === null) {
            $this->_isStdout = $this->getArgs()->hasOption('log-stdout');
        }
        return $this->_isStdout;
    }

    /**
     * 生成Log级别
     * @param int $level
     * @return string
     */
    public function makeLevel(int $level)
    {
        $label = 'OTHER';
        switch ($level) {
            case self::LEVEL_DEBUG :
                $label = 'DEBUG';
                break;
            case self::LEVEL_INFO :
                $label = 'INFO';
                break;
            case self::LEVEL_WARNING :
                $label = 'WARN';
                break;
            case self::LEVEL_ERROR :
                $label = 'ERROR';
                break;
            case self::LEVEL_FATAL :
                $label = 'FATAL';
                break;
        }
        return $label;
    }

    /**
     * DEBUG状态
     * @return bool
     */
    public function debugOn()
    {
        if ($this->_levelDebugOn === null) {
            $this->_levelDebugOn = $this->_level >= self::LEVEL_DEBUG;
        }
        return $this->_levelDebugOn;
    }

    /**
     * ERROR状态
     * @return bool
     */
    public function errorOn()
    {
        if ($this->_levelErrorOn === null) {
            $this->_levelErrorOn = $this->_level >= self::LEVEL_ERROR;
        }
        return $this->_levelErrorOn;
    }

    /**
     * INFO状态
     * @return bool
     */
    public function infoOn()
    {
        if ($this->_levelInfoOn === null) {
            $this->_levelInfoOn = $this->_level >= self::LEVEL_INFO;
        }
        return $this->_levelInfoOn;
    }

    /**
     * WARN状态
     * @return bool
     */
    public function warningOn()
    {
        if ($this->_levelWarningOn === null) {
            $this->_levelWarningOn = $this->_level >= self::LEVEL_WARNING;
        }
        return $this->_levelWarningOn;
    }

    /**
     * 格式化Logger数据
     * @param int    $level
     * @param string $prefix
     * @param string $message
     * @return array
     */
    public function formatData(int $level, string $prefix, string $message)
    {
        return [
            'time' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            'deploy' => $this->getConfig()->deployIp.':'.$this->getConfig()->port,
            'app' => $this->getConfig()->appName,
            'level' => $level,
            'message' => $prefix.$message
        ];
    }

    /**
     * 发送到Adapter
     * <code>
     * $this->senderAdapter(
     *     StdoutAdapter::class, [
     *         [
     *             'time' => '2019-03-20 15:39:36.467898',
     *             'deploy' => '172.16.0.56:8080',
     *             'level' => 2,
     *             'message' => 'Logger明细内容'
     *         ]
     *     ]
     * )
     * </code>
     * @param string $class
     * @param array  $datas
     */
    public function senderAdapter(string $class, array $datas)
    {
        if (!isset(self::$adapters[$class])) {
            self::$adapters[$class] = new $class();
        }
        self::$adapters[$class]->setLogger($this)->run($datas);
    }

    /**
     * 提交日志
     * @param XHttp|XOld|XSocket $server
     * @param array              $datas
     */
    public function senderServer($server, array $datas)
    {
        try {
            $server->runTask(LogTask::class, $datas);
        } catch(\Throwable $e) {
        }
    }

    /**
     * 向控制台发送Log
     * @param array $datas
     */
    public function senderStdout(array $datas)
    {
        try {
            $this->senderAdapter(StdoutAdapter::class, $datas);
        } catch(\Throwable $e) {
        }
    }
}
