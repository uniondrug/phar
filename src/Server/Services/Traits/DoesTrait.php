<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Services\Traits;

use Uniondrug\Phar\Server\Services\Http;
use Uniondrug\Phar\Server\Services\HttpDispatcher;
use Uniondrug\Phar\Server\Services\Socket;

/**
 * 业务处理
 * @package Uniondrug\Phar\Server\Services\Traits
 */
trait DoesTrait
{
    /**
     * @param Http|Socket    $server
     * @param HttpDispatcher $dispatcher
     */
    public function doAssetsRequest($server, HttpDispatcher $dispatcher)
    {
        $server->getLogger()->ignoreProfile(true);
        $dispatcher->setStatus(304);
        $dispatcher->setContentType("text/plain");
    }

    /**
     * 处理健康检查
     * @param Http|Socket    $server
     * @param HttpDispatcher $dispatcher
     */
    public function doHealthRequest($server, HttpDispatcher $dispatcher)
    {
        $server->getLogger()->ignoreProfile(true);
        $dispatcher->setContentType("application/json;charset=utf-8");
        $data = [];
        $name = $dispatcher->getHealthName();
        if ($name === 'sidecar') {
            $data['status'] = 'UP';
        } else {
            $data = $server->stats();
            $data['start_time'] = date('Y-m-d H:i:s', $data['start_time']);
            $data['procs'] = $server->getPidTable()->toArray();
            $data['stats'] = $server->getStatsTable()->toArray();
        }
        $dispatcher->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
