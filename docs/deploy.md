# 部署规范

> 当以`PHAR`在`development`、`testing`、`release`、`production`环境下部署时, 需遵循以下规范。

1. [目录结构](#目录结构)
1. [项目配置](#项目配置)
1. [启动项目](#启动项目)
1. [退出项目](#退出项目)
1. [重启项目](#重启项目)



### 目录结构

```text
/data
├── apps
│   └── backend.wx
│       ├── log
│       │   ├── 2019-01
│       │   │   └── 2019-01-24.log                              # 业务日志(下阶段迁入Kafka)
│       │   └── server.log
│       ├── server -> /data/phar/wx.backend-190124.phar         # PHAR软连接
│       └── tmp
│           ├── config.json
│           ├── config.php
│           ├── server.cfg
│           └── server.pid
├── conf
│   └── nginx
│       └── conf.d
│           └── backend.wx.conf                                 # 项目的Nginx反代配置
├── logs
│   └── nginx
└── phar
    └── wx.backend-190124.phar                                  # 项目PHAR包文件
```


### 项目配置

> 项目运行期的环境配置参数(如: mysql、redis连接信息)等，存储在Consul服务的KV中；
项目启动前需拉取KV配置信息, PHAR包将拉取到的配置信息和项目默认配置进行合并，写将合并
后的配置信息写入到`tmp/config.php`文件中；项目启动时以此配置为最终配置信息。命令如下

```bash
php server kv --consul udsdk.uniondrug.cn
```


### 启动项目

1. 快速启动
    ```bash
    php server start -e production -d
    ```
1. 启动并注册Consul服务, 以最终IP为准

    ```bash
    php server start -e production -d --consul-register 127.0.0.1:8500
    ```

### 退出项目

```bash
php server stop                         # 安全退出
php server stop -l --kill               # 发送SIGTERM信号退出
php server stop -l --force-kill         # 发送SIGKILL信息(强杀进程)退出
```

### 重启项目

```bash
php server stop -l --force-kill && \
php server start -e production -d
```
