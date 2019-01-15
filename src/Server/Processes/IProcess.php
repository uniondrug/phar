<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Processes;

use Uniondrug\Phar\Server\XHttp;

interface IProcess
{
    /**
     * Server对象
     * @return XHttp
     */
    public function getServer();

    /**
     * 任务过程
     * @return void
     */
    public function run();
}
