<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tables;

use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

/**
 * ITable
 * @package Uniondrug\Phar\Server\Tables
 */
interface ITable
{
    /**
     * 读取Server对象
     * @return XHttp|XOld|XSocket
     */
    public function getServer();

    /**
     * Key列表
     * @return array
     */
    public function keys();

    /**
     * 内存表数据转数组
     * @return array
     */
    public function toArray();

    /**
     * 内表数据转JSON
     * @return string
     */
    public function toJson();
}
