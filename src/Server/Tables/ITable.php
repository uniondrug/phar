<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-02
 */
namespace Uniondrug\Phar\Server\Tables;

use Uniondrug\Phar\Server\XHttp;

/**
 * ITable
 * @package Uniondrug\Phar\Server\Tables
 */
interface ITable
{
    /**
     * 读取表名
     * @return string
     */
    public function getName();

    /**
     * 读取Server对象
     * @return XHttp
     */
    public function getServer();

    /**
     * 转Array数组
     * @return array
     */
    public function toArray();

    /**
     * 转JSON字符串
     * @return string
     */
    public function toJson();
}
