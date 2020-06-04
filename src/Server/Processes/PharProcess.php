<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Processes;

use GuzzleHttp\Client;
use Phalcon\Config;
use Swoole\Process;
use Uniondrug\Phar\Server\Tasks\ICron;
use Uniondrug\Phar\Server\Tasks\ITask;

/**
 * PidProcess
 * @package Uniondrug\Phar\Server\Processes
 */
class PharProcess extends XProcess
{
    /**
     * 秒级任务
     * <code>
     * [
     *     [
     *         "class" => "ExampleClass",
     *         "seconds" => 10,
     *         "lastRun" => 1234567890,
     *         "allowDisable" => false
     *     ]
     * ]
     * </code>
     * @var array
     */
    private $_crontabSeconds = [];
    /**
     * 时级任务
     * <code>
     * [
     *     "00:00" => [
     *         "ExampleClass" => true
     *     ],
     *     "00:00:30" => [
     *         "ExampleClass"
     *     ]
     * ]
     * </code>
     * @var array
     */
    private $_crontabHours = [];
    private $_crontabDisabled = false;
    private $_secondTimer = 1000;
    /**
     * 进程检查
     * 每隔5秒, 检查一次进程与父状态, 任意一方退出
     * 时强制退出
     * @var int
     */
    private $_secondPidManager = 5000;
    /**
     * 空闲检查频率
     * 单位: 豪秒
     * @var int
     */
    private $_idleFrequency = 1000;
    /**
     * 连续忙超过指定次数时报警
     * @var int
     */
    private $_idleBusyTimes = 3;
    /**
     * 第N次忙
     * @var int
     */
    private $_idleBusyOffset = 0;
    private $_idelDingtalk = 'https://oapi.dingtalk.com/robot/send?access_token=03828d42e0a5cf15c30973079aa9288f588c9435a0ca327569fa135eda3057ab';
    private $_idelHttpClient;
    private $_idelHttpClientSending = false;
    private $_idelAlertCount = 0;
    private $_idelAlertDate = '';
    private $_idleStartWorker;
    private $_idleStartTasker;

    /**
     * @return bool
     */
    public function beforeRun()
    {
        try {
            $this->_crontabDisabled = $this->getServer()->getArgs()->hasOption('disable-cron');
            $cfg = $this->getServer()->getContainer()->getConfig()->path('server.idle');
            if ($cfg instanceof Config) {
                if (isset($cfg->frequency) && is_numeric($cfg->frequency) && $cfg->frequency > 0) {
                    $this->_idleFrequency = (int) $cfg->frequency;
                }
                if (isset($cfg->busyTimes) && is_numeric($cfg->busyTimes) && $cfg->busyTimes > 0) {
                    $this->_idleBusyTimes = (int) $cfg->busyTimes;
                }
                if (isset($cfg->dingtalk) && is_string($cfg->dingtalk) && $cfg->dingtalk !== '') {
                    $this->_idelDingtalk = $cfg->dingtalk;
                }
                $this->_idleStartWorker = (int) $this->getServer()->getConfig()->getSwooleSettings()['worker_num'];
                $this->_idleStartTasker = (int) $this->getServer()->getConfig()->getSwooleSettings()['task_worker_num'];
            }
            $this->scanCrontabs();
        } catch(\Throwable $e) {
            $this->getServer()->getLogger()->error("扫描定时器出错 - %s", $e->getMessage());
        }
        return parent::beforeRun();
    }

    /**
     * 设置定时器
     */
    public function run()
    {
        $this->getServer()->tick($this->_secondTimer, [
            $this,
            'handleTimer'
        ]);
        $this->getServer()->tick($this->_secondPidManager, [
            $this,
            'handlePid'
        ]);
        if (version_compare(SWOOLE_VERSION, "4.5.0", ">=")) {
            if ($this->_idelDingtalk !== 'off') {
                $this->getServer()->tick($this->_idleFrequency, [
                    $this,
                    'checkIdleWorkers'
                ]);
            }
        }
    }

    /**
     * Worker进程健康检查
     */
    public function checkIdleWorkers()
    {
        if ($this->_idelHttpClientSending) {
            return;
        }
        // 1. 读取状态
        $idle = true;
        $stats = $this->getServer()->stats();
        // 2. 状态检查
        //    1). worker idle
        //    2). tasker idle
        if ($stats['idle_worker_num'] < 1) {
            $idle = false;
        } else if ($stats['task_idle_worker_num'] < 1) {
            $idle = false;
        }
        // 3. 服务空闲
        if ($idle) {
            if ($this->_idleBusyOffset > 0) {
                $this->_idleBusyOffset = 0;
            }
            return;
        }
        // 4. 报警频次控制
        $this->_idleBusyOffset++;
        if ($this->_idleBusyOffset < $this->_idleBusyTimes) {
            return;
        }
        // 5. 发送报警
        try {
            // 5.1 status
            $this->_idelHttpClientSending = true;
            // 5.2 count
            $alertDate = date('Y-m-d');
            if ($alertDate !== $this->_idelAlertDate) {
                $this->_idelAlertDate = $alertDate;
                $this->_idelAlertCount = 0;
            }
            $this->_idelAlertCount++;
            // 5.3 http client
            if ($this->_idelHttpClient === null) {
                $this->_idelHttpClient = new Client();
            }
            // 5.4 text format
            $text = "# [IDLE][{$this->_idelAlertCount}] {$this->getServer()->getContainer()->getConfig()->path('app.appName')}";
            $text .= "\n\n";
            $text .= "> ";
            $text .= "同步负载 **".((int) (100 * ($this->_idleStartWorker - $stats['idle_worker_num']) / $this->_idleStartWorker))."**%, 空闲 **{$stats['idle_worker_num']}**/{$this->_idleStartWorker}; ";
            $text .= "异步负载 **".((int) (100 * ($this->_idleStartTasker - $stats['task_idle_worker_num']) / $this->_idleStartTasker))."**%, 空闲 **{$stats['task_idle_worker_num']}**/{$this->_idleStartTasker}, 待执行 **{$stats['tasking_num']}** 个任务; ";
            $text .= "当前服务 **{$stats['connection_num']}** 个连接, **{$stats['coroutine_num']}** 个协程。";
            $text .= "\n\n";
            $text .= "- 地址: ".$this->getServer()->getArgs()->getDeployIp()."\n";
            $text .= "- 端口: ".$this->getServer()->getConfig()->port."\n";
            $text .= "- 服务: ".$this->getServer()->getConfig()->appName."\n";
            $text .= "- 时间: ".date('Y-m-d H:i:s')."\n";
            $text .= "- 环境: ".$this->getServer()->getArgs()->getEnvironment();
            // 5.5 send text
            $this->_idelHttpClient->post($this->_idelDingtalk, [
                'header' => [
                    'content-type' => 'application/json'
                ],
                'json' => [
                    'msgtype' => 'markdown',
                    'markdown' => [
                        'title' => 'Service Busy',
                        'text' => $text
                    ]
                ],
                'timeout' => 2,
                'http_errors' => true
            ]);
        } catch(\Throwable $e) {
            // save to file.
            $file = $this->getServer()->getArgs()->logPath().'/idle-'.date('Y-m-d').'.log';
            $mode = file_exists($file) ? 'a+' : 'wb+';
            if (false !== ($fp = fopen($file, $mode))) {
                $text = sprintf("[%s][%d][worker=%d%%][idle-worker=%d/%d][tasker=%d%%][idle-tasker=%d/%d][wait-tasker=%d][connection=%d][coroutine=%d] %s\n", date("Y-m-d H:i:s"), $this->_idelAlertCount, ((int) (100 * ($this->_idleStartWorker - $stats['idle_worker_num']) / $this->_idleStartWorker)), $stats['idle_worker_num'], $this->_idleStartWorker, ((int) (100 * ($this->_idleStartTasker - $stats['task_idle_worker_num']) / $this->_idleStartTasker)), $stats['task_idle_worker_num'], $this->_idleStartTasker, $stats['tasking_num'], $stats['connection_num'], $stats['coroutine_num'], $e->getMessage());
                fwrite($fp, $text);
                fclose($fp);
            }
        } finally {
            $this->_idleBusyOffset = 0;
            $this->_idelHttpClientSending = false;
        }
    }

    /**
     * 定时检查PID进程状态
     */
    public function handlePid()
    {
        $procs = $this->getServer()->getPidTable()->toArray();
        foreach ($procs as $proc) {
            // 1. ignore master process
            if ($this->getServer()->getPidTable()->isMaster($proc)) {
                continue;
            }
            // 2. pid
            $killer = true;
            try {
                if ($this->pid === $proc['pid'] || Process::kill($proc['pid'], 0) === true) {
                    $killer = false;
                }
            } catch(\Throwable $e) {
            }
            // 3. parent
            if ($killer === false && isset($proc['ppid']) && $proc['ppid'] > 0) {
                try {
                    if (Process::kill($proc['ppid'], 0) !== true) {
                        $killer = true;
                    }
                } catch(\Throwable $e) {
                    $this->getServer()->getLogger()->warning("检查{%d}号{%s}进程的父进程失败 - %s", $proc['pid'], $proc['name'], $e->getMessage());
                }
            }
            // 4. killer
            if ($killer) {
                try {
                    $this->getServer()->getPidTable()->del($proc['pid']);
                    $this->getServer()->getLogger()->info("退出{%d}号{%s}进程", $proc['pid'], $proc['name']);
                    Process::kill($proc['pid'], SIGTERM);
                } catch(\Throwable $e) {
                    $this->getServer()->getLogger()->warning("退出{%d}号{%s}进程失败 - %s", $proc['pid'], $proc['name'], $e->getMessage());
                }
            }
        }
    }

    /**
     * 定时器
     */
    public function handleTimer()
    {
        /**
         * 1. 任务列表
         * @var ITask[]
         */
        $tasks = [];
        $timestamp = time();
        try {
            // 2. 提取时级
            $hour = date('H:i:s', $timestamp);
            if (isset($this->_crontabHours[$hour])) {
                foreach ($this->_crontabHours[$hour] as & $crontab) {
                    $tasks[] = $crontab['class'];
                    $crontab['lastRun'] = $timestamp;
                }
            }
            // 3. 提取秒级
            foreach ($this->_crontabSeconds as & $crontab) {
                if (($timestamp - $crontab['lastRun']) >= $crontab['seconds']) {
                    $crontab['lastRun'] = $timestamp;
                    if (!in_array($crontab['class'], $tasks)) {
                        $tasks[] = $crontab['class'];
                    }
                }
            }
        } catch(\Throwable $e) {
            $this->getServer()->getLogger()->error("检查定时期是否需要执行出错 - %s", $e->getMessage());
        }
        // 4. Run Task
        foreach ($tasks as $task) {
            $this->getServer()->runTask($task);
        }
    }

    /**
     * 扫描定时器目录
     */
    private function scanCrontabs()
    {
        // 1. 应用目录
        $folders = $this->getServer()->getConfig()->getSwooleCrontabs();
        $folders["vendor/uniondrug/phar/src/Server/Crons"] = "\\Uniondrug\\Phar\\Server\\Crons\\";
        // 2. 扫描目录
        foreach ($folders as $folder => $ns) {
            $path = $this->getServer()->getArgs()->basePath()."/".$folder;
            if (!is_dir($path)) {
                continue;
            }
            $scan = dir($path);
            while (false !== ($entry = $scan->read())) {
                // 3. not php
                if (preg_match("/^(\S+)\.php$/i", $entry, $m) === 0) {
                    continue;
                }
                $class = $ns.$m[1];
                // 4. ICron
                if ($class == "\\Uniondrug\\Phar\\Server\\Crons\\ICron" || $class == "\\Uniondrug\\Phar\\Server\\Crons\\XCron") {
                    continue;
                }
                // 5. not implements
                if (!is_a($class, ICron::class, true)) {
                    $this->getServer()->getLogger()->warning("定时任务{%s}未实现{%s}接口", $class, ICron::class);
                    continue;
                }
                // 6. parser
                $this->validCrontab($class);
            }
            $scan->close();
        }
    }

    /**
     * 通过反射解析
     * @param string $class
     */
    private function validCrontab(string $class)
    {
        $reflect = new \ReflectionClass($class);
        $comment = $reflect->getDocComment();
        // 1. not phpdoc comment
        if (!is_string($comment)) {
            $this->getServer()->getLogger()->warning("定时任务{%s}未定义PHPDoc", $class);
            return;
        }
        // 2. timer frequences
        if (preg_match_all("/@Timer\(([^\)]+)\)/i", $comment, $m) === 0) {
            $this->getServer()->getLogger()->warning("定时任务{%s}未通过@Timer()设置执行周期", $class);
            return;
        }
        // 3. allow disable
        $allowDisable = preg_match("/@NotAllowDisable\s*/i", $comment) === 0;
        if ($allowDisable && $this->_crontabDisabled) {
            $this->getServer()->getLogger()->warning("定时任务{%s}被--disable-cron选项忽略", $class);
            return;
        }
        // 4. parser frequences
        $withSeconds = 0;
        $withHours = [];
        foreach ($m[1] as $str) {
            $fre = $this->validFrequence($str);
            // 5. 秒级定义
            if (is_numeric($fre)) {
                $withSeconds = max($withSeconds, $fre);
                continue;
            }
            // 6. 时级定义
            if (is_array($fre)) {
                $withHours = $fre;
                continue;
            }
            // 7. 无效定时
            $this->getServer()->getLogger()->warning("定时任务{%s}未通过@Timer(%s)不合法", $class, $str);
            continue;
        }
        // 8. 加入秒记录
        $this->getServer()->getLogger()->info("定时期{%s}加入监控", $class, $str);
        if ($withSeconds > 0) {
            $this->_crontabSeconds[] = [
                'class' => $class,
                'seconds' => $withSeconds,
                'lastRun' => 0,
                'allowDisable' => $allowDisable
            ];
            $this->getServer()->getLogger()->debug("定时任务{%s}每隔{%d}秒执行一次", $class, $withSeconds);
        }
        // 9. 加入时级记录
        if (count($withHours) > 0) {
            $this->getServer()->getLogger()->debug("定时任务{%s}分别在{%s}各执行一次", $class, implode('/', $withHours));
            foreach ($withHours as $hour) {
                if (!isset($this->_crontabHours[$hour])) {
                    $this->_crontabHours[$hour] = [];
                }
                $this->_crontabHours[$hour][] = [
                    'class' => $class,
                    'lastRun' => 0,
                    'allowDisable' => $allowDisable
                ];
            }
        }
    }

    /**
     * 定时期类型
     * @param string $timer
     * @return int|array|false
     */
    private function validFrequence(string $timer)
    {
        // 1. 秒级
        $rexpSecs = "/^(\d+)([a-z])$/i";
        if (preg_match($rexpSecs, $timer, $m) > 0) {
            $seconds = (int) $m[1];
            switch (strtolower($m[2][0])) {
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
            }
            return $seconds;
        }
        // 2. 指定时间
        $rexpHour = "/^([^:]+):([^:]+)[:]?(\d*)$/";
        if (preg_match($rexpHour, $timer, $m) === 0) {
            $this->getServer()->getLogger()->warning("match hour failure.");
            return false;
        }
        $rexpHours = [
            [],
            [],
            []
        ];
        for ($i = 1; $i <= 3; $i++) {
            $m[$i] = isset($m[$i]) ? trim($m[$i]) : '';
            if ($m[$i] === '') {
                $m[$i] = '0';
            }
            if (preg_match_all("/(\d+)/", $m[$i], $n) > 0) {
                foreach ($n[1] as $nx) {
                    $rexpHours[$i - 1][] = (int) $nx;
                }
            }
        }
        $results = [];
        foreach ($rexpHours[0] as $h) {
            foreach ($rexpHours[1] as $m) {
                foreach ($rexpHours[2] as $s) {
                    if ($h <= 23 && $m <= 59 && $s <= 59) {
                        $results[] = sprintf("%02d:%02d:%02d", $h, $m, $s);
                    }
                }
            }
        }
        if (count($results) > 0) {
            return $results;
        }
        return false;
    }
}
