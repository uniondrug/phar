# 支持PHAR

> 当使用使用了Lib框架时, 需做如下修改, 已修改的文件在`lib.zip`文件中

1. `lib/SDK_PHP/AopSdk.php`
    1. Line:18
        ```php
        define("AOP_SDK_WORK_DIR", getcwd()."/tmp");
        ```
    1. Line:28
        ```php
            define("AOP_SDK_DEV_MODE", false);
        ```
    1. Line:38
        ```php
        $lotusHome = __DIR__. DIRECTORY_SEPARATOR . "lotusphp_runtime" . DIRECTORY_SEPARATOR;
        ```
    1. Line:41    
        ```php
        $lotus->option["autoload_dir"] = __DIR__ . DIRECTORY_SEPARATOR . 'aop';
        ```
1. `lib/SDK_PHP/lotusphp_runtime/Lotus.php`
    1. Line:25
        ```php
        $this->lotusRuntimeDir = __DIR__ . DIRECTORY_SEPARATOR;
        ```
    1. Line:55-62
        ```php
        LtStoreFile::$defaultStoreDir = $this->defaultStoreDir;
        ```
1. `lib/SDK_PHP/lotusphp_runtime/Autoloader/Autoloader.php`
    1. Line:94-106
        ```php
        return preg_replace("/[\/]+$/", '', $path);
        ```




