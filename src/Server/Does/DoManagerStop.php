<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\XHttp;

/**
 * 由onManagerStop()转发
 * @package Uniondrug\Phar\Server\Does
 */
trait DoManagerStop
{
    /**
     * @param XHttp $server
     */
    public function doManagerStop($server)
    {
    }
}
