<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\XHttp;

/**
 * 由onWorkerError()转发
 * @package Uniondrug\Phar\Server\Does
 */
trait DoWorkerError
{
    /**
     * @param XHttp $server
     * @param int   $workerId
     * @param int   $workerPid
     * @param int   $errno
     * @param int   $signal
     */
    public function doWorkerError($server, int $workerId, int $workerPid, int $errno, int $signal)
    {
    }
}
