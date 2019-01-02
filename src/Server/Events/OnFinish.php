<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * 响应Task完成事件
 * @package Uniondrug\Phar\Server\Events
 */
trait OnFinish
{
    /**
     * 响应Task完成事件
     * 当worker进程投递的任务在task_worker中完成后触发
     * 若onTask返回null, 则本方法不触发
     * @param XHttp $server HTTP实例
     * @param int   $taskId 任务ID
     * @param mixed $data   onTask()返回数据
     */
    final public function onFinish($server, int $taskId, $data)
    {
    }
}
