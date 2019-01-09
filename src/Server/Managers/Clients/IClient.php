<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * IClient/客户端命令接口
 * @package Uniondrug\Phar\Server\Managers\Clients
 */
interface IClient
{
    /**
     * 读取命令描述
     * @return string
     */
    public static function getDescription() : string;

    /**
     * 读取选项
     * @return array
     */
    public static function getOptions() : array;

    /**
     * 读取命令标题
     * @return string
     */
    public static function getTitle() : string;

    /**
     * @return void
     */
    public function run() : void;

    public function runHelp() : void;
}
