<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server\Services\Traits;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Process;
use Uniondrug\Phar\Exceptions\ServiceException;
use Uniondrug\Phar\Server\Services\Http;
use Uniondrug\Phar\Server\Services\HttpDispatcher;
use Uniondrug\Phar\Server\Services\Socket;
use Uniondrug\Phar\Server\Tasks\ITask;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;

/**
 * 事件定义
 * @package Uniondrug\Phar\Server\Services\Traits
 */
trait EventsTrait
{
    /**
     * 异步任务完成
     * @link https://wiki.swoole.com/wiki/page/136.html
     * @param Http|Socket $server Server对象
     * @param int         $taskId 任务ID
     * @param string      $data   任务执行结果(onTask的返回值)
     */
    public function onFinish($server, $taskId, $data)
    {
    }

    /**
     * Manager启动
     * @link https://wiki.swoole.com/wiki/page/190.html
     * @param Http|Socket $server Server对象
     */
    public function onManagerStart($server)
    {
        $server->setPid($server->getManagerPid(), 'manager');
        $server->getPidTable()->addManager($server->getPid(), $server->getPidName());
        $server->getLogger()->info("进程号{%d}启动为{%s}.", $server->getPid(), $server->getPidName());
        $server->getLogger()->setServer($server);
        // 0. 写入启动参数
        $options = $server->getArgs()->getOptions();
        $optionsFile = $server->getArgs()->tmpPath().'/server.opt';
        file_put_contents($optionsFile, json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        // 1. 注册SIGINT信号量
        //    当Manager进程收到该信号时, 主动退出Process/Worker/Tasker
        //    三类进程; 退出后, Manager进程会重新Fork新的进程
        Process::signal(SIGPIPE, function($signal) use ($server){
            $server->getLogger()->info("第{%d}号进程{%s}收到{%d}信号量(SIGPIPE)", $server->getPid(), $server->getPidName(), $signal);
            // 2. 加载配置文件
            //    由于Process/Worker/Task进程都是由Manager进程Fork出来
            //    的, 只有配置更新后新Fork出来的进程才会延用, 反之新的配置
            //    将无法生效
            $server->getLogger()->debug("加载配置参数", $signal);
            $server->getConfig()->reload();
            // 3. 退出指定进程
            $procs = $server->getPidTable()->toArray();
            foreach ($procs as $proc) {
                // 4. 退出Prccess进程
                if ($server->getPidTable()->isProcess($proc) || $server->getPidTable()->isWorker($proc)) {
                    $server->getPidTable()->del($proc['pid']);
                    $server->getLogger()->debug("发送{SIGTERM}信号给{%d}号{%s}进程", $proc['pid'], $proc['name']);
                    Process::kill($proc['pid'], SIGTERM);
                    continue;
                }
            }
        });
    }

    /**
     * Manager退出
     * @link https://wiki.swoole.com/wiki/page/191.html
     * @param Http|Socket $server Server对象
     */
    public function onManagerStop($server)
    {
        $server->getLogger()->warning("第{%d}号进程{%s}退出", $server->getPid(), $server->getPidName());
        $server->getPidTable()->del($this->getPid());
    }

    /**
     * 管道消息
     * @link https://wiki.swoole.com/wiki/page/366.html
     * @param Http|Socket $server Server对象
     * @param int         $srcWorkerId
     * @param string      $message
     * @return bool
     */
    public function onPipeMessage($server, $srcWorkerId, $message)
    {
        return $server->task($message, -1) !== false;
    }

    /**
     * 收到HTTP请求
     * @link https://wiki.swoole.com/wiki/page/330.html
     * @param SwooleRequest  $request
     * @param SwooleResponse $response
     */
    public function onRequest($request, $response)
    {
        /**
         * 1. 处理HTTP请求
         * @var XHttp|XOld $server
         */
        $server = $this;
        $dispatch = new HttpDispatcher($server, $request, $response);
        if ($dispatch->isAssets()) {
            // 2. 静态资源
            if ($dispatch->isHealth()) {
                // 2.1 健康检查
                $server->doHealthRequest($server, $dispatch);
            } else {
                // 2.2 静态资源
                //     忽略了rewrite
                $server->doAssetsRequest($server, $dispatch);
            }
        } else {
            $server->frameworkRequest($server, $dispatch);
        }
        $memoryLimit = $dispatch->end();
        unset($dispatch);
        // 内存极限处理
        $memoryLimit && $server->stop($server->getWorkerId());
    }

    /**
     * 服务启动
     * @link https://wiki.swoole.com/wiki/page/p-event/onStart.html
     * @param Http|Socket $server Server对象
     */
    public function onStart($server)
    {
        $server->setPid($server->getMasterPid(), 'master');
        $server->getPidTable()->addMaster($server->getPid(), $server->getPidName());
        $server->getLogger()->info("进程号{%d}启动为{%s}.", $server->getPid(), $server->getPidName());
        $server->getLogger()->setServer($server);
        // 1. 信号量覆盖
        //    覆盖默认Master进程的SIGUSR1信号量, 当收到此信号时
        //    转发SIGPIPE信号量给Manager进程, 由Manager进程退
        //    处理重启
        foreach ([SIGUSR1] as $signal) {
            Process::signal($signal, function($sig) use ($server){
                $server->getLogger()->info("第{%d}号进程{%s}收到{%d}信号量(SIGUSR1)", $server->getPid(), $server->getPidName(), $sig);
                Process::kill($server->getManagerPid(), SIGPIPE);
            });
        }
    }

    /**
     * 服务退出
     * @link https://wiki.swoole.com/wiki/page/p-event/onShutdown.html
     * @param Http|Socket $server Server对象
     */
    public function onShutdown($server)
    {
        $server->getLogger()->warning("第{%d}号进程{%s}退出", $server->getPid(), $server->getPidName());
        $server->getPidTable()->del($this->getPid());
    }

    /**
     * 开始异步任务
     * @link https://wiki.swoole.com/wiki/page/54.html
     * @param Http|Socket $server      Server对象
     * @param int         $taskId      任务ID
     * @param int         $srcWorkerId 由哪个Worker进程发送的任务
     * @param string      $message     任务入参
     * @return bool
     */
    public function onTask($server, $taskId, $srcWorkerId, $message)
    {
        $begin = microtime(true);
        $result = false;
        $requestId = 't';
        $requestId .= (int) (microtime(true) * 1000000);
        $requestId .= mt_rand(1000000, 9999999);
        $requestId .= mt_rand(10000000, 99999999);
        // 1. logger
        $server->getStatsTable()->incrTaskOn();
        $server->getLogger()->setPrefix("[r=%s][z=%d]", $requestId, $taskId)->startProfile();
        $server->getLogger()->info("开始Task任务");
        // 2. parser
        try {
            // 2.1 invalid json string
            $data = json_decode($message, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ServiceException("解析Task入参失败 - %s", json_last_error_msg());
            }
            // 2.2 params validator
            $data['class'] = isset($data['class']) && is_string($data['class']) ? $data['class'] : null;
            $data['params'] = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
            if (!is_a($data['class'], ITask::class, true)) {
                throw new ServiceException("Task{%s}未实现{%s}类", $data['class'], ITask::class);
            }
            /**
             * 2.3 执行任务
             * @var ITask $tasker
             */
            if ($server->getLogger()->isStdout()) {
                $server->getLogger()->debug("运行{%s}任务", $data['class']);
            }
            $tasker = new $data['class']($server, $taskId, $data['params']);
            if ($tasker->beforeRun() === true) {
                $result = $tasker->run();
                $result === null && $result = true;
                $tasker->afterRun($result);
            }
        } catch(\Throwable $e) {
            $server->getLogger()->error("执行失败出错 - %s");
            $server->getStatsTable()->incrTaskFailure();
        } finally {
            $duration = microtime(true) - $begin;
            $server->getLogger()->info("[d=%.06f]完成Task任务", $duration);
            $server->getLogger()->endProfile();
        }
        return $result;
    }

    /**
     * 管理进程启动
     * @link https://wiki.swoole.com/wiki/page/190.html
     * @param Http|Socket $server   Server对象
     * @param int         $workerId Worker进程编号
     */
    public function onWorkerStart($server, $workerId)
    {
        $server->setPid($server->getWorkerPid(), $server->isTasker() ? 'tasker' : 'worker', $workerId);
        $server->isTasker() ? $server->getPidTable()->addTasker($workerId, $server->getPid(), $server->getPidName()) : $server->getPidTable()->addWorker($workerId, $server->getPid(), $server->getPidName());
        $server->getLogger()->info("进程号{%d}启动为{%s}.", $server->getPid(), $server->getPidName());
        $server->getLogger()->setServer($server);
        $server->frameworkInitialize($server);
    }

    /**
     * 管理进程退出
     * @link https://wiki.swoole.com/wiki/page/191.html
     * @param Http|Socket $server   Server对象
     * @param int         $workerId Worker进程编号
     */
    public function onWorkerStop($server, $workerId)
    {
        $server->getLogger()->warning("第{%d}号进程{%s}退出", $server->getPid(), $server->getPidName());
        $server->getPidTable()->del($this->getPid());
    }
}
