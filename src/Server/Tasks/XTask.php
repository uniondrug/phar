<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tasks;

use Uniondrug\Phar\Exceptions\ServiceException;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

/**
 * 异步任务基类
 * 由业务代码调用runTask()方法触发, 如下例代码片段
 * <code>
 * $this->getServer()->runTask(ExampleTask::class, [
 *    "key" => "value"
 * ]);
 * </code>
 * @package Uniondrug\Phar\Server\Tasks
 */
abstract class XTask implements ITask
{
    /**
     * @var string
     * @deprecated
     */
    protected $logPrefix = '';
    /**
     * @var string
     * @deprecated
     */
    protected $logUniqid = '';
    /**
     * 任务ID
     * @var int
     */
    protected $taskId = -1;
    /**
     * 任务入参
     * @var array
     */
    protected $data = [];
    /**
     * Server对象
     * @var XHttp|XSocket|XOld
     */
    private $_server;

    /**
     * @param XHttp|XSocket|XHttp $server
     * @param int                 $taskId
     * @param array               $data
     */
    public function __construct($server, int $taskId, array $data)
    {
        $this->data = $data;
        $this->taskId = $taskId;
        $this->_server = $server;
        $this->_server->frameworkReConnect($this->_server);
    }

    /**
     * 加载依赖注入
     * @param string $name
     * @return mixed
     * @throws ServiceException
     */
    public function __get($name)
    {
        return $this->_server->getContainer()->get($name);
    }

    /**
     * 后置任务
     * 当run()方法执行完成后, 其返回结果作为参数以引用模式
     * 传递给本方法afterRun(), 本方法操作入参可改变最终的
     * run()方法返回的任务处理结果
     * @param mixed $data
     */
    public function afterRun(& $data)
    {
    }

    /**
     * 前置任务
     * 仅当返回true时, 继续调用run()方法, 反之, 跳出任务
     * 不做任务处理, 其中run(), afterRun()都不会触发
     * @return bool
     */
    public function beforeRun()
    {
        return true;
    }

    /**
     * 读取任务入参
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 读取Server对象
     * @return XHttp|XSocket|XOld
     */
    public function getServer()
    {
        return $this->_server;
    }

    /**
     * 读取任务ID
     * @return int
     */
    public function getTaskId()
    {
        return $this->taskId;
    }
}
