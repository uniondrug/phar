<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tasks;

/**
 * ITask
 * @package Uniondrug\Phar\Server\Tasks
 */
interface ITask
{
    public function afterRun(& $data);
    public function beforeRun();
    /**
     * 异步处理
     * 本方法为异步处理入口, 严禁在代码中直接调用, 需使用
     * 如下代码提交任务
     * <code>
     * $server->runTask(ExampleTask::class, [])
     * </code>
     * @return mixed
     */
    public function run();
}
