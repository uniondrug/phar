<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Bases;

use Uniondrug\Phar\Agents\Abstracts\IAgent;
use Uniondrug\Phar\Exceptions\ArgsException;
use Uniondrug\Phar\Server\Logs\Logger;

/**
 * 运行管理
 * @package Uniondrug\Phar\Server\Bases
 */
class Runner
{
    /**
     * @var Config
     */
    private $_config;
    /**
     * @var Logger
     */
    private $_logger;
    /**
     * 默认Agent
     * @var string
     */
    private $_agentDefault = "Help";

    /**
     * Runner constructor.
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(Config $config, Logger $logger)
    {
        $this->_config = $config;
        $this->_logger = $logger;
        $this->registerHandler();
    }

    /**
     * @return Args
     */
    public function getArgs()
    {
        return $this->getConfig()->getArgs();
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * 错误处理
     * @param string $error
     * @param string $file
     * @param int    $line
     */
    public function handleError(string $error, string $file, int $line, $code = 0)
    {
        if (!$this->_logger->errorOn()) {
            return;
        }
        $type = 'Exception';
        if ($code > 0) {
            switch ($code) {
                case E_ERROR :
                case E_USER_ERROR :
                case E_CORE_ERROR :
                case E_COMPILE_ERROR :
                    $type = 'Error';
                    break;
                case E_WARNING :
                case E_USER_WARNING :
                case E_CORE_WARNING :
                case E_COMPILE_WARNING :
                    $type = 'Warning';
                    break;
                case E_NOTICE :
                case E_USER_NOTICE :
                    $type = 'Notice';
                    break;
                case E_DEPRECATED :
                case E_USER_DEPRECATED :
                    $type = 'Deprecated';
                    break;
                default :
                    $type = "Error-{$code}";
                    break;
            }
        }
        $this->_logger->log(Logger::LEVEL_ERROR, $error);
        $this->_logger->log(Logger::LEVEL_DEBUG, "{$type}: {$file}({$line})");
    }

    /**
     * 注册错误处理
     */
    public function registerHandler()
    {
        $runner = $this;
        ini_set("display_errors", false);
        putenv("APP_ENV={$runner->_config->getArgs()->getEnvironment()}");
        /**
         * Log级别
         */
        $errorLevel = (string) $this->_config->getArgs()->getOption('error');
        if ($errorLevel === '') {
            switch ($this->_config->getArgs()->getEnvironment()) {
                case 'production' :
                    error_reporting(E_ALL ^ E_NOTICE | E_WARNING);
                    break;
                default :
                    error_reporting(E_ALL);
                    break;
            }
        } else {
            switch (strtoupper($errorLevel)) {
                case "ERROR" :
                    error_reporting(E_ERROR);
                    break;
                case "WARNING" :
                    error_reporting(E_ERROR | E_WARNING);
                    break;
                case "NOTICE" :
                    error_reporting(E_ERROR | E_WARNING | E_NOTICE);
                    break;
                default :
                    error_reporting(E_ALL);
                    break;
            }
        }
        /**
         * 异常处理
         * 1. \Exception
         * 2. \Error
         */
        set_exception_handler(function(\Throwable $e) use ($runner){
            $runner->handleError($e->getMessage(), $e->getFile(), $e->getLine());
        });
        /**
         * 错误处理
         * 1. Error
         * 2. Warning
         * 3. Notice
         * 4. Deprecated
         */
        set_error_handler(function($code, $message, $file, $line) use ($runner){
            $runner->handleError($message, $file, $line, $code);
        });
        /**
         * 异常退出
         */
        register_shutdown_function(function() use ($runner){
            $error = error_get_last();
            if ($error !== null) {
                error_clear_last();
                $runner->handleError($error['message'], $error['file'], $error['line'], $error['type']);
            }
        });
    }

    /**
     * 运行过程
     */
    public function run()
    {
        /**
         * @var string $name
         */
        $name = $this->_config->getArgs()->getCommandClass();
        $name || $name = $this->_agentDefault;
        /**
         * @var string $class
         */
        $class = "\\Uniondrug\\Phar\\Agents\\{$name}Agent";
        if (!is_a($class, IAgent::class, true)) {
            throw new ArgsException("unknown {$this->_config->getArgs()->getCommand()} command");
        }
        /**
         * @var IAgent $agent
         */
        $agent = new $class($this);
        $this->_config->getArgs()->hasOption('help') ? $agent->runHelp() : $agent->run();
    }
}
