<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Processes;

/**
 * Interface IProcess
 * @package Uniondrug\Phar\Server\Processes
 */
interface IProcess
{
    /**
     * 前置操作
     * @return bool
     */
    public function beforeRun();

    /**
     * 执行过程
     * @return void
     */
    public function run();
}
