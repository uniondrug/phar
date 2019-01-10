<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

use Swoole\Process;

/**
 * 重新加载
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class ReloadClient extends Abstracts\Client
{
    /**
     * 描述
     * @var string
     */
    protected static $description = '退出Worker/Tasker进程, 并重启';
    /**
     * 名称
     * @var string
     */
    protected static $title = '服务重载';

    /**
     * @inheritdoc
     */
    public function run() : void
    {
        $pid = $this->callMasterPid();
        // 1. pid not found
        if ($pid === false) {
            $this->printLine("指令错误: {red=服务未启动或已退出}");
            return;
        }
        // 2. 发送退出指定
        $this->printLine("发送指定: {yellow=正在发送重载指令}");
        while (true) {
            $status = Process::kill($pid, 0);
            // 2.1. 已退出
            if ($status === false) {
                $this->printLine("指令错误: {blue=服务已退出或未启动}");
                break;
            }
            // 2.2. 发送信号
            Process::kill($pid, SIGUSR1);
            break;
        }
    }
}
