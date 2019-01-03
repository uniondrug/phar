<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Tasks;

use Uniondrug\Phar\Server\XHttp;

/**
 * ITask
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
    protected $taskId;
    protected $logPrefix;

    /**
     * XTask constructor.
     * @param        $server
     * @param array  $data
     * @param string $logPrefix
     */
    final public function __construct($server, array $data, int $taskId, $logPrefix = '')
    {
        $this->data = $data;
        $this->server = $server;
        $this->taskId = $taskId;
        $this->logPrefix = $logPrefix;
    }

    public function __destruct()
    {
        $this->data = null;
        $this->server = null;
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
     * Server对象
     * @return XHttp
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * 任务ID
     * @return int
     */
    public function getTaskId()
    {
        return $this->taskId;
    }

    /**
     * Task结束前触发
     * 本方法入参为run()方法返回值, 可以本方法中操作入
     * 参数据, Task最终返回操作后的数据
     * @param mixed $result
     */
    public function afterRun(& $result)
    {
    }

    /**
     * Task开始前触发
     * 当返回true时, 继续触发run()、after()方法, 反之
     * 则退出Task
     * @return bool
     */
    public function beforeRun()
    {
        return true;
    }
}
