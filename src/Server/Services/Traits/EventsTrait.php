<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server\Services\Traits;

use App\Errors\Error;
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
use Uniondrug\Validation\Exceptions\ParamException;

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
        $server->getTrace()->reset($request->header);
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
        // date: 2019-05-27
        // 以下代码会造成 cURL timed out, 暂时取消主动退出进程操作
         $memoryLimit && $server->stop($server->getWorkerId(), true);
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
        // 1. 开始计时
        $begin = microtime(true);
        $result = false;
        // 2. 解析入参
        //    $data = [
        //        "class" => "ExampleTask",
        //        "params" => [],
        //        "headers" => []
        //    ]
        $data = json_decode($message, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $server->getLogger()->warning(sprintf("runTask入参不是有效的数组 - %s:%s", gettype($message), $message));
            return $result;
        }
        $data['class'] = isset($data['class']) && is_string($data['class']) ? $data['class'] : '';
        $data['params'] = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
        $data['headers'] = isset($data['headers']) && is_array($data['headers']) ? $data['headers'] : [];
        // 3. 验证入参
        if ($data['class'] === '' || !is_a($data['class'], ITask::class, true)) {
            $server->getLogger()->error("Task{".$data['class']."}未实现{".ITask::class."}类");
            return $result;
        }
        // 4. 初始化Task
        $server->getTrace()->reset($data['headers'], true);
        $requestId = $server->getTrace()->getRequestId();
        $_SERVER['REQUEST-ID'] = $requestId;
        $_SERVER['HTTP_REQUEST_ID'] = $requestId;
        // 5. stats
        $prefix = sprintf("%s[r=%s][z=%d]", $server->getTrace()->getLoggerPrefix(), $requestId, $taskId);
        $logger = $server->getLogger();
        $server->getStatsTable()->incrTaskOn();
        $logger->startProfile()->setPrefix($prefix);
        $debugOn = $logger->debugOn();
        // 6. parser
        try {
            // 6.1 开始执行
            $debugOn && $logger->debug("开始{".$data['class']."}任务");
            /**
             * 6.2 执行任务
             * @var ITask $tasker
             */
            $tasker = new $data['class']($server, $taskId, $data['params']);
            if ($tasker->beforeRun() === true) {
                $result = $tasker->run();
                $result === null && $result = true;
                $tasker->afterRun($result);
            }
        } catch(\Throwable $e) {
            $server->getStatsTable()->incrTaskFailure();
            // 7. 执行任务出错
            if (($e instanceof Error) || ($e instanceof ParamException)) {
                $logger->warning("执行Task出错 - %s", $e->getMessage());
            } else {
                $logger->error("执行Task出错 - %s", $e->getMessage());
            }
            $logger->debugOn() && $logger->debug("{".get_class($e)."}: {$e->getFile()}({$e->getLine()})");
        } finally {
            // 8. 完成任务
            $duration = microtime(true) - $begin;
            $debugOn && $logger->debug("[d=%.06f]完成Task任务", $duration);
            $logger->endProfile();
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
