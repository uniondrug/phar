<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Tasks;

/**
 * XCron/异步任务基类
 * @package Uniondrug\Phar\Server\Tasks
 */
abstract class XCron extends XTask implements ICron
{
    /**
     * 定时规则
     * @var string
     */
    protected $regular;
}
