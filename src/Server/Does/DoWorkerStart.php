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
     * @param XHttp $server
     * @param int   $workerId
     */
    public function doWorkerStart($server, int $workerId)
    {
    }
}
