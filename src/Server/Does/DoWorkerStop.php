<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\XHttp;

/**
 * 由onWorkerStop()转发
 * @package Uniondrug\Phar\Server\Does
 */
trait DoWorkerStop
{
    /**
     * @param XHttp $server
     * @param int   $workerId
     */
    public function doWorkerStop($server, int $workerId)
    {
    }
}
