<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\XHttp;

/**
 * @package Uniondrug\Phar\Server\Does
 */
trait DoTask
{
    /**
     * 执行Task任务
     * @param XHttp  $server
     * @param int    $taskId
     * @param string $data
     * @return mixed
     * @throws \Exception
     */
    public function doTask($server, $taskId, $data)
    {
    }
}
