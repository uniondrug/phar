# CRON

> 定时任务(Cron/Crontab), 
项目在启动时扫描`app/Servers/Crons`目录下的Cron文件, 
然后通过反射`Reflection`解析各计划任务的执行时段、时间点。


### 代码片段

```text
/**
 * 示例定时
 * @Timer(1m)
 */
class ExampleCron extends XCron 
{
    public function run()
    {
        // todo: logic codes
    }
}
```


1. 时间间隔, 连续2次执行的时间间隔
    1. `@Timer(5s)` - 每隔5秒执行1次
    1. `@Timer(3m)` - 每隔3分钟执行1次
    1. `@Timer(2h)` - 每隔2小时执行1次
1. 定时执行, 24小时制
    1. `@Timer(00:00)` - 凌晨00:00时执行
    1. `@Timer(00:30:15)` - 凌晨00:30:15执行