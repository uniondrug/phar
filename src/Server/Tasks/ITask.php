<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Tasks;

use Uniondrug\Phar\Server\XHttp;

/**
 * ITask/异步任务接口
 * @package Uniondrug\Phar\Server\Tasks
 */
interface ITask
{
    /**
     * 后置操作
     * 在run()方法之后执行, 并将run()方法的返回值作为参数, 由
     * afterRun()方法对其再加工;
     * 由于本方法处理为引用传递, 对入参进行操作会影响最终的返回
     * 结果
     * @param mixed $result
     * @return void
     */
    public function afterRun(& $result) : void;

    /**
     * 前置操作
     * 在调用run()方法前触发, 其返回值决定后续run()/afterRun()
     * 方法是否继续执行, 当返回true时, 继续执行run()/afterRun()
     * 方法, 反之则退出任务
     * @return bool
     */
    public function beforeRun() : bool;

    /**
     * Server对象
     * 在异步任务中, 通过本方法读取XHttp对象实例
     * @return XHttp
     */
    public function getServer();

    /**
     * 读取任务ID
     * @return int
     */
    public function getTaskId() : int;

    /**
     * 任务过程
     * @return mixed
     */
    public function run();
}
