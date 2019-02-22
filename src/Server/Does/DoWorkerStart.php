<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\XHttp;

/**
 * 由onWorkerStart()转发
 * @package Uniondrug\Phar\Server\Does
 */
trait DoWorkerStart
{
    /**
     * Worker进程启动
     * 当Worker进程启动时, 注册Phalcon框架到进程中
     * @param XHttp $server
     * @param int   $workerId
     */
    public function doWorkerStart($server, int $workerId)
    {
        if (method_exists($server, 'frameworkRegister')) {
            $server->frameworkRegister();
            if (method_exists($server, 'frameworkLogger')) {
                $server->frameworkLogger($server->getLogger());
            }
        }
    }
}
