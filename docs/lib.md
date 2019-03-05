# 支持PHAR

> 当项目使用了`Lotus`框架时, 若需要支持`PHAR`打包部署, 需做如下修改, 已修改的文件在`lib.gz`文件中

1. `lib/SDK_PHP/AopSdk.php`
    1. Line:18
        ```php
        defined("PHAR_WORKING_FILE") ? 
        define("AOP_SDK_WORK_DIR", getcwd()."/tmp/") : 
        define("AOP_SDK_WORK_DIR", "/tmp/");
        ```
    1. Line:28
        ```php
        defined("PHAR_WORKING_FILE") ? 
        define("AOP_SDK_DEV_MODE", APP_DEBUG) : 
        define("AOP_SDK_DEV_MODE", true);
        ```
    1. Line:38
        ```php
        // $lotusHome = __DIR__. DIRECTORY_SEPARATOR . "lotusphp_runtime" . DIRECTORY_SEPARATOR;
        ```
    1. Line:41    
        ```php
        // $lotus->option["autoload_dir"] = __DIR__ . DIRECTORY_SEPARATOR . 'aop';
        ```
1. `lib/SDK_PHP/lotusphp_runtime/Lotus.php`
    1. Line:25
        ```php
        // $this->lotusRuntimeDir = __DIR__ . DIRECTORY_SEPARATOR;
        ```
    1. Line:55-62
        ```php
        if (defined("PHAR_WORKING_FILE")){
            LtStoreFile::$defaultStoreDir = $this->defaultStoreDir;
        } else {
           // 原始代码
           // ....
        }
        ```
1. `lib/SDK_PHP/lotusphp_runtime/Autoloader/Autoloader.php`
    1. Line:94-106
        ```php
        if (defined("PHAR_WORKING_FILE")){
            return preg_replace("/[\/]+$/", '', $path);
        }
        // 原始代码
        // ...
        ```




