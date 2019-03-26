<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents;

use Swoole\Process;

/**
 * KV(Consul)
 * @package Uniondrug\Phar\Agents
 */
class ReloadAgent extends Abstracts\Agent
{
    protected static $title = '服务重载';
    protected static $description = '在不退出服务的前提下, 刷新配置并重新载入(重启Process/Worker/Tasker)';
    /**
     * 启动选项
     * @var array
     */
    protected static $options = [
        [
            'name' => 'pid',
            'value' => 'int',
            'desc' => 'Master进程ID'
        ]
    ];

    public function run()
    {
        $pid = $this->getRunner()->getArgs()->getOption('pid');
        $pid || $pid = $this->callMasterPid();
        // 1. pid not found
        if ($pid === false) {
            $this->printLine("指令错误: {red=未找到Master进程ID号}");
            return;
        }
        // 2. 发送指令
        $this->printLine("发送指定: 向Master进程[id={%d}]发送SIGPIPE信号", $pid);
        $sendStatus = Process::kill($pid);
        if ($sendStatus === false) {
            $this->printLine("指令错误: {red=服务未启动或已退出}");
            return;
        }
        // 3. 发起请求
        //shell_exec("kill -s SIGUSR1 {$pid}");
        Process::kill($pid, SIGPIPE);
    }

    /**
     * 打印帮助
     */
    public function runHelp() : void
    {
        $script = $this->getRunner()->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("重启选项: %s %s [{yellow=选项}]", $script, $this->getRunner()->getArgs()->getCommand());
        foreach (self::$options as $option) {
            $pre = isset($option['short']) ? "-{$option['short']}," : '   ';
            $opt = "{$pre}--{$option['name']}";
            if (isset($option['value'])) {
                $opt .= '=['.$option['value'].']';
            }
            $txt = isset($option['desc']) ? $option['desc'] : '';
            $this->printLine("          {yellow=%s} %s", sprintf("%-28s", $opt), $txt);
        }
    }

    /**
     * 从PID文件读取主进程ID
     * @return bool|int
     */
    protected function callMasterPid()
    {
        $pidFile = $this->getRunner()->getArgs()->tmpPath().'/server.pid';
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if (false !== $pid) {
                $pid = trim($pid);
                if (preg_match("/^\d+$/", $pid)) {
                    return (int) $pid;
                }
            }
        }
        return false;
    }
}
