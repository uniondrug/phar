<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server\Services;

use Uniondrug\Phar\Server\Services\Traits\ConstractTrait;
use Uniondrug\Phar\Server\Services\Traits\DoesTrait;
use Uniondrug\Phar\Server\Services\Traits\EventsTrait;
use Uniondrug\Phar\Server\Services\Traits\FrameworkTrait;
use Uniondrug\Phar\Server\Tasks\RunTaskTrait;

/**
 * WebSocket服务
 * @package Uniondrug\Phar\Server\Services
 */
abstract class Socket extends \swoole_websocket_server
{
    use ConstractTrait, EventsTrait, DoesTrait, RunTaskTrait;
    use FrameworkTrait;
}
