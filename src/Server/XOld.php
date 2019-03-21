<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server;

use Uniondrug\Phar\Server\Services\HttpDispatcher;

/**
 * HTTP服务
 * 基于xueron/pails的HTTP服务
 * @package Uniondrug\Phar\Server
 */
class XOld extends Services\Http
{
    private $_application;
    private $_container;
    private $_connectionActived = 0;
    /**
     * 连接刷新频次
     * 单位: 秒
     * 默认: 5
     * 当值为0时, 表示每次请求前主动检查, 反之由定时器
     * 每隔5秒检查一次连接
     * @var int
     */
    protected $connectionFrequences = 5;
    /**
     * 刷新MySQL共享名称
     * @var array
     */
    protected $connectionMysqls = [
        'db',
        'dbSlave'
    ];
    /**
     * 刷新Redis共享名称
     * @var array
     */
    protected $connectionRedises = [
        'redis'
    ];

    /**
     * 连接检查
     * @param XHttp|XSocket|XOld $server
     */
    public function frameworkReConnect($server)
    {
        $timestamp = time();
        if ($this->connectionFrequences > 0) {
            if (($timestamp - $this->_connectionActived) <= $this->connectionFrequences) {
                return;
            }
        }
        $this->_connectionActived = $timestamp;
        $this->_connectionCheckMysql($server);
        $this->_connectionCheckRedis($server);
    }

    /**
     * @param XHttp|XOld|XSocket $server
     */
    public function frameworkInitialize($server)
    {
    }

    /**
     * 转发Phalcon框架结果
     * @param XHttp|XOld|XSocket $server
     * @param HttpDispatcher     $dispatcher
     */
    public function frameworkRequest($server, HttpDispatcher $dispatcher)
    {
    }

    /**
     * 读取应用
     * @return mixed
     */
    public function getApplication()
    {
        return $this->_application;
    }

    /**
     * 读取容器
     * @return mixed
     */
    public function getContainer()
    {
        return $this->_container;
    }

    /**
     * 检查MySQL连接
     * @param XHttp|XSocket|XOld $server
     */
    private function _connectionCheckMysql($server)
    {
    }

    /**
     * 检查Redis连接
     * @param XHttp|XSocket|XOld $server
     */
    private function _connectionCheckRedis($server)
    {
    }
}
