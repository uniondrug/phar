<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-31
 */
namespace Uniondrug\Phar\Server\Does;

use Uniondrug\Phar\Server\Http;
use Uniondrug\Phar\Server\XHttp;

/**
 * BeforeStart
 * @package Uniondrug\Phar\Server\Does
 */
trait BeforeStart
{
    /**
     * 服务启动前触发
     * @param Http|XHttp $server
     * @return bool
     */
    public function beforeStart($server)
    {
        return true;
    }
}
