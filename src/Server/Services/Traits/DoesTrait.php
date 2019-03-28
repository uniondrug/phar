<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Services\Traits;

use Uniondrug\Phar\Server\Services\HttpDispatcher;
use Uniondrug\Phar\Server\Tables\ITable;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

/**
 * 业务处理
 * @package Uniondrug\Phar\Server\Services\Traits
 */
trait DoesTrait
{
    /**
     * @param XHttp|XOld|XSocket $server
     * @param HttpDispatcher     $dispatcher
     */
    public function doAssetsRequest($server, HttpDispatcher $dispatcher)
    {
        $server->getLogger()->ignoreProfile(true);
        $dispatcher->setStatus(304);
        $dispatcher->setContentType("text/plain");
    }

    /**
     * 处理健康检查
     * @param XHttp|XOld|XSocket $server
     * @param HttpDispatcher     $dispatcher
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
            $data['stats'] = $server->getStatsTable()->toArray();
        }
        $dispatcher->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 打印内存表信息
     * @param XHttp|XOld|XSocket $server
     * @param HttpDispatcher     $dispatcher
     */
    public function doTableRequest($server, HttpDispatcher $dispatcher)
    {
        try {
            /**
             * @var ITable $table
             */
            $table = $server->getTable($dispatcher->getTableName());
            $data = $table->toArray();
        } catch(\Throwable $e) {
            $data = [
                'errno' => 1,
                'error' => 'can not find '.$dispatcher->getTableName().' table'
            ];
        }
        $dispatcher->setContentType("application/json");
        $dispatcher->setContent(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 403禁止访问
     * @param XHttp|XOld|XSocket $server
     * @param HttpDispatcher     $dispatcher
     */
    public function doForbidRequest($server, HttpDispatcher $dispatcher)
    {
        $dispatcher->setStatus(403);
        $dispatcher->setContentType("text/plain");
        $dispatcher->setContent("Forbidden");
    }

    /**
     * 安全控制
     * @return bool
     */
    public function safeManager()
    {
        // todo: 安全控制未实现
        //       a). consul.health
        //       b). sidecar.health
        //       c). name.table
        return true;
    }
}
