<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
date_default_timezone_set("Asia/ShangHai");
// 1. load autoload
$vendorFile = null;
if (defined("PHAR_WORKING_DIR")) {
    // 1.1. in phar
    $vendorFile = __DIR__."/../../../autoload.php";
} else {
    // 1.2. in phar
    $vendorFile = getcwd().'/vendor/autoload.php';
}
if (!$vendorFile || !file_exists($vendorFile)) {
    echo "composer install|update not executed.";
    exit(1);
}
include($vendorFile);
// 2. server manager
$args = new \Uniondrug\Phar\Server\Args();
$logger = new \Uniondrug\Phar\Server\Logger($args);
// 2.1 shutdown
register_shutdown_function(function() use ($logger){
    $e = error_get_last();
    if ($e === null) {
        return;
    }
    $e['message'] = "在{{$e['file']}}的第{{$e['line']}}行触发 - {{$e['message']}}";
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
// 2.2 error
set_error_handler(function($errno, $error, $file, $line) use ($logger){
    $error = "在{{$file}}的第{{$line}}行触发 - {$error}";
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
// 2.3 exception
set_exception_handler(function(\Throwable $e) use ($logger){
    $logger->fatal("在{%s}的第{%d}行出现{%s}异常 - %s", $e->getFile(), $e->getLine(), get_class($e), $e->getMessage());
});
// 2.3 bootstrap
$config = new \Uniondrug\Phar\Server\Config($args);
if ($config->environment === "production") {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
} else {
    error_reporting(E_ALL);
}
$booter = new \Uniondrug\Phar\Server\Bootstrap($args, $config, $logger);
$booter->run();
