<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-02
 */
namespace Uniondrug\Phar\Server\Tables;

/**
 * ITable
 * @package Uniondrug\Phar\Server\Tables
 */
interface ITable
{
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
