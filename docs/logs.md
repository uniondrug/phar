# Change Logs

> PHAR Change lists

### 1.2

1. `1.2.0`
    1. Log加入部署IP - 调整0.0.0.0/127.0.0.1部署时, 上报Kafka的日志看不出来位置
    1. 发送日志到Kafka - 分步式/集群部署时, 上报Log到Kafka代替部署机器的文件Log
    1. PHP内存限制 - 自动按PHP设置的内存上限, 达临界值时退出Worker/Tasker进程, 重新启动
    1. 支持老版本的打包PHAR部署, 兼容tm-new-api、tm-appbackend、uniondrug等项目
    1. Server对象全局锁 - `->getServer()->getMutex()`


### 1.1

1. `1.1.1`
1. `1.1.0`


### 1.0

1. `1.0.6`
1. `1.0.5`
1. `1.0.4`
1. `1.0.3`
1. `1.0.2`
1. `1.0.1`
1. `1.0.0`

