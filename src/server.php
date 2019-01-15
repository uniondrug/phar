<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
date_default_timezone_set("Asia/ShangHai");
/**
 * Composer
 * 在phar和fpm模式下, vendor/autoload路径计算方式有差异
 * 按场景计算相对/绝对路径
 */
$vendorFile = null;
if (defined("PHAR_WORKING_DIR")) {
    $vendorFile = __DIR__."/../../../autoload.php";
} else {
    $vendorFile = getcwd().'/vendor/autoload.php';
}
if (!$vendorFile || !file_exists($vendorFile)) {
    echo "composer install|update not executed.";
    exit(1);
}
include($vendorFile);
/**
 * 初始化前设置处理实例
 * 1). 命令行Arguments
 * 2). 业务Logger存储
 */
$args = new \Uniondrug\Phar\Server\Args();
$logger = new \Uniondrug\Phar\Server\Logger($args);
/**
 * Fatal/Shutdown Handler
 * 在异步模式下, 当前脚本中止或Fatal错误时
 * 由本回调收集, 并做统一的日志处理
 */
register_shutdown_function(function() use ($logger){
    // 1. 读取最近Fatal错误
    $e = error_get_last();
    if ($e === null) {
        return;
    }
    // 2. 按Level进程业务转发
    $e['message'] = $e['message']." at {$e['file']}({$e['line']})";
    switch ($e['type']) {
        case E_ERROR :
        case E_USER_ERROR :
        case E_CORE_ERROR :
        case E_COMPILE_ERROR :
            $logger->fatal($e['message']);
            break;
        case E_WARNING :
        case E_USER_WARNING :
        case E_CORE_WARNING :
        case E_NOTICE :
        case E_USER_NOTICE :
        case E_DEPRECATED :
            $logger->warning($e['message']);
            break;
    }
});
/**
 * RuntimeError Handler
 * 在运行过程中, 产生的错误回调
 */
set_error_handler(function($errno, $error, $file, $line) use ($logger){
    $error = $error." at {$file}({$line})";
    switch ($errno) {
        case E_ERROR :
        case E_USER_ERROR :
        case E_CORE_ERROR :
        case E_COMPILE_ERROR :
            $logger->fatal($error);
            break;
        case E_DEPRECATED :
        case E_WARNING :
        case E_USER_WARNING :
        case E_CORE_WARNING :
        case E_NOTICE :
        case E_USER_NOTICE :
            $logger->warning($error);
            break;
    }
});
/**
 * Uncatch Exception/Handler
 * 未捕获的异常错误处理
 */
set_exception_handler(function(\Throwable $e) use ($logger){
    $logger->fatal("%s at %s(%d)", $e->getMessage(), $e->getFile(), $e->getLine());
});
/**
 * 兼容Console
 */
$config = new \Uniondrug\Phar\Server\Config($args);
/**
 * 入口转发
 */
if ($args->getCommand() === 'console') {
    /**
     * 1. 保持Console继续可用
     */
    $config->mergeArgs();
    // todo: 兼容console暂未实现
} else {
    // 2. BootStrap
    if ($config->environment === "production") {
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
    } else {
        error_reporting(E_ALL);
    }
    $booter = new \Uniondrug\Phar\Server\Bootstrap($args, $config, $logger);
    $booter->run();
}
