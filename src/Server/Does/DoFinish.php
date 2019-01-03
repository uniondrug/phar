<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\XHttp;

/**
 * 由onFinish()转发
 * @package Uniondrug\Phar\Server\Does
 */
trait DoFinish
{
    /**
     * @param XHttp $server
     * @param int   $taskId
     * @param mixed $data
     */
    public function doFinish($server, int $taskId, $data)
    {
    }
}
