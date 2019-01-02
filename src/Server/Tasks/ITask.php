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
interface ITask
{
    /**
     * @param mixed $result
     * @return void
     */
    public function afterRun(& $result);

    /**
     * @return bool
     */
    public function beforeRun();

    /**
     * Task数据
     * @return array
     */
    public function getData();

    /**
     * Server对象
     * @return XHttp
     */
    public function getServer();

    /**
     * 任务ID
     * @return int
     */
    public function getTaskId();

    /**
     * 任务过程
     * @return mixed
     */
    public function run();
}
