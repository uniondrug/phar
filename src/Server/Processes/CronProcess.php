<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Processes;

use Uniondrug\Phar\Server\Tasks\ICron;

/**
 * CronProcess/定时任务
 * @package Uniondrug\Phar\Server\Processes
 */
class CronProcess extends XProcess
{
    /**
     * 任务结构
     * <code>
     * $cronNames = [
     *     0 => [
     *         'name' => 'ExampleCron',     // 0号定时任务名称(类名)
     *         'time' => 1357924680         // 0号任务最后执行时间
     *     ]
     * ]
     * </code>
     * @var array
     */
    private $cronNames = [];
    /**
     * N秒执行1次
     * <code>
     * $cronSeconds = [
     *     0 => 60                          // 0号定时任务每60秒执行1次
     * ]
     * </code>
     * @var array
     */
    private $cronSeconds = [];
    /**
     * 指定时间执行
     * <code>
     * $cronDatetime = [
     *     '00:00:00' => [                  // 在00:00:00时执行0、2号2个任务
     *         0,
     *         2
     *     ]
     * ]
     * </code>
     * @var array
     */
    private $cronDatetime = [];

    /**
     * 前置操作
     */
    public function beforeRun()
    {
        $this->scanner();
    }

    /**
     * 定时遍历
     * @return bool
     */
    public function run()
    {
        // 1. 是否有定义
        if (count($this->cronSeconds) === 0 && count($this->cronDatetime) === 0) {
            $this->getServer()->getLogger()->debug("未找到需要定时执行的任务");
            return false;
        }
        // 2. 定时执行
        $this->runLoop();
        $this->getServer()->tick(1000, [
            $this,
            'runLoop'
        ]);
        return false;
    }

    /**
     * 遍历任务
     */
    public function runLoop()
    {
        try {
            // 1. 时间计算
            $timestamp = time();
            $datetime = date('H:i:s', $timestamp);
            // 2. 指定时间
            $crontabs = isset($this->cronDatetime[$datetime]) ? $this->cronDatetime[$datetime] : [];
            // 3. 间隔执行
            foreach ($this->cronSeconds as $index => $limit) {
                $names = $this->cronNames[$index];
                if (($timestamp - $names['time']) >= $limit) {
                    $this->cronNames[$index]['time'] = $timestamp;
                    if (!in_array($index, $crontabs)) {
                        $crontabs[] = $index;
                    }
                }
            }
            // 4. 执行Tasker
            if (count($crontabs) > 0) {
                foreach ($crontabs as $index) {
                    if (!isset($this->cronNames[$index], $this->cronNames[$index]['class'])) {
                        continue;
                    }
                    $class = $this->cronNames[$index]['class'];
                    $this->getServer()->runTask($class);
                }
            }
        } catch(\Throwable $e) {
            $this->getServer()->getLogger()->fatal("触发定时器失败 - %s", $e->getMessage());
        }
    }

    /**
     * 扫描CronTab
     * 从项目目录中扫描Crontab文件, 并加入定时任务
     * @return int
     */
    private function scanner()
    {
        $cronNum = 0;
        try {
            $this->getServer()->getLogger()->info("扫描定时任务/Crontab");
            // 1. 按配置或默认计算定时任务
            //    1): 扫描目录
            //    2): 命名空间
            $cronPath = $this->getServer()->getContainer()->getConfig()->path("server.cronPath", null);
            $cronNamespace = $this->getServer()->getContainer()->getConfig()->path("server.cronNamespace", null);
            if (!$cronPath || !$cronNamespace) {
                $cronPath = $this->getServer()->getContainer()->appPath()."/Servers/Crons";
                $cronNamespace = '\\App\\Servers\\Crons';
            }
            if (!is_dir($cronPath)) {
                $this->getServer()->getLogger()->warning("定时任务路径{%s}不合法", $cronPath);
                return $cronNum;
            }
            // 2. 扫描目录
            $i = 0;
            $d = dir($cronPath);
            while (false !== ($cronFile = $d->read())) {
                if (preg_match("/^(\S+)\.php$/", $cronFile, $m) > 0) {
                    $cronNum += $this->scannerReflect($i, $cronNamespace, $m[1]) ? 1 : 0;
                    $i++;
                }
            }
            $d->close();
        } catch(\Throwable $e) {
            $this->getServer()->getLogger()->fatal("扫描定时器失败 - %s", $e->getMessage());
        }
        return $cronNum;
    }

    /**
     * @param int    $index
     * @param string $name
     * @param string $timer
     */
    private function scannerParser(int $index, string $name, string $timer)
    {
        $rexpSeconds = "/^(\d+)([a-z]+)$/";
        $rexpDatetime = "/^(\d+):(\d+)$/";
        $rexpDatetimes = "/^(\d+):(\d+):(\d+)$/";
        // 1. 指定时、分
        if (preg_match($rexpDatetime, $timer, $m) > 0) {
            $timer .= ':00';
        }
        // 2. 指定时、分、秒
        if (preg_match($rexpDatetimes, $timer, $m) > 0) {
            $key = sprintf("%02d:%02d:%02d", (int) $m[1], (int) $m[2], (int) $m[3]);
            if (!isset($this->cronDatetime[$key])) {
                $this->cronDatetime[$key] = [];
            }
            if (!in_array($key, $this->cronDatetime[$key])) {
                $this->cronDatetime[$key][] = [$index];
            }
            $this->getServer()->getLogger()->debug("定时任务{%s}每天{%s}执行1次", $name, $key);
            return;
        }
        // 3. 时间间隔
        if (preg_match($rexpSeconds, $timer, $m) > 0) {
            $seconds = (int) $m[1];
            $unit = strtolower($m[2]);
            switch ($unit) {
                case 's' :
                    $seconds *= 1;
                    break;
                case 'm' :
                    $seconds *= 60;
                    break;
                case 'h' :
                    $seconds *= 3600;
                    break;
                case 'd' :
                    $seconds *= 86400;
                    break;
                default :
                    $seconds = 0;
                    break;
            }
            if ($seconds > 0) {
                $this->cronSeconds[$index] = $seconds;
                $this->getServer()->getLogger()->debug("定时任务{%s}每隔{%s}秒执行1次", $name, $seconds);
            } else {
                $this->getServer()->getLogger()->warning("定时任务{%s}的执行频率{%s}设置无效", $name, $timer);
            }
            return;
        }
    }

    /**
     * @param int    $index
     * @param string $name
     * @param string $schedule
     */
    private function scannerParserSchedule(int $index, string $name, string $schedule)
    {
        // todo: @Schedule注解暂未实现
    }

    /**
     * 反射Crontab
     * @param int    $index
     * @param string $cronNamespace
     * @param string $name
     * @return bool
     */
    private function scannerReflect(int $index, string $cronNamespace, string $name)
    {
        $this->getServer()->getLogger()->debug("发现{%s}定时任务", $name);
        // 1. 必须实现ICron接口
        $class = $cronNamespace.'\\'.$name;
        if (!is_a($class, ICron::class, true)) {
            $this->getServer()->getLogger()->warning("定时任务{%s}未实现{%s}接口", $name, ICron::class);
            return false;
        }
        // 2. 反射定时任务
        //    从注释中读取定时规则
        $times = null;
        $schedule = null;
        $reflect = new \ReflectionClass($class);
        $document = $reflect->getDocComment();
        if ($document) {
            if (preg_match_all("/@Timer\(([^\)]+)\)/i", $document, $m) > 0) {
                $times = $m[1];
            }
            // @Scheduled(cron="*/1 * * * * *")
            if (preg_match("/@Scheduled\(cron=\"([^\")]+)\"[^\)]*\)/i", $document, $m) > 0) {
                $schedule = $m[1];
            }
        }
        if ($times === null && $schedule === null) {
            $this->getServer()->getLogger()->warning("定时任务{%s}未定义执行周期", $name);
            return false;
        }
        // 3. 设置定时任务编号
        $this->cronNames[$index] = [
            'class' => $class,
            'time' => 0
        ];
        // 4. 遍历定时器
        if (is_array($times)) {
            foreach ($times as $timer) {
                $this->scannerParser($index, $name, $timer);
            }
        }
        if ($schedule !== null) {
            $this->scannerParserSchedule($index, $name, $schedule);
        }
        return true;
    }
}
