<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

use Swoole\Process;

/**
 * 退出服务
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class StopClient extends Abstracts\Client
{
    /**
     * 描述
     * @var string
     */
    protected static $description = 'stop http server';
    /**
     * 名称
     * @var string
     */
    protected static $title = '退出服务';
    /**
     * 启动选项
     * @var array
     */
    protected static $options = [
        [
            'name' => 'kill',
            'short' => 'k',
            'desc' => '向指定进程发送SIGTERM退出信号'
        ],
        [
            'name' => 'list',
            'short' => 'l',
            'desc' => '按名称列出进程列表'
        ],
        [
            'name' => 'name',
            'desc' => '指定进程名称'
        ]
    ];

    /**
     * @inheritdoc
     */
    public function run() : void
    {
        $useForce = false;
        foreach ([
            'k',
            'kill',
            'l',
            'list',
            'name'
        ] as $x) {
            if ($this->boot->getArgs()->hasOption($x)) {
                $useForce = true;
                break;
            }
        }
        $useForce ? $this->runForce() : $this->runNormal();
    }

    /**
     * Kill进程
     */
    public function runForce() : void
    {
        // 1. 进程名称
        $name = $this->boot->getArgs()->getOption('name');
        $name || $name = substr($this->boot->getConfig()->environment, 0, 1).'.'.$this->boot->getConfig()->name;
        $this->printLine("发送指定: {yellow=列出参数含【".$name."】的进程}");
        // 2. 是否Kill进程
        $kill = ($this->boot->getArgs()->hasOption('k') || $this->boot->getArgs()->hasOption('kill'));
        // 3. 读取进程
        $data = $this->callProcessByName($name);
        foreach ($data as $proc) {
            $this->printLine("          {green=%d}({yellow=%d}): %s %s", $proc['pid'], $proc['ppid'], $proc['args'], $kill ? '  {red=killed}' : '');
            // 4. send signal
            if ($kill) {
                Process::kill($proc['pid'], 0) && Process::kill($proc['pid'], SIGTERM);
            }
        }
    }

    /**
     * 发送退出信号
     * sig: SIGTERM
     */
    public function runNormal() : void
    {
        $pid = $this->callMasterPid();
        // 1. pid not found
        if ($pid === false) {
            $this->printLine("指令错误: {red=服务未启动或已退出}");
            return;
        }
        // 2. 发送退出指定
        $this->printLine("发送指定: {yellow=正在发送退出指令}");
        while (true) {
            $status = Process::kill($pid, 0);
            // 2.1. 已退出
            if ($status === false) {
                $this->printLine("指令错误: {blue=服务已退出或未启动}");
                break;
            }
            // 2.2. 发送信号
            Process::kill($pid, SIGTERM);
            sleep(2);
        }
    }

    /**
     * 退出帮助
     */
    public function runHelp() : void
    {
        $script = $this->boot->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("退出脚本: %s %s [{yellow=选项}]", $script, $this->boot->getArgs()->getCommand());
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
}
