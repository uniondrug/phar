<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents\Abstracts;

interface IAgent
{
    public static function getTitle();
    public static function getDescription();

    public function run();
    public function runHelp();
}
