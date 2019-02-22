<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Tasks;

use Uniondrug\Phar\Server\XHttp;

/**
 * XTask/异步任务基类
 * @package Uniondrug\Phar\Server\Tasks
 */
abstract class XTask implements ITask
{
    /**
     * XHttp实例
     * @var XHttp
     */
    private $server;
    /**
     * Task入参
     * @var array
     */
    protected $data = [];
    /**
     * 任务ID
     * @var int
     */
    protected $taskId;
    /**
     * Log前缀
     * @var string
     */
    protected $logPrefix = '';
    protected $logOriginPrefix = null;
    protected $logUniqid;

    /**
     * @param XHttp  $server
     * @param array  $data
     * @param string $logUniqid
     * @param string $logPrefix
     */
    public function __construct($server, array $data, int $taskId, $logUniqid, $logPrefix = '')
    {
        $server->getContainer();
        $this->data = $data;
        $this->server = $server;
        $this->taskId = $taskId;
        $this->logUniqid = $logUniqid;
        $this->logOriginPrefix = $server->getLogger()->getPrefix();
        $this->server->getLogger()->setPrefix($this->logOriginPrefix.$logPrefix);
    }

    /**
     * 释放资源
     */
    public function __destruct()
    {
        $this->server->getLogger()->setPrefix($this->logOriginPrefix);
        $this->data = null;
        $this->server = null;
        $this->taskId = null;
        $this->logPrefix = null;
    }

    /**
     * 读取容器实例
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->server->getContainer()->getShared($name);
    }

    /**
     * 后置操作
     * 在run()方法之后执行, 并将run()方法的返回值作为参数, 由
     * afterRun()方法对其再加工;
     * 由于本方法处理为引用传递, 对入参进行操作会影响最终的返回
     * 结果
     * @param mixed $result
     * @return void
     */
    public function afterRun(& $result) : void
    {
    }

    /**
     * 前置操作
     * 在调用run()方法前触发, 其返回值决定后续run()/afterRun()
     * 方法是否继续执行, 当返回true时, 继续执行run()/afterRun()
     * 方法, 反之则退出任务
     * @return bool
     */
    public function beforeRun() : bool
    {
        return true;
    }

    /**
     * Server对象
     * 在异步任务中, 通过本方法读取XHttp对象实例
     * @return XHttp
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * 读取任务ID
     * @return int
     */
    public function getTaskId() : int
    {
        return $this->taskId;
    }
}
