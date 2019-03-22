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
 * Class XTask
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
     * DI
     * @param string $name
     * @return mixed
     * @throws ServiceException
     */
    public function __get($name)
    {
        return $this->_server->getContainer()->get($name);
    }

    /**
     * 处理结果
     * 当run()方法执行完成后, 本方法可依据结果再处理
     * @param mixed $data
     */
    public function afterRun(& $data)
    {
    }

    /**
     * 前置任务
     * 仅当返回true时, 继续调用run()方法
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
