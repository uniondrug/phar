# Change Logs

> PHAR Change lists

### 1.2

1. `1.2.10`
    1. 切换Log上报模式, 由tick定时器改为while循环加延时
    1. 在`log-stdout`选项开启时, 不加载内存表
1. `1.2.9`
    1. 切换Log上传模式, 由多进程改为Process独立专用进程
1. `1.2.8`
    1. 调整HTTP请求响应过程
    1. 调整Logger日志前缀兼容
    1. Phalcon Response中的Header/Cookie转给Swoole Response
1. `1.2.7`
    1. 向Kafka提交数据超时时长改为25秒
1. `1.2.6`
    1. Log默认级别设置成INFO级
1. `1.2.5`
    1. Kafka状态检测修正
1. `1.2.4`
1. `1.2.3`
1. `1.2.2`
    1. 去除LogTable加锁, 解决运行过程中拒绝服务情况.
1. `1.2.1`
    1. debug: sprintf
1. `1.2.0`
    1. Log加入部署IP - 调整0.0.0.0/127.0.0.1部署时, 上报Kafka的日志看不出来位置
    1. 发送日志到Kafka - 分步式/集群部署时, 上报Log到Kafka代替部署机器的文件Log
    1. PHP内存限制 - 自动按PHP设置的内存上限, 达临界值时退出Worker/Tasker进程, 重新启动
    1. 支持老版本的打包PHAR部署, 兼容tm-new-api、tm-appbackend、uniondrug等项目
    1. Server对象全局锁 - `->getServer()->getMutex()`
    1. Start/Stop命令选项支持
    1. RequestId固定32个字符长度
