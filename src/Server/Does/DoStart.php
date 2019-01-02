<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\XHttp;

/**
 * BeforeStart
 * @package Uniondrug\Phar\Server\Does
 */
trait DoStart
{
    /**
     * 服务启动前触发
     * @param XHttp $server
     * @return bool
     */
    public function doStart($server)
    {
    }
}
