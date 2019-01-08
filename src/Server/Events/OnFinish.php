<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Events;

use Uniondrug\Phar\Server\XHttp;

/**
 * Task完成时触发
 * 当在onTask()执行完成时, 若返回为非null, 则
 * 触发该方法, 反之不触发
 * @package Uniondrug\Phar\Server\Events
 */
trait OnFinish
{
    /**
     * @link https://wiki.swoole.com/wiki/page/136.html
     * @param XHttp $server
     * @param int   $taskId
     * @param mixed $data
     */
    final public function onFinish($server, int $taskId, $data)
    {
        $this->doFinish($server, $taskId, $data);
    }
}
