<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents;

use Swoole\Process;

/**
 * 退出服务
 * @package Uniondrug\Phar\Agents
 */
class StopAgent extends Abstracts\Agent
{
    protected static $title = '退出服务';
    protected static $description = '';

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
            'name' => 'force-kill',
            'desc' => '向指定进程发送SIGKILL退出信号'
        ],
        [
            'name' => 'name',
            'short' => 'n',
            'desc' => '指定进程名称'
        ]
    ];

    /**
     * @inheritdoc
     */
    public function run() : void
    {
        $this->runForce();
    }

    /**
     * Kill进程
     */
    public function runForce() : void
    {
        // 1. 进程名称
        $name = $this->getRunner()->getArgs()->getOption('n');
        $name || $name = $this->getRunner()->getArgs()->getOption('name');
        $name || $name = substr($this->getRunner()->getArgs()->getEnvironment(), 0, 1).'.'.$this->getRunner()->getConfig()->appName;
        // 2. 是否Kill进程
        $kill = ($this->getRunner()->getArgs()->hasOption('k') || $this->getRunner()->getArgs()->hasOption('kill') || $this->getRunner()->getArgs()->hasOption('force-kill'));
        $signal = $this->getRunner()->getArgs()->hasOption('force-kill') ? SIGKILL : SIGTERM;
        // 3. 读取进程
        $data = $this->callProcessByName($name);
        $this->printLine("发送指定: 列出含【{yellow=".$name."}】的进程共【{yellow=".count($data)."}】个");
        foreach ($data as $proc) {
            $this->printLine("          {green=%d}({yellow=%d}): %s %s", $proc['pid'], $proc['ppid'], $proc['args'], $kill ? '  {red=killed}' : '');
            // 4. send signal
            if ($kill) {
                Process::kill($proc['pid'], 0) && Process::kill($proc['pid'], $signal);
            }
        }
    }

    /**
     * 退出帮助
     */
    public function runHelp() : void
    {
        $script = $this->getRunner()->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("退出脚本: %s %s [{yellow=选项}]", $script, $this->getRunner()->getArgs()->getCommand());
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
     * @param string $name
     * @return array
     */
    protected function callProcessByName(string $name)
    {
        // 1. prepare args
        $num = 0;
        $cmd = "ps x -o ppid,pid,args";
        foreach (explode(" ", $name) as $arg) {
            $arg = trim($arg);
            if ($arg !== '') {
                $num++;
                $cmd .= " | grep '{$arg}'";
            }
        }
        $cmd .= " | grep -v grep | grep -v '".$this->getRunner()->getArgs()->getCommand()."'";
        $data = [];
        // 2. no args
        if ($num === 0) {
            return $data;
        }
        // 3. run shell
        $str = shell_exec($cmd);
        foreach (explode("\n", $str) as $line) {
            // 4. line
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // 5. column
            $cols = explode(" ", preg_replace("/\s+/", " ", $line));
            $lens = count($cols);
            if ($lens < 3) {
                continue;
            }
            // 6. parser
            $data[] = [
                'ppid' => $cols[0],
                'pid' => $cols[1],
                'args' => substr(implode(" ", array_slice($cols, 2, $lens - 2)), 0, 120)
            ];
        }
        return $data;
    }
}
