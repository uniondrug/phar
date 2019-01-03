<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
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
// 2.0 shutdown
register_shutdown_function(function() use ($logger){
    $e = error_get_last();
    if ($e === null) {
        return;
    }
    $e['message'] .= " at {$e['file']} on line {$e['line']}";
    switch ($e['type']) {
        case E_ERROR :
        case E_USER_ERROR :
        case E_CORE_ERROR :
        case E_COMPILE_ERROR :
            $logger->error($e['message']);
            throw new \Uniondrug\Phar\Server\Exceptions\ErrorExeption($e['message'], $e['type']);
            break;
        case E_DEPRECATED :
            $logger->warning($e['message']);
            throw new \Uniondrug\Phar\Server\Exceptions\ErrorExeption($e['message'], $e['type']);
            break;
        case E_WARNING :
        case E_USER_WARNING :
        case E_CORE_WARNING :
            $logger->warning($e['message']);
            break;
        case E_NOTICE :
        case E_USER_NOTICE :
            $logger->notice($e['message']);
            break;
    }
});
// 2.1 exception
set_exception_handler(function(\Throwable $e) use ($logger){
    $logger->error("%s: %s", get_class($e), $e->getMessage());
});
// 2.2 error
set_error_handler(function($errno, $error, $file, $line) use ($logger){
    $error .= " at {$file} on line {$line}";
    switch ($errno) {
        case E_ERROR :
        case E_USER_ERROR :
        case E_CORE_ERROR :
        case E_COMPILE_ERROR :
            $logger->fatal($error);
            throw new \Uniondrug\Phar\Server\Exceptions\ErrorExeption($error, $errno);
            break;
        case E_DEPRECATED :
            $logger->warning($error);
            throw new \Uniondrug\Phar\Server\Exceptions\ErrorExeption($error, $errno);
            break;
        case E_WARNING :
        case E_USER_WARNING :
        case E_CORE_WARNING :
            $logger->warning($error);
            break;
        case E_NOTICE :
        case E_USER_NOTICE :
            $logger->notice($error);
            break;
    }
});
// 2.3 bootstrap
$config = new \Uniondrug\Phar\Server\Config($args);
$booter = new \Uniondrug\Phar\Server\Bootstrap($args, $config, $logger);
$booter->run();
