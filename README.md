# PHAR

> 本项目用于，将基于`uniondrug/sketch`创建的应用，构建成`PHAR`（`PHP Archive`）包，并以`swoole`模式启动。



### 安装依赖

```bash
composer require uniondrug/phar
```



### 构建PHAR

1. command
    1. `phar`
1. options
    1. `--name` package name
    1. `--tag` package tag
    1. `--compress` compress as GZ
    1. `--consul` Consul Server Address

```bash
php console phar \
    --name example \
    --tag 1.2.3 \
    --consul sdk.uniondrug.net
```



### 启动PHAR

> 启动时, 自动创建`log`、`tmp`目录，用于存储临时启动文件。


```bash
php example-1.2.3.phar start \
    --host=eth0 \
    --port=8080 \
    -e release \
    -d
```

