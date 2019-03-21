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

    public function afterRun(& $data)
    {
    }

    /**
     * 前置任务
     * @return bool
     */
    public function beforeRun()
    {
        return true;
    }

    /**
     * @return XHttp|XSocket|XOld
     */
    public function getServer()
    {
        return $this->_server;
    }
}
