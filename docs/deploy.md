# 部署过程

> 以`PHAR`部署时, 项目目录结构如下

```
├── phars
│   ├── package-1.0.0.phar
│   └── package-1.1.0.phar
├── log
│   ├── month
│   │   └── date.log
│   └── server.log
├── tmp
│   └── server.pid
└── server -> phars/package-1.1.0.phar
```


### 下载PHAR

> 下载待发布的`PHAR`包文件到`phars`目录下, 一般有3种方式, 任选一

1. 在待部署机通过`wget/apt-get`待下载源码包
1. 在构建机通过`scp/rsync`等上传到目标机器
1. 使用`Jekins`等工具

```bash
# 1. 下载文件
# 2. 构建文件连接(软连接)到目标文件
wget -O phars/package-1.1.0.phar http://hub.uniondrug.net/module/package-1.1.0.phar
rm -rf server
ln -s phars/package-1.1.0.phar server
```


### 同步KV

> 使用phar包同步Consul/KV配置。
说明: 本操作非必须，若构建PHAR时，已经合入指定环境的配置则可跳过

```bash
php server kv --consul sdk.uniondrug.net
```

### 启动服务

```bash
php server stop
php server start -e release -d
```

### 反向代理

> 以PHAR启动时, 使用的是Swoole模式, 若开放域名访问，则使用NGINX反代模式

```text
upstream srv8888 {
    server 192.168.3.195:8101;
    keepalive 2000;
}
server {
    listen       80;
    server_name  host.module.uniondrug.net;
    client_max_body_size 1024M;
    access_log /data/logs/nginx/host.module.uniondrug.net.access.log main1;
    error_log /data/logs/nginx/host.module.uniondrug.net.error.log error;
    location / {
        proxy_pass http://srv8888;
        proxy_set_header Host $host:$server_port;
    }
}
```

