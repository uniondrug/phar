<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-24
 */
namespace Uniondrug\Phar\Server\Listeners;

use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

class RedisListener
{
    /**
     * @var XHttp|XOld|XSocket
     */
    private $server;

    public function __construct($server)
    {
        $this->server = $server;
    }
}
