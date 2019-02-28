<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
date_default_timezone_set("Asia/ShangHai");
/**
 * 计算路径
 * 在phar和fpm模式下, vendor/autoload路径计算方式有差异
 * 按场景计算相对/绝对路径
 */
$vendorBoot = null;
if (defined("PHAR_WORKING_DIR")) {
    $vendorBoot = __DIR__."/../../../../";
} else {
    $vendorBoot = getcwd();
}
$vendorFile = $vendorBoot.'/vendor/autoload.php';
if ($vendorBoot === null || !file_exists($vendorFile)) {
    echo "composer install|update not executed.";
    exit(1);
}
include($vendorFile);
defined("PHAR_WORKING_DIR") || define("PHAR_WORKING_DIR", getcwd());
defined("PHAR_WORKING_FILE") || define("PHAR_WORKING_FILE", getcwd());
/**
 * 初始化前设置实例
 * 1). 命令行Arguments
 * 2). 业务Logger存储
 */
$args = new \Uniondrug\Phar\Server\Args();
$logger = new \Uniondrug\Phar\Server\Logger($args);
/**
 * 错误报警级别
 */
$errorReporting = (string) $args->getOption('error');
$errorReporting = $errorReporting === '' ? '' : strtoupper($errorReporting);
$errorReportingCode = E_ALL;
switch ($errorReporting) {
    case 'ERROR' :
        $errorReportingCode = E_ERROR;
        break;
    case 'PARSE' :
        $errorReportingCode = E_ERROR | E_PARSE;
        break;
    case 'WARNING' :
        $errorReportingCode = E_ERROR | E_PARSE | E_WARNING;
        break;
    case 'OFF' :
        $errorReportingCode = 0;
        break;
    default :
        break;
}
error_reporting($errorReportingCode);
/**
 * Fatal/Shutdown Handler
 * 在异步模式下, 当前脚本中止或Fatal错误时
 * 由本回调收集, 并做统一的日志处理
 */
register_shutdown_function(function() use ($logger, $errorReportingCode){
    // 1. 读取最近Fatal错误
    $e = error_get_last();
    if ($e === null) {
        return;
    }
    // 2. 忽略错误
    error_clear_last();
    if ($e['type'] > $errorReportingCode){
        return;
    }
    // 3.
    switch ($e['type']) {
        case E_ERROR :
        case E_USER_ERROR :
        case E_CORE_ERROR :
        case E_COMPILE_ERROR :
            $logger->fatal("[errno=%d]%s", $e['type'], $e['message']);
            $logger->enableDebug() && $logger->debug("错误位于{%s}的第{%d}行", $e['file'], $e['line']);
            break;
        case E_WARNING :
        case E_USER_WARNING :
        case E_CORE_WARNING :
        case E_NOTICE :
        case E_USER_NOTICE :
        case E_DEPRECATED :
            $logger->warning("[errno=%d]%s", $e['type'], $e['message']);
            $logger->enableDebug() && $logger->debug("报警位于{%s}的第{%d}行", $e['file'], $e['line']);
            break;
    }
});
/**
 * RuntimeError Handler
 * 在运行过程中, 产生的错误回调
 */
set_error_handler(function($errno, $error, $file, $line) use ($logger){
    switch ($errno) {
        case E_ERROR :
        case E_USER_ERROR :
        case E_CORE_ERROR :
        case E_COMPILE_ERROR :
            $logger->fatal("[errno=%d]%s", $errno, $error);
            $logger->enableDebug() && $logger->debug("错误位于{%s}的第{%d}行", $file, $line);
            break;
        case E_DEPRECATED :
        case E_WARNING :
        case E_USER_WARNING :
        case E_CORE_WARNING :
        case E_NOTICE :
        case E_USER_NOTICE :
            $logger->warning("[errno=%d]%s", $errno, $error);
            $logger->enableDebug() && $logger->debug("报警位于{%s}的第{%d}行", $file, $line);
            break;
    }
});
/**
 * Uncatch Exception/Handler
 * 未捕获的异常错误处理
 */
set_exception_handler(function(\Throwable $e) use ($logger){
    $logger->fatal("[exception=%s]%s", get_class($e), $e->getMessage());
    $logger->enableDebug() && $logger->debug("异常位于{%s}的第{%d}行", $e->getFile(), $e->getLine());
});
/**
 * 导入配置文件
 */
$config = new \Uniondrug\Phar\Server\Config($args);
/**
 * 入口转发
 * 兼容console, 由原命令`php console`变更为`php server console`
 */
if ($args->getCommand() === 'console') {
    /**
     * 1. 保持Console继续可用
     */
    $config->mergeArgs();
    // 2. reset SERVER
    $serv = $_SERVER['argv'];
    array_shift($serv);
    $_SERVER['argv'] = $serv;
    $_SERVER['argc'] = count($serv);
    // 3. run command
    $container = new \Uniondrug\Framework\Container($vendorBoot);
    $application = (new \Uniondrug\Framework\Application($container))->boot();
    $console = new \Uniondrug\Console\Console($container);
    $console->run();
} else {
    // 2. BootStrap
    $booter = new \Uniondrug\Phar\Server\Bootstrap($args, $config, $logger);
    $booter->run();
}
