<?php
/**
 * Server入口
 */
use Uniondrug\Phar\Server\Bases\Args;
use Uniondrug\Phar\Server\Bases\Config;
use Uniondrug\Phar\Server\Bases\Runner;
use Uniondrug\Phar\Server\Logs\Logger;

/**
 * 1. 全局控制
 *    a): 时区
 *    b): 错误输出
 */
date_default_timezone_set("Asia/Shanghai");
ini_set("display_errors", false);
/**
 * 2. 计算路径
 *    并加载Vendor依赖
 */
$vendorBoot = null;
if (defined("PHAR_WORKING_FILE")) {
    $vendorBoot = __DIR__."/../../../../";
} else {
    $vendorBoot = getcwd();
}
define("PHAR_ROOT", $vendorBoot);
$composerVendor = PHAR_ROOT.'/vendor/autoload.php';
if (file_exists($composerVendor)) {
    include($composerVendor);
} else {
    echo "composer required, please run `composer install`";
    exit(1);
}
/**
 * 3. 运行实例
 *    按执行脚本与命令运行程序主
 *    进程
 */
$args = new Args();
$config = new Config($args);
$logger = new Logger($config);
$runner = new Runner($config, $logger);
$runner->run();
