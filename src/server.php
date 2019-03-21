<?php
/**
 * Server入口
 */
use Uniondrug\Phar\Server\Bases\Args;
use Uniondrug\Phar\Server\Bases\Config;
use Uniondrug\Phar\Server\Bases\Runner;
use Uniondrug\Phar\Server\Logs\Logger;

date_default_timezone_set("Asia/Shanghai");
ini_set("display_errors", false);
/**
 * 入口路径
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
 * 全局实例
 */
$args = new Args();
$config = new Config($args);
$logger = new Logger($config);
$runner = new Runner($config, $logger);
$runner->run();
