<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server\Services\Traits;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Uniondrug\Phar\Exceptions\ServiceException;
use Uniondrug\Phar\Server\Logs\Logger;
use Uniondrug\Phar\Server\Services\Http;
use Uniondrug\Phar\Server\Services\HttpDispatcher;
use Uniondrug\Phar\Server\Services\Socket;
use Uniondrug\Phar\Server\Tasks\Consul\RegisterTask;
use Uniondrug\Phar\Server\Tasks\ITask;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

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
                $server->safeManager() ? $server->doHealthRequest($server, $dispatch) : $server->doForbidRequest($server, $dispatch);
            } else if ($dispatch->isTable()) {
                // 2.2 内存表资源
                $server->safeManager() ? $server->doTableRequest($server, $dispatch) : $server->doForbidRequest($server, $dispatch);
            } else {
                // 2.3 静态资源
                //     忽略了rewrite
                $server->safeManager() ? $server->doAssetsRequest($server, $dispatch) : $server->doForbidRequest($server, $dispatch);
            }
        } else {
            // 3. 框架资源
            //    phalcon
            $server->frameworkRequest($server, $dispatch);
        }
        // 4. 内存状态
        $memoryLimit = $dispatch->end();
        unset($dispatch);
        // 5. 退出Worker进程
        //    当worker进程占用的内存资源达到临界值时
        //    主动发起退出进程请求
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
        // 1. stats
        $prefix = sprintf("[r=%s][z=%d]", $requestId, $taskId);
        $server->getStatsTable()->incrTaskOn();
        $server->getLogger()->startProfile()->setPrefix($prefix);
        // 2. parser
        try {
            // 2.2 parser message to json
            $data = json_decode($message, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // note: 当解析JSON失败时不写入Logger
                //       a): 无效的JSON数据
                //       b): 解压JSON数据失败
                $server->getLogger()->ignoreProfile(true);
                throw new ServiceException("解析Task入参为JSON失败 - ".$message);
            }
            // 2.3 params validator
            $data['class'] = isset($data['class']) && is_string($data['class']) ? $data['class'] : null;
            $data['params'] = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
            if (!is_a($data['class'], ITask::class, true)) {
                throw new ServiceException("Task{".$data['class']."}未实现{".ITask::class."}类");
            }
            // 2.3 开始执行
            $server->getLogger()->setPrefix("%s[y=%s]", $prefix, $data['class']);
            $server->getLogger()->debug("开始Task任务");
            /**
             * 2.4 执行任务
             * @var ITask $tasker
             */
            $_SERVER['request-id'] = $requestId;
            $_SERVER['HTTP_REQUEST_ID'] = $requestId;
            $tasker = new $data['class']($server, $taskId, $data['params']);
            if ($tasker->beforeRun() === true) {
                $result = $tasker->run();
                $result === null && $result = true;
                $tasker->afterRun($result);
            }
        } catch(\Throwable $e) {
            // 3. 执行任务出错
            $server->getLogger()->error("执行任务出错 - %s", $e->getMessage());
            $server->getLogger()->log(Logger::LEVEL_DEBUG, "{".get_class($e)."}: {$e->getFile()}({$e->getLine()})");
            $server->getStatsTable()->incrTaskFailure();
        } finally {
            // 4. 完成任务
            $duration = microtime(true) - $begin;
            $server->getLogger()->debug("[d=%.06f]完成Task任务", $duration);
            $server->getLogger()->endProfile();
        }
        return $result;
    }

    /**
     * 管理进程启动
     * @link https://wiki.swoole.com/wiki/page/190.html
     * @param XHttp|XOld|XSocket $server   Server对象
     * @param int                $workerId Worker进程编号
     */
    public function onWorkerStart($server, $workerId)
    {
        $server->getConfig()->reload();
        $server->setPid($server->getWorkerPid(), $server->isTasker() ? 'tasker' : 'worker', $workerId);
        $server->isTasker() ? $server->getPidTable()->addTasker($workerId, $server->getPid(), $server->getPidName()) : $server->getPidTable()->addWorker($workerId, $server->getPid(), $server->getPidName());
        $server->getLogger()->info("进程号{%d}启动为{%s}.", $server->getPid(), $server->getPidName());
        $server->getLogger()->setServer($server);
        $server->frameworkInitialize($server);
        // 1. 注册服务
        if ($workerId === 0) {
            $consul = 'consul-register';
            if ($server->getArgs()->hasOption($consul)) {
                $consulUrl = (string) $server->getArgs()->getOption($consul);
                if ($consulUrl !== '') {
                    $server->runTask(RegisterTask::class, [
                        'url' => $consulUrl
                    ]);
                }
            }
        }
    }

    /**
     * Worker/Tasker进程退出
     * @link https://wiki.swoole.com/wiki/page/191.html
     * @param XHttp|XOld|XSocket $server   Server对象
     * @param int                $workerId Worker进程编号
     */
    public function onWorkerStop($server, $workerId)
    {
        $server->getLogger()->warning("第{%d}号进程{%s}退出", $server->getPid(), $server->getPidName());
        $server->getPidTable()->del($this->getPid());
    }
}
