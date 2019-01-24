# PHAR

> 基于`uniondrug/sketch`模板构建的项目, 可平滑切换到`PHAR`+`Swoole`运行模式。

1. [安装依赖](#安装依赖) - 在项目添加`uniondrug/phar`依赖
1. [添加入口](#添加入口) - 让项目支持`Command`入口
1. [项目打包](#项目打包) - 将项目打成`PHAR`包
1. [启动项目](#启动项目) - 以`PHAR`方式启动项目
1. [退出服务](#退出服务)
1. [项目部署](./docs/deploy.md) - 在`development`、`testing`、`release`、`production`环境下的部署规范
1. [注意事项](#注册事项) - 构建PHAR包时注意事项
    1. 项目名称与版本
    1. 启动IP与端口
    1. 服务参数



### 安装依赖

> 请在项目根目录下的`composer.json`文件加添加`uniondrug/phar`扩展；效果如下

```text
{
    ...
    "require" : {
        ...
        "uniondrug/console" : "^2.2",
        "uniondrug/phar" : "^1.0"
    },
    ...
}
```



### 添加入口

> 创建`app/Commands/PharCommand.php`文件, 完整代码如下

```php
<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-24
 */
namespace App\Commands;

/**
 * 构建PHAR入口
 * @package App\Commands
 */
class PharCommand extends \Uniondrug\Phar\Commands\PharCommand
{
}

```



### 项目打包

> 将项目构建成`PHAR`包

1. 语法
    ```bash
    php console phar -h
    ```
1. 示例
    ```bash
    php console phar
    php console phar -e production
    php console phar -e production --tag version
    php console phar -e production --tag version --name package
    ```


### 启动项目

1. 语法
    ```bash
    php package-version.phar start -h
    ```
1. 示例
    ```bash
    php package-version.phar start -h 
    php package-version.phar start -e production
    php package-version.phar start -e production -d
    php package-version.phar start -e production -d --consul-register 127.0.0.1:8500
    php package-version.phar start -e production --log-stdout
    ```



### 退出服务

1. 语法
    ```bash
    php package-version.phar stop -h
    ```
1. 示例
    ```bash
    php package-version.phar stop -l 
    php package-version.phar stop -l --kill 
    php package-version.phar stop -l --force-kill
    ```
